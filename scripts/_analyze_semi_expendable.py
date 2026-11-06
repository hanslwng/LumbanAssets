"""
Comprehensive Semi-Expendable Property Data Analysis
Municipality of Lumban - Complete Data Audit & ML Readiness Assessment
"""

import openpyxl
import re
from collections import defaultdict, Counter
from datetime import datetime
import json

# Load workbook
wb = openpyxl.load_workbook(r'e:\RPCSP-semi-expendable-2025.xlsx', data_only=True)

# Initialize data structures
all_records = []
department_data = {}
data_quality_issues = []
property_numbers = []
all_items = []
all_categories = []
unit_measures = []
remarks_data = []

# Department name mapping
DEPT_MAPPING = {
    'SWM': 'Solid Waste Management',
    'PDAO': 'Office of the Person With Disability Affairs',
    'SENIOR': 'Office of the Senior Citizen Affairs (OSCA)',
    'MCR': 'Municipal Civil Registrar',
    'GSO': 'General Services Office',
    'HRMO': 'Human Resource Management Office',
    'LICENSE': 'License Office',
    'MARKET': 'Lumban Public Market',
    'PESO': 'Public Employment Services Office',
    'ENGR': 'Engineering Office',
    'MPDC': 'Municipal Planning and Development Office',
    'BUDGET': 'Budget Office',
    'MSWDO': 'Municipal Social Welfare and Development Office',
    'ACCOUNTING': 'Accounting Office',
    'LWS': 'Lumban Waterworks System',
    'ASSESSOR': "Assessor's Office",
    'DILG': 'Department of the Interior and Local Government',
    'TRADING POST': 'Organic Trading Post',
    'DA': 'Department of Agriculture',
    'COA': 'Commission on Audit',
    'TREASURY': 'Treasury Office',
    'ADMIN': 'Administrative Office',
    'OM': 'Office of the Mayor',
    'MDRRMO': 'Municipal Disaster Risk Reduction and Management Office',
    'RHU': 'Rural Health Unit',
    'SB': 'Sangguniang Bayan Office',
    'DEPED': 'Department of Education'
}

def clean_value(val):
    """Clean cell value"""
    if val is None:
        return ''
    if isinstance(val, str):
        return val.strip()
    return str(val)

def extract_number(val):
    """Extract numeric value from string or number"""
    if val is None or val == '':
        return None
    if isinstance(val, (int, float)):
        return float(val)
    # Handle string with commas, periods as thousand separators
    val_str = str(val).replace(',', '').replace(' ', '')
    # Try to extract first number
    match = re.search(r'[\d.]+', val_str)
    if match:
        try:
            return float(match.group())
        except:
            return None
    return None

def find_header_row(ws):
    """Find the row containing 'Article / Item' header"""
    for row_idx in range(1, 30):
        cell_val = clean_value(ws.cell(row_idx, 1).value)
        if 'Article' in cell_val or 'article' in cell_val.lower():
            return row_idx
    return None

def extract_report_date(ws):
    """Extract report date from header"""
    for row_idx in range(1, 15):
        for col_idx in range(1, 10):
            val = clean_value(ws.cell(row_idx, col_idx).value)
            if 'As at' in val or 'as at' in val.lower():
                # Look for date pattern
                match = re.search(r'(\d{4})', val)
                if match:
                    return match.group(1)
    return None

def extract_accountable_officer(ws):
    """Extract accountable officer info"""
    officer_name = ''
    designation = ''
    entity = ''
    
    for row_idx in range(1, 20):
        cell1 = clean_value(ws.cell(row_idx, 1).value)
        cell2 = clean_value(ws.cell(row_idx, 2).value)
        cell3 = clean_value(ws.cell(row_idx, 3).value)
        cell5 = clean_value(ws.cell(row_idx, 5).value)
        
        if 'For which' in cell1:
            officer_name = cell2
            designation = cell3
            entity = cell5
            break
    
    return officer_name, designation, entity

print("="*80)
print("COMPREHENSIVE SEMI-EXPENDABLE PROPERTY DATA ANALYSIS")
print("Municipality of Lumban, Province of Laguna")
print("="*80)
print()

