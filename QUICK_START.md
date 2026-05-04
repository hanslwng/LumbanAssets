# 🚀 LGU Inventory System - Quick Start Guide

## ⚡ Fastest Way to Setup (3 Steps)

### Step 1: Open Setup Script
```
http://localhost/lgu-inventory/setup-database.php
```

This will automatically:
- ✅ Create database
- ✅ Create all tables
- ✅ Insert sample offices
- ✅ Verify installation

### Step 2: Open Application
```
http://localhost/lgu-inventory/index.php
```

### Step 3: Test Everything Works
```
http://localhost/lgu-inventory/diagnose.html
```

---

## 📋 Manual Setup (If Needed)

### Option A: Using phpMyAdmin
1. Open phpMyAdmin
2. Click "Import"
3. Choose `database.sql`
4. Click "Go"

### Option B: Using Command Line
```bash
mysql -u root -p < database.sql
```

### Option C: Using MySQL Workbench
1. Open MySQL Workbench
2. File → Run SQL Script
3. Select `database.sql`
4. Execute

---

## 🔧 Configuration

### Update Database Credentials (if needed)

Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'lgu_inventory');
```

---

## ✅ Verification Checklist

### 1. Database Check
```
http://localhost/lgu-inventory/test-connection.php
```

Should show:
- ✅ Config file loaded
- ✅ Database connection successful
- ✅ All 6 tables exist
- ✅ 10 sample offices loaded

### 2. System Check
```
http://localhost/lgu-inventory/diagnose.html
```

Should show:
- ✅ JavaScript working
- ✅ Event parameters working
- ✅ app.js loaded
- ✅ All files exist

### 3. API Check
```
http://localhost/lgu-inventory/test-chatbot-api.html
```

Should show:
- ✅ All API endpoints working
- ✅ Summary data loaded
- ✅ Search working
- ✅ Natural language queries working

### 4. Main Application
```
http://localhost/lgu-inventory/index.php
```

Should show:
- ✅ Dashboard loads
- ✅ Sidebar navigation works
- ✅ Buttons clickable
- ✅ Modals open

---

## 🎯 Quick Test Workflow

### 1. Encode an Item
1. Click "Encode Item" button
2. Fill in:
   - Description: "Laptop Computer"
   - Unit Value: 55000
   - Quantity: 1
   - Office: "Office of the Mayor"
   - Date: Today
3. Click "Save Item"
4. Should see success message

### 2. Issue the Item
1. Click "Issue Item" button
2. Select the laptop you just created
3. System should show "PAR" (because ≥₱50k)
4. Fill in:
   - Form Number: PAR-2025-001
   - Issued To: "Juan Dela Cruz"
   - Position: "Administrative Officer"
   - Date: Today
5. Click "Issue Item"
6. Item status should change to "Issued"

### 3. Test Chatbot API
1. Open `test-chatbot-api.html`
2. Click "Test Summary"
3. Should see: 1 item, 1 PPE, total value ₱55,000
4. Try natural language: "How many items are there?"
5. Should answer: "There is 1 property item in the system."

---

## 🆘 Troubleshooting

### Database Won't Load

**Problem:** "Database connection failed"

**Solutions:**
1. Check if MySQL is running
2. Verify credentials in `config.php`
3. Run `setup-database.php` again
4. Check MySQL error logs

**Test:**
```
http://localhost/lgu-inventory/test-connection.php
```

---

### Clicks Not Working

**Problem:** Can't click sidebar or buttons

**Solutions:**
1. Clear browser cache: `Ctrl+Shift+Delete`
2. Hard refresh: `Ctrl+Shift+R`
3. Check browser console (F12) for errors
4. Verify `assets/js/app.js` exists

**Test:**
```
http://localhost/lgu-inventory/diagnose.html
```

---

### CSS Not Loading

**Problem:** Page has no styling

**Solutions:**
1. Verify `assets/css/style.css` exists
2. Clear browser cache
3. Check file permissions
4. Hard refresh page

**Test:**
View page source, check if CSS link is correct

---

### API Not Working

**Problem:** Chatbot API returns errors

**Solutions:**
1. Check if database is set up
2. Verify `api/chatbot.php` exists
3. Check PHP error logs
4. Test with `test-chatbot-api.html`

**Test:**
```
http://localhost/lgu-inventory/api/chatbot.php?action=summary
```

---

## 📁 File Structure Check

Make sure these files exist:

### Core Files
- ✅ `config.php`
- ✅ `index.php`
- ✅ `database.sql`

### API Files
- ✅ `api/dashboard.php`
- ✅ `api/items.php`
- ✅ `api/offices.php`
- ✅ `api/issuance.php`
- ✅ `api/returns.php`
- ✅ `api/unserviceable.php`
- ✅ `api/chatbot.php`

### Assets
- ✅ `assets/css/style.css`
- ✅ `assets/js/app.js`

### Includes
- ✅ `includes/modals.php`

### Test Files
- ✅ `setup-database.php`
- ✅ `test-connection.php`
- ✅ `test-chatbot-api.html`
- ✅ `diagnose.html`

---

## 🎓 Learning Path

### Day 1: Setup & Basic Usage
1. Run `setup-database.php`
2. Open `index.php`
3. Encode 3-5 sample items
4. Issue 1-2 items
5. Test navigation

### Day 2: Advanced Features
1. Record a return
2. Mark item as unserviceable
3. Test search functionality
4. Generate reports

### Day 3: Chatbot Integration
1. Test API with `test-chatbot-api.html`
2. Try natural language queries
3. Integrate with your chatbot
4. Test live data access

---

## 📊 Default Data

After setup, you'll have:

### 10 Sample Offices:
1. Office of the Mayor (MAYOR)
2. Office of the Vice Mayor (VMAY)
3. Accounting Office (ACCT)
4. Budget Office (BUDG)
5. Treasury Office (TRES)
6. Engineering Office (ENGR)
7. Health Office (HLTH)
8. Social Welfare Office (SWDO)
9. Planning Office (PLAN)
10. General Services Office (GSO)

### 0 Items (You add these)
- Start by encoding sample items
- Test with different categories (SE/PPE)
- Try different offices

---

## 🔗 Important URLs

| Purpose | URL |
|---------|-----|
| **Setup Database** | `setup-database.php` |
| **Main Application** | `index.php` |
| **Test Connection** | `test-connection.php` |
| **System Diagnostic** | `diagnose.html` |
| **API Test** | `test-chatbot-api.html` |
| **API Endpoint** | `api/chatbot.php` |

---

## ✅ Success Indicators

You'll know everything is working when:

1. ✅ `setup-database.php` shows all green checkmarks
2. ✅ `index.php` loads with proper styling
3. ✅ Sidebar navigation works
4. ✅ Can encode items successfully
5. ✅ Can issue items (PAR/ICS)
6. ✅ API returns data
7. ✅ Chatbot can query database

---

## 🎉 You're Ready!

If all checks pass, your system is ready to use!

**Next:** Start encoding your actual property items and using the system for real inventory management.

**Support Files:**
- `CHATBOT_API_DOCUMENTATION.md` - Full API reference
- `CHATBOT_FINAL_5000.txt` - Knowledge base for chatbot
- `README.md` - Complete system documentation

---

**Setup Time:** ~5 minutes
**Difficulty:** Easy
**Status:** ✅ Ready to use!
