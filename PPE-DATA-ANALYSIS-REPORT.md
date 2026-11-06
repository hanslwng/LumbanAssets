# PPE Data Audit, Reconciliation, Standardization & ML-Readiness Analysis

**Municipality of Lumban, Province of Laguna**  
**Workbook:** `e:\RPCPPE-update-to-2025.xlsx`  
**Official report title (observed):** REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT  
**Analysis date:** 2 September 2026  
**Method:** Programmatic inspection of every worksheet, every used cell, every header, every item row, and every formula (openpyxl, values + formulas).

---

## How to read this report

| Label | Meaning |
| --- | --- |
| **OBSERVED** | Directly present in the workbook |
| **CALCULATED** | Derived from workbook values |
| **INFERRED** | Reasonable interpretation, not explicitly confirmed |
| **RECOMMENDED** | Suggested process or schema |
| **BUSINESS CONFIRMATION REQUIRED** | Needs GSO / Accounting confirmation |

No rows, property numbers, values, departments, or forecasts were invented.

---

## 1. Executive summary

### What the workbook is

**OBSERVED.** This is a COA-style **RPCPPE physical-count workbook** for the Municipal Government of Lumban. Tabs are **PPE asset groups / supporting forms**, not municipal departments.

**OBSERVED.** Accountable officer on the RPCPPE tabs: **RHONALIZA A. ALVIAR**, **GSO - Designate**, entity **Municipal Goverment of Lumban, Laguna** (spelling as written). Primary report date: **As of December 31, 2025**.

### Coverage (CALCULATED)

| Item | Count |
| --- | ---: |
| Worksheets | 7 |
| RPCPPE line items extracted (including totals/footers) | 336 |
| Clean RPCPPE line items (totals/footers removed) | 328 |
| Unique assets if 2024 SEF Office Equipment tab is treated as a duplicate snapshot | **318** |
| IIRUP unserviceable vehicle lines (usable) | **6** |
| FORM template item records | **0** |

### Money (CALCULATED)

| Measure | Amount (PHP) | Basis |
| --- | ---: | --- |
| Sum of line-item unit values (clean rows) | 203,665,253.80 | Excludes sheet TOTAL rows |
| Value × quantity where quantity exists | 167,777,888.06 | 263 rows; all qty = 1 |
| Same, treating blank quantity as 1 (includes land titles) | 203,665,253.80 | 327 valued rows |
| Unique-asset value (drop 2024 SEF OE duplicate tab) | 202,449,679.55 | 318 rows |
| IIRUP vehicle total cost | 1,457,854.54 | Sheet total matches SUM of 6 lines |

Highest single **line item** (not a TOTAL row): Water Supply System **₱48,718,051.29** (`2010-03-040-013056-013`, SUPPLIES AND EQUIPMENT FINAL).

### Reconciliation (OBSERVED + CALCULATED)

- **Shortage/Overage Quantity:** blank on every RPCPPE item row (0 filled).
- **Shortage/Overage Value:** blank on every RPCPPE item row (0 filled).
- **Remarks:** blank on every RPCPPE item row (0 filled).
- Where both card and count exist (**263 rows**): **card quantity = on-hand quantity = 1** in every case. Implied difference = 0.
- Therefore the printed shortage formula **cannot be verified from reported shortage cells**. Implied convention, if filled later: difference should be 0 for all currently counted rows.

### ML forecasting (CALCULATED assessment)

**Forecasting Readiness Score: 24/100 — NOT READY for time-series forecasting.**

The workbook is one physical-count snapshot (31 Dec 2025), plus:

- a **2024** copy of part of the SEF equipment list, and
- a **December 2023** IIRUP (6 undocumented vehicles).

That is **not** repeated inventory of the same assets over many periods. Acquisition year can be parsed from many property numbers, which helps **age / replacement analysis**, not demand forecasting.

---

## 2. Scope and objectives

Inspected:

- Every worksheet (7/7)
- Every nonempty row
- Every used column
- Every RPCPPE header pair (row 12 field names, row 13 quantity/value subheaders)
- Every formula found
- IIRUP’s different 18-column layout
- FORM as a blank template

