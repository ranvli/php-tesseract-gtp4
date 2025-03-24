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
