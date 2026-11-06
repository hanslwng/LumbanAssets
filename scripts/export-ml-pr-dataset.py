# -*- coding: utf-8 -*-
"""
Parse Lumban ML PR Excel -> JSON for PHP import.
"""
from __future__ import annotations

import json
import re
from collections import defaultdict
from datetime import date, datetime
from pathlib import Path

from openpyxl import load_workbook

XLSX = Path(r"D:\Downloads\Lumban_LGU_PR_Historical_Jan-Sep_2026_ML_Dataset.xlsx")
OUT = Path(r"C:\xampp\htdocs\lgu_invent\storage\imports\ml_pr_jan_sep_2026.json")

CODE_TO_APP = {
    "ACCOUNTING": "ACCT",
    "ADMIN": "ADMIN",
    "ASSESSOR": "ASSO",
    "BUDGET": "BUDG",
    "COA": "COA",
    "DA": "AGRI",
    "DEPED": "DEPED",
    "DILG": "DILG",
    "ENGR": "ENGR",
    "GSO": "GSO",
    "HRMO": "HRMO",
    "LICENSE": "LICENSE",
    "LWS": "LWS",
    "MARKET": "MARKET",
    "MCR": "MCR",
    "MDRRMO": "MDRRMO",
    "MPDC": "PLAN",
    "MSWDO": "SWDO",
    "OM": "MAYOR",
    "PDAO": "PDAO",
    "PESO": "PESO",
    "RHU": "HLTH",
    "SB": "VMAY",
    "SENIOR": "SENIOR",
    "SWM": "ENRO",
    "TRADING POST": "OTP",
    "TREASURY": "TRES",
}

# Illustrative unit costs (PHP) — scaled per PR to match PR Total.
PRICE = {
    "ballpen blue": 12,
    "ballpen black": 12,
    "pencils": 8,
    "highlighter": 25,
    "permanent marker": 35,
    "correction tape": 45,
    "stapler": 120,
    "staples": 25,
    "paper clips": 20,
    "folders": 15,
    "record folders": 25,
    "long envelopes": 8,
    "mailing envelope": 8,
    "invitation envelopes": 12,
    "notepads": 35,
    "logbook": 85,
    "bond paper a4": 280,
    "legal-size paper": 320,
    "manila paper": 18,
    "carbon paper": 60,
    "masking tape": 40,
    "scotch tape": 35,
    "printer toner": 1850,
    "printer ink": 650,
    "face masks": 180,
    "alcohol 70%": 120,
    "disinfectant": 150,
    "bleach": 85,
    "detergent": 95,
    "cleaning rag": 25,
    "broom": 120,
    "mop head": 90,
    "rubber gloves": 55,
    "garbage bags, large": 140,
    "garbage bags large": 140,
    "plunger": 110,
    "zonrox, original": 250,
    "zonrox, violet": 400,
    "lysol": 800,
    "albatros": 110,
    "liquid sosa": 350,
}

ITEM_RE = re.compile(r"^\s*(.+?)\s+(\d+(?:\.\d+)?)\s+([A-Za-z][A-Za-z.]*)\s*$")


def parse_items(blob: str) -> list[dict]:
    parts = [p.strip() for p in str(blob or "").split(",") if p.strip()]
    merged: list[str] = []
    buf = ""
    for p in parts:
        cand = f"{buf}, {p}".strip(", ").strip() if buf else p
        if ITEM_RE.match(cand):
            merged.append(cand)
            buf = ""
        elif ITEM_RE.match(p):
            if buf:
                merged.append(buf)
            merged.append(p)
            buf = ""
        else:
            buf = cand
    if buf:
        merged.append(buf)

    out = []
    for m in merged:
        m2 = ITEM_RE.match(m)
        if not m2:
            continue
        name = m2.group(1).strip()
        qty = float(m2.group(2))
        unit = m2.group(3).strip().lower()
        if unit in ("pcs", "pc."):
            unit = "pc"
        if unit == "btl":
            unit = "bottle"
        key = name.lower()
        unit_cost = float(PRICE.get(key, 50.0))
        out.append({"name": name, "qty": qty, "unit": unit, "unit_cost": unit_cost})
    return out