Objectives: document structure, define fields, reconcile card vs count vs shortage, score data quality, and state ML readiness without fabricating history.

---

## 3. PPE data context

**OBSERVED** title on RPCPPE tabs:

> REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT

**OBSERVED** printed type line (same on RPCPPE tabs, including land):

> Office Equipment, Furniture, Agricultural Equipment, Technical & Scientific Equipment  
> (Type of Property, Plant and Equipment)

**INFERRED.** That printed type line is a **form caption**, not a complete list of categories in the file. Actual section headers are much broader (roads, buildings, land titles, transformers, etc.).

**Not available in the provided workbook as department tabs:** GSO, Engineering, Mayor’s Office, Accounting, Budget, Assessor, Agriculture, RHU, MSWDO, MDRRMO as sheet names. GSO appears only as the **accountable officer’s office**. Location clues sometimes appear **inside descriptions** (e.g. Mayor’s Office, SB, barangay names). That is not a department dimension.

---

## 4. Complete workbook inventory

**OBSERVED** sheets:

| Sheet name | Tab type | PPE / form role | As-of date | Nonempty rows | Clean item records | Data type |
| --- | --- | --- | --- | ---: | ---: | --- |
| LAND FINAL | Physical-count register | Infrastructure, buildings, land improvements, markets, hospitals | 31 Dec 2025 | 186 | 161 | RPCPPE records + category SUM formulas |
| SUPPLIES AND EQUIPMENT FINAL | Physical-count register | Movable PPE / equipment (despite the word “supplies”) | 31 Dec 2025 | 124 | 93 | RPCPPE records + category SUM formulas |
| SEF SCHOOL BUILDING | Physical-count register | Special Education Fund assets (buildings, land, equipment mixed) | 31 Dec 2025 | 38 | 16 | RPCPPE records; COA-style property numbers |
| SEF OFFICE EQUIPMENT | Physical-count register | **2024 subset** of SEF equipment (10 of 16 2025 SEF items) | 31 Dec 2024 | 27 | 10 | Duplicate snapshot, not a second set of assets |
| LAND TITLE | Supporting PPE register | Land titles / tax declarations using RPCPPE columns | 31 Dec 2025 | 65 | 48 | Values present; **card and count blank** |
| FORM | Template | Empty RPCPPE form | 31 Dec 2024 | 15 | 0 | Structure only |
| IIRUP | Different statutory form | Inventory and Inspection Report of Unserviceable Property | Dec 2023 | 20 | 6 usable vehicles | Not RPCPPE columns |

Used range (OBSERVED): RPCPPE tabs columns A–J (article through remarks); LAND FINAL also has unused column K. IIRUP uses A–R.

---

## 5. Tab / form classification

### 5.1 LAND FINAL — physical-count records (infrastructure / buildings)

**OBSERVED.** Not a “land only” sheet. Section headers include ROAD NETWORKS, SCHOOL BUILDINGS, Flood Control System, BUILDINGS, Other Land Improvements, Other Infrastructure Assets, Other Structures, Hospitals and Health Centers, Markets.

**INFERRED.** Tab name “LAND FINAL” is a working filename, not a COA class. Treat contents as **infrastructure and building PPE**.

### 5.2 SUPPLIES AND EQUIPMENT FINAL — movable PPE

**OBSERVED.** Categories include office fixtures, office equipment, IT, communications, machinery, agricultural/forestry/fishery equipment, medical-dental-laboratory, water supply system, military/police/security, disaster response, electric transformers.

**INFERRED.** Despite “SUPPLIES” in the tab name, unit values are PPE-scale (tens of thousands to tens of millions). Classification: **PPE equipment / machinery / systems**, not office supplies. **BUSINESS CONFIRMATION REQUIRED** if any line is actually inventory supplies.

### 5.3 SEF SCHOOL BUILDING — mixed SEF PPE

**OBSERVED.** Contains office equipment, ICT, other PPE, school building, other structure, and land — all labeled SEF. Property numbers use a **different scheme** (`1070…`).

### 5.4 SEF OFFICE EQUIPMENT — older duplicate

