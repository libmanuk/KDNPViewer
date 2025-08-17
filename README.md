# Newspaper Viewer

A lightweight PHP-based viewer for digitized and born-digital newspaper issues in PDF format, rendered using [PDF.js](https://mozilla.github.io/pdf.js/). The viewer uses an `id` URL parameter to locate and display specific newspaper issues along with their metadata.

---

## 📘 URL Structure

The viewer uses a URL parameter `id` with the following format:


- `ttt`: Newspaper title code (3 lowercase letters, a–z)
- `YYYY`: Year (4 digits)
- `MM`: Month (2 digits)
- `DD`: Day (2 digits)
- `CC`: Copy number (2 digits)

**Example:**


→ _Adair County News_, May 18, 1923, Copy 01 (ada1923051801)

---

## 📁 Directory Structure

The system separates metadata and PDF content into two top-level directories:


- `meta/`: Contains XML metadata files
- `pv/`: Contains PDF files
- Subdirectory structure is based on:
  - Title code (`ttt`)
  - Full issue ID (`tttYYYYMMDDCC`)
  - meta/ttt/tttYYYYMMDDCC.xml
  - pv/ttt/tttYYYYMMDDCC/tttYYYYMMDDCC.pdf

---

## 🔧 Viewer Workflow

1. `viewer.php` reads the `id` parameter from the URL
2. Extracts:
   - Title code (first 3 letters)
   - Full issue ID
3. Constructs paths:
   - PDF: `pv/ttt/tttYYYYMMDDCC/tttYYYYMMDDCC.pdf`
   - Metadata: `meta/ttt/tttYYYYMMDDCC.xml`
4. Loads:
   - PDF via **PDF.js**
   - Metadata XML
5. Displays:
   - PDF pages
   - Metadata (title, date, etc.)
   - Viewer features powered by metadata
6. Features:
   - Page searching
   - Page navigation/turning
   - Page section selection and clipping tool
   - Display title histories

---

## 📦 Dependencies

- [PDF.js](https://mozilla.github.io/pdf.js/): Client-side PDF rendering
- [html-to-image](https://github.com/bubkoo/html-to-image): Client-side Generate an image from a DOM node
- PHP: For backend routing and file access
- XML: Metadata format per issue

---

## ✅ Notes

- Requires valid `id` format in requests
- Metadata schema must be consistent across issues
- Designed for minimalism and performance

---