def scale_to_total(lines: list[dict], pr_total: float) -> list[dict]:
    if not lines:
        return lines
    raw = sum(max(0.0, l["qty"]) * max(0.01, l["unit_cost"]) for l in lines)
    if raw <= 0 or pr_total <= 0:
        share = (pr_total / len(lines)) if lines else 0.0
        out = []
        for l in lines:
            qty = max(1.0, float(l["qty"] or 1))
            uc = round(share / qty, 2)
            out.append({
                "name": l["name"],
                "qty": l["qty"],
                "unit": l["unit"],
                "unit_cost": uc,
                "line_total": round(qty * uc, 2),
            })
        if out:
            drift = round(pr_total - sum(x["line_total"] for x in out), 2)
            out[-1]["line_total"] = round(out[-1]["line_total"] + drift, 2)
        return out

    factor = pr_total / raw
    out = []
    running = 0.0
    for i, l in enumerate(lines):
        qty = float(l["qty"] or 0) or 1.0
        if i == len(lines) - 1:
            lt = round(pr_total - running, 2)
            uc = round(lt / qty, 2)
        else:
            uc = round(float(l["unit_cost"]) * factor, 2)
            lt = round(qty * uc, 2)
            running += lt
        out.append({
            "name": l["name"],
            "qty": l["qty"],
            "unit": l["unit"],
            "unit_cost": uc,
            "line_total": lt,
        })
    return out


def main() -> None:
    wb = load_workbook(XLSX, data_only=True, read_only=True)

    budgets = {}
    ceilings = defaultdict(dict)
    if "Budget & Demand" in wb.sheetnames:
        ws = wb["Budget & Demand"]
        rows = list(ws.iter_rows(values_only=True))
        hdr = [str(h).strip() if h else "" for h in rows[0]]
        month_cols = {}
        for i, h in enumerate(hdr):
            m = re.search(r"(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep)", h, re.I)
            if m:
                mon = {"jan": 1, "feb": 2, "mar": 3, "apr": 4, "may": 5, "jun": 6, "jul": 7, "aug": 8, "sep": 9}[m.group(1).lower()]
                month_cols[i] = f"2026-{mon:02d}"
        code_i = hdr.index("Department Code")
        base_i = hdr.index("Base Max Monthly Budget")
        for r in rows[1:]:
            if not r or r[code_i] is None:
                continue
            code = str(r[code_i]).strip().upper()
            app = CODE_TO_APP.get(code)
            if not app:
                continue
            budgets[app] = float(r[base_i] or 0)
            for i, ym in month_cols.items():
                ceilings[app][ym] = float(r[i] or 0)

    ws = wb["PR Transactions"]
    rows = list(ws.iter_rows(values_only=True))
    hdr = [str(h).strip() if h else "" for h in rows[0]]
    hi = {h: i for i, h in enumerate(hdr)}

    prs = []
    skipped = []
    for r in rows[1:]:
        if not r or not any(c is not None and str(c).strip() for c in r):
            continue
        code = str(r[hi["Department Code"]] or "").strip().upper()
        app_code = CODE_TO_APP.get(code)
        if not app_code:
            skipped.append({"reason": "unmapped_code", "code": code})
            continue
        pr_no = str(r[hi["PR No."]] or "").strip()
        raw_date = r[hi["PR Date"]]
        if isinstance(raw_date, datetime):
            d = raw_date.date()
        elif isinstance(raw_date, date):
            d = raw_date
        else:
            skipped.append({"reason": "bad_date", "pr": pr_no})
            continue
        dept = str(r[hi["Department"]] or "").strip()
        blob = r[hi["Items in the PR"]]
        total = float(r[hi["PR Total"]] or 0)
        ceiling = float(r[hi["Monthly Budget Ceiling"]] or 0)
        lines = scale_to_total(parse_items(blob), total)
        if not lines:
            skipped.append({"reason": "no_lines", "pr": pr_no})
            continue
        prs.append({
            "pr_number": pr_no,
            "pr_date": d.isoformat(),
            "office_code": app_code,
            "excel_code": code,
            "excel_department": dept,
            "purpose": f"Consumable supplies for {dept}",
            "pr_total": round(total, 2),
            "monthly_ceiling": round(ceiling, 2),
            "lines": lines,
        })

    OUT.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "source_file": str(XLSX.name),
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "office_code_map": CODE_TO_APP,
        "budgets_by_office_code": budgets,
        "ceilings_by_office_code": ceilings,
        "purchase_requests": prs,
        "skipped": skipped,
        "stats": {
            "prs": len(prs),
            "lines": sum(len(p["lines"]) for p in prs),
            "skipped": len(skipped),
            "offices": len({p["office_code"] for p in prs}),
        },
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(payload["stats"], indent=2))
    print("wrote", OUT)


if __name__ == "__main__":
    main()