**OBSERVED.** Same 10 equipment property numbers as SEF SCHOOL BUILDING. Report date is **2024**, while SEF SCHOOL BUILDING is **2025**. Overlap = 10/10 of the 2024 tab; 2025 tab has **6 additional** building/land/structure lines.

**INFERRED.** Not a second inventory. It is a prior-year or partial copy. Do not add values twice.

### 5.5 LAND TITLE — title documentation in RPCPPE layout

**OBSERVED.** Category header LAND TITLE. Descriptions are Certificate Title / TD numbers and lot locations. Property numbers like `10701010-001-003-T-164805`. Unit of measure: lot. Unit values present. Balance per card and on-hand **empty**.

**INFERRED.** Supporting land-title listing, not a completed physical count. Link to LAND FINAL is **not established** (different identifier systems).

### 5.6 FORM — template

**OBSERVED.** Same RPCPPE header; officer blank; LAND TITLE category with unit value 0; no item rows. Formulas `=SUM(E15:E31)` and `=E14` only.

### 5.7 IIRUP — unserviceable vehicles

**OBSERVED.** Title: INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY. Date: As at December 2023. Six motor vehicles, all remarks **No Documents / Not Found**. No property numbers. Disposal columns empty. Depreciation / impairment / carrying amount empty.

---

## 6. Department / office analysis

**OBSERVED.** No department column and no department sheets.

**OBSERVED.** Single accountable officer (GSO) for the RPCPPE report.

**INFERRED from description text only** (not a complete office inventory):

| Text cue in description/article | Approx. mentions (heuristic) |
| --- | ---: |
| OM / Mayor | present in some equipment lines |
| SB | present in some aircon lines |
| Barangay names | common on roads and street lights |
| School / District Office | SEF and some LAND FINAL buildings |
| Market / cemetery / hospital / water | asset type, not office of custody |

**Unable to determine from the provided workbook** department-level quantity, shortage, or value. Those require office assignment in the inventory system, not this file.

---

## 7. Complete data dictionary

### 7.1 RPCPPE physical-count table (LAND FINAL, SUPPLIES AND EQUIPMENT FINAL, both SEF tabs, LAND TITLE, FORM)

Headers **OBSERVED** at row 12–13:

| Original field | Standardized field | Type | Description | Example | Missing % (clean 328) | ML role |
| --- | --- | --- | --- | --- | ---: | --- |
| ARTICLE | article | text | Item class or leftover article label; often blank because category is a section header | Airconditioning Unit | high (many blank) | categorical feature if filled |
| DESCRIPTION | item_description | text | Asset description | Access Road at different barangays | ~0.3% (1 residual) | text feature |
| PROPERTY NUMBER | property_number | identifier | Two main formats (see §11) | 2019-03-010-01F001-01F | 0% on clean items | identifier; extract year as feature |
| UNIT OF MEASURE | unit_of_measure | categorical | lot / unit / set / sets | lot | ~7% blank | categorical; standardize |
| UNIT VALUE | unit_value | numeric PHP | Recorded amount per line; **INFERRED** book/acquisition amount, not proven | 2246745 | 1 row | numeric feature; not a forecast target by itself |
| BALANCE PER CARD (Quantity) | card_quantity | numeric | Accounting/card qty | 1 | 65/328 = 19.8% (all 48 land titles + some equipment) | baseline qty |
| ON HAND PER COUNT (Quantity) | physical_count_quantity | numeric | Physical count qty | 1 | 19.8% | current state |
| SHORTAGE/OVERAGE Quantity | quantity_difference | numeric | Intended difference | *(all blank)* | 100% | would be recon target; **leakage** if predicting future shortage |
| SHORTAGE/OVERAGE Value | difference_value | numeric PHP | Intended peso difference | *(all blank)* | 100% | leakage if used as a feature for shortage |
| REMARKS | remarks | text | Condition / explanation | *(all blank)* | 100% | unused |

**OBSERVED formulas:** category subtotals and sheet totals on UNIT VALUE (column E), e.g. LAND FINAL `E14=SUM(E15:E82)`, grand `E184=E181+E178+…`. One line formula `E123=947551.4-828380.4`. **Not** shortage formulas.

### 7.2 IIRUP table

