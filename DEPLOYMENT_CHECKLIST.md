# Deployment Checklist
## LGU Property Inventory Management System

Use this checklist to ensure proper deployment and configuration.

## Pre-Deployment

### System Requirements
- [ ] PHP 7.4 or higher installed
- [ ] MySQL 5.7 or higher installed
- [ ] Apache with mod_rewrite enabled
- [ ] Minimum 2GB RAM available
- [ ] 500MB free disk space

### Files and Folders
- [ ] All project files extracted to web directory
- [ ] `storage/` folder created and writable
- [ ] `public/assets/` folder writable
- [ ] `.htaccess` file present in `public/` directory

## Database Setup

- [ ] Database `lgu_inventory` created
- [ ] Character set: utf8mb4
- [ ] Collation: utf8mb4_unicode_ci
- [ ] `schema.sql` imported successfully
- [ ] `seed.sql` imported successfully
- [ ] 8 tables created (users, offices, items, property_cards, issuances, returns, unserviceable_items, forecasts)
- [ ] 3 default users exist
- [ ] 8 sample offices exist

## Configuration

- [ ] `config/database.php` updated with correct credentials
- [ ] Database connection tested
- [ ] Session configuration verified
- [ ] Timezone set to Asia/Manila (or appropriate)
- [ ] Error reporting configured (off for production)

## Security

- [ ] All default passwords changed
- [ ] Strong passwords implemented
- [ ] Database user has appropriate permissions only
- [ ] File permissions set correctly (755 for folders, 644 for files)
- [ ] `storage/` folder writable by web server
- [ ] HTTPS enabled (for production)
- [ ] Firewall rules configured
- [ ] Backup system in place

## Testing

### Authentication
- [ ] Can login with admin account
- [ ] Can login with staff accounts
- [ ] Cannot access pages without login
- [ ] Session timeout works (after 1 hour)
- [ ] Logout works correctly

### Dashboard
- [ ] Dashboard loads without errors
- [ ] Statistics display correctly
- [ ] Recent transactions show (if any)
- [ ] Quick action buttons work

### Inventory Management
- [ ] Can add new item
- [ ] Property number auto-generates correctly
- [ ] Classification auto-determines (PPE/Semi-Expendable)
- [ ] Property card auto-creates
- [ ] Can view item details
- [ ] Can filter items by office/classification/status
- [ ] Can print property card

### Issuance System
- [ ] Can create issuance
- [ ] Form type auto-determines (PAR/ICS)
- [ ] Quantity validation works
- [ ] Available quantity updates correctly
- [ ] Property card updates automatically
- [ ] Can view issuance details
- [ ] Can print PAR form
- [ ] Can print ICS form

### Return System
- [ ] Can process return
- [ ] Condition assessment works
- [ ] Action options work correctly
- [ ] Damaged items cannot be transferred (validation)
- [ ] Return to storage updates quantity
- [ ] Mark as unserviceable flags item
- [ ] Issuance status updates correctly

### Reports
- [ ] Inventory report generates
- [ ] Unserviceable items report generates
- [ ] Filters work correctly
- [ ] Reports are printable
- [ ] PDF export works (via browser print)

### UI/UX
- [ ] Logo displays (or placeholder shows)
- [ ] Sidebar navigation works
- [ ] All menu items accessible
- [ ] Responsive design works on different screens
- [ ] Icons display correctly (Font Awesome)
- [ ] Colors match theme (government red)
- [ ] Forms validate correctly
- [ ] Success/error messages display

### Placeholders
- [ ] Chatbot button displays
- [ ] Chatbot panel opens/closes
- [ ] Chatbot shows placeholder message
- [ ] Forecasting page displays
- [ ] Forecasting shows "under development" message

## Customization

- [ ] LGU logo added to `/public/assets/logo.png`
- [ ] System title updated (if needed)
- [ ] Offices added/updated for your LGU
- [ ] COA templates reviewed (PAR, ICS, Property Card)
- [ ] Excel export template prepared (if available)

## Documentation

- [ ] README.md reviewed
- [ ] INSTALLATION.md reviewed
- [ ] Module documentation reviewed
- [ ] Staff training materials prepared
- [ ] User manual created (if needed)

## Production Deployment

### Server Configuration
- [ ] Virtual host configured (if applicable)
- [ ] Domain/subdomain pointed to `public/` folder
- [ ] SSL certificate installed
- [ ] HTTPS redirect enabled
- [ ] Error pages configured (404, 500)

### Performance
- [ ] PHP opcache enabled
- [ ] Database queries optimized
- [ ] Static assets cached
- [ ] Gzip compression enabled

### Monitoring
- [ ] Error logging enabled
- [ ] Access logging enabled
- [ ] Backup schedule configured
- [ ] Monitoring system in place (optional)

### Backup Strategy
- [ ] Database backup script created
- [ ] Backup schedule set (daily recommended)
- [ ] Backup storage location configured
- [ ] Backup restoration tested
- [ ] Off-site backup configured (recommended)

## Post-Deployment

### Initial Data
- [ ] All offices added
- [ ] Initial inventory items added (if any)
- [ ] Test issuance created and verified
- [ ] Test return processed and verified

### User Training
- [ ] Admin trained on system management
- [ ] Staff trained on daily operations
- [ ] Training materials distributed
- [ ] Support contact information provided

### Go-Live
- [ ] System announced to users
- [ ] Support team ready
- [ ] Monitoring active
- [ ] Backup verified

## Maintenance Schedule

### Daily
- [ ] Check error logs
- [ ] Verify backup completed
- [ ] Monitor system performance

### Weekly
- [ ] Review user activity
- [ ] Check disk space
- [ ] Test backup restoration

### Monthly
- [ ] Update passwords (if policy requires)
- [ ] Review security logs
- [ ] Update documentation (if needed)
- [ ] Check for PHP/MySQL updates

### Quarterly
- [ ] Full system audit
- [ ] User feedback review
- [ ] Performance optimization
- [ ] Security assessment

## Support Contacts

**Technical Support:**
- GSO IT Department
- System Administrator: _______________
- Database Administrator: _______________

**COA Compliance:**
- COA Auditor: _______________
- Property Officer: _______________

**Emergency Contacts:**
- After-hours support: _______________
- Backup administrator: _______________

## Sign-Off

**Deployed By:**
- Name: _______________
- Date: _______________
- Signature: _______________

**Verified By:**
- Name: _______________
- Date: _______________
- Signature: _______________

**Approved By:**
- Name: _______________
- Position: _______________
- Date: _______________
- Signature: _______________

---

## Notes

Use this space for deployment-specific notes:

_____________________________________________
_____________________________________________
_____________________________________________
_____________________________________________

---

**Deployment Status:** [ ] Complete [ ] Pending [ ] Issues Found

**Production URL:** _______________________________

**Go-Live Date:** _______________________________
