"""
Demo-only: replace property numbers that start with 0000 with years 2023-2026
in the Semi-Expendable workbook, matching includes/demo_acquisition_year.php.
"""
import os
import sys
import zlib

from openpyxl import load_workbook

YEARS = [2023, 2024, 2025, 2026]
SOURCE = r"e:\RPCSP-semi-expendable-2025.xlsx"
DEST = os.path.join(os.path.dirname(__file__), "..", "storage", "demo", "RPCSP-semi-expendable-demo-years.xlsx")


def php_crc32(s: str) -> int:
    return zlib.crc32(s.encode("utf-8")) & 0xFFFFFFFF


def pick_year(h: int) -> int:
    bucket = h % 100
    if bucket < 18:
        return 2023
    if bucket < 42:
        return 2024
    if bucket < 74:
        return 2025
    return 2026


def main() -> int:
    src = SOURCE
    if len(sys.argv) > 1:
        src = sys.argv[1]
    if not os.path.isfile(src):
        print(f"Workbook not found: {src}")
        return 1

    dest = os.path.abspath(DEST)
    os.makedirs(os.path.dirname(dest), exist_ok=True)

    wb = load_workbook(src)
    skip = {"Sheet1", "Sheet", "Sheet (6)"}
    changed = 0
    by_year = {y: 0 for y in YEARS}

    for name in wb.sheetnames:
        if name in skip:
            continue
        ws = wb[name]
        for row in ws.iter_rows(min_col=3, max_col=3):
            cell = row[0]
            val = cell.value
            if not isinstance(val, str):
                continue
            pn = val.strip()
            if not pn.startswith("0000"):
                continue
            h = php_crc32(pn + "|")
            year = pick_year(h)
            cell.value = str(year) + pn[4:]
            changed += 1
            by_year[year] += 1

    wb.save(dest)
    print(f"Saved demo workbook: {dest}")
    print(f"Property numbers remapped: {changed}")
    for y in YEARS:
        print(f"  {y}: {by_year[y]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
