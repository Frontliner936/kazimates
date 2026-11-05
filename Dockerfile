FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev nginx gettext-base supervisor \
    && docker-php-ext-install pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/* \
    && sed -ri 's/^;?clear_env = .*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

RUN printf 'upload_max_filesize=6M\npost_max_size=7M\nmax_file_uploads=1\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html
COPY nginx.conf.template /etc/nginx/nginx.conf.template
COPY supervisord.conf /etc/supervisor/conf.d/kazimates.conf
COPY railway-start.sh /usr/local/bin/railway-start
RUN chmod +x /usr/local/bin/railway-start

ENV PORT=8080
EXPOSE 8080
CMD ["/usr/local/bin/railway-start"]
