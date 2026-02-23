FROM php:8.2-apache
WORKDIR /var/www/html
COPY . /var/www/html
RUN a2enmod rewrite
EXPOSE 80
CMD ["apache2-foreground"]
