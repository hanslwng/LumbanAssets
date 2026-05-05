# RBAC Quick Reference Card

## 🚀 Quick Start

### Test Users
```
Admin:  admin@gso.local   / password123
Staff:  staff@gso.local   / password123
Viewer: viewer@gso.local  / password123
```

### Test URLs
```
Login:     http://localhost/lgu_invent/login
Dashboard: http://localhost/lgu_invent/
API Test:  http://localhost/lgu_invent/api/dashboard.php
```

---

## 📋 Role Permissions Matrix

| Permission | Admin | Staff | Viewer |
|-----------|-------|-------|--------|
| view | ✅ | ✅ | ✅ |
| create | ✅ | ✅ | ❌ |
| edit | ✅ | ✅ | ❌ |
| delete | ✅ | ❌ | ❌ |
| export | ✅ | ✅ | ✅ |
| print | ✅ | ✅ | ✅ |
| manage_users | ✅ | ❌ | ❌ |
| manage_offices | ✅ | ❌ | ❌ |
| approve | ✅ | ❌ | ❌ |

---

## 💻 Code Examples

### Backend: Check Permission
```php
// Require permission (throws 401/403 if not allowed)
PermissionHelper::require('view');
PermissionHelper::require('create');
PermissionHelper::require('edit');
PermissionHelper::require('delete');

// Check permission (returns boolean)
if (PermissionHelper::can('create')) {
    // User has permission
}

// Check role
if (PermissionHelper::isViewer()) {
    // User is viewer
}
if (PermissionHelper::isAdmin()) {
    // User is admin
}
```

### Backend: API Endpoint Protection
```php
<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
require_once '../app/helpers/PermissionHelper.php';

header('Content-Type: application/json');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        PermissionHelper::require('view');
        getData($conn);
        break;
    case 'POST':
        PermissionHelper::require('create');
        createData($conn);
        break;
    case 'PUT':
        PermissionHelper::require('edit');
        updateData($conn);
        break;
    case 'DELETE':
        PermissionHelper::require('delete');
        deleteData($conn);
        break;
}
```

### Frontend: Check Permission (PHP)
```php
<?php
$canCreate = PermissionHelper::can('create');
$canEdit = PermissionHelper::can('edit');
$canDelete = PermissionHelper::can('delete');
$isViewer = PermissionHelper::isViewer();
?>

<?php if ($canCreate): ?>
<button onclick="createItem()">Create Item</button>
<?php endif; ?>

<?php if ($isViewer): ?>
<div class="viewer-badge">Read-Only Mode</div>
<?php endif; ?>
```

### Frontend: Check Permission (JavaScript)
```javascript
// Load permissions on page load
document.addEventListener('DOMContentLoaded', function() {
    const userData = document.getElementById('userData');
    userPermissions.canCreate = userData.dataset.canCreate === '1';
    userPermissions.canEdit = userData.dataset.canEdit === '1';
    userPermissions.canDelete = userData.dataset.canDelete === '1';
    userPermissions.isViewer = userData.dataset.isViewer === '1';
});

// Check permission before action
function createItem() {
    if (!checkPermission('create')) {
        return; // Shows error toast
    }
    // Proceed with creation
}

// Conditional UI
if (userPermissions.canCreate) {
    showCreateButton();
}
```

---

## 🔒 HTTP Status Codes

### 200 OK
```json
{
    "success": true,
    "data": {...}
}
```

### 401 Unauthorized (Not Logged In)
```json
{
    "success": false,
    "message": "Authentication required",
    "error": "You must be logged in to access this resource"
}
```

### 403 Forbidden (Insufficient Permissions)
```json
{
    "success": false,
    "message": "Permission denied",
    "error": "You do not have permission to perform this action",
    "required_permission": "create",
    "your_role": "viewer"
}
```

---

## 🧪 Quick Tests

### Test 1: Viewer Cannot Create
```javascript
// Login as viewer, then run in console:
fetch('/lgu_invent/api/items.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        description: 'Test',
        unit_value: 1000,
        quantity: 1,
        office_id: 1
    })
}).then(r => r.json()).then(console.log)

// Expected: 403 Forbidden
```

### Test 2: Viewer Can View
```javascript
// Login as viewer, then run in console:
fetch('/lgu_invent/api/dashboard.php')
    .then(r => r.json())
    .then(console.log)

// Expected: 200 OK with data
```

### Test 3: Staff Cannot Delete
```javascript
// Login as staff, then run in console:
fetch('/lgu_invent/api/items.php?id=1', {
    method: 'DELETE'
}).then(r => r.json()).then(console.log)

// Expected: 403 Forbidden
```

---

## 📁 Key Files

