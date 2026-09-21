FROM php:8.2-apache

# Install system packages & PHP build libraries
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Enable Apache modules
RUN a2enmod rewrite remoteip headers

# Configure Apache to respect reverse proxy headers & prevent internal port leakage
RUN echo "UseCanonicalName Off" >> /etc/apache2/apache2.conf && \
    echo "UseCanonicalPhysicalPort Off" >> /etc/apache2/apache2.conf && \
    echo "SetEnvIf X-Forwarded-Proto https HTTPS=on" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy application files
COPY . /var/www/html/

# Install composer dependencies
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader --no-interaction; fi

# Configure PHP settings (upload size, memory limit)
RUN { \
    echo 'upload_max_filesize = 32M'; \
    echo 'post_max_size = 32M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 120'; \
    echo 'date.timezone = Asia/Karachi'; \
} > /usr/local/etc/php/conf.d/custom.ini

# Ensure correct file permissions for web server and make entrypoint executable
RUN mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/images/uploads/event_registrations \
    && mkdir -p /var/www/html/images/uploads/payments \
    && mkdir -p /var/www/html/images/uploads/social_registrations \
    && mkdir -p /var/www/html/images/uploads/receipts \
    && mkdir -p /var/www/html/images/uploads/team \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/images/uploads \
    && chmod +x /var/www/html/entrypoint.sh

EXPOSE 80

CMD ["/var/www/html/entrypoint.sh"]
