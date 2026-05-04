# LGU Property Inventory Management System (PHP Version)

This is a PHP conversion of the LGU Inventory System, designed for managing property inventory for Local Government Units (LGU) in the Philippines.

## Features

- **Property Management**: Track Semi-Expendable (SE) and Property, Plant & Equipment (PPE) items
- **Office-Based Tracking**: Segregate items by office/department
- **Issuance System**: Generate PAR (Property Acknowledgement Receipt) and ICS (Inventory Custodian Slip) forms
- **Returns Management**: Track returned items with transfer or unserviceable marking
- **Unserviceable Tracking**: Mark and track damaged or unusable items
- **Disposal Records**: Record disposal of unserviceable items
- **COA Reporting**: Generate Commission on Audit reports
- **Dashboard**: Overview of inventory statistics and recent activities

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Apache/Nginx web server
- Modern web browser (Chrome, Firefox, Edge, Safari)

## Installation

### 1. Database Setup

```bash
# Import the database schema
mysql -u root -p < database.sql
```

Or manually create the database:
- Open phpMyAdmin or MySQL client
- Create a new database named `lgu_inventory`
- Import the `database.sql` file

### 2. Configure Database Connection

Edit `config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'lgu_inventory');
```

### 3. Set Up Web Server

#### Apache
- Copy all files to your web root directory (e.g., `/var/www/html/lgu-inventory/` or `C:\xampp\htdocs\lgu-inventory\`)
- Ensure `.htaccess` is enabled (if using Apache)
- Access via `http://localhost/lgu-inventory/`

#### Nginx
- Configure your Nginx server block to point to the project directory
- Ensure PHP-FPM is properly configured

### 4. File Permissions

Ensure proper permissions for PHP to read/write:

```bash
chmod 755 /path/to/lgu-inventory
chmod 644 /path/to/lgu-inventory/*.php
```

## Project Structure

```
lgu-inventory/
├── api/                    # API endpoints
│   ├── items.php          # Property items CRUD
│   ├── offices.php        # Offices management
│   ├── issuance.php       # Issuance records (PAR/ICS)
│   ├── returns.php        # Return records
│   ├── unserviceable.php  # Unserviceable items
│   └── dashboard.php      # Dashboard statistics
├── assets/
│   ├── css/
│   │   └── style.css      # Application styles
│   └── js/
│       └── app.js         # Main JavaScript application
├── includes/
│   └── modals.php         # Modal dialogs
├── config.php             # Database configuration
├── database.sql           # Database schema
├── index.php              # Main application file
└── README.md              # This file
```

## Database Schema

### Tables

1. **offices** - Office/department information
2. **property_items** - Property inventory items
3. **issuance_records** - PAR/ICS issuance records
4. **return_records** - Item return records
5. **unserviceable_records** - Unserviceable item tracking
6. **disposal_records** - Disposal records

## API Endpoints

### Items API (`api/items.php`)
- `GET` - Retrieve items (with filters: category, status, office_id, search)
- `POST` - Create new item
- `PUT` - Update item
- `DELETE` - Delete item

### Offices API (`api/offices.php`)
- `GET` - Retrieve offices with item counts
- `POST` - Create new office
- `PUT` - Update office
- `DELETE` - Delete office

### Issuance API (`api/issuance.php`)
- `GET` - Retrieve issuance records
- `POST` - Create new issuance (PAR/ICS)

### Returns API (`api/returns.php`)
- `GET` - Retrieve return records
- `POST` - Create return record

### Unserviceable API (`api/unserviceable.php`)
- `GET` - Retrieve unserviceable items
- `POST` - Mark item as unserviceable
- `PUT` - Update disposal status

### Dashboard API (`api/dashboard.php`)
- `GET` - Retrieve dashboard statistics and recent activities

## Usage Guide

### Encoding New Items

1. Click "Encode Item" button in the topbar
2. Fill in item details:
   - Description (required)
   - Unit Value (required) - System auto-classifies as SE or PPE
   - Quantity
   - Assigned Office (required)
   - Acquisition Date
   - Remarks
3. System automatically generates property number in format: `YYYY-[SE|PPE]-XXXXXX`

### Issuing Items (PAR/ICS)

1. Click "Issue Item" button
2. Select property item
3. System automatically determines form type:
   - **PAR** for PPE items (₱50,000 and above)
   - **ICS** for Semi-Expendable items (below ₱50,000)
4. Fill in recipient details
5. Generate form

### Recording Returns

1. Click "Record Return" button
2. Select issued item
3. Choose return action:
   - **Mark Unserviceable** - Item is damaged/unusable
   - **Transfer to Office** - Reassign to another office
4. Record return details

### Property Classification

- **Semi-Expendable (SE)**: Items with unit value below ₱50,000
  - Property Number Format: `YYYY-SE-XXXXXX`
  - Form Type: ICS (Inventory Custodian Slip)
  
- **Property, Plant & Equipment (PPE)**: Items with unit value ₱50,000 and above
  - Property Number Format: `YYYY-PPE-XXXXXX`
  - Form Type: PAR (Property Acknowledgement Receipt)

## Security Considerations

1. **Input Sanitization**: All user inputs are sanitized using `sanitizeInput()` function
2. **Prepared Statements**: All database queries use prepared statements to prevent SQL injection
3. **Session Management**: PHP sessions are used for user state management
4. **Access Control**: Implement authentication and authorization as needed

## Customization

### Changing Classification Threshold

Edit `config.php`:

```php
define('PPE_THRESHOLD', 50000); // Change threshold value
```

### Adding Custom Fields

1. Modify database schema in `database.sql`
2. Update API endpoints in `api/` folder
3. Update forms in `includes/modals.php`
4. Update JavaScript in `assets/js/app.js`

## Troubleshooting

### Database Connection Error
- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database exists and user has proper permissions

### API Not Working
- Check PHP error logs
- Verify file permissions
- Ensure mod_rewrite is enabled (Apache)

### Blank Page
- Enable PHP error reporting in `php.ini`
- Check browser console for JavaScript errors

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## License

Internal use only for LGU Property Office.

## Support

For technical support or questions, contact your system administrator.

## Version History

- **v2.0** - PHP conversion with RESTful API
- **v1.0** - Original HTML/JavaScript version
