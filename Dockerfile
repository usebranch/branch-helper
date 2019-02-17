FROM wordpress:php7.0-apache

COPY branch-helper.php /var/www/html/wp-content/plugins/branch-helper/branch-helper.php

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp && \
    wp --info
