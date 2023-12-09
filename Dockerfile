FROM php:7.2

RUN apt-get update
RUN apt-get install -y zlib1g-dev zip unzip libpng-dev
RUN docker-php-ext-install gd zip pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG PUID
ARG PGID

WORKDIR /app
