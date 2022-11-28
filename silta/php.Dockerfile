# Dockerfile for the PHP container.
FROM wunderio/silta-php-fpm:8.1-fpm-v0.1

COPY --chown=www-data:www-data . /app
