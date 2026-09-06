from __future__ import annotations

import hashlib
import json
import re
import subprocess
from collections import Counter
from pathlib import Path

import fitz


ROOT = Path(__file__).resolve().parents[1]
REPORT = ROOT / "storage" / "app" / "private" / "reports" / "marketing_capacity_pdf_source_coverage.json"
VISUAL_EVIDENCE = ROOT / "docs" / "catalog" / "evidence" / "daikin_room_air_pages_23_24_reviewed.json"
IDENTITY_CONFLICTS = ROOT / "docs" / "catalog" / "evidence" / "product_identity_conflicts_reviewed.json"


def normalized(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (value or "").upper())


def inventory() -> list[dict]:
    result = subprocess.run(
        ["php", "artisan", "catalog:export-marketing-capacity-inventory", "--json"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        encoding="utf-8",
        check=True,
    )
    payload = json.loads(result.stdout)
    if payload.get("read_only") is not True:
        raise RuntimeError("Product inventory did not prove read-only mode.")
    return payload["products"]


def source_files() -> list[Path]:
    candidates = list((ROOT / "data dieu hoa").rglob("*.pdf"))
    selected = [
        path for path in candidates
        if (path.parent.name == "DAIKIN AIR" and ("SKY AIR" in path.name or "ROOM AIR" in path.name))
        or (path.parent.name == "GREE AIR" and path.name in {
            "Catalogue GMV6 Solar.pdf",
            "E-CATALOGUE LAC 2025.pdf",
            "Gree Umatch R32 Cooling Only.pdf",
        })
    ]
    if len(selected) != 5:
        raise RuntimeError(f"Expected exactly five approved source PDFs; found {len(selected)}.")
    return sorted(selected)


def searchable_pages(path: Path) -> list[str]:
    document = fitz.open(path)
    try:
        return [normalized(page.get_text()) for page in document]
    finally:
        document.close()


def model_parts(product: dict) -> list[str]:
    model = product.get("model_code") or product.get("sku") or ""
    parts = [normalized(part) for part in re.split(r"[/+]", model) if normalized(part)]
    return list(dict.fromkeys(parts))


def main() -> None:
    products = inventory()
    sources = source_files()
    visual_payload = json.loads(VISUAL_EVIDENCE.read_text(encoding="utf-8"))
    conflict_payload = json.loads(IDENTITY_CONFLICTS.read_text(encoding="utf-8"))
    visual_by_model = {normalized(row["model_code"]): row for row in visual_payload["rows"]}
    conflicts_by_product = {int(row["product_id"]): row for row in conflict_payload["rows"]}
    indexed = {str(path): searchable_pages(path) for path in sources}
    source_meta = {
        str(path): {
            "filename": path.name,
            "sha256": hashlib.sha256(path.read_bytes()).hexdigest().upper(),
            "pages": len(indexed[str(path)]),
        }
        for path in sources
    }

    rows = []
    for product in products:
        parts = model_parts(product)
        matches = []
        for path, pages in indexed.items():
            page_numbers = [
                index + 1 for index, text in enumerate(pages)
                if parts and all(part in text for part in parts)
            ]
            if page_numbers:
                matches.append({"source": source_meta[path]["filename"], "pages": page_numbers})

        expected_brand = (product.get("brand_slug") or "").lower()
        brand_matches = [
            match for match in matches
            if (expected_brand == "daikin" and match["source"].startswith("DAIKIN"))
            or (expected_brand == "gree" and not match["source"].startswith("DAIKIN"))
        ]
        visual = visual_by_model.get(normalized(product.get("model_code") or ""))
        conflict = conflicts_by_product.get(int(product["product_id"]))
        if visual and not brand_matches:
            brand_matches.append({
                "source": visual_payload["source_file"],
                "pages": [visual["source_page"]],
                "evidence_mode": "REVIEWED_VISUAL_TABLE",
                "review_scope": visual_payload["scope"],
            })

        if conflict:
            status = "CONFLICT"
        elif not parts:
            status = "UNKEYABLE"
        elif len(brand_matches) == 0:
            status = "MISSING"
        else:
            # The same exact Product may legitimately be documented by more
            # than one official catalogue. That is multi-source provenance,
            # not an identity ambiguity and does not authorize a tier value.
            status = "MATCH"
        rows.append({
            **product,
            "model_parts": parts,
            "source_matches": brand_matches,
            "source_match_count": len(brand_matches),
            "multiple_source_evidence": len(brand_matches) > 1,
            "identity_conflict": conflict,
            "commercial_tier_status": "NOT_PROVEN_BY_IDENTITY_MATCH",
            "status": status,
        })

    counts = Counter(row["status"] for row in rows)
    report = {
        "read_only": True,
        "product_count": len(products),
        "sources": list(source_meta.values()),
        "reviewed_visual_evidence": {
            "source": visual_payload["source_file"],
            "sha256": visual_payload["source_sha256"],
            "rows": len(visual_payload["rows"]),
            "scope": visual_payload["scope"],
            "commercial_tier_status": visual_payload["commercial_tier_status"],
        },
        "identity_conflicts": conflict_payload["rows"],
        "counts": dict(sorted(counts.items())),
        "rows": rows,
        "product_mutation": "NONE",
    }
    REPORT.parent.mkdir(parents=True, exist_ok=True)
    REPORT.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({**report["counts"], "product_count": len(products), "report": str(REPORT), "product_mutation": "NONE"}, ensure_ascii=False))


if __name__ == "__main__":
    main()
