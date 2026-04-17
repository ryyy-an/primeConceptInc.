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
RUN a2enmod rewrite headers deflate

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

# 9. Configure Apache to listen on Railway's dynamic $PORT
# Railway provides a $PORT environment variable. Apache defaults to 80.
# We modify Apache to listen on $PORT and use it for the VirtualHost.
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 10. Fix permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# The port is dynamic, but Railway expects 80 by default unless told otherwise.
# In Railway Dashboard, ensure the PORT variable is used or set to 80.
# This sed command ensures Apache dynamically adapts to Railway.

CMD ["apache2-foreground"]
