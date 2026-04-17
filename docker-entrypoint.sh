#!/bin/bash
set -e

# 1. FORCE DISABLE conflicting Apache MPM modules at RUNTIME
# This is the most robust way to solve AH00534 in stubborn environments.
echo "Cleaning up Apache MPM modules..."
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf || true
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf || true

# 2. ENSURE only mpm_prefork is active
echo "Enabling mpm_prefork..."
a2enmod mpm_prefork || true

# 3. DYNAMIC PORT MAPPING
# Railway provides $PORT. Apache expects 80 by default.
echo "Setting Apache to listen on port ${PORT:-80}..."
sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/g" /etc/apache2/sites-available/000-default.conf

# 4. Start Apache in the foreground
echo "Starting Apache..."
exec apache2-foreground
