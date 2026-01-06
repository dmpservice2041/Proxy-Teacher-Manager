const Tesseract = require('tesseract.js');
const fs = require('fs');
const path = require('path');

const imagePath = process.argv[2];

if (!imagePath) {
    console.error('Usage: node ocr_helper.js <image_path>');
    process.exit(1);
}

if (!fs.existsSync(imagePath)) {
    console.error(`Error: File not found: ${imagePath}`);
    process.exit(1);
}

console.error(`Processing ${imagePath}...`);

Tesseract.recognize(
    imagePath,
    'eng',
    { 
        logger: m => console.error(m),
        tessedit_pageseg_mode: Tesseract.PSM.SPARSE_TEXT // Good for tables
    }
).then(({ data: { text } }) => {
    process.stdout.write(text);
    process.exit(0);
}).catch(err => {
    console.error('OCR Error:', err);
    process.exit(1);
});
