# Use the official PHP image with Composer
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock /var/www/

# Install Laravel (composer) dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-ansi --no-progress \
    || { echo 'Composer install failed'; exit 1; }

# Copy the rest of the application files
COPY . /var/www

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 0755 /var/www/storage /var/www/bootstrap/cache

# IMPORTANT: Do NOT run php artisan key:generate here.
# We expect APP_KEY to be provided at runtime via environment variables (Render).
# If APP_KEY is not provided at runtime, Laravel will still boot but encryption may fail for sessions/encrypted data.

# Final permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Start Laravel built-in server (for simple deployments)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
