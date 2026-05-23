import json
import re
from pathlib import Path
import pdfplumber

PDF_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
OUT_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/storage/catalogs/verified_pdf_extract_gree_lac2025_pairs.json")

# modelA d r c modelB d r c
PAIR_RE = re.compile(
    r"^([A-Za-z0-9/\-().]+)\s+(\d{2,4})\s+(\d{2,4})\s+(\d{2,4})\s+([A-Za-z0-9/\-().]+)\s+(\d{2,4})\s+(\d{2,4})\s+(\d{2,4})$"
)


def norm_line(s: str) -> str:
    s = (s or "").replace("×", "x")
    s = re.sub(r"\s+", " ", s).strip()
    return s


def fix_model_token(token: str) -> str:
    return token.strip().replace("NHA-S", "NhA-S")


def main():
    rows = []
    with pdfplumber.open(str(PDF_PATH)) as pdf:
        for page_no, page in enumerate(pdf.pages, start=1):
            text = page.extract_text() or ""
            lines = [norm_line(x) for x in text.splitlines() if norm_line(x)]
            for line in lines:
                m = PAIR_RE.match(line)
                if not m:
                    continue
                indoor = fix_model_token(m.group(1))
                in_dims = f"{m.group(2)}x{m.group(3)}x{m.group(4)}"
                outdoor = fix_model_token(m.group(5))
                out_dims = f"{m.group(6)}x{m.group(7)}x{m.group(8)}"

                model_pair = f"{indoor}/{outdoor}"
                row = {
                    "model": model_pair,
                    "sku": model_pair.replace("/", "-"),
                    "indoor_dimensions": in_dims,
                    "indoor_dimensions__source_file": str(PDF_PATH).replace("\\", "/"),
                    "indoor_dimensions__source_page": page_no,
                    "indoor_dimensions__source_text": line,
                    "indoor_dimensions__confidence": 0.92,
                    "outdoor_dimensions": out_dims,
                    "outdoor_dimensions__source_file": str(PDF_PATH).replace("\\", "/"),
                    "outdoor_dimensions__source_page": page_no,
                    "outdoor_dimensions__source_text": line,
                    "outdoor_dimensions__confidence": 0.92,
                    "model_indoor": indoor,
                    "model_indoor__source_file": str(PDF_PATH).replace("\\", "/"),
                    "model_indoor__source_page": page_no,
                    "model_indoor__source_text": line,
                    "model_indoor__confidence": 0.92,
                    "model_outdoor": outdoor,
                    "model_outdoor__source_file": str(PDF_PATH).replace("\\", "/"),
                    "model_outdoor__source_page": page_no,
                    "model_outdoor__source_text": line,
                    "model_outdoor__confidence": 0.92,
                }
                rows.append(row)

    # Deduplicate by model pair
    uniq = {}
    for r in rows:
        uniq.setdefault(r["model"], r)
    final_rows = list(uniq.values())

    OUT_PATH.write_text(json.dumps(final_rows, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"written={OUT_PATH}")
    print(f"rows={len(final_rows)}")


if __name__ == "__main__":
    main()

