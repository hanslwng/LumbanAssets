# Authentication Module Documentation

## Purpose
The Authentication module handles user login and session management for the LGU Property Inventory Management System. It provides secure access control with fixed user accounts only.

## How It Works

### Login Process
1. User accesses the login page (`/login`)
2. User enters email and password
3. System validates credentials against database
4. On success:
   - Session variables are set
   - User is redirected to dashboard
5. On failure:
   - Error message is displayed
   - User remains on login page

### Session Management
- **Session Lifetime**: 1 hour (3600 seconds)
- **Session Timeout**: Automatic logout after inactivity
- **Session Variables**:
  - `user_id`: User's database ID
  - `user_email`: User's email address
  - `user_name`: User's full name
  - `user_role`: User's role (admin/staff)
  - `last_activity`: Timestamp of last activity

### Security Features
- Password hashing using PHP's `password_hash()` with PASSWORD_DEFAULT
- Password verification using `password_verify()`
- Input sanitization (email filtering)
- Session-based authentication
- Automatic session timeout
- Protected routes (middleware check on all pages)

## Fixed User Accounts

**No public registration is allowed.** Only these three accounts can access the system:

| Email | Password | Role |
|-------|----------|------|
| admin@gso.local | password123 | Administrator |
| staff1@gso.local | password123 | Staff |
| staff2@gso.local | password123 | Staff |

## Files Structure

```
app/
├── controllers/
│   └── AuthController.php      # Handles login/logout
├── models/
│   └── User.php                # User data management
└── views/
    └── auth/
        └── login.php           # Login page
```

## Key Functions

### AuthController
- `showLogin()`: Display login page
- `login()`: Process login form submission
- `logout()`: End user session

### User Model
- `authenticate($email, $password)`: Verify user credentials
- `getUserById($id)`: Retrieve user information
- `getAllUsers()`: Get all users (admin function)

## Configuration

### Changing Session Lifetime
Edit `config/app.php`:
```php
define('SESSION_LIFETIME', 3600); // Change to desired seconds
```

### Adding New Users
Since registration is disabled, new users must be added directly to the database:

```sql
INSERT INTO users (email, password, name, role) VALUES
('newuser@gso.local', '$2y$10$...', 'New User Name', 'staff');
```

**Note**: Password must be hashed using PHP:
```php
$hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
```

## What to Configure Later

1. **Password Policy**: Implement stronger password requirements
2. **Two-Factor Authentication**: Add 2FA for enhanced security
3. **Password Reset**: Add password reset functionality (if needed)
4. **Role-Based Permissions**: Implement granular permissions per role
5. **Audit Logging**: Log all login attempts and activities
6. **Account Lockout**: Implement lockout after failed login attempts
7. **LDAP Integration**: Connect to existing LGU Active Directory (if applicable)

## Security Best Practices

1. **Change Default Passwords**: Update all default passwords immediately after deployment
2. **Use HTTPS**: Always use HTTPS in production
3. **Regular Password Updates**: Enforce periodic password changes
4. **Monitor Sessions**: Review active sessions regularly
5. **Secure Session Storage**: Ensure session files are stored securely

## Troubleshooting

### Cannot Login
- Verify database connection
- Check if user exists in database
- Verify password hash matches
- Check session configuration

### Session Timeout Too Quick
- Increase `SESSION_LIFETIME` in config
- Check server session garbage collection settings

### Redirect Loop
- Clear browser cookies
- Check session configuration
- Verify `.htaccess` rewrite rules

## API Reference

### Login Endpoint
- **URL**: `/login`
- **Method**: POST
- **Parameters**:
  - `email` (required): User email
  - `password` (required): User password
- **Response**: Redirect to dashboard or login with error

### Logout Endpoint
- **URL**: `/logout`
- **Method**: GET
- **Response**: Redirect to login page

## Testing

Test the authentication system:

1. **Valid Login**: Use default credentials
2. **Invalid Login**: Try wrong password
3. **Session Timeout**: Wait for timeout period
4. **Protected Routes**: Try accessing pages without login
5. **Logout**: Verify session is destroyed

---

**Last Updated**: <?php echo date('F d, Y'); ?>
