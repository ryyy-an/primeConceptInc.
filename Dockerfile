# Production-ready Apache Environment (Build Trigger: 2026-04-17-04-METAL-ULTRA)
FROM php:8.2-apache

# 1. Install all system dependencies, Node.js, and PHP extensions in one optimized layer
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo pdo_mysql opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Enable Apache modules for routing and performance
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_worker.load || true && \
    a2enmod mpm_prefork rewrite headers deflate

# 3. Configure PHP (Production Settings + OPcache)
COPY PisOfficial/php-prod.ini /usr/local/etc/php/conf.d/php-prod.ini

# 5. Set working directory
WORKDIR /var/www/html

# 6. Copy package files first for better caching
COPY PisOfficial/package*.json ./
RUN npm install

# 7. Copy the rest of the application
COPY PisOfficial/ .

# 7.5 Create a backup of images to populate Volume on startup
RUN cp -r public/assets/img/furnitures /var/www/html/img_backup

# 8. Build Tailwind CSS
RUN npm run build

# 9. Final setup: Copy entrypoint script and fix permissions
COPY PisOfficial/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
RUN chown -R www-data:www-data /var/www/html

# Use our custom entrypoint but keep the standard command
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
