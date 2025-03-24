# 🧾 OCR Invoice Parser (PHP + Tesseract + GPT-4o)

This project is an invoice data extraction prototype written in PHP. It uses **Tesseract OCR** to extract text from images or PDFs and leverages **GPT-4o (via Azure OpenAI)** to parse and structure the extracted content.

## ⚙️ Technologies Used

- PHP 8+
- Tesseract OCR (via `thiagoalessio/tesseract_ocr`)
- ImageMagick (for image pre-processing)
- GPT-4o (via Azure OpenAI)
- Smalot PDF Parser (`smalot/pdfparser`)
- Spatie PDF to Image (`spatie/pdf-to-image`)

## 📦 Features

- Accepts invoice files in PDF or image format (JPG, PNG).
- Pre-processes images to enhance OCR accuracy.
- Extracts raw text using Tesseract.
- Sends the text to GPT-4o to identify fields like:
  - Invoice Number
  - Supplier Name
  - Date
  - Total Amount
  - Currency
  - Line Items
- Returns a structured **PHP associative array**.
- Optionally saves JSON output for debugging purposes.
- Includes a simple HTML interface for testing.

## 📁 Example Output

```php
[
  "invoice_number" => "INV-1234",
  "supplier" => "ABC Ltd.",
  "date" => "2025-03-01",
  "total" => 2345.67,
  "currency" => "EUR",
  "items" => [ ... ]
]
```

## 🎥 Demo

🔗 Video: https://jam.dev/c/3fe4a708-bb90-4dd6-812c-40de6c8267e9
🔗 Test URL (archived)

## ⚠️ Freelancer.com Arbitration Warning

This project was originally commissioned via Freelancer.com. Despite fully delivering:

- A working solution
- A 10-minute demo video parsing 30+ invoices
- Structured output in array format
- Debug JSON only for frontend/testing
- 90%+ accuracy, as confirmed by the client's own message

Freelancer's arbitration refunded **100% of the milestone** without evaluating the evidence. They sided with the client based solely on their rejection, **ignoring the video, working code, and functionality.**

📎 You can view the arbitration summary and context here: [Insert link or issue]

## 📌 Important

This repository is preserved as technical evidence and for future developers working on OCR + LLM-based document understanding. The logic can be easily extracted into a function or class for integration.

## 📄 License

MIT — Feel free to use or adapt this project. Attribution is appreciated.

---

### 🙋‍♂️ Author

**Randall V. Li**  
Top-Rated Plus Freelancer | Software Engineer  
📧 xxx
🌐 www.linkedin.com/in/ranvli
