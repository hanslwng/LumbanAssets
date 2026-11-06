"""First-pass inspection of the entire PPE workbook."""
from openpyxl import load_workbook

SRC = r"e:\RPCPPE-update-to-2025.xlsx"
wb = load_workbook(SRC, data_only=True, read_only=True)

print("FILE:", SRC)
print("SHEET_COUNT:", len(wb.sheetnames))
print("SHEETS:", wb.sheetnames)
print()

for name in wb.sheetnames:
    ws = wb[name]
    max_row = ws.max_row or 0
    max_col = ws.max_column or 0
    nonempty = 0
    first_rows = []
    used_cols = set()
    for idx, row in enumerate(ws.iter_rows(values_only=True), 1):
        vals = list(row)
        if any(v is not None and str(v).strip() != "" for v in vals):
            nonempty += 1
            for c, v in enumerate(vals, 1):
                if v is not None and str(v).strip() != "":
                    used_cols.add(c)
        if idx <= 25:
            clipped = []
            for v in vals[:14]:
                if v is None:
                    clipped.append("")
                else:
                    s = str(v).replace("\n", " ").strip()
                    clipped.append(s[:45])
            first_rows.append((idx, clipped))
    print("=" * 80)
    print(f"SHEET: {name}")
    print(f"max_row={max_row} max_col={max_col} nonempty_rows={nonempty} used_cols={sorted(used_cols)}")
    print("FIRST 25 NONEMPTY-AWARE ROWS (cols A-N):")
    for idx, clipped in first_rows:
        print(f"  R{idx:03d}: {clipped}")
    print()

wb.close()