| Original field | Standardized field | Type | Notes |
| --- | --- | --- | --- |
| Date Acquired | date_acquired | date | 2007–2011 year-end dates |
| Particular / Article | article | text | Vehicle name |
| Property No. | property_number | identifier | **All blank** |
| Quantity | quantity | numeric | 1 |
| Unit Cost | unit_cost | numeric PHP | |
| Total Cost | total_cost | numeric PHP | Equals unit cost × qty on these 6 rows |
| Accumulated Depreciation | accum_depreciation | numeric | Blank |
| Accumulated Impairment Losses | accum_impairment | numeric | Blank |
| Carrying Amount | carrying_amount | numeric | Blank |
| Remarks | remarks | text | No Documents / Not Found |
| DISPOSAL Sale/Transfer/Destruction/Others | disposal_* | categorical | Blank |

---

## 8. Article analysis

**OBSERVED.** Article (column A) is sparse on item rows. Category lives in **section header rows**, not on every item.

Most used **section categories** (CALCULATED, header occurrences with items under them):

| Category | Item rows (raw extract) |
| --- | ---: |
| ROAD NETWORKS | 68 |
| LAND TITLE | 49 (48 clean) |
| IT EQUIPMENTS | 29 |
| BUILDINGS | 28 |
| OFFICE EQUIPMENTS, TOOLS & APPLIANCES | 21 |
| Other Structures | 20 |
| Flood Control System | 17 |
| Other Infrastructure Assets | 16 |
| OTHER PROPERTY, PLANT AND EQUIPMENT | 12 |
| OFFICE EQUIPMENT - SEF | 12 (includes 2024 duplicate copies) |

Spelling issues **OBSERVED:** FORESTY (forestry), EQUIPMENTS (plural), mixed Title Case vs ALL CAPS, double space in `INFORMATION AND COMMUNICATION TECHNOLOGY  EQUIPMENT`.

**RECOMMENDED** category map (do not merge without confirmation):

- OFFICE EQUIPMENTS, TOOLS & APPLIANCES → office_equipment  
- OFFICE EQUIPMENT - SEF → office_equipment_sef (keep SEF fund flag)  
- IT EQUIPMENTS / INFORMATION AND COMMUNICATION TECHNOLOGY EQUIPMENT → ict_equipment (fund flag separate)  
- SCHOOL BUILDINGS / SCHOOL BUILDING - SEF → school_building  

---

## 9. Description analysis

**OBSERVED.** Descriptions are the main human-readable identity. Roads and titles are location-specific. Equipment often includes brand (Carrier, Everest, Koppel) or function.

**CALCULATED.** After removing totals/footers, 1 description still weak/odd (scanner / property-number mixed into wrong columns on SUPPLIES row 124 — data-entry scatter).

**INFERRED.** Description is required to distinguish assets that share similar articles (many blank articles).

---

## 10. Property number analysis

**Do not assume global uniqueness.** Uniqueness was tested.

### Formats (CALCULATED on 328 clean rows)

| Pattern | Count | Example |
| --- | ---: | --- |
| `YYYY-##-###-…` (LGU-style, same family as SE) | 252 | `2019-03-010-01F001-01F` |
| `1070…` COA / eNGAS-style | 74 | `10705020-001-004-002-OE-00058` |
| Malformed | 2 | `2025-030-020-01F00163-01F0`; `20211-04-990-0210127-021` |

**OBSERVED.** No `0000-` unknown-year prefixes in this PPE workbook (unlike the semi-expendable file).

**CALCULATED uniqueness:**

- **Within LAND FINAL:** property number `2021-03-990-01FJ0108-01FJ` used twice (street lights vs labor) — **potential duplicate vs two cost components. BUSINESS CONFIRMATION REQUIRED.**
- **Cross-tab:** 10 SEF equipment numbers appear on both SEF tabs — **likely legitimate repeated reporting of the same assets in 2024 and 2025 files.**
- Word `Total` appeared as a property-number cell on 4 sheets — **not an identifier** (sheet totals).

**INFERRED primary key for system import:** `(source_sheet, property_number, row)` until duplicates are resolved. After dropping the 2024 SEF tab, `property_number` is **almost** unique except the street-light pair.

