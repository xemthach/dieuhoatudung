import json
import re
from pathlib import Path

import pdfplumber


PDF_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
OUT_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/storage/catalogs/verified_pdf_extract_gree_lac2025_full.json")

MODEL_RE = re.compile(r"\b[A-Z0-9]{2,}(?:[/-][A-Z0-9()]{1,}){1,6}\b")


def norm_text(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())


def norm_model(s: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (s or "").upper())


def detect_model_tokens(line: str):
    tokens = []
    for m in MODEL_RE.finditer((line or "").upper()):
        t = m.group(0).strip(" -")
        if len(norm_model(t)) < 6:
            continue
        tokens.append(t)
    return tokens


def map_label_to_key(label: str):
    l = label.lower()
    if "công suất định mức" in l and "btu" in l:
        return "capacity_btu"
    if "công suất định mức" in l and "kw" in l:
        return "capacity_kw"
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
    if "kích thước" in l and "dàn lạnh" in l:
        return "indoor_dimensions"
    if "kích thước" in l and "dàn nóng" in l:
        return "outdoor_dimensions"
    if "khối lượng" in l and "dàn lạnh" in l:
        return "indoor_weight"
    if "khối lượng" in l and "dàn nóng" in l:
        return "outdoor_weight"
    if "môi chất lạnh" in l:
        return "refrigerant"
    if "áp suất tĩnh" in l:
        return "static_pressure"
    if "ống lỏng" in l:
        return "pipe_size_liquid"
    if "ống gas" in l or "ống hơi" in l:
        return "pipe_size_gas"
    return None


def parse_models_from_header_text(block: str):
    lines = [norm_text(x) for x in (block or "").splitlines() if norm_text(x)]
    token_lines = []
    for ln in lines:
        toks = detect_model_tokens(ln)
        if len(toks) >= 2:
            token_lines.append(toks)

    if len(token_lines) >= 2 and len(token_lines[0]) == len(token_lines[1]):
        return [f"{token_lines[0][i]}/{token_lines[1][i]}" for i in range(len(token_lines[0]))]
    if token_lines:
        return token_lines[0]
    return []


def main():
    rows = []
    by_model = {}

    with pdfplumber.open(str(PDF_PATH)) as pdf:
        for page_idx, page in enumerate(pdf.pages, start=1):
            tables = page.extract_tables() or []
            for table in tables:
                if not table or len(table) < 6:
                    continue

                # find model header block
                header_block = ""
                for r in table[:5]:
                    for c in r:
                        if not c:
                            continue
                        t = norm_text(str(c))
                        if "model" in t.lower() or len(detect_model_tokens(t)) >= 2:
                            header_block += "\n" + t

                header_models = parse_models_from_header_text(header_block)
                if len(header_models) < 1:
                    continue

                for m in header_models:
                    nm = norm_model(m)
                    if nm not in by_model:
                        by_model[nm] = {"model": m, "sku": m.replace("/", "-")}

                section = ""
                for r in table:
                    row_cells = [norm_text(str(x)) if x is not None else "" for x in r]
                    joined = " ".join([x for x in row_cells if x])
                    if not joined:
                        continue

                    lj = joined.lower()
                    if "dàn lạnh" in lj:
                        section = "indoor"
                        continue
                    if "dàn nóng" in lj:
                        section = "outdoor"
                        continue
                    if "mặt nạ" in lj:
                        section = "panel"
                        continue

                    label = row_cells[1] if len(row_cells) > 1 else row_cells[0]
                    unit = row_cells[3] if len(row_cells) > 3 else ""
                    full_label = f"{label} {unit}".strip()

                    key = map_label_to_key(full_label)
                    if not key:
                        # contextual mapping
                        if "kích thước" in label.lower():
                            if section == "indoor":
                                key = "indoor_dimensions"
                            elif section == "outdoor":
                                key = "outdoor_dimensions"
                        elif "khối lượng" in label.lower():
                            if section == "indoor":
                                key = "indoor_weight"
                            elif section == "outdoor":
                                key = "outdoor_weight"
                    if not key:
                        continue

                    values = row_cells[4:] if len(row_cells) > 4 else []
                    if not values:
                        continue

                    count = min(len(values), len(header_models))
                    for i in range(count):
                        value = values[i]
                        if not value:
                            continue
                        model = header_models[i]
                        nm = norm_model(model)
                        by_model[nm][key] = value
                        by_model[nm][f"{key}__source_file"] = str(PDF_PATH).replace("\\", "/")
                        by_model[nm][f"{key}__source_page"] = page_idx
                        by_model[nm][f"{key}__source_text"] = f"table_row={joined}"
                        by_model[nm][f"{key}__confidence"] = 0.92

    for _, item in by_model.items():
        # keep only rows with at least one technical field
        has_field = any(
            k not in {"model", "sku"} and "__" not in k
            for k in item.keys()
        )
        if has_field:
            rows.append(item)

    OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUT_PATH.write_text(json.dumps(rows, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"written={OUT_PATH}")
    print(f"rows={len(rows)}")


if __name__ == "__main__":
    main()
