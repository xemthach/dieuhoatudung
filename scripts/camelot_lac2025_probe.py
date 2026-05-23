from pathlib import Path
import camelot

pdf = Path(r"d:/laragon/www/dieuhoa-tudung/data dieu hoa/GREE AIR/E-CATALOGUE LAC 2025.pdf")

for flavor in ["lattice", "stream"]:
    try:
        tables = camelot.read_pdf(str(pdf), pages="30-45", flavor=flavor)
        print(flavor, "tables=", tables.n)
        if tables.n:
            t = tables[0].df
            print("sample_shape=", t.shape)
            print(t.head(10).to_string(index=False))
    except Exception as e:
        print(flavor, "error:", e)

