# PHP 8.2 versiyasini Apache bilan birga ishlatamiz
FROM php:8.2-apache

# Tizim uchun zarur kutubxonalarni o'rnatish
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip curl json

# Apache modulini yoqish (URL-larni chiroyli qilish uchun)
RUN a2enmod rewrite

# Ishchi katalogni belgilash
WORKDIR /var/www/html

# Loyiha fayllarini konteynerga nusxalash
COPY . .

# Composer o'rnatish (agar kerak bo'lsa)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Ma'lumotlar saqlanadigan papkaga ruxsat berish (Muhim!)
RUN mkdir -p data/users && chmod -R 777 data

# Apache portini sozlash (Render uchun)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Apache serverini ishga tushirish
CMD ["apache2-foreground"]
