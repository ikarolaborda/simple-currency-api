FROM php:8.4-fpm

LABEL maintainer="Ikaro C. Laborda <iclaborda@msn.com>"

RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev zip \
    && docker-php-ext-install intl opcache pdo pdo_mysql \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

WORKDIR /var/www/currency-api

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY --chmod=755 entrypoint.sh /usr/local/bin/

#Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

RUN mkdir -p -m 777 /tmp
RUN umask 000 && touch /tmp/xdebug.log && chmod 777 /tmp/xdebug.log

ENTRYPOINT ["entrypoint.sh"]