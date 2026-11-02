FROM php:8.2-apache
RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql && a2enmod rewrite
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/html
COPY composer.json .
RUN composer install --no-dev --no-interaction
COPY . .
COPY docker/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/apache-app.conf /etc/apache2/conf-available/app.conf
RUN a2enconf app \
    && mkdir -p public/uploads/avatars storage/attachments \
    && chown -R www-data:www-data public/uploads storage
