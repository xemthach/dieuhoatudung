import re
import sys
from pathlib import Path
import pdfplumber

sys.stdout.reconfigure(encoding="utf-8")

PDF = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")

for pg in [45, 46, 49]:
    print(f"\n=== PAGE {pg} ===")
    with pdfplumber.open(str(PDF)) as pdf:
        lines = [re.sub(r"\s+", " ", x).strip() for x in (pdf.pages[pg - 1].extract_text() or "").splitlines() if x.strip()]
    for i, line in enumerate(lines[:120], 1):
        print(f"{i:03d}: {line}")

