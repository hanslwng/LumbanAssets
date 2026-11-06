# Task for Cursor: Replace the GSO System's Physical Count Report Template with the Official RPCPPE Format

## 0. Before touching anything — investigate the existing codebase first

This is a **retrofit**, not a greenfield feature. Before writing code:

1. Find the current "Print Physical Count Report" feature end-to-end: the button/route that triggers it, the backend handler or service that builds the file, the template file it currently uses (if any), and the library used to write Excel files (e.g. `exceljs`, `xlsx`/SheetJS, `openpyxl`, `PhpSpreadsheet`, `EPPlus`, `Apache POI`, etc.).
2. Identify the data model this report pulls from (PPE/asset table, categories, property numbers, quantities, accountable officer, etc.) and how "as of" date / accountable officer / office is currently selected.
3. Identify what "editable inside the system in Excel" currently means in this codebase, if anything exists already — e.g. is there an in-app spreadsheet/grid component (Handsontable, Luckysheet, x-spreadsheet, AG Grid, etc.), or is editing done by exporting, editing in Excel, and re-uploading?
4. Report back a short summary of findings (files, stack, libraries) before generating the new template, so the implementation matches existing conventions instead of introducing a parallel pattern.

Everything below describes the **target format** to implement and the **behavior** required. Adapt the "how" to whatever stack step 0 reveals; do not assume a specific language.

---

## 1. Background

The municipality's GSO (General Services Office) uses a **Report on the Physical Count of Property, Plant and Equipment (RPCPPE)** — the standard Philippine LGU/COA physical-inventory form — to reconcile "balance per card" vs. "on hand per count" for each PPE category (Land, Buildings, Office Equipment, IT Equipment, Infrastructure, etc.).

I analyzed a reference workbook (`RPCPPE-update-to-2025.xlsx`) that contains the **exact layout the system's printed report must match**. That workbook has 7 tabs:

| Tab | Role |
|---|---|
| `FORM` | **The master blank template.** This is the canonical layout to implement in code — a clean, mostly-empty version of the form with one sample category row. |
| `LAND FINAL` | Populated example — Land/Infrastructure category (roads, bridges, buildings, flood control, etc.), ~190 rows, proves how the template behaves with many categories and many rows. |
| `SUPPLIES AND EQUIPMENT FINAL` | Populated example — Office furniture, IT equipment, appliances, etc., ~366 rows. |
| `SEF SCHOOL BUILDING` | Populated example — Special Education Fund, school buildings category. |
| `SEF OFFICE EQUIPMENT` | Populated example — Special Education Fund, office equipment category. |
| `LAND TITLE` | Supporting schedule of land titles — separate reference list, not part of the RPCPPE layout itself. |
| `IIRUP` | A *different* COA form (Inventory and Inspection Report of Unserviceable Property). **Out of scope for this task** — do not merge its layout in. |

Use `FORM` as the structural spec, and `LAND FINAL` / `SUPPLIES AND EQUIPMENT FINAL` as reference cases for "many categories, many rows" and for confirming the subtotal/grand-total formula pattern.

---

## 2. Exact target format specification

### 2.1 Header block (rows 1–10)

