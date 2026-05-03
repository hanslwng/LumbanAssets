# LGU Property Inventory Management System
## Installation Guide

This guide will help you set up the LGU Property Inventory Management System on your Laragon environment.

## Prerequisites

- **Laragon** (or XAMPP/WAMP)
- **PHP 7.4 or higher**
- **MySQL 5.7 or higher**
- **Apache with mod_rewrite enabled**

## Step-by-Step Installation

### 1. Extract Files

Extract the project files to your Laragon `www` directory:
```
C:\laragon\www\lgu-inventory\
```

### 2. Create Database

1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Create a new database named `lgu_inventory`
3. Or use MySQL command line:
```sql
CREATE DATABASE lgu_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Import Database Schema

Import the database schema and seed data:

**Option A: Using phpMyAdmin**
1. Select the `lgu_inventory` database
2. Click "Import" tab
3. Choose file: `database/schema.sql`
4. Click "Go"
5. Repeat for `database/seed.sql`

**Option B: Using Command Line**
```bash
cd C:\laragon\www\lgu-inventory
mysql -u root -p lgu_inventory < database/schema.sql
mysql -u root -p lgu_inventory < database/seed.sql
```

### 4. Configure Database Connection

Edit `config/database.php` if your database settings are different:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'lgu_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');  // Change if you have a password
```

### 5. Configure Apache (if needed)

If using Laragon, it should work automatically. Otherwise, ensure your Apache configuration includes:

```apache
<Directory "C:/laragon/www/lgu-inventory">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 6. Set Permissions (Windows)

Ensure the following directories are writable:
- `storage/`
- `public/assets/`

Right-click → Properties → Security → Edit → Allow "Full Control" for your user.

### 7. Access the System

Open your browser and navigate to:
```
http://localhost/lgu-inventory/public/
```

Or if you set up a virtual host:
```
http://lgu-inventory.test
```

### 8. Login

Use one of the default accounts:

| Email | Password | Role |
|-------|----------|------|
| admin@gso.local | password123 | Administrator |
| staff1@gso.local | password123 | Staff |
| staff2@gso.local | password123 | Staff |

## Setting Up Virtual Host (Optional but Recommended)

### Using Laragon

1. Right-click Laragon tray icon
2. Select "Apache" → "sites-enabled" → "Add..."
3. Enter domain name: `lgu-inventory.test`
4. Select project folder
5. Restart Apache

### Manual Configuration

Edit `C:\laragon\etc\apache2\sites-enabled\lgu-inventory.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/lgu-inventory/public"
    ServerName lgu-inventory.test
    ServerAlias *.lgu-inventory.test
    <Directory "C:/laragon/www/lgu-inventory/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Edit `C:\Windows\System32\drivers\etc\hosts` (as Administrator):
```
127.0.0.1 lgu-inventory.test
```

Restart Apache.

## Troubleshooting

### Issue: "Database connection failed"
**Solution**: 
- Check database credentials in `config/database.php`
- Ensure MySQL is running in Laragon
- Verify database `lgu_inventory` exists

### Issue: "404 Not Found" or routes not working
**Solution**:
- Ensure `.htaccess` file exists in `public/` directory
- Enable `mod_rewrite` in Apache
- Check Apache configuration allows `.htaccess` overrides

### Issue: "Session errors"
**Solution**:
- Check PHP session configuration
- Ensure `storage/` directory is writable
- Clear browser cookies

### Issue: Blank page or PHP errors
**Solution**:
- Check PHP error log in Laragon
- Ensure PHP version is 7.4 or higher
- Verify all files were extracted correctly

### Issue: CSS/JS not loading
**Solution**:
- Check if accessing through `public/` directory
- Verify `assets/` folder exists in `public/`
- Check browser console for 404 errors

## Post-Installation Steps

### 1. Change Default Passwords
**IMPORTANT**: Change all default passwords immediately:
```sql
UPDATE users SET password = '$2y$10$...' WHERE email = 'admin@gso.local';
```

Use PHP to generate new password hash:
```php
<?php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
?>
```

### 2. Add LGU Logo
Replace the placeholder logo:
- Add your logo file to: `public/assets/logo.png`
- Recommended size: 80x80 pixels
- Format: PNG with transparent background

### 3. Configure COA Templates
Update the placeholder templates with official COA formats:
- PAR Template: `app/views/issuances/print_par.php`
- ICS Template: `app/views/issuances/print_ics.php`
- Property Card: `app/views/items/print_property_card.php`

### 4. Add Offices
Add your LGU's offices:
1. Login as admin
2. Navigate to database via phpMyAdmin
3. Add offices to `offices` table

Or create an admin interface for office management.

### 5. Backup Database
Set up regular database backups:
```bash
mysqldump -u root -p lgu_inventory > backup_YYYYMMDD.sql
```

## System Requirements

### Minimum Requirements
- **Processor**: 1 GHz or faster
- **RAM**: 2 GB
- **Storage**: 500 MB free space
- **Browser**: Chrome, Firefox, Edge (latest versions)

### Recommended Requirements
- **Processor**: 2 GHz or faster
- **RAM**: 4 GB or more
- **Storage**: 1 GB free space
- **Browser**: Chrome (latest version)

## Security Recommendations

1. **Change Default Passwords**: Update all user passwords
2. **Use HTTPS**: Install SSL certificate for production
3. **Regular Updates**: Keep PHP and MySQL updated
4. **Backup Regularly**: Schedule automatic database backups
5. **Restrict Access**: Use firewall rules to limit access
6. **Monitor Logs**: Review Apache and PHP error logs regularly

## Getting Help

### Documentation
- Main README: `README.md`
- Module Documentation: `docs/` directory
- Authentication: `docs/AUTHENTICATION.md`
- Inventory: `docs/INVENTORY.md`
- Issuance: `docs/ISSUANCE.md`
- Returns: `docs/RETURNS.md`
- Property Card: `docs/PROPERTY_CARD.md`

### Common Issues
Check the troubleshooting section above or review the documentation.

### Support
Contact your GSO IT Department for technical support.

## Next Steps

After installation:
1. ✅ Login with default credentials
2. ✅ Change default passwords
3. ✅ Add LGU logo
4. ✅ Add offices
5. ✅ Add initial inventory items
6. ✅ Test issuance process
7. ✅ Test return process
8. ✅ Generate reports
9. ✅ Train staff on system usage

## Production Deployment

When deploying to production:
1. Set `display_errors = 0` in PHP configuration
2. Use strong passwords for all accounts
3. Enable HTTPS
4. Set up regular backups
5. Configure firewall rules
6. Monitor system logs
7. Test all functionality thoroughly

---

**Installation Complete!** 🎉

You can now start using the LGU Property Inventory Management System.