# Skip sheets that are clearly not department data
skip_sheets = ['Sheet1']

total_records = 0
dept_count = 0

print("ANALYZING ALL SHEETS...")
print()

for sheet_name in wb.sheetnames:
    if sheet_name in skip_sheets:
        continue
    
    ws = wb[sheet_name]
    
    # Find header row
    header_row = find_header_row(ws)
    if not header_row:
        continue
    
    # Extract metadata
    report_year = extract_report_date(ws)
    officer_name, designation, entity = extract_accountable_officer(ws)
    
    # Column mapping (based on common structure)
    # Row 16: Headers
    # Row 17: Sub-headers
    col_article = 1
    col_description = 2
    col_prop_no = 3
    col_unit_measure = 4
    col_unit_value = 5
    col_card_qty = 6
    col_count_qty = 7
    col_shortage_qty = 8
    col_shortage_val = 9
    col_remarks = 10
    
    dept_abbrev = sheet_name
    dept_full_name = DEPT_MAPPING.get(dept_abbrev, dept_abbrev)
    
    dept_records = []
    dept_categories = set()
    current_category = None
    
    # Start from row after headers
    data_start_row = header_row + 2
    
    for row_idx in range(data_start_row, ws.max_row + 1):
        article = clean_value(ws.cell(row_idx, col_article).value)
        description = clean_value(ws.cell(row_idx, col_description).value)
        prop_no = clean_value(ws.cell(row_idx, col_prop_no).value)
        unit_measure = clean_value(ws.cell(row_idx, col_unit_measure).value)
        unit_value_raw = ws.cell(row_idx, col_unit_value).value
        card_qty_raw = ws.cell(row_idx, col_card_qty).value
        count_qty_raw = ws.cell(row_idx, col_count_qty).value
        shortage_qty_raw = ws.cell(row_idx, col_shortage_qty).value
        shortage_val_raw = ws.cell(row_idx, col_shortage_val).value
        remarks = clean_value(ws.cell(row_idx, col_remarks).value)
        
        # Check if this is a category header (has value in article but no property number)
        if article and not prop_no and not description:
            # This is likely a category/section header
            current_category = article
            dept_categories.add(article)
            continue
        
        # Skip empty rows
        if not article and not description and not prop_no:
            continue
        
        # Parse numeric values
        unit_value = extract_number(unit_value_raw)
        card_qty = extract_number(card_qty_raw)
        count_qty = extract_number(count_qty_raw)
        shortage_qty = extract_number(shortage_qty_raw)
        shortage_val = extract_number(shortage_val_raw)
        
        # Create record
        record = {
            'sheet_name': sheet_name,
            'dept_abbrev': dept_abbrev,
            'dept_full_name': dept_full_name,
            'report_year': report_year,
            'accountable_officer': officer_name,
            'designation': designation,
            'entity': entity,
            'category': current_category,
            'row_number': row_idx,
            'article': article,
            'description': description,
            'property_no': prop_no,
            'unit_measure': unit_measure,
            'unit_value': unit_value,
            'card_qty': card_qty,
            'count_qty': count_qty,
            'shortage_qty': shortage_qty,
            'shortage_val': shortage_val,
            'remarks': remarks
        }
        
        dept_records.append(record)
        all_records.append(record)
        total_records += 1
        
        # Collect for analysis
        if prop_no:
            property_numbers.append(prop_no)
        if article:
            all_items.append(article)
        if current_category:
            all_categories.append(current_category)
        if unit_measure:
            unit_measures.append(unit_measure)
        if remarks:
            remarks_data.append(remarks)
    
    # Store department data
    if dept_records:
        dept_count += 1
        department_data[dept_abbrev] = {
            'full_name': dept_full_name,
            'report_year': report_year,
            'officer': officer_name,
            'designation': designation,
            'entity': entity,
            'records': dept_records,
            'record_count': len(dept_records),
            'categories': list(dept_categories)
        }

wb.close()

