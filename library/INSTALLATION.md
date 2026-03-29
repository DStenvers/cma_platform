# Installation Guide

This PHP application was converted from ASP and requires some setup before it can run.

## Prerequisites

- PHP 7.4 or higher
- Composer (for dependency management)
- Nginx web server
- APCu extension (optional, but recommended for performance)


## Installation Steps

### 1. Install Dependencies

```bash
composer install
```

This will install:
- `vlucas/phpdotenv` - Environment variable management
- `phpmailer/phpmailer` - Email sending library

### 2. Configure Environment

Copy the environment template:

```bash
cp .env.example .env
```

Edit `.env` and fill in your actual credentials:

```bash
nano .env  # or your preferred editor
```

**IMPORTANT**: Never commit `.env` to version control!

### 3. Configure Nginx Auto-Prepend

The application includes an `nginx.conf.snippet` file with the required configuration.

**Installation Steps:**

1. **Add the configuration to your nginx server block**:

   ```bash
   sudo nano /etc/nginx/sites-available/your-site
   ```

2. **Include the snippet** (recommended):

   Add this line inside your `server` block:
   ```nginx
   include /path/to/your/app/nginx.conf.snippet;
   ```

   OR manually add the configuration from `nginx.conf.snippet` to your server block.

3. **Update the PHP-FPM socket path** in the configuration:

   Change this line to match your PHP-FPM socket:
   ```nginx
   fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
   ```

   Common paths:
   - Ubuntu/Debian: `/var/run/php/php7.4-fpm.sock`
   - CentOS/RHEL: `/var/run/php-fpm/www.sock`

4. **Update the bootstrap path**:

   Change the auto_prepend_file path to your actual application path:
   ```nginx
   fastcgi_param PHP_VALUE "auto_prepend_file=/full/path/to/_bootstrap.php";
   ```

5. **Test the configuration**:

   ```bash
   sudo nginx -t
   ```

6. **Reload nginx**:

   ```bash
   sudo systemctl reload nginx
   ```

**Verify it's working:**

Create a test file `test.php`:

```php
<?php
echo "Bootstrap loaded: " . (isset($GLOBALS['Application']) ? "YES" : "NO");
?>
```

Access it through nginx. If you see "Bootstrap loaded: YES", you're all set!

**If auto_prepend doesn't work:**

Configure in `php.ini` or `php-fpm` pool config:

```ini
php_admin_value[auto_prepend_file] = /full/path/to/_bootstrap.php
```

### 4. Set Permissions

```bash
# Make sure the web server can write to the app started flag file
chmod 664 .app_started
chown www-data:www-data .app_started  # Adjust user/group for your server
```

### 5. Force Application_OnStart

To run `Application_OnStart` again (e.g., after deployment):

```bash
# If using APCu:
php -r "apcu_clear_cache();"

# If using file-based:
rm .app_started
```

## Environment Variables

The following environment variables can be configured in `.env`:

### Required

- `APP_ENVIRONMENT` - Environment type: O (Dev), T (Test), A (Accept), P (Prod)

### Email Configuration

- (No mail variables found)


### SSO Configuration

- (No sso variables found)


### API Keys

- (No api variables found)


## Troubleshooting

### Bootstrap not loading

1. Check that `.htaccess` is being read:
   ```bash
   php -i | grep auto_prepend_file
   ```

2. Verify file permissions:
   ```bash
   ls -la _bootstrap.php
   ```

3. Check Apache error log for details

### Application variables not set

1. Verify `Application_OnStart` has run:
   ```bash
   # Check for flag file
   ls -la .app_started

   # Or check APCu
   php -r "var_dump(apcu_fetch('application_started'));"
   ```

2. Force re-initialization:
   ```bash
   rm .app_started
   # Then reload any page
   ```

### Environment variables not loading

1. Verify `.env` file exists and is readable:
   ```bash
   ls -la .env
   ```

2. Check Composer dependencies installed:
   ```bash
   composer install
   ```

3. Check for syntax errors in `.env`:
   ```bash
   cat .env
   ```

## Production Deployment

For production (`APP_ENVIRONMENT=P`):

1. **Disable error display**:
   ```ini
   # In .htaccess or php.ini
   display_errors = 0
   log_errors = 1
   ```

2. **Enable APCu** for better performance:
   ```bash
   # Install APCu
   pecl install apcu

   # Enable in php.ini
   extension=apcu.so
   apc.enabled=1
   ```

3. **Secure .env file**:
   ```bash
   chmod 600 .env
   chown www-data:www-data .env
   ```

4. **Set restrictive file permissions**:
   ```bash
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   ```

## Application Architecture

This converted application uses:

- **`_bootstrap.php`** - Auto-loaded before every request
- **`app.php`** - Non-sensitive application settings ( moved to root)
- **`global.asa.php`** - `Application_OnStart` logic
- **`.env`** - Sensitive credentials (NOT in version control)

### Helper Classes (library/)

The conversion includes helper classes for safe data access:

- **`Application.php`** - Safe access to `$GLOBALS['Application']` variables
  ```php
  // Instead of: $GLOBALS['Application']['key']
  // Use: Application::get('key', 'default_value')
  Application::set('key', 'value');
  Application::has('key');
  ```

- **`Cookie.php`** - Safe access to cookies with proper defaults
  ```php
  // Instead of: $_COOKIE['name'] ?? ''
  // Use: Cookie::get('name', 'default_value')
  Cookie::set('name', 'value', 3600*24*30);
  Cookie::has('name');
  Cookie::delete('name');
  ```

- **`RecordSet.php`** - ADO-compatible database recordset wrapper

These classes prevent "Undefined array key" warnings and provide a cleaner API.

## Support

For issues or questions about the conversion, contact your development team.
