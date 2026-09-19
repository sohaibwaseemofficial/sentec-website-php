# Environment Variables Setup

This project uses environment variables to store sensitive configuration data like database credentials and API keys.

## Setup Instructions

### 1. Create your `.env` file

Copy the example file to create your own `.env` file:

```bash
cp .env.example .env
```

### 2. Update the `.env` file

Edit the `.env` file and replace the placeholder values with your actual credentials:

```env
# Database Configuration
DB_HOST=localhost
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
DB_NAME=sentec

# Google reCAPTCHA Configuration
RECAPTCHA_SITE_KEY=your_actual_site_key
RECAPTCHA_SECRET_KEY=your_actual_secret_key

# Email Configuration
ADMIN_EMAIL=your-email@sentec.live
FROM_EMAIL=no-reply@sentec.live
FROM_NAME=SENTEC
```

### 3. Security Notes

- **NEVER commit the `.env` file to Git** - it contains sensitive information
- The `.env` file is already added to `.gitignore` to prevent accidental commits
- Always use `.env.example` as a template for others (without real credentials)
- Keep your `.env` file secure and only on your local/server environment

## Getting reCAPTCHA Keys

1. Go to [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin/create)
2. Register your site
3. Choose reCAPTCHA v2 ("I'm not a robot" Checkbox)
4. Add your domain (e.g., sentec.live)
5. Copy the Site Key and Secret Key to your `.env` file

## How It Works

The project uses a custom `env_loader.php` file that:
- Loads variables from `.env` file
- Makes them available via `env()` helper function
- Provides fallback default values

All sensitive configuration is now loaded from environment variables instead of being hardcoded in PHP files.