print(f"[OK] Total sheets analyzed: {len(wb.sheetnames)}")
print(f"[OK] Departments with data: {dept_count}")
print(f"[OK] Total property records extracted: {total_records}")
print()

# Save raw data
with open('scripts/_semi_expendable_raw_data.json', 'w', encoding='utf-8') as f:
    json.dump({
        'total_records': total_records,
        'department_count': dept_count,
        'departments': list(department_data.keys()),
        'records': all_records,
        'extraction_timestamp': datetime.now().isoformat()
    }, f, indent=2, ensure_ascii=False)

print("[OK] Raw data saved to: scripts/_semi_expendable_raw_data.json")
print()

# ===========================
# DATA QUALITY ANALYSIS
# ===========================

print("="*80)
print("DATA QUALITY AUDIT")
print("="*80)
print()

# Missing property numbers
missing_prop_no = [r for r in all_records if not r['property_no']]
print(f"Missing Property Numbers: {len(missing_prop_no)} / {total_records} ({len(missing_prop_no)/total_records*100:.1f}%)")

# Property numbers with '0000' year
prop_0000 = [r for r in all_records if r['property_no'] and r['property_no'].startswith('0000')]
print(f"Property Numbers with '0000' year: {len(prop_0000)} / {total_records} ({len(prop_0000)/total_records*100:.1f}%)")

# Missing descriptions
missing_desc = [r for r in all_records if not r['description']]
print(f"Missing Descriptions: {len(missing_desc)} / {total_records} ({len(missing_desc)/total_records*100:.1f}%)")

# Missing unit values
missing_unit_val = [r for r in all_records if r['unit_value'] is None]
print(f"Missing Unit Values: {len(missing_unit_val)} / {total_records} ({len(missing_unit_val)/total_records*100:.1f}%)")

# Missing quantities
missing_card_qty = [r for r in all_records if r['card_qty'] is None]
missing_count_qty = [r for r in all_records if r['count_qty'] is None]
print(f"Missing Card Quantity: {len(missing_card_qty)} / {total_records} ({len(missing_card_qty)/total_records*100:.1f}%)")
print(f"Missing Count Quantity: {len(missing_count_qty)} / {total_records} ({len(missing_count_qty)/total_records*100:.1f}%)")

# Check duplicate property numbers
prop_no_counts = Counter([r['property_no'] for r in all_records if r['property_no']])
duplicate_prop_nos = {k: v for k, v in prop_no_counts.items() if v > 1}
print(f"Duplicate Property Numbers: {len(duplicate_prop_nos)} unique numbers appear multiple times")

print()

# ===========================
# DEPARTMENT STATISTICS
# ===========================

print("="*80)
print("DEPARTMENT SUMMARY")
print("="*80)
print()

dept_stats = []
for dept_abbrev, dept_info in sorted(department_data.items(), key=lambda x: x[1]['record_count'], reverse=True):
    records = dept_info['records']
    
    # Calculate statistics
    total_items = len(records)
    total_unit_value = sum(r['unit_value'] for r in records if r['unit_value'] is not None)
    items_with_0000 = len([r for r in records if r['property_no'] and r['property_no'].startswith('0000')])
    items_with_remarks = len([r for r in records if r['remarks']])
    
    dept_stats.append({
        'abbrev': dept_abbrev,
        'full_name': dept_info['full_name'],
        'officer': dept_info['officer'],
        'report_year': dept_info['report_year'],
        'total_items': total_items,
        'total_unit_value': total_unit_value,
        'items_0000': items_with_0000,
        'items_with_remarks': items_with_remarks,
        'categories': dept_info['categories']
    })
    
    print(f"{dept_abbrev:15} | {dept_info['full_name']:45} | Items: {total_items:4} | Value: PHP{total_unit_value:,.0f}")

print()
print(f"TOTAL: {dept_count} departments | {total_records} items")
print()

# Save department stats
with open('scripts/_department_statistics.json', 'w', encoding='utf-8') as f:
    json.dump(dept_stats, f, indent=2, ensure_ascii=False)

print("[OK] Department statistics saved")
print()

