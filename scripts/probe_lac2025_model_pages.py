import re
from pathlib import Path
import pdfplumber

PDF_PATH = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")
MODEL_RE = re.compile(r"\b[A-Z0-9]{2,}(?:[/-][A-Z0-9()]{1,}){1,6}\b")


def norm(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())


def toks(line: str):
    return [m.group(0).strip() for m in MODEL_RE.finditer((line or "").upper())]


with pdfplumber.open(str(PDF_PATH)) as pdf:
    for page_no, page in enumerate(pdf.pages, start=1):
        text = page.extract_text() or ""
        lines = [norm(x) for x in text.splitlines() if norm(x)]
        if not lines:
            continue
        midx = -1
        for i, ln in enumerate(lines):
            if "model" == ln.lower() or ln.lower().startswith("model "):
                midx = i
                break
        if midx < 0:
            continue
        token_lines = []
        for j in range(midx + 1, min(midx + 8, len(lines))):
            ts = toks(lines[j])
            if len(ts) >= 2:
                token_lines.append(ts)
        if token_lines:
            print(f"page={page_no} midx={midx} token_line_sizes={[len(x) for x in token_lines]} line1={token_lines[0][:8]}")

