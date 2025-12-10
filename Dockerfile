FROM php:8.2-apache

# Serve the application from /var/www/html/src (user requested `/src` as docroot)
ENV APACHE_DOCUMENT_ROOT /var/www/html/src

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

# Configure Apache to use the new document root
RUN sed -ri -e 's!/var/www/html!/var/www/html/src!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!<Directory /var/www/html>!<Directory /var/www/html/src>!g' /etc/apache2/apache2.conf

# Copy application files
WORKDIR /var/www/html
COPY ./src/ /var/www/html

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
