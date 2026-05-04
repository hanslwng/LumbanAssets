# Semi-Expandable Table Implementation Guide

## Overview

The semi-expandable table feature allows inventory reports to be displayed in a grouped, collapsible format similar to official government property reports. Items are grouped by category with expandable/collapsible rows showing detailed item information.

## Features

✅ **Category Grouping** - Items automatically grouped by category
✅ **Expand/Collapse** - Click category headers to show/hide items
✅ **Category Totals** - Automatic calculation of subtotals per category
✅ **Grand Total** - Overall total displayed at the bottom
✅ **Expand/Collapse All** - Button to expand or collapse all categories at once
✅ **Smooth Animations** - CSS transitions for professional appearance
✅ **Print-Friendly** - Optimized for printing reports

## File Structure

### CSS Styles
**Location:** `assets/css/style.css`

New CSS classes added:
- `.semi-expandable-table` - Main table container
- `.category-header-row` - Category header rows (clickable)
- `.category-items-row` - Hidden rows containing item details
- `.nested-items-table` - Inner table for item details
- `.category-subtotal-row` - Subtotal rows per category
- `.grand-total-row` - Grand total row at bottom
- `.expand-all-btn` - Button to expand/collapse all

### JavaScript Functions
**Location:** `assets/js/app.js`

New functions added:
```javascript
toggleCategory(categoryId)           // Toggle single category
expandAllCategories()                // Expand all categories
collapseAllCategories()              // Collapse all categories
renderSemiExpandableReport(items, containerId)  // Render table from data
loadSemiExpandableReport()           // Load data and render
```

### PHP Implementation
**Location:** `app/views/reports/inventory.php`

Added:
- View mode selector (Standard vs Semi-Expandable)
- Category grouping logic
- Semi-expandable table rendering
- Inline JavaScript for toggle functions

## Usage

### 1. In Reports

Navigate to **Reports → Inventory Report**

1. Select filters (Office, Classification)
2. Choose **View Mode: Semi-Expandable (Grouped)**
3. Click **Filter** button
4. Use **▶ Expand All** button to expand all categories
5. Click individual category headers to toggle specific categories

### 2. Standalone Demo

Open `test-semi-expandable.html` in a browser to see a working demo with sample data.

### 3. Custom Implementation

To implement in your own page:

```html
<!-- Include CSS -->
<link rel="stylesheet" href="assets/css/style.css">

<!-- Container for table -->
<div id="my-report-container"></div>

<!-- Include JavaScript -->
<script src="assets/js/app.js"></script>

<script>
// Your data array
const myData = [
    {
        item_category: 'OFFICE FIXTURES & FURNITURES',
        item_name: 'Table',
        description: 'Executive table',
        property_number: '0000-07-010-01001-01',
        unit_of_measure: 'pc',
        unit_value: 18000.00,
        quantity: 1,
        available_quantity: 1,
        remarks: ''
    },
    // ... more items
];

// Render the table
renderSemiExpandableReport(myData, 'my-report-container');
</script>
```

## Table Structure

```
┌─────────────────────────────────────────────────────────────┐
│ Article/Item │ Description │ Property No. │ ... │ Total     │
├─────────────────────────────────────────────────────────────┤
│ ▶ CATEGORY NAME                              │ ₱132,022.00 │ ← Click to expand
├─────────────────────────────────────────────────────────────┤
│   Item 1     │ Details...  │ 0000-01      │ ... │           │ ← Hidden by default
│   Item 2     │ Details...  │ 0000-02      │ ... │           │
│   Subtotal - CATEGORY NAME                   │ ₱132,022.00 │
├─────────────────────────────────────────────────────────────┤
│ ▶ ANOTHER CATEGORY                           │ ₱98,500.00  │
├─────────────────────────────────────────────────────────────┤
│ GRAND TOTAL                                  │ ₱230,522.00 │
└─────────────────────────────────────────────────────────────┘
```

## Category Grouping Logic

Items are automatically categorized based on keywords in the item name:

### OFFICE FIXTURES & FURNITURES
- Keywords: table, chair, cabinet, partition, desk, shelf

### INFORMATION AND COMMUNICATION TECHNOLOGY EQUIPMENT
- Keywords: computer, laptop, printer, monitor, scanner, router

### OFFICE EQUIPMENT
- Keywords: air con, fan, dispenser, refrigerator, microwave

### UNCATEGORIZED
- Items that don't match any category

**Customization:** Edit the categorization logic in `app/views/reports/inventory.php` around line 50-70.

## Styling Customization

### Change Category Header Color

```css
.category-header-row {
    background: #your-color;
}

.category-header-row.expanded {
    background: #your-expanded-color;
}
```

### Change Grand Total Style

```css
.grand-total-row {
    background: #your-color;
    color: #your-text-color;
    font-size: 14px;
}
```

### Adjust Animation Speed

```css
.office-chevron {
    transition: transform 0.3s; /* Change duration */
}
```

## Data Format

The table expects items with the following structure:

```javascript
{
    item_category: 'CATEGORY NAME',        // Category for grouping
    item_name: 'Item Name',                // Short name
    description: 'Full description',       // Detailed description
    property_number: '0000-07-010-01001-01', // Property number
    unit_of_measure: 'pc',                 // Unit (pc, unit, set, etc.)
    unit_value: 18000.00,                  // Price per unit
    quantity: 1,                           // Total quantity
    available_quantity: 1,                 // Available quantity
    remarks: 'Optional remarks'            // Additional notes
}
```

## Browser Compatibility

✅ Chrome 90+
✅ Firefox 88+
✅ Safari 14+
✅ Edge 90+

## Print Optimization

The table is optimized for printing:
- Borders are enhanced for clarity
- Colors are adjusted for black & white printing
- Page breaks are handled appropriately
- Filter controls are hidden in print view

To print:
1. Click the **Print Report** button, or
2. Use browser print (Ctrl+P / Cmd+P)

## Troubleshooting

### Categories not expanding
- Check browser console for JavaScript errors
- Ensure `assets/js/app.js` is loaded
- Verify `toggleCategory()` function exists

### Styling issues
- Clear browser cache
- Check `assets/css/style.css` is loaded
- Verify CSS classes are applied correctly

### Wrong categorization
- Edit categorization logic in `inventory.php`
- Add custom keywords for your item types
- Consider adding a `category` field to database

## Future Enhancements

Potential improvements:
- [ ] Add search/filter within expanded categories
- [ ] Export to Excel with grouped format
- [ ] Save expanded/collapsed state in session
- [ ] Add sorting options per category
- [ ] Custom category definitions in admin panel
- [ ] Drag-and-drop to reorder categories

## Related Files

- `assets/css/style.css` - Styling
- `assets/js/app.js` - JavaScript functions
- `app/views/reports/inventory.php` - PHP implementation
- `test-semi-expandable.html` - Demo page
- `app/controllers/ReportController.php` - Controller logic

## Support

For questions or issues:
1. Check this documentation
2. Review the demo page (`test-semi-expandable.html`)
3. Inspect browser console for errors
4. Check PHP error logs

---

**Last Updated:** <?php echo date('F d, Y'); ?>
**Version:** 1.0.0
