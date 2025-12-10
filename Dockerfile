FROM php:8.2-apache

# Use the default Apache document root (/var/www/html)

# Install system dependencies and PHP extensions (curl, mbstring)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        zip \
        unzip \
    && docker-php-ext-install mbstring curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# No custom document root configured — use default /var/www/html

# Copy application files
WORKDIR /var/www/html
COPY . /var/www/html

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