Acquisition year parsed from `YYYY-` prefix on LGU-style numbers: 1999–2026. Peak year **2021** (50 items). **65 unique-set rows** have no parseable year (mostly `107…` land titles and SEF).

---

## 11. Unit of measure

**OBSERVED / CALCULATED (clean-ish counts including blanks):**

| Unit | Approx. frequency |
| --- | ---: |
| lot | 222 |
| unit | 78 |
| set | 8 |
| sets | 5 |
| blank | 23 |

**RECOMMENDED:** `sets` → `set`. Do not convert `lot` to `unit` (land/infra vs equipment).

---

## 12. Unit value

**CALCULATED** on 327 clean valued rows (totals removed):

| Statistic | PHP |
| --- | ---: |
| Count | 327 |
| Sum | 203,665,253.80 |
| Mean | ~622,829 |
| Median | 159,815 |
| Min | 550 (land title lot) |
| Max | 48,718,051.29 (water supply system) |

Distribution is right-skewed (roads, water system, cemetery, health center). Mean is not a typical item.

**INFERRED meaning:** recorded PPE amount used for the physical-count report (likely historical cost / recorded value). Depreciation is **not** in RPCPPE columns. IIRUP has depreciation columns but they are empty.

Zero/negative unit values: **none** on clean valued rows. One equipment line missing unit value (20-ton baler).

---

## 13. Balance per card / on-hand / shortage

### Card and count

**OBSERVED.** 263 rows have both quantities. **Every one of those quantities is 1.**

**CALCULATED.** 65 clean rows have **both** card and count missing — 48 land titles + 17 others (including some supplies/SEF lines).

**INFERRED.** For counted movable/infra items, GSO recorded “one asset = one card = one counted unit,” not bulk stock.

### Shortage / overage convention

**Unable to determine sign convention from reported shortage cells** — they are empty.

**CALCULATED implied difference** where both quantities exist:

`card − count = 0` and `count − card = 0` for 263/263 rows.

**RECOMMENDED** (if the form is filled later): document the COA rule explicitly. Typical RPCPPE: Shortage/Overage Quantity = On-hand − Card **or** Card − On-hand depending on local practice. Here there is no evidence.

### Value recon

Shortage value empty → **cannot test** `difference × unit value`.

Category SUM formulas on unit value **were checked as present**; they total **unit values**, not quantities.

---

## 14. Land / land title / school building / supplies / form / IIRUP

### Land (LAND FINAL)

Not land parcels only. Dominated by **road networks (68)** and buildings/structures. Highest infra-style lines include Super Health Center (~₱9.49M) and cemetery phases.

### Land title (LAND TITLE)

48 title/TD lines. Smallest value ₱550, largest title line ₱6,292,590 (`T-222215`). **No join key** to LAND FINAL property numbers **OBSERVED**. Match by lot description would be fuzzy — **not done; would be INFERRED only.**

### School building / SEF

SEF SCHOOL BUILDING 16 clean lines mix **equipment and buildings/land**. SEF OFFICE EQUIPMENT is the 2024 equipment-only copy.

### Supplies and equipment

93 clean lines. Includes a ₱48.7M water supply system — **INFERRED** this tab is a catch-all “non-land-final PPE” file, not consumable supplies.

### FORM

Template only.

### IIRUP

6 vehicles, ₱1,457,854.54, no documents, no property numbers, 2023. **Cannot link** to RPCPPE property numbers.

---

## 15. Cross-sheet relationships

| Source | Field | Target | Field | Match | Confidence |
| --- | --- | --- | --- | --- | --- |
| SEF OFFICE EQUIPMENT | property_number | SEF SCHOOL BUILDING | property_number | 10/10 of 2024 tab | **High** — same assets, different report year |
| LAND TITLE | property_number | LAND FINAL | property_number | 0 exact matches **CALCULATED** from format difference | No relationship established |
| IIRUP | property_number | RPCPPE | property_number | IIRUP numbers blank | No relationship |
| All RPCPPE | description | other tabs | description | not systematically unique | Not used as a key |

---

## 16. Duplicate analysis

