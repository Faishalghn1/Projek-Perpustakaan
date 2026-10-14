FROM php:8.2-apache

# Pasang ekstensi PHP untuk koneksi ke MariaDB/MySQL
RUN docker-php-ext-install mysqli pdo_mysql

# Aktifkan mod_rewrite Apache (untuk URL yang rapi)
RUN a2enmod rewrite

# Salin konfigurasi PHP kustom (opsional)
# COPY php.ini /usr/local/etc/php/

WORKDIR /var/www/html