#!/bin/sh
set -e

# Default PORT to 80 if not set by Render
PORT="${PORT:-80}"

# Configure Apache to listen on the exact port
sed -i "s/Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost \*:$PORT>/" /etc/apache2/sites-available/000-default.conf

# Clean any existing pid
rm -f /var/run/apache2/apache2.pid

exec apache2-foreground
