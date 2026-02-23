FROM php:8.2-apache
WORKDIR /var/www/html
COPY . /var/www/html
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
EXPOSE 80
CMD ["apache2-foreground"]
