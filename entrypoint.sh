#!/bin/sh
set -e

# Default PORT to 80 if not set by Render
PORT="${PORT:-80}"

# Configure Apache to listen on the exact port
sed -i "s/Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost \*:$PORT>/" /etc/apache2/sites-available/000-default.conf

# Configure VirtualHost & Apache core to never leak internal port or protocol
if ! grep -q "UseCanonicalPhysicalPort" /etc/apache2/sites-available/000-default.conf 2>/dev/null; then
    sed -i "/<VirtualHost \*:$PORT>/a \    UseCanonicalName Off\n    UseCanonicalPhysicalPort Off" /etc/apache2/sites-available/000-default.conf
fi

if ! grep -q "UseCanonicalPhysicalPort" /etc/apache2/apache2.conf 2>/dev/null; then
    echo "UseCanonicalPhysicalPort Off" >> /etc/apache2/apache2.conf
    echo "UseCanonicalName Off" >> /etc/apache2/apache2.conf
fi

# Enable reverse-proxy SSL detection and client IP forwarding
cat << 'EOF' > /etc/apache2/conf-available/reverse-proxy.conf
<IfModule mod_remoteip.c>
    RemoteIPHeader X-Forwarded-For
    RemoteIPInternalProxy 10.0.0.0/8
    RemoteIPInternalProxy 172.16.0.0/12
    RemoteIPInternalProxy 192.168.0.0/16
</IfModule>
<IfModule mod_setenvif.c>
    SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on
</IfModule>
EOF
a2enconf reverse-proxy 2>/dev/null || true

# Clean any existing pid
rm -f /var/run/apache2/apache2.pid

# Export container environment variables into .env so env_loader.php can always read them
printenv | grep -E '^(DB_|SMTP_|APP_|ADMIN_|CLIENT_|EVENT_|SOCIAL_|JWT_|FROM_|IMPERSONATE_|RECAPTCHA_|RESEND_)' > /var/www/html/.env || true
chown www-data:www-data /var/www/html/.env || true
chmod 640 /var/www/html/.env || true

# Also export them into /etc/apache2/envvars for Apache worker processes
printenv | grep -E '^(DB_|SMTP_|APP_|ADMIN_|CLIENT_|EVENT_|SOCIAL_|JWT_|FROM_|IMPERSONATE_|RECAPTCHA_|RESEND_)' | while IFS= read -r line; do
    echo "export $line" >> /etc/apache2/envvars
done || true

exec apache2-foreground
