FROM php:8.0-apache


RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*


RUN docker-php-ext-install pdo_mysql mysqli mbstring zip gd


RUN a2enmod rewrite


COPY ai_standalone/ /var/www/html/


RUN mkdir -p /var/www/html/uploads/syllabuses /var/www/html/uploads/converted \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
