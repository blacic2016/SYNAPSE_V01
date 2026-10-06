import pypdf

reader = pypdf.PdfReader("asms-api (1).pdf")
print(f"Number of pages: {len(reader.pages)}")

with open("extracted_text.txt", "w", encoding="utf-8") as f:
    for i, page in enumerate(reader.pages):
        text = page.extract_text()
        f.write(f"--- PAGE {i+1} ---\n")
        f.write(text)
        f.write("\n")

print("Done! Extracted text saved to extracted_text.txt")
