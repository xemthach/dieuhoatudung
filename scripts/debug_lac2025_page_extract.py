from pathlib import Path
import pdfplumber
import sys

sys.stdout.reconfigure(encoding="utf-8")

pdf_path = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
page_num = 33

with pdfplumber.open(str(pdf_path)) as pdf:
    p = pdf.pages[page_num - 1]
    text = p.extract_text() or ""
    print("=== TEXT SAMPLE ===")
    for i, line in enumerate(text.splitlines()[:120], start=1):
        print(f"{i:03d}: {line}")

    print("\n=== TABLES ===")
    tables = p.extract_tables()
    print("table_count=", len(tables))
    for t_idx, t in enumerate(tables[:5], start=1):
        print(f"\n-- table {t_idx} rows={len(t)}")
        for r in t[:15]:
            print(r)
