# Viewer Role Testing Guide

## Quick Test Checklist

### ✅ Login as Viewer

**Test Credentials** (if using test data):
```
Email: viewer@gso.local
Password: viewer123
```

---

## Visual Indicators

### 1. Topbar Check ✅

**What You Should See**:
```
[🔒 Read-Only Mode] [User Profile ▼]
```

**What You Should NOT See**:
- ❌ "Record Return" button
- ❌ "Issue Item" button
- ❌ "Encode Item" button

### 2. User Profile Check ✅

**Click on user profile, should show**:
```
┌─────────────────────────────┐
│ [Red Header]                │
│ Viewer User                 │
│ viewer@gso.local            │
├─────────────────────────────┤
│ 🚪 Logout                   │
└─────────────────────────────┘
```

**Role Display**: "Viewer (Read-Only)"

---

## Functional Tests

### Test 1: Dashboard Access ✅

**Action**: Navigate to dashboard  
**Expected**: ✅ Dashboard loads successfully  
**Expected**: ✅ All statistics visible  
**Expected**: ✅ All data tables visible  

### Test 2: View Items ✅

**Action**: Click "Semi-Expendable" or "PPE"  
**Expected**: ✅ Items list loads  
**Expected**: ✅ Can see all item details  
**Expected**: ✅ Can expand office accordions  

### Test 3: Try to Create Item ❌

**Action**: Look for "Encode Item" button  
**Expected**: ❌ Button not visible  
**Expected**: ✅ "Read-Only Mode" badge shown  

**If you try via API**:
```bash
curl -X POST http://localhost/lgu_invent/api/items.php \
  -H "Content-Type: application/json" \
  -d '{"description":"Test","unit_value":1000,"office_id":1}'
```
**Expected Response**:
```json
{
  "success": false,
  "message": "Permission denied",
  "error": "You do not have permission to perform this action",
  "required_permission": "create",
  "your_role": "viewer"
}
```

### Test 4: Try to Edit Item ❌

**Action**: Click on an item  
**Expected**: ❌ No edit button visible  
**Expected**: ✅ Can view item details  

### Test 5: Try to Delete Item ❌

**Action**: Look for delete button  
**Expected**: ❌ Delete button not visible  

### Test 6: Try to Issue Item ❌

**Action**: Look for "Issue Item" button  
**Expected**: ❌ Button not visible  

### Test 7: Try to Record Return ❌

**Action**: Look for "Record Return" button  
**Expected**: ❌ Button not visible  

### Test 8: Export/Print ✅

**Action**: Click print button on reports  
**Expected**: ✅ Print dialog opens  
**Expected**: ✅ Can export data  

### Test 9: View Reports ✅

**Action**: Navigate to "COA Report"  
**Expected**: ✅ Report loads  
**Expected**: ✅ Can view all data  
**Expected**: ✅ Can print report  

---

## Browser Console Tests

### Check User Permissions

**Open browser console (F12), type**:
```javascript
userPermissions
```

**Expected Output**:
```javascript
{
  canCreate: false,
  canEdit: false,
  canDelete: false,
  isViewer: true
}
```

### Try to Call Save Function

**In console, type**:
```javascript
saveItem()
```

**Expected**: Toast message "You do not have permission to create items (Read-Only Mode)"

---

## API Endpoint Tests

### Test All Protected Endpoints

**1. Create Item (POST)**:
```bash
curl -X POST http://localhost/lgu_invent/api/items.php \
  -H "Content-Type: application/json" \
  -d '{"description":"Test","unit_value":1000,"office_id":1}'
```
**Expected**: 403 Forbidden

**2. Edit Item (PUT)**:
```bash
curl -X PUT http://localhost/lgu_invent/api/items.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"description":"Updated"}'
```
**Expected**: 403 Forbidden

**3. Delete Item (DELETE)**:
```bash
curl -X DELETE http://localhost/lgu_invent/api/items.php?id=1
```
**Expected**: 403 Forbidden

**4. View Items (GET)**:
```bash
curl http://localhost/lgu_invent/api/items.php
```
**Expected**: 200 OK with data

**5. View Dashboard (GET)**:
```bash
curl http://localhost/lgu_invent/api/dashboard.php
```
**Expected**: 200 OK with data

---

## Comparison: Viewer vs Staff vs Admin

### Feature Matrix

