FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql mbstring

WORKDIR /var/www/html
COPY public/ /var/www/html/

COPY start.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

CMD ["/usr/local/bin/start-app"]
