"""
Temporal Analysis of Semi-Expendable Property Data
Determine if multi-year data exists for forecasting
"""

import json
from collections import Counter, defaultdict

# Load the extracted data
with open('scripts/_semi_expendable_raw_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

records = data['records']

print("="*80)
print("TEMPORAL DIMENSION ANALYSIS")
print("="*80)
print()

# Group by department and report year
dept_year_map = defaultdict(lambda: defaultdict(int))
dept_year_details = defaultdict(lambda: defaultdict(list))

for record in records:
    dept = record['dept_abbrev']
    year = record['report_year']
    dept_year_map[dept][year] += 1
    dept_year_details[dept][year].append(record)

print("DEPARTMENTS BY REPORT YEAR")
print("="*80)
print()

years_2023 = []
years_2024 = []
years_2025 = []
years_mixed = []

for dept in sorted(dept_year_map.keys()):
    years = list(dept_year_map[dept].keys())
    year_str = ", ".join([f"{y}({dept_year_map[dept][y]} items)" for y in sorted(years)])
    
    print(f"{dept:15} : {year_str}")
    
    if len(years) == 1:
        if '2023' in years:
            years_2023.append(dept)
        elif '2024' in years:
            years_2024.append(dept)
        elif '2025' in years:
            years_2025.append(dept)
    else:
        years_mixed.append(dept)

print()
print("SUMMARY BY YEAR:")
print(f"  Only 2023: {len(years_2023)} departments - {years_2023}")
print(f"  Only 2024: {len(years_2024)} departments - {years_2024}")
print(f"  Only 2025: {len(years_2025)} departments - {years_2025}")
print(f"  Mixed years: {len(years_mixed)} departments - {years_mixed}")
print()

# Check if we have true multi-year data (same department, multiple years)
print("="*80)
print("MULTI-YEAR TRACKING ASSESSMENT")
print("="*80)
print()

multi_year_depts = [dept for dept in dept_year_map if len(dept_year_map[dept]) > 1]

if multi_year_depts:
    print(f"[WARN] Found {len(multi_year_depts)} departments with multiple report years")
    print("This may indicate:")
    print("  1. Historical data from multiple physical counts")
    print("  2. Data entry inconsistencies (mixed template dates)")
    print("  3. Incomplete sheets from different years")
    print()
    for dept in multi_year_depts:
        print(f"  {dept}: {dict(dept_year_map[dept])}")
    print()
else:
    print("[INFO] No department appears in multiple years")
    print("Each department has data from only ONE report period")
    print()

# Analyze property number consistency
print("="*80)
print("PROPERTY NUMBER TEMPORAL PATTERNS")
print("="*80)
print()

# Group property numbers by department and year
prop_tracking = defaultdict(lambda: defaultdict(set))

for record in records:
    prop_no = record.get('property_no', '')
    if prop_no and prop_no != '':
        dept = record['dept_abbrev']
        year = record['report_year']
        prop_tracking[prop_no][year].add(dept)

# Check if any property numbers appear in multiple years
multi_year_props = {
    prop_no: years 
    for prop_no, years in prop_tracking.items() 
    if len(years) > 1
}

if multi_year_props:
    print(f"[OK] Found {len(multi_year_props)} property numbers appearing in MULTIPLE years")
    print("This suggests longitudinal tracking of the same items over time!")
    print()
    print("Sample property numbers tracked across years:")
    for prop_no, years in list(multi_year_props.items())[:10]:
        year_list = sorted(years.keys())
        print(f"  {prop_no:35} : Years {', '.join(year_list)}")
    print()
else:
    print("[INFO] No property numbers appear in multiple years")
    print("Each property number is unique to its report year")
    print()

# Check if Card Quantity exists (historical baseline)
print("="*80)
print("HISTORICAL BASELINE DATA")
print("="*80)
print()

records_with_card_qty = [r for r in records if r.get('card_qty') is not None]
records_with_count_qty = [r for r in records if r.get('count_qty') is not None]

print(f"Records with Card Quantity (baseline): {len(records_with_card_qty)} / {len(records)} ({len(records_with_card_qty)/len(records)*100:.1f}%)")
print(f"Records with Count Quantity (physical): {len(records_with_count_qty)} / {len(records)} ({len(records_with_count_qty)/len(records)*100:.1f}%)")
print()

if len(records_with_card_qty) == 0:
    print("[CRITICAL] NO CARD QUANTITY DATA EXISTS")
    print("The 'Balance Per Card' column is completely empty!")
    print()
    print("IMPACT:")
    print("  - Cannot calculate shortage/overage from formula")
    print("  - No historical baseline quantity reference")
    print("  - Only physical count snapshot exists")
    print()

# Final ML assessment
print("="*80)
print("FINAL ML FORECASTING READINESS")
print("="*80)
print()

has_temporal = len(multi_year_props) > 0 or len(multi_year_depts) > 0
has_baseline = len(records_with_card_qty) > 0
has_physical_counts = len(records_with_count_qty) > len(records) * 0.8

print("TEMPORAL DATA ASSESSMENT:")
if not has_temporal:
    print("[WARN] INSUFFICIENT TEMPORAL DATA")
    print("  - Workbook contains snapshots from 2023, 2024, 2025 BUT they are from")
    print("    DIFFERENT departments (not the same department tracked over time)")
    print("  - This is essentially a SINGLE SNAPSHOT, not a time-series")
    print()
    print("EXPLANATION:")
    print("  Some departments used 2023 templates, some 2024, most 2025.")
    print("  There is no evidence that the same property was counted in multiple years.")
    print("  This is NOT longitudinal tracking.")
    print()
else:
    print("[OK] TEMPORAL DATA EXISTS")
    print(f"  - {len(multi_year_props)} properties tracked across years")
    print(f"  - {len(multi_year_depts)} departments have multi-year data")
    print()

print()
print("BASELINE DATA ASSESSMENT:")
if not has_baseline:
    print("[WARN] NO HISTORICAL BASELINE")
    print("  - Card Quantity (accounting baseline) is missing")
    print("  - Only physical count snapshot available")
    print("  - Cannot establish historical inventory levels")
    print()
else:
    print("[OK] BASELINE DATA EXISTS")
    print(f"  - {len(records_with_card_qty)} records have card quantity")
    print()

print()
print("="*80)
print("RECOMMENDATION FOR ML FORECASTING")
print("="*80)
print()

if not has_temporal:
    print("[ACTION REQUIRED]")
    print()
    print("The current workbook is a SINGLE POINT-IN-TIME snapshot, despite having")
    print("mixed dates from different departments using different template years.")
    print()
    print("TO ENABLE ML DEMAND FORECASTING:")
    print()
    print("1. IMPORT THIS DATA as your BASELINE INVENTORY (Dec 2025)")
    print()
    print("2. BEGIN COLLECTING TIME-SERIES DATA:")
    print("   - Monthly issuance records (who requested what, when, quantity)")
    print("   - Procurement records (what was purchased, when, quantity, cost)")
    print("   - Return records (what was returned, when, quantity, condition)")
    print("   - Transfer records (inter-department transfers)")
    print()
    print("3. WAIT 6-12 MONTHS to accumulate historical demand patterns")
    print()
    print("4. BUILD A DEMAND HISTORY TABLE:")
    print("   Structure: [department, item_category, item_name, month, qty_issued, qty_returned]")
    print()
    print("5. THEN APPLY ML FORECASTING:")
    print("   - Forecast monthly demand per department per item category")
    print("   - Predict reorder points")
    print("   - Optimize inventory levels")
    print()
    print("CURRENT STATE:")
    print("  [X] Baseline inventory snapshot - AVAILABLE")
    print("  [ ] Historical demand data - NOT AVAILABLE")
    print("  [ ] Multi-period observations - NOT AVAILABLE")
    print("  [ ] Consumption patterns - NOT AVAILABLE")
    print()
    print("ML FORECASTING STATUS: NOT READY")
    print("ESTIMATED TIME TO READINESS: 6-12 months after system goes live")
    print()
else:
    print("[FURTHER ANALYSIS REQUIRED]")
    print("Some multi-year data detected. Need to verify if this is true")
    print("longitudinal tracking or data inconsistencies.")
    print()

print("="*80)
print("ANALYSIS COMPLETE")
print("="*80)
