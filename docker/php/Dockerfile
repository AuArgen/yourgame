FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmagickwand-dev \
    unzip \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql gd

# Install Imagick extension from PECL
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Set working directory
WORKDIR /var/www/html

# Expose port 9000
EXPOSE 9000

CMD ["php-fpm"]
