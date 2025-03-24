<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -------------------------------------------------------------------
// CONFIGURATION AND DEPENDENCIES
// -------------------------------------------------------------------

// Note: Requires Composer and packages specified in composer.json:
// {
//    "require": {
//        "smalot/pdfparser": "^2.11",    // PDF text extraction
//        "spatie/pdf-to-image": "^1.2",  // PDF to image conversion
//        "thiagoalessio/tesseract_ocr": "^2.13"  // OCR wrapper
//    }
// }
// Also requires system installations of Tesseract OCR and ImageMagick

// Load Composer autoloader for dependency management
require __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser as PdfParser;
use Spatie\PdfToImage\Pdf as PdfToImage;
use thiagoalessio\TesseractOCR\TesseractOCR;

// Configure upload directory for processed files
define('UPLOAD_DIR', __DIR__ . '/facturas_subidas/');
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);  // Create directory with full permissions if missing
}

// System paths for required binaries (adjust according to server environment)
define("TESSERACT_PATH", "C:\\Program Files\\Tesseract-OCR\\tesseract.exe");
define("IMAGEMAGICK_PATH", "C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe");

// Azure OpenAI API configuration (replace with actual credentials)
$azureOpenAiApiKey = "SU_LLAVE_AQUI";         // Replace with API key
$azureOpenAiEndpoint = "SU_ENDPOINT_AQuI";    // Replace with endpoint URL


// -------------------------------------------------------------------
// FUNCTION: Check for key fields in OCR text
// -------------------------------------------------------------------
/**
 * Verifies if extracted text contains any of the required invoice fields
 * @param string $texto Extracted text content
 * @param array $camposClave Array of possible field aliases
 * @return bool True if any key field is found
 */
function contieneCamposClave($texto, $camposClave) {
    foreach ($camposClave as $campo) {
        if (stripos($texto, $campo) !== false) {
            return true;
        }
    }
    return false;
}

// -------------------------------------------------------------------
// FUNCTION: Image preprocessing for OCR optimization
// -------------------------------------------------------------------
/**
 * Enhances image quality using ImageMagick for better OCR accuracy
 * @param string $rutaImagen Path to source image
 * @return string Path to processed temporary image
 */
function preprocesarImagen($rutaImagen) {
    $rutaSalida = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
    // ImageMagick processing chain:
    $cmd = "magick convert " . escapeshellarg($rutaImagen) 
         . " -resize 300% "              // Upscale resolution
         . "-colorspace Gray "            // Convert to grayscale
         . "-brightness-contrast 20x20 "  // Enhance contrast
         . "-sharpen 0x1 "                // Apply sharpening
         . escapeshellarg($rutaSalida);
    exec($cmd);
    return $rutaSalida;
}

// -------------------------------------------------------------------
// FUNCTION: Parse Azure OpenAI API response
// -------------------------------------------------------------------
/**
 * Processes and validates the response from Azure OpenAI API
 * @param string $azureRespuesta Raw API response
 * @return array Decoded invoice data or error information
 */
function parseAzureOpenAIResponse($azureRespuesta) {
    $respuestaArray = json_decode($azureRespuesta, true);
    if (!$respuestaArray || !isset($respuestaArray['choices'][0]['message']['content'])) {
        return [
            "error" => "Unexpected API response format",
            "raw_response" => $azureRespuesta
        ];
    }

    $contenido = $respuestaArray['choices'][0]['message']['content'];
    // Clean JSON formatting from response
    $contenidoLimpio = preg_replace('/^```json\s*/', '', $contenido);
    $contenidoLimpio = preg_replace('/\s*```$/', '', $contenidoLimpio);
    
    $datosFactura = json_decode($contenidoLimpio, true);
    if ($datosFactura === null) {
        return [
            "error" => "JSON decoding failed",
            "raw_content" => $contenidoLimpio
        ];
    }
    return $datosFactura;
}

// -------------------------------------------------------------------
// TEXT EXTRACTION FUNCTIONS
// -------------------------------------------------------------------

/**
 * Extracts text from PDF files using native parser or OCR fallback
 * @param string $rutaPDF Path to PDF file
 * @return string Extracted text content
 */
function extraerTextoPDF($rutaPDF) {
    $parser = new PdfParser();
    try {
        $pdf = $parser->parseFile($rutaPDF);
        $textoNativo = $pdf->getText();
        if (trim($textoNativo) !== '') {
            return $textoNativo;  // Return native text if available
        }
        
        // Fallback to OCR if PDF is image-based
        $pdfToImage = new PdfToImage($rutaPDF);
        $tempImagePath = __DIR__ . '/temp_page.png';
        $pdfToImage->saveImage($tempImagePath);
        
        $textoOCR = (new TesseractOCR($tempImagePath))
            ->executable(TESSERACT_PATH)
            ->lang('eng+spa')     // Spanish + English OCR
            ->psm(6)             // Page segmentation mode
            ->run();
        
        @unlink($tempImagePath); // Cleanup temporary image
        return $textoOCR;
    } catch (Exception $e) {
        return "";
    }
}