# ===========================
# PROPERTY CATEGORY ANALYSIS
# ===========================

print("="*80)
print("PROPERTY CATEGORIES")
print("="*80)
print()

category_counter = Counter(all_categories)
print(f"Unique Categories Found: {len(category_counter)}")
print()
for cat, count in category_counter.most_common(20):
    print(f"  {cat[:70]:70} : {count:4} occurrences")

print()

# ===========================
# UNIT OF MEASURE ANALYSIS
# ===========================

print("="*80)
print("UNIT OF MEASURE")
print("="*80)
print()

unit_counter = Counter(unit_measures)
print(f"Unique Units: {len(unit_counter)}")
print()
for unit, count in unit_counter.most_common(20):
    print(f"  {unit[:30]:30} : {count:4} occurrences")

print()

# ===========================
# ML FORECASTING READINESS
# ===========================

print("="*80)
print("ML FORECASTING READINESS ASSESSMENT")
print("="*80)
print()

# Check temporal dimension
report_years = set([r['report_year'] for r in all_records if r['report_year']])
print(f"Report Years Found: {sorted(report_years) if report_years else 'None identified'}")
print()

print("ASSESSMENT:")
print()
if len(report_years) <= 1:
    print("[WARN] INSUFFICIENT TEMPORAL DATA FOR TIME-SERIES FORECASTING")
    print()
    print("The workbook contains a SINGLE SNAPSHOT physical count report.")
    print("Time-series forecasting requires MULTIPLE historical observations over time.")
    print()
    print("CURRENT DATA STRUCTURE:")
    print("  - Single physical count (December 31, 2024-2025)")
    print("  - Property inventory snapshot")
    print("  - No historical trends")
    print("  - No demand/consumption history")
    print("  - No acquisition patterns over time")
    print()
    print("REQUIRED FOR ML FORECASTING:")
    print("  1. Multiple physical count reports (monthly, quarterly, or yearly)")
    print("  2. Historical issuance/consumption records")
    print("  3. Historical procurement/acquisition records")
    print("  4. Time-series of quantity changes")
    print("  5. Department-level demand patterns over time")
    print()
    print("RECOMMENDATION:")
    print("  [OK] Import this snapshot into the database as baseline inventory")
    print("  [OK] Begin collecting time-series data from issuances/returns/procurement")
    print("  [OK] After 6-12 months of operational data, ML forecasting becomes viable")
    print("  [OK] Build a historical demand table: [department, item, month, quantity_issued]")
    print()
else:
    print(f"[OK] Multiple report periods detected: {len(report_years)} years")
    print("   Further temporal analysis required to assess forecasting viability")
    print()

# Save analysis summary
summary = {
    'analysis_date': datetime.now().isoformat(),
    'workbook_path': r'e:\RPCSP-semi-expendable-2025.xlsx',
    'total_sheets': len(wb.sheetnames),
    'departments': dept_count,
    'total_records': total_records,
    'report_years': sorted(list(report_years)) if report_years else [],
    'data_quality': {
        'missing_property_no': len(missing_prop_no),
        'property_0000_year': len(prop_0000),
        'missing_description': len(missing_desc),
        'missing_unit_value': len(missing_unit_val),
        'duplicate_property_nos': len(duplicate_prop_nos)
    },
    'ml_readiness': {
        'temporal_dimension': len(report_years),
        'forecasting_viable': len(report_years) > 1,
        'recommendation': 'Import as baseline, collect operational time-series data' if len(report_years) <= 1 else 'Further analysis required'
    }
}

with open('scripts/_analysis_summary.json', 'w', encoding='utf-8') as f:
    json.dump(summary, f, indent=2, ensure_ascii=False)

print("[OK] Analysis summary saved")
print()

print("="*80)
print("ANALYSIS COMPLETE")
print("="*80)
print()
print("Generated Files:")
print("  1. scripts/_semi_expendable_raw_data.json - Complete extracted data")
print("  2. scripts/_department_statistics.json - Department-level statistics")
print("  3. scripts/_analysis_summary.json - Executive summary")
print()
