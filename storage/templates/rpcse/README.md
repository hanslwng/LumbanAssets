# RPCSE Template Folder

This folder holds the official Semi-Expendable physical count report format,
as provided by the Municipality of Lumban, Laguna (GSO). The system fills this exact file with data —
it does not rebuild the format in code.

The active file must be named **`rpcse-template.xlsx`**. The client's original filename
(e.g. `RPCSP-semi-expendable-2025.xlsx`) can change year to year; rename it to this
internal name when placing it here.

If `rpcse-template.xlsx` is missing, the system will generate a blank master that
matches the confirmed cell map (`includes/rpcse_cell_map.php`). Replace that generated
file with the client's real workbook as soon as you have it.

## To update the template (when the client changes their format):
1. Move the current `rpcse-template.xlsx` into `archive/` and rename it with
   today's date, e.g. `archive/rpcse-template-2026-08-28.xlsx`.
   (The admin **Replace RPCSE template** button on Semi-Expendable does this automatically.)
2. Place the new file here as `rpcse-template.xlsx` (exact filename required).
3. If the new file's cell layout differs from the mapping in
   `includes/rpcse_cell_map.php`,
   that config must be updated to match — otherwise data will land in the
   wrong cells. Do not skip this step.
4. Test-generate one report before relying on it
   (`php scripts/test-rpcse-generate.php`, or Print physical count report in the app).

Do not edit rpcse-template.xlsx directly by hand for report generation —
the system only ever works on temporary copies of it.

## Cell map (do not change without updating PHP)

| Area | Location |
|---|---|
| Republic / Province / Municipality | A1 / A2 / A3 (merged A:J) |
| Annex A | J4 |
| Title | A5:J5 |
| Type description | A6:J6 |
| Type caption | A7:J7 |
| As at date | A8:J8 |
| Fund Cluster | A10 (label), B10 (value, usually blank) |
| Accountable officer | B11 name, C11:D11 designation, E11:G11 entity |
| Date of assumption | B13 |
| Table header | rows 16–17 |
| First data row | 18 |
| Category subtotal | column E `=SUM(...)` — no grand-total row |
| Signature block | immediately after last item (+ 2 spacer rows) |
| Master tab name | `Sheet` |
