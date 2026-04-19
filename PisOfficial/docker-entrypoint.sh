#!/bin/bash
# Build Tag: 2026-04-17-02
set -e

# 1. FORCE DISABLE conflicting Apache MPM modules at RUNTIME
# This is the most robust way to solve AH00534 in stubborn environments.
echo "Cleaning up Apache MPM modules..."
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf || true
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf || true

# 2. ENSURE only mpm_prefork is active
echo "Enabling mpm_prefork..."
a2enmod mpm_prefork || true

# 3. DYNAMIC PORT MAPPING (Robust Regex Version)
# Inayusan natin ito para sigurado kahit may extra spaces o ibang port ang base image.
echo "Setting Apache to listen on port ${PORT:-80}..."
sed -i "s/Listen [0-9]*/Listen ${PORT:-80}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT:-80}>/g" /etc/apache2/sites-available/000-default.conf

# 4. SYNC IMAGES TO VOLUME
# If a volume is mounted, it might be empty. We copy stock images back.
# -n (no-clobber) ensures we don't mess with new uploads.
echo "Synchronizing images to volume..."
mkdir -p /var/www/html/public/assets/img/furnitures
cp -rn /var/www/html/img_backup/* /var/www/html/public/assets/img/furnitures/ || true
chown -R www-data:www-data /var/www/html/public/assets/img/furnitures
chmod -R 777 /var/www/html/public/assets/img/furnitures

# 4. Start Apache in the foreground
echo "Starting Apache..."
exec apache2-foreground
