FROM php:7.1-apache
WORKDIR /var/www/html
RUN a2enmod rewrite

COPY src/ .
VOLUME /var/www/html
EXPOSE 80:80