FROM php:8.3-apache

# Dependencias de sistema necesarias para las extensiones PHP y Composer.
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP requeridas por la app (ver app/Services/Forgery/* usa GD).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: puerto propio del contenedor (mapeado a 80 del host vía "80:83")
# y mod_rewrite para las rutas "bonitas" de Laravel.
RUN a2enmod rewrite headers
COPY docker/app/ports.conf /etc/apache2/ports.conf
COPY docker/app/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 83

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
