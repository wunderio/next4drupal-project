# Dockerfile for the Shell container.
FROM wunderio/silta-php-shell:php8.1-v0.1

COPY --chown=www-data:www-data . /app
