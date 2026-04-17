# Use PHP 8.2 CLI as base
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Set working directory
WORKDIR /app

# Copy package files first for better caching
COPY package*.json ./

# Install npm dependencies
RUN npm install

# Copy the rest of the application
COPY . .

# Build Tailwind CSS
RUN npm run build

# Expose the port Railway provides
EXPOSE 8000

# Start the PHP built-in server using the router
CMD ["php", "-S", "0.0.0.0:8000", "router.php"]
