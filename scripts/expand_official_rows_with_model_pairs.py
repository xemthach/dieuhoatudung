import json
import re
from pathlib import Path

import pdfplumber

PDF_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
IN_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/storage/catalogs/verified_pdf_extract_gree_lac2025_camelot.json")
OUT_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/storage/catalogs/verified_pdf_extract_gree_lac2025_camelot_expanded.json")

MODEL_RE = re.compile(r"\b[A-Z0-9]{2,}(?:[/-][A-Z0-9()]{1,}){1,6}\b")


def norm(s: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (s or "").upper())


def toks(line: str):
    return [m.group(0).strip() for m in MODEL_RE.finditer((line or "").upper())]


def page_pair_map():
    by_page = {}
    with pdfplumber.open(str(PDF_PATH)) as pdf:
        for page_no, page in enumerate(pdf.pages, start=1):
            text = page.extract_text() or ""
            lines = [re.sub(r"\s+", " ", x).strip() for x in text.splitlines() if x.strip()]
            model_idx = -1
            for i, ln in enumerate(lines):
                if ln.lower() == "model":
                    model_idx = i
                    break
            if model_idx < 0:
                continue
            token_lines = []
            for j in range(model_idx + 1, min(model_idx + 6, len(lines))):
                ts = toks(lines[j])
                if len(ts) >= 2:
                    token_lines.append(ts)
                    if len(token_lines) == 2:
                        break
            if len(token_lines) < 2 or len(token_lines[0]) != len(token_lines[1]):
                continue
            pairs = {}
            for indoor, outdoor in zip(token_lines[0], token_lines[1]):
                pairs[norm(outdoor)] = f"{indoor}/{outdoor}"
            by_page[page_no] = pairs
    return by_page


def main():
    rows = json.loads(IN_PATH.read_text(encoding="utf-8"))
    pair_map = page_pair_map()
    out = []

    for row in rows:
        out.append(row)
        model = str(row.get("model", ""))
        page = int(row.get("eer__source_page") or row.get("capacity_btu__source_page") or 0)
        if page <= 0:
            continue
        pairs = pair_map.get(page, {})
        if not pairs:
            continue
        n = norm(model)
        combined = pairs.get(n)
        if not combined:
            continue
        cloned = dict(row)
        cloned["model"] = combined
        cloned["sku"] = combined.replace("/", "-")
        out.append(cloned)

    # dedupe by model + first source page
    uniq = {}
    for r in out:
        model = str(r.get("model", ""))
        page = r.get("eer__source_page") or r.get("capacity_btu__source_page") or r.get("voltage__source_page") or ""
        k = f"{model}|{page}"
        if k not in uniq:
            uniq[k] = r

    final_rows = list(uniq.values())
    OUT_PATH.write_text(json.dumps(final_rows, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"written={OUT_PATH}")
    print(f"rows={len(final_rows)}")


if __name__ == "__main__":
    main()

