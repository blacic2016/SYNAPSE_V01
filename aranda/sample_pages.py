with open("extracted_text.txt", "r", encoding="utf-8") as f:
    text = f.read()

pages = text.split("--- PAGE ")

# Let's print pages 2, 7, and 15
print("=== PAGE 2 ===")
print(pages[2][:1500])

print("\n=== PAGE 7 ===")
print(pages[7][:1500])

print("\n=== PAGE 15 ===")
print(pages[15][:1500])
