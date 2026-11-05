FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

# mod_php requires prefork; disable other Apache MPMs to avoid startup conflicts.
RUN if [ -e /etc/apache2/mods-enabled/mpm_event.load ]; then a2dismod mpm_event; fi \
    && if [ -e /etc/apache2/mods-enabled/mpm_worker.load ]; then a2dismod mpm_worker; fi \
    && a2enmod mpm_prefork

WORKDIR /var/www/html
COPY . /var/www/html
COPY railway-start.sh /usr/local/bin/railway-start
RUN chmod +x /usr/local/bin/railway-start \
    && chown -R www-data:www-data /var/www/html

ENV PORT=8080
EXPOSE 8080
CMD ["/usr/local/bin/railway-start"]