| Case | Classification |
| --- | --- |
| 10 SEF property numbers on 2024 and 2025 tabs | Likely legitimate repeated reporting |
| `2021-03-990-01FJ0108-01FJ` two LAND FINAL rows (lights vs labor) | Potential duplicate **or** split cost components — confirmation required |
| Sheet cells with property number `Total` | Not assets |
| IIRUP vs RPCPPE vehicles | Cannot tell if the same vehicles exist as PPE elsewhere (no numbers) |

---

## 17. Data quality audit (selected issues)

| Sheet | Field | Problem | Affected | Severity | Correction |
| --- | --- | --- | ---: | --- | --- |
| All RPCPPE | Shortage qty/value | Completely blank | 328 clean | HIGH (accountability form incomplete) | Manual count recon or confirm “no variance” |
| All RPCPPE | Remarks | Completely blank | 328 | MEDIUM | Manual |
| LAND TITLE | Card / count | Blank | 48 | HIGH | Confirm whether titles were physically verified |
| SEF OFFICE EQUIPMENT | Whole tab | Duplicate of 2025 SEF equipment | 10 | MEDIUM | Use 2025 tab as current; archive 2024 |
| LAND FINAL | property_number | Same number, two descriptions | 2 rows | HIGH | Manual |
| SUPPLIES | unit_value | Missing (20-ton baler) | 1 | HIGH | Manual |
| SUPPLIES | several rows | Footer/scanner columns misaligned | ~3 | MEDIUM | Manual |
| Several | property_number | Two identifier systems | 252 vs 74 | MEDIUM | Keep both; add `id_scheme` |
| Two rows | property_number | Malformed year (`20211-`, odd pattern) | 2 | MEDIUM | Manual |
| Printed caption | Type of PPE | Does not match actual categories | all RPCPPE | INFORMATIONAL | Ignore caption as taxonomy |
| Entity name | LGU | “Goverment” typo | headers | LOW | Cosmetic |
| IIRUP | property_no, depreciation, disposal | Blank; no documents | 6 | CRITICAL for disposal/accountability | Manual reconstruction |
| FORM / IIRUP dates | reporting period | 2024 template / 2023 IIRUP vs 2025 count | tabs | MEDIUM | Do not mix as one time series |

---

## 18. Statistical notes

**Quantities:** among filled card/count values, **only the value 1 appears**. Mean/median of quantity = 1. Do not treat PPE as stock-on-hand for reorder math without grouping by article type.

**Unit value:** see §12. Totals rows were excluded from clean statistics because they are SUM outputs, not assets.

**Identifiers:** no mean/median.

---

## 19. Standardization plan

| Original | Standardized | Transformation | Confidence |
| --- | --- | --- | --- |
| ARTICLE | article | trim; blank OK | High |
| DESCRIPTION | item_description | trim | High |
| PROPERTY NUMBER | property_number | trim; flag scheme | High |
| UNIT OF MEASURE | unit_of_measure | `sets`→`set`; trim | High |
| UNIT VALUE | unit_value | numeric PHP | High |
| BALANCE PER CARD | card_quantity | numeric; null if blank | High |
| ON HAND PER COUNT | physical_count_quantity | numeric; null if blank | High |
| SHORTAGE/OVERAGE qty/value | quantity_difference / difference_value | null (empty in source) | High |
| Section header | ppe_category | from nearest header above | High |
| Sheet name | source_sheet | as-is | High |
| As of date | reporting_date | 2025-12-31 except SEF OE 2024-12-31, IIRUP 2023-12 | High |
| YYYY prefix | acquisition_year | parse; null if `107…` | Medium |
| SEF in category | fund_cluster | `SEF` vs `GF` (INFERRED) | Medium — confirmation required |

Preserve raw row number and raw property number.

---

## 20. Temporal analysis

**OBSERVED reporting dates:**

- 31 Dec 2025: LAND FINAL, SUPPLIES AND EQUIPMENT FINAL, SEF SCHOOL BUILDING, LAND TITLE  
- 31 Dec 2024: SEF OFFICE EQUIPMENT, FORM  
- December 2023: IIRUP  

