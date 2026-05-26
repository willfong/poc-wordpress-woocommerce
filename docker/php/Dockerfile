ARG WORDPRESS_VERSION=6.9.4
ARG PHP_VERSION=8.3
FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip less default-mysql-client msmtp; \
    rm -rf /var/lib/apt/lists/*
