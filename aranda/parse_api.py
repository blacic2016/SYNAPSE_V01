import re

with open("extracted_text.txt", "r", encoding="utf-8") as f:
    text = f.read()

# Let's find lines that contain typical API patterns like POST / GET / PUT / DELETE or URL routes
lines = text.split("\n")
print(f"Total lines: {len(lines)}")

# Let's search for some patterns
api_patterns = [
    r'(GET|POST|PUT|DELETE)\s+https?://',
    r'/(api|v1|v2|asms)/[a-zA-Z0-9_\-/]+',
    r'Method\s*:\s*(GET|POST|PUT|DELETE)',
    r'URL\s*:\s*\S+',
]

print("\n--- Potential API Endpoints / References ---")
matching_lines = []
for i, line in enumerate(lines):
    for pattern in api_patterns:
        if re.search(pattern, line, re.IGNORECASE):
            matching_lines.append((i+1, line))
            break

# Print first 100 matching lines to see what they look like
for ln, line in matching_lines[:100]:
    print(f"L{ln}: {line.strip()}")