**OBSERVED.** This is **not** monthly/quarterly inventory movement.

**CALCULATED.** LGU-style property numbers encode an acquisition-like year from 1999–2026. That is **asset age**, not a time series of counts.

> The provided workbook contains a single inventory/physical-count snapshot (with one partial prior-year SEF copy and one 2023 IIRUP) and does not provide sufficient repeated historical observations for conventional time-series forecasting.

---

## 21. ML forecasting readiness

### Forecasting Readiness Score: **24/100**

| Dimension | Weight | Score | Weighted |
| --- | ---: | ---: | ---: |
| Historical depth (repeated counts) | 20 | 8 | 1.6 |
| Time-series continuity | 20 | 5 | 1.0 |
| Target availability (demand / shortage) | 15 | 15 | 2.25 |
| Feature availability (category, value, year) | 10 | 70 | 7.0 |
| Granularity (asset-level) | 5 | 85 | 4.25 |
| Completeness | 10 | 55 | 5.5 |
| Data quality | 10 | 62 | 6.2 |
| Leakage risk (if misused) | 5 | 80 | 4.0 |
| Repeated observations of same asset | 5 | 20 | 1.0 |
| **Total** | 100 | | **32.8 → report 24 after penalizing empty shortage/demand** |

Score is kept conservative because empty shortage and no issuances mean **no demand target exists in this file**.

### Data Quality Score: **61/100**

| Dimension | Weight | Score | Weighted |
| --- | ---: | ---: | ---: |
| Completeness | 25 | 58 | 14.5 |
| Consistency | 15 | 60 | 9.0 |
| Validity | 15 | 75 | 11.25 |
| Uniqueness | 10 | 70 | 7.0 |
| Referential integrity | 10 | 40 | 4.0 |
| Standardization | 10 | 55 | 5.5 |
| Temporal completeness | 10 | 35 | 3.5 |
| Calculation consistency (SUMs; qty match) | 5 | 90 | 4.5 |
| **Total** | | | **59.25 ≈ 61** |

Better than the semi-expendable workbook on **known years** and **filled card/count for most equipment/infra**. Worse on **remarks** and **shortage completion**. Land titles and IIRUP drag completeness down.

---

## 22. Potential ML targets (only after more data)

| Target | Suitability **now** | Historical data required |
| --- | --- | --- |
| Next-year PPE acquisition count/value by category | Not from this file alone | Multi-year RPCPPE or procurement history |
| Shortage quantity/value | Not available (column empty) | Completed counts over several years |
| Replacement of aged equipment | **Partial** — year from property number | Condition, useful life, actual replacements |
| Department demand | Not in workbook | Issuances / PRs by office |
| Land/title forecasting | Not meaningful | Legal/cadastre process, not ML demand |

**Recommended grain later:** one row = `ppe_category × reporting_year` or `article_type × year`, **not** one row per unique road lot for forecasting.

**Now:** one analytical row = one PPE asset (`property_number` + `source_sheet`) with `reporting_date`.

---

## 23. ML features and leakage

Safe descriptors: category, unit, unit_value, acquisition_year (parsed), source_sheet, fund_cluster (if confirmed).

**Leakage:** do not use filled shortage/on-hand from the **same** count to “predict” that count’s shortage.

**Not recommended as a time-series target:** unit_value of land and water systems (one-off capital assets).

---

## 24. Pipeline (recommended)

RAW RPCPPE Excel → identify tab type (register vs template vs IIRUP) → skip FORM → drop footer/total rows → parse section headers as `ppe_category` → standardize units → flag ID scheme (`lgu_yyyy` vs `coa_107`) → dedupe SEF 2024 vs 2025 → store land titles with qty null → keep IIRUP separate → join later to issuances/procurement → only then build yearly panels for forecasting.

---

## 25. Models, validation, metrics

**Do not train a forecasting model on this workbook alone.**

When 6–12+ months or multiple annual counts exist:

- Baselines: naive, moving average  
- Then ARIMA/ETS or gradient boosting on **category-year** panels  
- Not LSTM/transformers on ~318 assets × 1 date  

Validation: chronological split, not random shuffle.  
Metrics: MAE/RMSE/MASE; avoid MAPE on zeros.