| Cell(s) | Content | Notes |
|---|---|---|
| `A1:J1` (merged) | `REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT` | Title. Font **Arial Black, 14pt**, centered. |
| `A3:J3` (merged) | The PPE category/type being reported, e.g. `Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment` | **This line must be dynamic** — it names whichever PPE category/account group this specific report run covers. Font Arial 12pt, bold italic, centered. |
| `A4:J4` (merged) | `(Type of Property, Plant and Equipment)` | Static caption under the line above. Arial 10pt italic-style caption, centered. |
| `A5:J5` (merged) | `As of December 31, 2025` | **Dynamic** — the "as of" date for the count. Arial 10pt bold, centered. |
| `A7` | `For which` | Static label. |
| `B7` | Accountable officer's full name (e.g. `RHONALIZA A. ALVIAR`) | **Dynamic**, pulled from the accountable-officer field on the report run. |
| `C7` | Accountable officer's designation (e.g. `GSO - Designate`) | **Dynamic**. |
| `E7:G7` (merged) | LGU/office name (e.g. `Municipal Goverment of Lumban, Laguna`) | **Dynamic**, but typically a fixed org setting. |
| `H7` (spans to remaining cols) | `is accountable, having assumed such` | Static. |
| `B8` | `(Name of Accountable Officer)` | Static caption. |
| `C8` | `(Official Designation)` | Static caption. |
| `E8:G8` (merged) | `(LGU)` | Static caption. |
| `A9` | `          accountability on` (right-aligned, leading spaces preserved) | Static label. |
| `B9` | Date of assumption | **Dynamic** date value. |
| `B10` | `(Date of Assumption)` | Static caption. |

### 2.2 Table header (rows 12–13, frozen structure — always the same)

Row 12 (bold, centered, wrap text, thin/hair border box):

| A12:A13 | B12:B13 | C12:C13 | D12:D13 | E12:E13 | F12 | G12 | H12:I12 | J12:J13 |
|---|---|---|---|---|---|---|---|---|
| `ARTICLE` | `DESCRIPTION` | `PROPERTY NUMBER` | `UNIT OF                     MEASURE` | `UNIT VALUE` | `BALANCE                    PER CARD` | `ON HAND               PER COUNT` | `SHORTAGE/OVERAGE` | `REMARKS` |

Row 13 (sub-labels under the merged F/G/H/I header cells):

| F13 | G13 | H13 | I13 |
|---|---|---|---|
| `(Quantity)` | `(Quantity)` | `Quantity` | `Value` |

Reproduce the header text **exactly**, including the internal multi-space padding in `UNIT OF                     MEASURE` and `BALANCE                    PER CARD` and `ON HAND               PER COUNT` — this is how the source file literally has them (likely a manual column-alignment hack from the original COA template; keep it for pixel-parity when printed).

### 2.3 Data-row pattern (this repeats per PPE major category, then per item)

The body is **not** a flat table — it's grouped by major asset category, COA-style:

1. **Category header row** — one per major category (e.g. `ROAD NETWORKS`, `SCHOOL BUILDINGS`, `BUILDINGS`, `OFFICE EQUIPMENTS, TOOLS & APPLIANCES`, `IT EQUIPMENTS`, etc.):
   - Column A: category name, **bold, Arial 11pt**, left-aligned.
   - Columns B–D: blank.
   - Column E: **`=SUM(E<first_item_row>:E<last_item_row>)`** — a real formula summing that category's item rows, not a hardcoded number.
   - Columns F–J: blank.
