# Production-ready Apache Environment for Prime-In-Sync
FROM php:8.2-apache

# 1. Install system dependencies & PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql opcache

# 2. Enable Apache modules for routing and performance
# More aggressive fix for "More than one MPM loaded" error
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_worker.load || true && \
    a2enmod mpm_prefork rewrite headers deflate

# 3. Configure PHP (Production Settings + OPcache)
COPY php-prod.ini /usr/local/etc/php/conf.d/php-prod.ini

# 4. Install Node.js 20 (For Tailwind build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# 5. Set working directory
WORKDIR /var/www/html

# 6. Copy package files first for better caching
COPY package*.json ./
RUN npm install

# 7. Copy the rest of the application
COPY . .

# 8. Build Tailwind CSS
RUN npm run build

# 9. Fix permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# 10. Configure Apache to listen on $PORT at RUNTIME
# We use a custom entrypoint command to ensure $PORT is correctly mapped
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Use the standard start command but Apache will now pick up the $PORT env var
CMD ["apache2-foreground"]