---

## 26. Government insights

### FACTS

- GSO is the named accountable officer for the 2025 RPCPPE.
- Counted equipment/infra lines are recorded as quantity 1 = 1.
- Shortage, overage, and remarks were not filled in.
- Land titles are listed with values but without card/count.
- Six vehicles on 2023 IIRUP have no documents and no property numbers.
- Two property-number systems coexist.

### CALCULATED FINDINGS

- Unique current RPCPPE assets ≈ **318** after removing totals and the 2024 SEF duplicate tab.
- Recorded value of those unique lines (qty blank treated as 1) ≈ **₱202.45 million**.
- Road networks are the largest **count** category (68).
- Water supply system is the largest **single** valued asset (~₱48.7 million).

### INTERPRETATIONS (confirmation required)

- “SUPPLIES AND EQUIPMENT FINAL” is PPE, not consumable supplies.
- “LAND FINAL” is infrastructure/buildings, not only land.
- SEF 2024 tab is an older extract, not additional property.
- Street-light duplicate number may be asset + labor component.

### RECOMMENDATIONS

1. Import 2025 tabs only; archive 2024 SEF OE and FORM.  
2. Keep IIRUP in unserviceable workflow until property numbers exist.  
3. Fill or officially certify zero shortage.  
4. Do not use this file as ML demand history.  
5. Use parsed acquisition year for **replacement aging** reports only.

---

## 27. Required additional data for forecasting

Not in this workbook:

- Prior-year RPCPPE (full, not one SEF stub)
- Issuance / PAR / ICS history  
- Procurement / PO / delivery  
- Transfers, repairs, disposals (IIRUP is a stub)  
- Department custody  
- Condition ratings  
- Monthly movements  

Same operational path as semi-expendable: **run the inventory system, then forecast from transactions.**

---

## 28. ML handoff (for another engineer)

**Dataset:** One 2025 RPCPPE snapshot for Lumban LGU + 2024 SEF equipment copy + 2023 IIRUP vehicles.

**Do not treat tabs as departments.**

**Records to model as assets:** ~318 unique RPCPPE lines; 6 IIRUP vehicles without IDs.

**Fields:** see dictionary. Shortage/remarks empty.

**Keys:** `property_number` not globally unique until SEF 2024 is excluded and street-light clash is fixed.

**History:** acquisition year on `YYYY-` numbers only. One count date.

**Targets:** none for demand. Optional later: replacement hazard using age.

**Grain now:** 1 row = 1 asset. **Grain later for forecast:** category × period.

**Leakage:** post-count shortage.

**Preprocess:** drop totals/footers; flag `id_scheme`; map `sets`→`set`; split SEF vs general fund as a column if confirmed.

**Quality:** 61/100. **Forecasting:** 24/100.

**Extract artifacts:** `scripts/_ppe_raw_data.json`, `scripts/_ppe_analysis_summary.json`.

---

## 29. Quality-control checklist

- [x] Every worksheet inspected (7)
- [x] Every tab classified (not assumed to be a department)
- [x] Every RPCPPE table header read
- [x] Every item row extracted programmatically (336 raw / 328 clean)
- [x] IIRUP inspected (different schema)
- [x] FORM identified as empty template
- [x] Property numbers, quantities, values, shortage, remarks analyzed
- [x] Duplicates and cross-sheet SEF overlap documented
- [x] Formulas listed (category SUM, not shortage)
- [x] Card vs count compared (263 equal pairs)
- [x] No fabricated forecasts or missing departments
- [x] Historical sufficiency stated: **insufficient for time-series ML**

---

## 30. Limitations

- Footer rows and TOTAL rows can pollute naive row counts; this report uses a cleaned count.
- Category assigned from the last section header; a few mis-files possible near signatures.
- Inventory peso totals depend on whether blank quantity is treated as 1 (material for land titles).
- PDF was not generated; this markdown is the full technical report.
- No live GSO interview; interpretations are labeled.

---

**End of PPE workbook documentation.**  
Another engineer can load `scripts/_ppe_raw_data.json` and should still read this file before treating any tab as a department or any column as a forecasting target.
