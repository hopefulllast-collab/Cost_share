# Use the official PHP 8.2 image with Apache
FROM php:8.2-apache

# Install PDO MySQL extension and update packages
RUN apt-get update && apt-get install -y \
    libonig-dev \
    curl \
    unzip \
    ca-certificates \
    && docker-php-ext-install pdo pdo_mysql mbstring

# Enable Apache mod_rewrite for .htaccess files
RUN a2enmod rewrite

# Update CA Certificates to ensure secure connections to cloud databases like TiDB
RUN update-ca-certificates

# Copy all application files to the Apache document root
COPY . /var/www/html/

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 (default for Apache and Render)
EXPOSE 80