| Feature | Viewer | Staff | Admin |
|---------|--------|-------|-------|
| View Dashboard | ✅ | ✅ | ✅ |
| View Items | ✅ | ✅ | ✅ |
| Create Items | ❌ | ✅ | ✅ |
| Edit Items | ❌ | ✅ | ✅ |
| Delete Items | ❌ | ❌ | ✅ |
| Issue Items | ❌ | ✅ | ✅ |
| Record Returns | ❌ | ✅ | ✅ |
| Mark Unserviceable | ❌ | ✅ | ✅ |
| Manage Offices | ❌ | ❌ | ✅ |
| Manage Users | ❌ | ❌ | ✅ |
| Export Data | ✅ | ✅ | ✅ |
| Print Reports | ✅ | ✅ | ✅ |

---

## Expected UI Differences

### Viewer UI

**Topbar**:
```
[🔒 Read-Only Mode] [User Profile ▼]
```

**Dashboard**:
- All statistics visible
- All data tables visible
- No action buttons

**Items Page**:
- Can view all items
- No "Encode Item" button
- No edit/delete buttons

### Staff UI

**Topbar**:
```
[Record Return] [Issue Item] [Encode Item] [User Profile ▼]
```

**Dashboard**:
- All statistics visible
- All data tables visible
- Action buttons visible

**Items Page**:
- Can view all items
- "Encode Item" button visible
- Edit buttons visible
- No delete buttons

### Admin UI

**Topbar**:
```
[Record Return] [Issue Item] [Encode Item] [User Profile ▼]
```

**Dashboard**:
- All statistics visible
- All data tables visible
- All action buttons visible

**Items Page**:
- Can view all items
- "Encode Item" button visible
- Edit buttons visible
- Delete buttons visible

---

## Troubleshooting

### Issue: Viewer Can Still See Action Buttons

**Solution**:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+F5)
3. Logout and login again
4. Check session: `print_r($_SESSION);`

### Issue: Viewer Gets 403 on Dashboard

**Solution**:
1. Check if 'view' permission is set to true for viewer
2. Verify PermissionHelper is loaded
3. Check session role: `echo $_SESSION['user_role'];`

### Issue: API Returns 200 Instead of 403

**Solution**:
1. Verify PermissionHelper::require() is called in API
2. Check if session is active
3. Verify user is logged in

---

## Success Criteria

### ✅ All Tests Must Pass

**Viewer Role**:
- [x] Cannot see create buttons
- [x] Cannot see edit buttons
- [x] Cannot see delete buttons
- [x] Can view all data
- [x] Can export/print
- [x] "Read-Only Mode" badge visible
- [x] API returns 403 for write operations
- [x] API returns 200 for read operations

**Staff Role**:
- [x] Can see create buttons
- [x] Can see edit buttons
- [x] Cannot see delete buttons
- [x] Can create items
- [x] Can edit items
- [x] Cannot delete items

**Admin Role**:
- [x] Can see all buttons
- [x] Can perform all operations
- [x] No restrictions

---

## Quick Command Reference

### Login as Different Roles

**Viewer**:
```
Email: viewer@gso.local
Password: viewer123
```

**Staff**:
```
Email: staff@gso.local
Password: staff123
```

**Admin**:
```
Email: admin@gso.local
Password: admin123
```

### Check Session in PHP

```php
<?php
session_start();
echo "Role: " . $_SESSION['user_role'];
echo "\nCan Create: " . (PermissionHelper::can('create') ? 'Yes' : 'No');
?>
```

### Check Permissions in Browser Console

```javascript
// Check user permissions
console.log(userPermissions);

// Try to create (should fail for viewer)
checkPermission('create');
```

---

## Final Verification

### Complete Test Sequence

1. **Login as Viewer** ✅
2. **Check topbar** → Should show "Read-Only Mode" badge ✅
3. **Check buttons** → No create/edit/delete buttons ✅
4. **View dashboard** → Should load successfully ✅
5. **View items** → Should load successfully ✅
6. **Try to create** → Should be blocked ✅
7. **Try API create** → Should return 403 ✅
8. **Export/Print** → Should work ✅
9. **Logout** → Should work ✅

### If All Tests Pass

**System Status**: ✅ RBAC Working Correctly  
**Viewer Role**: ✅ Properly Enforced  
**Security**: ✅ Production Ready  

---

**Testing Guide By**: Kiro AI Assistant  
**Date**: May 4, 2026  
**Status**: ✅ READY FOR TESTING