/**
 * Extracts text from image files using OCR with quality optimization
 * @param string $rutaImagen Path to image file
 * @return string Extracted text content
 */
function extraerTextoImagen($rutaImagen) {
    // Initial OCR attempt
    $ocr = (new TesseractOCR($rutaImagen))
        ->executable(TESSERACT_PATH)
        ->lang('eng+spa')
        ->psm(6)
        ->run();
    
    // Validate OCR results against key fields
    $camposClave = ['Factura', 'Fecha', 'Total', 'Cliente', 'Proveedor'];
    if (!contieneCamposClave($ocr, $camposClave)) {
        // Apply image preprocessing if initial OCR fails
        $imagenPreprocesada = preprocesarImagen($rutaImagen);
        $ocr = (new TesseractOCR($imagenPreprocesada))
            ->executable(TESSERACT_PATH)
            ->lang('eng+spa')
            ->psm(6)
            ->run();
        @unlink($imagenPreprocesada);
    }
    return $ocr;
}

// -------------------------------------------------------------------
// FUNCTION: Azure OpenAI API Communication
// -------------------------------------------------------------------
/**
 * Sends extracted text to Azure OpenAI for structured data extraction
 * @param string $textoFactura Extracted invoice text
 * @param array $requiredFields Expected data structure
 * @return string API response
 */
function llamarAzureOpenAI($textoFactura, $requiredFields) {
    global $azureOpenAiApiKey, $azureOpenAiEndpoint;
    
    // Construct AI prompt with structured JSON template
    $prompt = <<<PROMPT
You are an invoice processing expert. Extract information from this invoice and return EXACTLY this JSON structure:
{
    "invoice_number": "",
    "issue_date": "",
    ...
    "items": [{"description": "", "quantity": "", ...}]
}
Use the following invoice text:
$textoFactura
Return ONLY the JSON without additional explanations.
PROMPT;

    $data = [
        "messages" => [
            ["role" => "system", "content" => "Expert invoice data extractor"],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0,      // Minimize creativity for consistent output
        "max_tokens" => 2000     // Control response length
    ];

    // Execute API request
    $ch = curl_init($azureOpenAiEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "api-key: $azureOpenAiApiKey"
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return json_encode(["error" => "API request failed", "details" => $error]);
    }
    curl_close($ch);
    return $response;
}

// -------------------------------------------------------------------
// FILE PROCESSING LOGIC
// -------------------------------------------------------------------
$azureRespuestaPretty = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["invoice_file"])) {
    // Validate uploaded file
    $nombreArchivo = $_FILES["invoice_file"]["name"];
    $rutaTemp = $_FILES["invoice_file"]["tmp_name"];
    $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
    $formatosPermitidos = ['pdf', 'png', 'jpg', 'jpeg'];

    if (!in_array($extension, $formatosPermitidos)) {
        echo "<p>Unsupported format. Allowed: PDF, PNG, JPG, JPEG.</p>";
    } else {
        $rutaDestino = UPLOAD_DIR . basename($nombreArchivo);
        if (move_uploaded_file($rutaTemp, $rutaDestino)) {
            // Extract text based on file type
            $textoExtraido = ($extension === 'pdf') 
                ? extraerTextoPDF($rutaDestino) 
                : extraerTextoImagen($rutaDestino);
            
            if (trim($textoExtraido) === "") {
                echo "<p>Text extraction failed (protected PDF or poor image quality).</p>";
            } else {
                // Process through Azure OpenAI
                $azureRespuesta = llamarAzureOpenAI($textoExtraido);
                $datosFactura = parseAzureOpenAIResponse($azureRespuesta);
                $azureRespuestaPretty = json_encode($datosFactura, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
                // Save JSON output for debugging
                $output_dir = __DIR__ . '/json_output/';
                if (!is_dir($output_dir)) mkdir($output_dir, 0777, true);
                file_put_contents(
                    $output_dir . 'invoice-' . date('Ymd-His') . '.json',
                    $azureRespuestaPretty
                );
            }
        } else {
            echo "<p style='color:red;'>File upload error.</p>";
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice Processing with GPT-4 - Demo</title>
    <style>
        /* Interface styling remains unchanged */
    </style>
</head>
<body>
<div class="container">
    <h1>Invoice Processing with GPT-4</h1>
    <div id="processing_message">Processing, please wait...</div>
    
    <form action="" method="post" enctype="multipart/form-data" id="invoice_file_form">
        <label>Upload Invoice (PDF/Image):</label><br>
        <input type="file" name="invoice_file" required><br><br>
        <button type="submit">Process Invoice</button>
    </form>

    <?php if ($azureRespuestaPretty !== null): ?>
        <h2>Processed Results:</h2>
        <pre><?php echo htmlspecialchars($azureRespuestaPretty); ?></pre>
    <?php endif; ?>
</div>

<script>
// Show processing message during form submission
document.getElementById("invoice_file_form").addEventListener("submit", function(){
    document.getElementById("processing_message").style.display = "block";
});
</script>
</body>
</html>