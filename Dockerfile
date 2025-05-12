# Use the official Laravel Sail image as a parent image
FROM laravelsail/php81-composer:latest

# Install necessary dependencies and PostgreSQL driver
RUN apt-get update \
    && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql
