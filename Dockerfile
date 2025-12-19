# PHP 8.2 Apache bazasida
FROM php:8.2-apache

# Tizim paketlarini yangilash va faqat kerakli kutubxonalarni o'rnatish
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP modullarini o'rnatish
# Eslatma: json va curl PHP 8.x da o'rnatilgan bo'ladi, faqat zip ni o'rnatamiz
RUN docker-php-ext-install zip

# Apache modullarini yoqish
RUN a2enmod rewrite

# Ishchi katalogni sozlash
WORKDIR /var/www/html

# Fayllarni nusxalash
COPY . .

# Composer o'rnatish
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Ma'lumotlar papkasini yaratish va ruxsat berish
RUN mkdir -p data/users && chmod -R 777 data

# Render portini sozlash
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

CMD ["apache2-foreground"]
