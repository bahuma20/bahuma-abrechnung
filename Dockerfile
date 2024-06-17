FROM php:8.2-apache
COPY . /var/www/html
RUN apt-get update && apt-get install -y unzip
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install
EXPOSE 80
