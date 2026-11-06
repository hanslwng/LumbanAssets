"""Complete PPE workbook extraction and quality analysis."""
from __future__ import annotations

import json
import math
import re
from collections import Counter, defaultdict
from datetime import datetime
from openpyxl import load_workbook

SRC = r"e:\RPCPPE-update-to-2025.xlsx"
OUT = r"scripts/_ppe_raw_data.json"
SUM = r"scripts/_ppe_analysis_summary.json"

SKIP_SHEETS_AS_RECORDS = {"FORM"}  # template

HEADER_MARKERS = ("ARTICLE", "PROPERTY")


def clean(v):
    if v is None:
        return ""
    if isinstance(v, datetime):
        return v.isoformat(sep=" ")
    s = str(v).replace("\n", " ").strip()
    return s


def to_num(v):
    if v is None or v == "":
        return None
    if isinstance(v, (int, float)) and not isinstance(v, bool):
        if isinstance(v, float) and (math.isnan(v) or math.isinf(v)):
            return None
        return float(v)
    s = str(v).strip()
    if s == "":
        return None
    s = s.replace(",", "").replace(" ", "")
    # handle 12.000.00 style
    if s.count(".") > 1:
        parts = s.split(".")
        s = "".join(parts[:-1]) + "." + parts[-1]
    try:
        return float(s)
    except ValueError:
        m = re.search(r"-?[\d.]+", s)
        if not m:
            return None
        try:
            return float(m.group())
        except ValueError:
            return None


def extract_year_from_prop(pn: str):
    if not pn:
        return None
    m = re.match(r"^(\d{4})-", pn)
    if m:
        y = int(m.group(1))
        if 1900 <= y <= 2100:
            return y
    return None


wb_values = load_workbook(SRC, data_only=True)
wb_formulas = load_workbook(SRC, data_only=False)

all_records = []
sheet_meta = []
categories = Counter()
units = Counter()
remarks = Counter()
prop_nos = []
formulas_found = []
iirup_records = []

