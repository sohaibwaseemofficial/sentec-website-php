#!/bin/sh
set -e

# Default PORT to 80 if not set by Render
PORT="${PORT:-80}"

# Configure Apache to listen on the exact port
sed -i "s/Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost \*:$PORT>/" /etc/apache2/sites-available/000-default.conf

# Clean any existing pid
rm -f /var/run/apache2/apache2.pid

# Export container environment variables into .env so env_loader.php can always read them
printenv | grep -E '^(DB_|SMTP_|APP_|ADMIN_|CLIENT_|EVENT_|SOCIAL_|JWT_|FROM_|IMPERSONATE_|RECAPTCHA_)' > /var/www/html/.env || true
chown www-data:www-data /var/www/html/.env || true
chmod 640 /var/www/html/.env || true

# Also export them into /etc/apache2/envvars for Apache worker processes
printenv | grep -E '^(DB_|SMTP_|APP_|ADMIN_|CLIENT_|EVENT_|SOCIAL_|JWT_|FROM_|IMPERSONATE_|RECAPTCHA_)' | while IFS= read -r line; do
    echo "export $line" >> /etc/apache2/envvars
done || true

exec apache2-foreground