2. **Item rows** — one per asset/property record under that category:
   - `A`: blank
   - `B`: Description
   - `C`: Property Number
   - `D`: Unit of Measure
   - `E`: Unit Value (number, accounting format)
   - `F`: Balance per Card — quantity
   - `G`: On Hand per Count — quantity
   - `H`: Shortage/Overage — quantity (leave blank unless F ≠ G; when populated it should be a formula `=G<row>-F<row>` or system-computed value, not silently omitted)
   - `I`: Shortage/Overage — value (blank unless there's a variance; formula pattern `=H<row>*E<row>` when populated)
   - `J`: Remarks

### 2.4 Grand total row

- Column C: `TOTAL` (bold, italic, right-aligned)
- Column E: a formula that **sums each category header row's E cell explicitly**, not a single contiguous range — e.g. in the reference file: `=E181+E178+E157+E140+E134+E105+E87+E14+E83`. Because category header rows are not evenly spaced, **generate this formula dynamically at render time** by concatenating references to every category-header row actually written for that report (in the order they appear).

### 2.5 Signature block (bottom of sheet)

| Left side | Right side |
|---|---|
| `Prepared by:` | `Approved by:` |
| *(blank rows for spacing)* | |
| Accountable officer's full name, centered, bold | Approving officer's full name (e.g. Municipal Mayor), centered, bold |
| Officer's designation, centered (e.g. `Supply Officer II/GSO-Designate`) | Approver's designation, centered (e.g. `Municipal Mayor`) |
| `Signature over Printed Name of Inventory Committee Chair and Members` | `Signature over Printed Name of Head of Agency/Entity or Authorized Representative` |
| `Date:` | `Date:` |

All four names/designations here are **dynamic** and should come from the report configuration (accountable officer profile + designated approver, e.g. the mayor), not hardcoded.

### 2.6 Styling summary

| Element | Font | Size | Weight | Alignment |
|---|---|---|---|---|
| Main title (row 1) | Arial Black | 14 | — | Center |
| Category-of-PPE line (row 3) | Arial | 12 | Bold + Italic | Center |
| Caption lines (rows 4, 8, 10) | Arial | 10 | Regular | Center |
| "As of" date (row 5) | Arial | 10 | Bold | Center |
| Table header (row 12) | Arial | 10 (9 for the two-word "BALANCE/ON HAND" headers) | Bold | Center, wrap text |
| Table sub-header (row 13) | Arial | 9–10 | Bold | Center |
| Category header rows | Arial | 11 | Bold | Left (col A) |
| Item rows | Arial | 10–11 | Regular | per-column (numbers right-aligned) |
| `TOTAL` label | Arial | 12 | Bold + Italic | Right |
| Signature names | Arial | 11 | Bold | Center |

- **Number format** for Unit Value, category subtotals, and grand total: `_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)` (accounting format, 2 decimals, zero shown as `-`).
- **Borders**: hairline borders around the entire table region (header rows and every data row), forming a grid — matches the reference file's `hair`-style borders on all four sides of each table cell.
- **Column widths** (in Excel width units): A≈27.7, B≈53.4 (Description is intentionally wide), C≈27.4, D≈10.6, E≈16, F≈10.3, G≈11.4, H≈default, I≈14.3, J≈18.3.
- **Page setup**: Landscape orientation, legal-size paper (paperSize=14 in the reference file), margins left/right = 0, top ≈0.51", bottom ≈0.39".
- **Recommended improvement over the source file** (it currently lacks this): set **print titles / repeat rows 1–13 on every printed page**, and set fit-to-width = 1 page, since category tables can run to 100+ rows and must stay readable when printed or exported to PDF across multiple pages. Flag this as an intentional enhancement, not a deviation from spec.

---

## 3. Data field mapping

Map the system's asset/PPE record fields to the columns above. Adjust the left column names to whatever the actual schema calls them once you've inspected the codebase (step 0):

| Excel column | System field (expected) |
|---|---|
| Category header text | PPE major category / account group |
| B — Description | Asset description/name |
| C — Property Number | Asset tag / property number |
| D — Unit of Measure | UoM |
| E — Unit Value | Acquisition cost / unit value |
| F — Balance per Card | Quantity per book/ledger (property card) |
| G — On Hand per Count | Quantity counted during physical inventory |
| H — Shortage/Overage (Qty) | Computed: G − F |
| I — Shortage/Overage (Value) | Computed: H × E |
| J — Remarks | Free-text remarks/notes field |
| Header block | Accountable officer, designation, LGU/office, date of assumption, as-of date |
| Signature block | Accountable officer (prepared by) + designated approver (approved by), with designations |

---

## 4. Functional requirements

### 4.1 "Print Physical Count Report" button

When clicked, it must:

1. Let the user pick (if not already contextually scoped): PPE category/account group(s), as-of date, and accountable officer/office — whatever the system already uses to scope a physical count.
2. Pull the matching asset records, grouped by major category in the same grouping logic the current report/inventory module already uses.
3. Generate a workbook that reproduces the format in section 2 **exactly**, with real formulas (not pre-computed literals) for category subtotals and the grand total, so the file still recalculates correctly if opened and tweaked in Excel.
4. If multiple categories span very different account groups (e.g. Land vs. Office Equipment), replicate the source workbook's own pattern: **one sheet per category group**, each sheet independently laid out per section 2, inside a single workbook — not one giant mixed sheet.
5. Offer the result as a downloadable `.xlsx` (and, if the system already supports it, a PDF export using the same landscape/fit-to-page settings).
6. Replace whatever old template/generation code currently produces the physical count report — this format supersedes it. Do not leave two competing code paths; if other features depend on the old generator's function signature or output location, preserve the interface but swap the internals.

### 4.2 "Editable inside the system in Excel"

Confirm with the actual codebase (step 0) which of these patterns fits, and implement accordingly — default to (a) if there's no existing precedent:

- **(a) Round-trip pattern**: Generate the `.xlsx` → user edits it in Excel/LibreOffice (e.g. correcting on-hand counts or remarks) → re-upload → system parses it back using the same cell map from section 2 (skip category-header and total rows, read item rows only) → validates and updates the underlying records. This requires a parser that mirrors the writer's cell layout exactly, so document the cell map as a shared constant/config both the writer and reader use — don't duplicate magic numbers in two places.
- **(b) In-app spreadsheet editing**: If the system already has (or should have) a web-based spreadsheet grid, render the generated report data in that grid using the same column layout, and save edits back to the database directly, with the printable `.xlsx` generated on demand from current data rather than being the source of truth.

Either way: the exported/printed file and the in-system editable view must always be **the same layout and same column order**, so a user comparing a printout to the on-screen version isn't confused.

---

## 5. Implementation checklist

- [ ] Inspect and report current stack/implementation (section 0).
- [ ] Extract the cell-map/style spec from section 2 into a single reusable template definition (config object, template class, or a literal `.xlsx` template file the code fills in) rather than scattering cell coordinates through report logic.
- [ ] Implement dynamic header block (officer name, designation, LGU, as-of date, category name).
- [ ] Implement grouped body generation: category header row with `SUM` formula + item rows, repeated per category present in the dataset.
- [ ] Implement dynamic grand-total formula that references every category header row actually rendered.
- [ ] Implement signature block with dynamic names/designations.
- [ ] Apply fonts, number formats, borders, column widths, and landscape/legal page setup exactly as specified.
- [ ] Add print titles (repeat header rows) and fit-to-width — noted improvement over the source file.
- [ ] Wire the "Print Physical Count Report" button to this generator; remove/retire the old template path.
- [ ] Implement the editing pathway chosen in 4.2 (round-trip re-import parser, or in-app grid + on-demand export) using the same shared cell-map.
- [ ] Add tests: generate a report from seeded data with 2+ categories and confirm (i) formulas are present and correct, not hardcoded, (ii) grand total formula lists every category row, (iii) header/signature fields populate from the right records, (iv) file opens without corruption in Excel/LibreOffice.

---

## 6. Acceptance criteria

- Clicking "Print Physical Count Report" produces a workbook visually and structurally matching section 2, populated with live system data.
- Opening the generated file in Excel shows working formulas (subtotals and grand total recalculate if a value is edited), not static numbers.
- The old report template/generator is fully replaced, with no leftover dead code path still reachable from the UI.
- The report can be edited (via whichever pattern from 4.2 was implemented) and changes are reflected back in the system's records.
- Multi-category runs produce correctly separated sections/sheets per the source workbook's convention.
- Printed/PDF output is legible in landscape without manual re-formatting (header repeats per page, content fits page width).

---

## 7. Open questions to resolve with the user before/while implementing

- Confirm whether "editable inside the system in Excel" means an in-app spreadsheet UI, or an export → edit in Excel → re-upload flow.
- Confirm whether each PPE category should produce a separate sheet within one workbook (as in the source file) or separate files per category.
- Confirm the exact list of PPE categories/account groups currently tracked in the system, to make sure the category-header grouping logic matches what's used elsewhere (e.g. asset ledgers, depreciation schedules).