for name in wb_values.sheetnames:
    ws = wb_values[name]
    wf = wb_formulas[name]
    max_row = ws.max_row or 0
    max_col = ws.max_column or 0
    nonempty = 0
    formula_count = 0
    header_row = None
    report_as_of = ""
    officer = ""
    designation = ""
    entity = ""
    assumption = ""

    for r in range(1, min(20, max_row) + 1):
        a = clean(ws.cell(r, 1).value)
        b = clean(ws.cell(r, 2).value)
        c = clean(ws.cell(r, 3).value)
        e = clean(ws.cell(r, 5).value)
        if "As of" in a or "As at" in a:
            report_as_of = a
        if a.lower().startswith("for which"):
            officer = b
            designation = c
            entity = e
        if "accountability on" in a.lower():
            assumption = b
        if "ARTICLE" in a.upper() and "DESCRIPTION" in (clean(ws.cell(r, 2).value).upper()):
            header_row = r

    for r in range(1, max_row + 1):
        row_has = False
        for c in range(1, max_col + 1):
            v = ws.cell(r, c).value
            fv = wf.cell(r, c).value
            if v is not None and str(v).strip() != "":
                row_has = True
            if isinstance(fv, str) and fv.startswith("="):
                formula_count += 1
                if len(formulas_found) < 80:
                    formulas_found.append({"sheet": name, "cell": f"{wf.cell(r, c).coordinate}", "formula": fv[:120]})
        if row_has:
            nonempty += 1

    sheet_info = {
        "sheet": name,
        "max_row": max_row,
        "max_col": max_col,
        "nonempty_rows": nonempty,
        "header_row": header_row,
        "report_as_of": report_as_of,
        "officer": officer,
        "designation": designation,
        "entity": entity,
        "assumption": assumption,
        "formula_count": formula_count,
        "records": 0,
        "categories": [],
        "notes": "",
    }

    if name == "IIRUP":
        # Different form: Inventory and Inspection Report of Unserviceable Property
        current_group = ""
        for r in range(13, max_row + 1):
            date_acq = ws.cell(r, 1).value
            article = clean(ws.cell(r, 2).value)
            prop = clean(ws.cell(r, 3).value)
            qty = to_num(ws.cell(r, 4).value)
            unit_cost = to_num(ws.cell(r, 5).value)
            total_cost = to_num(ws.cell(r, 6).value)
            accum_dep = to_num(ws.cell(r, 7).value)
            accum_imp = to_num(ws.cell(r, 8).value)
            carrying = to_num(ws.cell(r, 9).value)
            rem = clean(ws.cell(r, 10).value)
            sale = clean(ws.cell(r, 11).value)
            transfer = clean(ws.cell(r, 12).value)
            destruction = clean(ws.cell(r, 13).value)
            others = clean(ws.cell(r, 14).value)
            appraised = to_num(ws.cell(r, 15).value) if max_col >= 15 else None
            rec_mode = clean(ws.cell(r, 16).value) if max_col >= 16 else ""
            inspect_officer = clean(ws.cell(r, 17).value) if max_col >= 17 else ""
            extra = clean(ws.cell(r, 18).value) if max_col >= 18 else ""

            if article and qty is None and unit_cost is None and not prop and date_acq in (None, ""):
                current_group = article
                continue
            if str(article).lower() == "total" or str(clean(ws.cell(r, 5).value)).lower() == "total":
                continue
            if not article and date_acq in (None, "") and qty is None:
                continue
            rec = {
                "sheet": name,
                "tab_type": "IIRUP_unserviceable",
                "row": r,
                "category": current_group,
                "date_acquired": clean(date_acq) if not isinstance(date_acq, datetime) else date_acq.date().isoformat(),
                "article": article,
                "description": article,
                "property_no": prop,
                "quantity": qty,
                "unit_cost": unit_cost,
                "total_cost": total_cost,
                "accumulated_depreciation": accum_dep,
                "accumulated_impairment": accum_imp,
                "carrying_amount": carrying,
                "remarks": rem,
                "disposal_sale": sale,
                "disposal_transfer": transfer,
                "disposal_destruction": destruction,
                "disposal_others": others,
            }
            iirup_records.append(rec)
            all_records.append({
                "sheet": name,
                "row": r,
                "record_kind": "iirup",
                "category": current_group,
                "article": article,
                "description": article,
                "property_no": prop,
                "unit_measure": "",
                "unit_value": unit_cost,
                "card_qty": qty,
                "count_qty": None,
                "shortage_qty": None,
                "shortage_val": None,
                "remarks": rem,
                "report_as_of": report_as_of,
            })
        sheet_info["records"] = len(iirup_records)
        sheet_info["notes"] = "IIRUP form; not RPCPPE physical-count layout"
        sheet_meta.append(sheet_info)
        continue

    if name == "FORM" or header_row is None:
        sheet_info["notes"] = "Template / no item records" if name == "FORM" else "Header not found"
        sheet_meta.append(sheet_info)
        continue

    current_category = None
    recs = 0
    cats = []
    for r in range(header_row + 2, max_row + 1):
        article = clean(ws.cell(r, 1).value)
        desc = clean(ws.cell(r, 2).value)
        prop = clean(ws.cell(r, 3).value)
        uom = clean(ws.cell(r, 4).value)
        uv = to_num(ws.cell(r, 5).value)
        card = to_num(ws.cell(r, 6).value)
        count = to_num(ws.cell(r, 7).value)
        sh_qty = to_num(ws.cell(r, 8).value)
        sh_val = to_num(ws.cell(r, 9).value)
        rem = clean(ws.cell(r, 10).value)

        # category header: article filled, no property number, no description
        if article and not prop and not desc:
            current_category = article
            cats.append(article)
            categories[article] += 0
            continue
        if not desc and not prop and not article:
            continue
        # skip obvious footers
        joined = (article + " " + desc + " " + prop).upper()
        if any(x in joined for x in ["NOTHING FOLLOWS", "CERTIFIED CORRECT", "APPROVED BY", "SIGNATURE OVER"]):
            continue

        recs += 1
        if current_category:
            categories[current_category] += 1
        if uom:
            units[uom] += 1
        if rem:
            remarks[rem] += 1
        if prop:
            prop_nos.append((prop, name, r))

        all_records.append({
            "sheet": name,
            "row": r,
            "record_kind": "rpcppe",
            "category": current_category,
            "article": article,
            "description": desc,
            "property_no": prop,
            "unit_measure": uom,
            "unit_value": uv,
            "card_qty": card,
            "count_qty": count,
            "shortage_qty": sh_qty,
            "shortage_val": sh_val,
            "remarks": rem,
            "report_as_of": report_as_of,
            "officer": officer,
            "acq_year_from_pn": extract_year_from_prop(prop),
        })

    sheet_info["records"] = recs
    sheet_info["categories"] = cats
    sheet_meta.append(sheet_info)

