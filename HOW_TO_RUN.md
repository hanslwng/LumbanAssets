# 🚀 HOW TO RUN THE LGU PROPERTY INVENTORY SYSTEM

## ✅ REQUIREMENTS VERIFICATION

All requirements have been completed:

### ✅ Core Features
- [x] Authentication (Login only, 3 fixed accounts)
- [x] Dashboard (Statistics, Recent Transactions)
- [x] Office-Based Inventory
- [x] Item Encoding (Auto-classify, Auto-generate property numbers)
- [x] Property Card (Auto-generated, Viewable, Printable)
- [x] Issuance System (PAR/ICS auto-determined)
- [x] Return System (Condition assessment, Actions)
- [x] Unserviceable Items (Separate tracking)
- [x] Reports (Inventory, Unserviceable, Printable)
- [x] Forecasting Module (Placeholder)
- [x] Chatbot (Placeholder)

### ✅ UI/UX
- [x] Government Red Theme (#B22222)
- [x] Left Sidebar Navigation
- [x] Top Bar with Logo Placeholder
- [x] Clean, Minimal Design
- [x] Font Awesome Icons
- [x] Responsive Layout

### ✅ Security
- [x] Prepared Statements (PDO)
- [x] SQL Injection Prevention
- [x] Input Validation
- [x] XSS Protection
- [x] Session Timeout

### ✅ Documentation
- [x] Main README
- [x] Installation Guide
- [x] Module Documentation (5 files)
- [x] Code Comments
- [x] Deployment Checklist

---

## 🎯 OPTION 1: QUICK RUN (Using Laragon)

### Step 1: Start Laragon
1. Open **Laragon**
2. Click **Start All** button
3. Wait for Apache and MySQL to start (green indicators)

### Step 2: Create Database
1. Click **Database** button in Laragon
2. Or open browser: `http://localhost/phpmyadmin`
3. Click **New** in left sidebar
4. Database name: `lgu_inventory`
5. Collation: `utf8mb4_unicode_ci`
6. Click **Create**

### Step 3: Import Database
1. In phpMyAdmin, select `lgu_inventory` database
2. Click **Import** tab
3. Click **Choose File**
4. Navigate to: `database/schema.sql`
5. Click **Go** (wait for success message)
6. Repeat for: `database/seed.sql`
7. Click **Go**

### Step 4: Access the System
Open your browser and go to:
```
http://localhost/GSO/public/
```

### Step 5: Login
Use any of these accounts:
```
Email: admin@gso.local
Password: password123

Email: staff1@gso.local
Password: password123

Email: staff2@gso.local
Password: password123
```

---

## 🎯 OPTION 2: DETAILED SETUP (Step by Step)

### Prerequisites Check
- [ ] Laragon installed
- [ ] Apache running
- [ ] MySQL running
- [ ] PHP 7.4+ installed

### Step 1: Verify File Location
Your files should be in:
```
D:\laragon\www\GSO\
```

Check if you see these folders:
- app/
- config/
- database/
- public/
- routes/

### Step 2: Create Database

**Method A: Using phpMyAdmin**
1. Open: `http://localhost/phpmyadmin`
2. Click **New** (left sidebar)
3. Database name: `lgu_inventory`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**

**Method B: Using Laragon Terminal**
1. In Laragon, click **Terminal**
2. Type:
```bash
mysql -u root -p
```
3. Press Enter (no password needed usually)
4. Type:
```sql
CREATE DATABASE lgu_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### Step 3: Import Database Schema

**Method A: Using phpMyAdmin**
1. Select `lgu_inventory` database
2. Click **Import** tab
3. Click **Choose File**
4. Select: `D:\laragon\www\GSO\database\schema.sql`
5. Click **Go**
6. Wait for success message: "Import has been successfully finished"

**Method B: Using Laragon Terminal**
```bash
cd D:\laragon\www\GSO
mysql -u root lgu_inventory < database/schema.sql
```

### Step 4: Import Seed Data

**Method A: Using phpMyAdmin**
1. Still in `lgu_inventory` database
2. Click **Import** tab
3. Click **Choose File**
4. Select: `D:\laragon\www\GSO\database\seed.sql`
5. Click **Go**
6. Wait for success message

**Method B: Using Laragon Terminal**
```bash
mysql -u root lgu_inventory < database/seed.sql
```

### Step 5: Verify Database
1. In phpMyAdmin, select `lgu_inventory`
2. You should see 8 tables:
   - users (3 rows)
   - offices (8 rows)
   - items
   - property_cards
   - issuances
   - returns
   - unserviceable_items
   - forecasts

### Step 6: Check Configuration
Open: `config/database.php`

Verify these settings:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'lgu_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');  // Empty for Laragon default
```

### Step 7: Access the System

Open your browser and navigate to:
```
http://localhost/GSO/public/
```

**Important**: Make sure to include `/public/` at the end!

### Step 8: Login

You should see the login page with:
- LGU Logo placeholder
- Email and Password fields
- Default accounts listed

Login with:
```
Email: admin@gso.local
Password: password123
```

---

## 🎯 OPTION 3: USING VIRTUAL HOST (Recommended)

### Why Use Virtual Host?
- Cleaner URL: `http://lgu-inventory.test` instead of `http://localhost/GSO/public/`
- More professional
- Easier to remember

### Setup Virtual Host in Laragon

1. **Right-click Laragon tray icon**
2. Select **Apache** → **sites-enabled** → **lgu-inventory.conf**
3. Add this content:

```apache
<VirtualHost *:80>
    DocumentRoot "D:/laragon/www/GSO/public"
    ServerName lgu-inventory.test
    ServerAlias *.lgu-inventory.test
    <Directory "D:/laragon/www/GSO/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

4. **Save the file**
5. **Right-click Laragon** → **Apache** → **Reload**
6. **Access**: `http://lgu-inventory.test`

---

## 🔍 TROUBLESHOOTING

### Problem: "Database connection failed"
**Solution**:
1. Check if MySQL is running in Laragon
2. Verify database name is `lgu_inventory`
3. Check `config/database.php` settings
4. Try accessing phpMyAdmin to confirm MySQL works

### Problem: "404 Not Found" or blank page
**Solution**:
1. Make sure you're accessing: `http://localhost/GSO/public/`
2. Check if `.htaccess` file exists in `public/` folder
3. Verify Apache mod_rewrite is enabled
4. Check Laragon Apache error log

### Problem: "Cannot find database tables"
**Solution**:
1. Import `database/schema.sql` first
2. Then import `database/seed.sql`
3. Verify tables exist in phpMyAdmin

### Problem: CSS/JS not loading
**Solution**:
1. Check if `public/assets/` folder exists
2. Verify `style.css` and `app.js` are in correct folders
3. Clear browser cache (Ctrl+F5)

### Problem: "Session errors"
**Solution**:
1. Check if `storage/` folder exists
2. Make sure `storage/` is writable
3. Clear browser cookies

---

## ✅ VERIFICATION CHECKLIST

After running the system, verify these:

### Login Page
- [ ] Login page loads
- [ ] Logo placeholder shows
- [ ] Email and password fields present
- [ ] Can login with admin@gso.local

### Dashboard
- [ ] Dashboard loads after login
- [ ] Statistics cards show (0 values initially)
- [ ] Sidebar navigation visible
- [ ] Top bar shows username
- [ ] Logout button works

### Navigation
- [ ] Dashboard link works
- [ ] Inventory link works
- [ ] Issuances link works
- [ ] Returns link works
- [ ] Reports link works
- [ ] Forecasting link works

### Add Item
- [ ] Can access "Add New Item" page
- [ ] Form fields display correctly
- [ ] Can select office from dropdown
- [ ] Can save item
- [ ] Property number auto-generates
- [ ] Redirects to item view page

### View Item
- [ ] Item details display
- [ ] Property card shows initial entry
- [ ] Can print property card

### Create Issuance
- [ ] Can access "Create Issuance" page
- [ ] Can select item from dropdown
- [ ] Form validates quantity
- [ ] Can save issuance
- [ ] Form type auto-determines (PAR/ICS)

### Process Return
- [ ] Can access "Process Return" page
- [ ] Can select active issuance
- [ ] Condition dropdown works
- [ ] Action dropdown works
- [ ] Transfer office shows when needed

### Reports
- [ ] Reports page loads
- [ ] Can generate inventory report
- [ ] Can generate unserviceable report
- [ ] Can print reports

### Chatbot (Placeholder)
- [ ] Chatbot button visible (bottom right)
- [ ] Chatbot panel opens
- [ ] Shows placeholder message

### Forecasting (Placeholder)
- [ ] Forecasting page loads
- [ ] Shows "under development" message
- [ ] Placeholder UI displays

---

## 🎉 SUCCESS!

If all checks pass, your system is running correctly!

### Next Steps:
1. ✅ Change default passwords
2. ✅ Add your LGU logo to `public/assets/logo.png`
3. ✅ Add your offices (or edit existing ones in database)
4. ✅ Start adding inventory items
5. ✅ Test complete workflow (Add → Issue → Return)
6. ✅ Generate reports
7. ✅ Train your staff

---

## 📞 NEED HELP?

### Quick Reference
- **Main README**: `README.md`
- **Installation Guide**: `INSTALLATION.md`
- **Quick Start**: `QUICK_START.md`
- **Module Docs**: `docs/` folder

### Common URLs
- **System**: `http://localhost/GSO/public/`
- **phpMyAdmin**: `http://localhost/phpmyadmin`
- **Laragon**: Right-click tray icon for menu

### Default Credentials
```
admin@gso.local / password123
staff1@gso.local / password123
staff2@gso.local / password123
```

---

## 🎯 QUICK TEST WORKFLOW

Once logged in, try this complete workflow:

### 1. Add an Item (2 minutes)
- Click **Inventory** → **Add New Item**
- Item Name: "Desktop Computer"
- Unit Value: 55000 (will auto-classify as PPE)
- Quantity: 5
- Office: Select any office
- Year Acquired: 2024
- Click **Save**
- Note the auto-generated property number (e.g., 2024-PPE-000001)

### 2. Create Issuance (2 minutes)
- Click **Issuances** → **Create Issuance**
- Select the item you just created
- Quantity: 2
- Issued To: "Juan Dela Cruz"
- Office: Select any office
- Form Number: "PAR-2024-001"
- Click **Create Issuance**
- Note: System auto-determined PAR (because PPE)

### 3. Process Return (2 minutes)
- Click **Returns** → **Process Return**
- Select the issuance you just created
- Quantity: 1
- Condition: "Good"
- Action: "Return to Storage"
- Click **Process Return**

### 4. View Property Card (1 minute)
- Click **Inventory**
- Click **View** on your item
- Scroll to Property Card section
- You should see 3 entries:
  1. Initial IN (5 items)
  2. Issuance OUT (2 items, balance: 3)
  3. Return IN (1 item, balance: 4)

### 5. Generate Report (1 minute)
- Click **Reports**
- Click **Generate Report** for Inventory
- Your item should appear in the list
- Click **Print** to test PDF export

---

**🎊 CONGRATULATIONS! Your LGU Property Inventory System is now running!**
