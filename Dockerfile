FROM php:8.2-apache

# Instalamos el driver de MySQL y habilitamos mod_rewrite
RUN docker-php-ext-install pdo_mysql mysqli
RUN a2enmod rewrite

# Copiamos el proyecto
COPY . /var/www/html/

# 🔧 SOLUCIÓN AL ERROR DE PERMISOS: Dar propiedad al usuario de Apache (www-data)
RUN mkdir -p /var/www/html/comprobantes && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/comprobantes

# Exponemos el puerto 80 (Railway se encarga de redirigir el tráfico)
EXPOSE 80

# Forzar mpm_prefork en el arranque (el fix anterior que ya te funcionó)
CMD ["bash", "-lc", "set -eux; a2dismod mpm_event mpm_worker || true; rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* || true; a2enmod mpm_prefork; apache2ctl -t; exec apache2-foreground"]