wb_values.close()
wb_formulas.close()

rpcppe = [r for r in all_records if r.get("record_kind") == "rpcppe"]
n = len(rpcppe)

def pct(x, d):
    return round(100.0 * x / d, 1) if d else 0.0

missing_pn = sum(1 for r in rpcppe if not r["property_no"])
missing_desc = sum(1 for r in rpcppe if not r["description"])
missing_uv = sum(1 for r in rpcppe if r["unit_value"] is None)
missing_card = sum(1 for r in rpcppe if r["card_qty"] is None)
missing_count = sum(1 for r in rpcppe if r["count_qty"] is None)
filled_sh_qty = sum(1 for r in rpcppe if r["shortage_qty"] not in (None,))
filled_sh_val = sum(1 for r in rpcppe if r["shortage_val"] not in (None,))
filled_remarks = sum(1 for r in rpcppe if r["remarks"])
zero_uv = sum(1 for r in rpcppe if r["unit_value"] == 0)
neg_uv = sum(1 for r in rpcppe if r["unit_value"] is not None and r["unit_value"] < 0)

# reconciliation
consistent_card_minus_count = 0
consistent_count_minus_card = 0
inconsistent = []
both_qty = 0
for r in rpcppe:
    if r["card_qty"] is None or r["count_qty"] is None:
        continue
    both_qty += 1
    diff_a = r["card_qty"] - r["count_qty"]
    diff_b = r["count_qty"] - r["card_qty"]
    if r["shortage_qty"] is None:
        if abs(diff_a) < 1e-9:
            consistent_card_minus_count += 1
            consistent_count_minus_card += 1
        continue
    if abs((r["shortage_qty"] or 0) - diff_a) < 1e-6:
        consistent_card_minus_count += 1
    elif abs((r["shortage_qty"] or 0) - diff_b) < 1e-6:
        consistent_count_minus_card += 1
    else:
        inconsistent.append({"sheet": r["sheet"], "row": r["row"], "pn": r["property_no"], "card": r["card_qty"], "count": r["count_qty"], "reported": r["shortage_qty"]})

value_ok = 0
value_mismatch = []
for r in rpcppe:
    if r["shortage_qty"] in (None, 0) and r["shortage_val"] in (None, 0):
        continue
    if r["shortage_qty"] is None or r["unit_value"] is None or r["shortage_val"] is None:
        continue
    expected = r["shortage_qty"] * r["unit_value"]
    if abs(expected - r["shortage_val"]) < 0.05:
        value_ok += 1
    else:
        value_mismatch.append({"sheet": r["sheet"], "pn": r["property_no"], "expected": expected, "reported": r["shortage_val"]})

pn_counter = Counter([p[0] for p in prop_nos])
dup_pns = {k: v for k, v in pn_counter.items() if v > 1}

# cross-sheet pn
pn_sheets = defaultdict(set)
for p, sh, _r in prop_nos:
    pn_sheets[p].add(sh)
cross = {k: sorted(v) for k, v in pn_sheets.items() if len(v) > 1}

# years
years = Counter([r["acq_year_from_pn"] for r in rpcppe if r.get("acq_year_from_pn")])
pn_0000 = sum(1 for r in rpcppe if (r["property_no"] or "").startswith("0000"))

# values
vals = [r["unit_value"] for r in rpcppe if r["unit_value"] is not None]
vals_sorted = sorted(vals)
total_value_unit = sum(vals)
# inventory value = unit_value * count or card
inv_card = 0.0
inv_count = 0.0
for r in rpcppe:
    if r["unit_value"] is None:
        continue
    if r["card_qty"] is not None:
        inv_card += r["unit_value"] * r["card_qty"]
    if r["count_qty"] is not None:
        inv_count += r["unit_value"] * r["count_qty"]

