import json
import re
from pathlib import Path
import sys

import camelot

sys.stdout.reconfigure(encoding="utf-8")

PDF_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
OUT_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/storage/catalogs/verified_pdf_extract_gree_lac2025_camelot.json")

MODEL_RE = re.compile(r"\b[A-Z0-9]{2,}(?:[/-][A-Z0-9()]{1,}){1,6}\b")


def clean(s):
    return re.sub(r"\s+", " ", str(s or "")).strip()


def tokens(s):
    return [m.group(0).strip() for m in MODEL_RE.finditer((s or "").upper())]


def key_from_label(label):
    l = label.lower()
    if "btu/h" in l:
        return "capacity_btu"
    if "eer/cop" in l:
        return "eer"
    if "nguồn điện" in l:
        return "voltage"
    if "công suất điện" in l:
        return "power_input"
    if "dòng điện định mức" in l:
        return "current"
    if "độ ồn" in l:
        return "noise_level"
    if "lưu lượng gió" in l:
        return "airflow"
    if "inch (mm)" in l and "lỏng" in l:
        return "pipe_size_liquid"
    if "inch (mm)" in l and ("gas" in l or "hơi" in l):
        return "pipe_size_gas"
    return None


def set_field(rows, model, key, val, page, src):
    if not val:
        return
    row = rows.setdefault(model, {"model": model, "sku": model.replace("/", "-")})
    row[key] = val
    row[f"{key}__source_file"] = str(PDF_PATH).replace("\\", "/")
    row[f"{key}__source_page"] = page
    row[f"{key}__source_text"] = src
    row[f"{key}__confidence"] = 0.9


def main():
    tables = camelot.read_pdf(str(PDF_PATH), pages="all", flavor="stream")
    rows = {}

    for t in tables:
        df = t.df
        # find model lines
        model_lines = []
        for i in range(min(8, len(df))):
            line = clean(" ".join(df.iloc[i].tolist()))
            tk = tokens(line)
            if len(tk) >= 2:
                model_lines.append(tk)
        if not model_lines:
            continue

        if len(model_lines) >= 2 and len(model_lines[0]) == len(model_lines[1]):
            models = [f"{model_lines[0][i]}/{model_lines[1][i]}" for i in range(len(model_lines[0]))]
        else:
            models = model_lines[0]

        for _, r in df.iterrows():
            cells = [clean(x) for x in r.tolist() if clean(x)]
            if len(cells) < 2:
                continue
            line = " ".join(cells)
            key = key_from_label(line)
            if not key:
                continue
            vals = cells[-len(models):]
            if len(vals) != len(models):
                continue
            for m, v in zip(models, vals):
                set_field(rows, m, key, v, t.page, line)

    out = []
    for row in rows.values():
        if any(k not in {"model", "sku"} and "__" not in k for k in row.keys()):
            out.append(row)

    OUT_PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    print("written=", OUT_PATH)
    print("rows=", len(out))


if __name__ == "__main__":
    main()

