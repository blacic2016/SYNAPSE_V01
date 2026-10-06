import re

with open("extracted_text.txt", "r", encoding="utf-8") as f:
    text = f.read()

# Let's split by page
pages = text.split("--- PAGE ")
print(f"Total pages: {len(pages)}")

apis = []
for p_idx, page in enumerate(pages):
    if p_idx == 0:
        continue
    # Let's check if "Detalles de la petición" is in this page
    if "Detalles de la petición" in page:
        # Find Uri and Tipo
        uri_match = re.search(r'Uri:\s*(.*)', page)
        tipo_match = re.search(r'Tipo:\s*(.*)', page)
        
        # Let's find the text before "Detalles de la petición"
        # We look at the lines in the page before this phrase
        lines = page.split("\n")
        detalles_idx = -1
        for idx, line in enumerate(lines):
            if "Detalles de la petición" in line:
                detalles_idx = idx
                break
        
        title = "Unknown Title"
        if detalles_idx > 0:
            # Look up for a non-empty line
            for look_back in range(detalles_idx - 1, -1, -1):
                cand = lines[look_back].strip()
                if cand and cand != f"{p_idx} ---" and "---" not in cand:
                    title = cand
                    break
        
        uri = uri_match.group(1).strip() if uri_match else "Unknown"
        tipo = tipo_match.group(1).strip() if tipo_match else "Unknown"
        
        # Clean double entries like "Consulta de Casos\nConsulta de Casos"
        if "\n" in title:
            title = title.split("\n")[0]
        
        apis.append({
            "page": p_idx,
            "title": title,
            "uri": uri,
            "method": tipo
        })

print(f"Found {len(apis)} API endpoints:")
for api in apis:
    print(f"Page {api['page']} | {api['title']} | {api['method']} | {api['uri']}")