def stats(arr):
    if not arr:
        return None
    a = sorted(arr)
    n = len(a)
    mean = sum(a) / n
    var = sum((x - mean) ** 2 for x in a) / n
    def pctile(p):
        i = min(n - 1, max(0, int(round((p / 100) * (n - 1)))))
        return a[i]
    return {
        "count": n,
        "sum": sum(a),
        "mean": mean,
        "median": a[n // 2] if n % 2 else (a[n // 2 - 1] + a[n // 2]) / 2,
        "min": a[0],
        "max": a[-1],
        "stdev": math.sqrt(var),
        "p10": pctile(10),
        "p25": pctile(25),
        "p75": pctile(75),
        "p90": pctile(90),
        "p95": pctile(95),
        "p99": pctile(99),
    }

by_sheet = Counter(r["sheet"] for r in rpcppe)
by_cat = Counter(r["category"] or "(uncategorized)" for r in rpcppe)

# IIRUP extras
iirup_total_cost = sum(r["total_cost"] or 0 for r in iirup_records)

summary = {
    "file": SRC,
    "sheets": [s["sheet"] for s in sheet_meta],
    "sheet_meta": sheet_meta,
    "rpcppe_records": n,
    "iirup_records": len(iirup_records),
    "total_extracted_rows": len(all_records),
    "by_sheet": dict(by_sheet),
    "categories": dict(categories),
    "units": dict(units),
    "remarks_top": remarks.most_common(30),
    "remarks_filled": filled_remarks,
    "missing": {
        "property_no": missing_pn,
        "description": missing_desc,
        "unit_value": missing_uv,
        "card_qty": missing_card,
        "count_qty": missing_count,
        "shortage_qty_blank": n - filled_sh_qty,
        "shortage_val_blank": n - filled_sh_val,
    },
    "zero_unit_value": zero_uv,
    "neg_unit_value": neg_uv,
    "duplicate_property_numbers": len(dup_pns),
    "duplicate_pn_examples": list(dup_pns.items())[:40],
    "cross_sheet_property_numbers": {k: v for k, v in list(cross.items())[:50]},
    "cross_sheet_count": len(cross),
    "years_from_property_no": {str(k): v for k, v in sorted(years.items())},
    "pn_0000": pn_0000,
    "unit_value_stats": stats(vals),
    "inventory_value_card": inv_card,
    "inventory_value_count": inv_count,
    "reconciliation": {
        "records_with_both_qty": both_qty,
        "consistent_card_minus_count": consistent_card_minus_count,
        "consistent_count_minus_card": consistent_count_minus_card,
        "inconsistent_count": len(inconsistent),
        "inconsistent_examples": inconsistent[:20],
        "shortage_qty_filled": filled_sh_qty,
        "shortage_val_filled": filled_sh_val,
        "value_formula_matches": value_ok,
        "value_formula_mismatches": len(value_mismatch),
    },
    "iirup_total_cost": iirup_total_cost,
    "formulas_found": formulas_found[:40],
    "formula_count_by_sheet": {s["sheet"]: s["formula_count"] for s in sheet_meta},
}

with open(OUT, "w", encoding="utf-8") as f:
    json.dump({"records": all_records, "iirup": iirup_records, "sheet_meta": sheet_meta}, f, ensure_ascii=False, indent=2)

with open(SUM, "w", encoding="utf-8") as f:
    json.dump(summary, f, ensure_ascii=False, indent=2)

print("RPC PPE records:", n)
print("IIRUP records:", len(iirup_records))
print("By sheet:", dict(by_sheet))
print("Categories:")
for k, v in categories.most_common():
    print(f"  {k}: {v}")
print("Units:", dict(units))
print("Missing PN/desc/uv/card/count:", missing_pn, missing_desc, missing_uv, missing_card, missing_count)
print("Shortage qty/val filled:", filled_sh_qty, filled_sh_val)
print("Dup PNs:", len(dup_pns), "cross-sheet PNs:", len(cross))
print("0000 PNs:", pn_0000)
print("Years:", dict(years))
print("Inv value card/count:", round(inv_card, 2), round(inv_count, 2))
print("IIRUP total cost:", iirup_total_cost)
print("Remarks filled:", filled_remarks)
print("Top remarks:", remarks.most_common(15))
print("Recon both qty:", both_qty, "inconsistent:", len(inconsistent))
print("Saved", OUT, SUM)
