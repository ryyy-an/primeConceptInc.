# Production-ready Apache Environment (Build Trigger: 2026-04-17-01)
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
COPY PisOfficial/package*.json ./
RUN npm install

# 7. Copy the rest of the application
COPY PisOfficial/ .

# 8. Build Tailwind CSS
RUN npm run build

# 9. Final setup: Copy entrypoint script and fix permissions
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
RUN chown -R www-data:www-data /var/www/html

# Use our custom entrypoint but keep the standard command
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
