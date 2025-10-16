# --------------------------------------------------------
# 1. Use official PHP image with Composer preinstalled
# --------------------------------------------------------
FROM php:8.2-cli

# --------------------------------------------------------
# 2. Install system dependencies
# --------------------------------------------------------
RUN apt-get update && apt-get install -y \
    zip unzip git curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# --------------------------------------------------------
# 3. Install Composer
# --------------------------------------------------------
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# --------------------------------------------------------
# 4. Set working directory
# --------------------------------------------------------
WORKDIR /var/www/html

# --------------------------------------------------------
# 5. Copy all files to the container
# --------------------------------------------------------
COPY . .

# --------------------------------------------------------
# 6. Install Laravel dependencies
# --------------------------------------------------------
RUN composer install --no-dev --optimize-autoloader

# --------------------------------------------------------
# 7. Generate Laravel key (optional if you already have APP_KEY in Render)
# --------------------------------------------------------
RUN php artisan key:generate || true

# --------------------------------------------------------
# 8. Expose port 8000
# --------------------------------------------------------
EXPOSE 8000

# --------------------------------------------------------
# 9. Run Laravel server when container starts
# --------------------------------------------------------
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
