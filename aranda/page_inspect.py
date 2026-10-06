import re

with open("extracted_text.txt", "r", encoding="utf-8") as f:
    text = f.read()

pages = text.split("--- PAGE ")
print(f"Total pages parsed: {len(pages)}")

for p in pages[1:]:
    lines = p.split("\n")
    page_num = lines[0].split(" ---")[0]
    # search for Uri: or similar
    uri_lines = [l for l in lines if "Uri:" in l]
    if uri_lines:
        print(f"Page {page_num}: {uri_lines} | First line: {lines[1][:50] if len(lines)>1 else ''}")