### Backend
```
app/helpers/PermissionHelper.php  - RBAC core
api/dashboard.php                 - Dashboard API
api/offices.php                   - Offices API
api/items.php                     - Items API
api/issuance.php                  - Issuance API
api/returns.php                   - Returns API
api/unserviceable.php             - Unserviceable API
config.php                        - Database config
```

### Frontend
```
index.php                         - Main dashboard
assets/js/app.js                  - JavaScript logic
assets/css/style.css              - Styling
```

### Documentation
```
RBAC_IMPLEMENTATION_COMPLETE.md   - Full details
RBAC_TESTING_GUIDE.md             - Testing steps
VIEWER_ROLE_COMPLETE.md           - Summary
RBAC_BEFORE_AFTER.md              - Comparison
EXECUTIVE_SUMMARY.md              - Executive summary
RBAC_QUICK_REFERENCE.md           - This file
```

---

## 🐛 Troubleshooting

### Issue: 403 on dashboard even when logged in
**Solution**: Clear cookies and cache, login again

### Issue: Buttons still showing for viewer
**Solution**: Hard refresh (Ctrl+Shift+R)

### Issue: Session not persisting
**Solution**: Check session_start() in API files

### Issue: Permission checks not working
**Solution**: Verify $_SESSION['user_role'] is set

---

## 🔧 Adding New API Endpoint

```php
<?php
// 1. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Include dependencies
require_once '../config.php';
require_once '../app/helpers/PermissionHelper.php';

// 3. Set JSON header
header('Content-Type: application/json');

// 4. Add permission checks
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        PermissionHelper::require('view');
        // Your code here
        break;
    case 'POST':
        PermissionHelper::require('create');
        // Your code here
        break;
    case 'PUT':
        PermissionHelper::require('edit');
        // Your code here
        break;
    case 'DELETE':
        PermissionHelper::require('delete');
        // Your code here
        break;
}
```

---

## 📊 Permission Hierarchy

```
Admin (Highest)
  ├─ All permissions
  ├─ Can manage users
  ├─ Can manage offices
  └─ Can delete items

Staff (Medium)
  ├─ Can view
  ├─ Can create
  ├─ Can edit
  └─ Cannot delete

Viewer (Lowest)
  ├─ Can view
  ├─ Can export
  ├─ Can print
  └─ Cannot modify
```

---

## 🎯 Common Use Cases

### Use Case 1: Hide Button for Viewers
```php
<?php if (PermissionHelper::can('create')): ?>
<button onclick="createItem()">Create</button>
<?php endif; ?>
```

### Use Case 2: Show Read-Only Badge
```php
<?php if (PermissionHelper::isViewer()): ?>
<div class="viewer-badge">Read-Only Mode</div>
<?php endif; ?>
```

### Use Case 3: Protect API Endpoint
```php
PermissionHelper::require('create');
// Code only runs if user has create permission
```

### Use Case 4: Check Multiple Permissions
```php
if (PermissionHelper::canAll(['create', 'edit'])) {
    // User has both permissions
}

if (PermissionHelper::canAny(['create', 'edit'])) {
    // User has at least one permission
}
```

---

## 📝 Session Variables

```php
$_SESSION['user_id']        // User ID
$_SESSION['user_email']     // User email
$_SESSION['user_name']      // User full name
$_SESSION['user_role']      // User role (admin/staff/viewer)
$_SESSION['last_activity']  // Last activity timestamp
```

---

## ⚡ Performance Tips

1. **Cache Permission Checks**: Store results in variables
2. **Minimize Session Reads**: Read once, use multiple times
3. **Use Prepared Statements**: Always use PDO prepared statements
4. **Optimize Queries**: Add indexes on frequently queried columns

---

## 🔐 Security Checklist

- [x] Session started before permission checks
- [x] All API endpoints protected
- [x] SQL injection prevention (PDO)
- [x] XSS prevention (sanitization)
- [x] Password hashing (bcrypt)
- [x] Session timeout (1 hour)
- [x] Proper error responses (401/403)
- [x] Frontend permission checks
- [x] Clear user feedback

---

## 📞 Support

### Documentation
- Read: `RBAC_IMPLEMENTATION_COMPLETE.md`
- Test: `RBAC_TESTING_GUIDE.md`
- Compare: `RBAC_BEFORE_AFTER.md`

### Quick Help
- Check session: `var_dump($_SESSION);`
- Check role: `echo PermissionHelper::getRole();`
- Check permission: `var_dump(PermissionHelper::can('create'));`

---

## ✅ Status

**System**: 🟢 100% Working  
**Security**: 🔒 Fully Protected  
**Tests**: ✅ All Passed  
**Documentation**: 📝 Complete  
**Production**: 🚀 Ready

---

**Last Updated**: May 5, 2026  
**Version**: 1.0  
**Status**: Production Ready ✅
