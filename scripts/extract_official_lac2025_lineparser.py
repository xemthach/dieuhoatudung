import json
import re
from pathlib import Path

import pdfplumber

PDF_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
OUT_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/storage/catalogs/verified_pdf_extract_gree_lac2025_lineparser.json")

MODEL_RE = re.compile(r"\b[A-Z0-9]{2,}(?:[/-][A-Z0-9()]{1,}){1,6}\b")


def norm(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())


def model_tokens(line: str):
    return [m.group(0).strip() for m in MODEL_RE.finditer((line or "").upper())]


def split_values(line: str):
    parts = [x.strip() for x in re.split(r"\s{2,}", line) if x.strip()]
    return parts


def set_field(bag, model, key, val, page, source_line):
    if not val:
        return
    row = bag.setdefault(model, {"model": model, "sku": model.replace("/", "-")})
    row[key] = val
    row[f"{key}__source_file"] = str(PDF_PATH).replace("\\", "/")
    row[f"{key}__source_page"] = page
    row[f"{key}__source_text"] = source_line
    row[f"{key}__confidence"] = 0.9


def parse_page(lines, page_no, bag):
    model_idx = -1
    for i, ln in enumerate(lines):
        if ln.lower() == "model":
            model_idx = i
            break
    if model_idx < 0:
        return

    token_lines = []
    for j in range(model_idx + 1, min(model_idx + 6, len(lines))):
        toks = model_tokens(lines[j])
        if len(toks) >= 2:
            token_lines.append(toks)
            if len(token_lines) == 2:
                break
    if not token_lines:
        return

    if len(token_lines) >= 2 and len(token_lines[0]) == len(token_lines[1]):
        models = [f"{token_lines[0][k]}/{token_lines[1][k]}" for k in range(len(token_lines[0]))]
    else:
        models = token_lines[0]

    section = ""
    for ln in lines[model_idx + 1 :]:
        l = ln.lower()
        if "dàn lạnh" in l:
            section = "indoor"
            continue
        if "dàn nóng" in l:
            section = "outdoor"
            continue

        key = None
        values = []
        cells = split_values(ln)
        if len(cells) < 2:
            continue

        if "btu/h" in l:
            key = "capacity_btu"
            idx = next((i for i, c in enumerate(cells) if "BTU/h" in c or "btu/h" in c.lower()), -1)
            values = cells[idx + 1 :] if idx >= 0 else cells[1:]
        elif "eer/cop" in l:
            key = "eer"
            values = cells[-len(models) :]
        elif "nguồn điện" in l:
            key = "voltage"
            values = cells[-len(models) :]
        elif "công suất điện" in l:
            key = "power_input"
            values = cells[-len(models) :]
        elif "dòng điện định mức" in l:
            key = "current"
            values = cells[-len(models) :]
        elif "độ ồn" in l:
            key = "noise_level"
            values = cells[-len(models) :]
        elif "lưu lượng gió" in l:
            key = "airflow"
            values = cells[-len(models) :]
        elif "inch (mm)" in l and "lỏng" in l:
            key = "pipe_size_liquid"
            values = cells[-len(models) :]
        elif "inch (mm)" in l and ("gas" in l or "hơi" in l):
            key = "pipe_size_gas"
            values = cells[-len(models) :]
        elif "chiều dài m" in l:
            key = "max_pipe_length"
            values = cells[-len(models) :]
        elif "chiều cao m" in l:
            key = "max_height_difference"
            values = cells[-len(models) :]
        elif "máy mm" in l and section == "indoor":
            key = "indoor_dimensions"
            values = cells[-len(models) :]
        elif "máy mm" in l and section == "outdoor":
            key = "outdoor_dimensions"
            values = cells[-len(models) :]
        elif "máy kg" in l and section == "indoor":
            key = "indoor_weight"
            values = cells[-len(models) :]
        elif "máy kg" in l and section == "outdoor":
            key = "outdoor_weight"
            values = cells[-len(models) :]

        if not key or not values:
            continue

        if len(values) < len(models):
            continue
        values = values[-len(models) :]
        for m, v in zip(models, values):
            set_field(bag, m, key, v, page_no, ln)


def main():
    bag = {}
    with pdfplumber.open(str(PDF_PATH)) as pdf:
        for page_no, page in enumerate(pdf.pages, start=1):
            text = page.extract_text() or ""
            lines = [norm(x) for x in text.splitlines() if norm(x)]
            if not lines:
                continue
            parse_page(lines, page_no, bag)

    rows = []
    for row in bag.values():
        if any(k not in {"model", "sku"} and "__" not in k for k in row.keys()):
            rows.append(row)
    OUT_PATH.write_text(json.dumps(rows, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"written={OUT_PATH}")
    print(f"rows={len(rows)}")


if __name__ == "__main__":
    main()

