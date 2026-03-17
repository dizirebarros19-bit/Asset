FROM php:8.2-apache

# Install dependencies for Composer and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql

# Copy all your files into the web server directory
COPY . /var/www/html/

# Make sure the web server owns the files
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
