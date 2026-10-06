with open("extracted_text.txt", "r", encoding="utf-8") as f:
    text = f.read()

# Let's search for "api/v9/item" and show 30 lines before and 50 lines after to understand the documentation style
import re
match = re.search(r'api/v9/item', text)
if match:
    start_pos = max(0, match.start() - 100)
    end_pos = min(len(text), match.end() + 2000)
    print("--- SAMPLE 1 ---")
    print(text[start_pos:end_pos])
else:
    print("No match found for api/v9/item")
