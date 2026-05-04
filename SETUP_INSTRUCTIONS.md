# LGU Inventory System - Setup Instructions

## 🔧 Quick Setup Guide

### Step 1: Database Setup

You have **3 options** to set up the database:

#### Option A: Automated Setup (Recommended)
1. Open your browser and navigate to: `http://localhost/your-project/setup-database.php`
2. The script will automatically:
   - Create the database `lgu_inventory`
   - Create all required tables
   - Insert sample offices
   - Set up default admin user

#### Option B: Import SQL File
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "Import" tab
3. Choose file: `database/lgu_inventory_system.sql`
4. Click "Go" to import

#### Option C: Command Line
```bash
mysql -u root -p < database/lgu_inventory_system.sql
```

### Step 2: Configure Database Connection

Edit `config.php` and verify these settings:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lgu_inventory');
```

### Step 3: Access the System

1. Open your browser
2. Navigate to: `http://localhost/your-project/`
3. Default login credentials:
   - **Username:** admin
   - **Password:** password

---

## 🐛 Troubleshooting

### Error: "SyntaxError: Unexpected token '<'"

**Cause:** Database is not set up or connection failed

**Solution:**
1. Run `setup-database.php` in your browser
2. Check that MySQL/MariaDB is running
3. Verify database credentials in `config.php`

### Error: "Database connection failed"

**Solution:**
1. Ensure MySQL/MariaDB service is running
2. Check username and password in `config.php`
3. Verify the database name exists

### Error: "Table doesn't exist"

**Solution:**
1. Re-run `setup-database.php`
2. Or import `database/lgu_inventory_system.sql` via phpMyAdmin

---

## 📊 Database Structure

The system includes these tables:
- `users` - System users and authentication
- `offices` - Government offices/departments
- `property_items` - Main inventory
- `issuance_records` - PAR/ICS records
- `return_records` - Return tracking
- `unserviceable_records` - Unserviceable items
- `disposal_records` - Disposal documentation
- `inventory_history` - Audit trail
- `system_settings` - Configuration

---

## 🔐 Default Credentials

**Admin Account:**
- Username: `admin`
- Password: `password`

⚠️ **Important:** Change the default password after first login!

---

## 📝 Sample Data

The setup includes:
- 15 pre-configured government offices
- Default system settings
- Admin user account

---

## 🚀 Next Steps

After setup:
1. Login with admin credentials
2. Change default password
3. Add property items via "Encode Item" button
4. Configure offices if needed
5. Start issuing items using PAR/ICS forms

---

## 💡 Tips

- **PPE Items:** Items with value ≥ ₱50,000 (use PAR form)
- **Semi-Expendable:** Items with value < ₱50,000 (use ICS form)
- The system automatically determines the correct form type based on item value

---

## 📞 Support

If you encounter issues:
1. Check the browser console for error messages (F12)
2. Verify MySQL service is running
3. Check file permissions (if on Linux/Mac)
4. Ensure PHP extensions are enabled: `mysqli`, `json`

---

## ✅ Verification Checklist

- [ ] MySQL/MariaDB is running
- [ ] Database `lgu_inventory` exists
- [ ] All tables are created
- [ ] Sample offices are loaded
- [ ] Can access the system in browser
- [ ] Can login with admin credentials
- [ ] Dashboard loads without errors

---

**System Version:** 2.0  
**Last Updated:** 2026-05-03
