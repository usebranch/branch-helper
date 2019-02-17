# Branch Helper plugin

### Install WordPress and activate plugin

```bash
./vessel exec wordpress wp core install --url=localhost --title="Branch Helper Test" --admin_user=admin --admin_password=123 --admin_email=info@example.com --allow-root

./vessel exec wordpress wp plugin activate branch-helper --allow-root
```

### Run Composer

```bash
docker-compose run --rm composer require --dev phpunit/phpunit
```

### Run tests

```bash
docker-compose exec wordpress \
    wp-content/plugins/branch-helper/vendor/bin/phpunit \
    --configuration wp-content/plugins/branch-helper/phpunit.xml
```

### If permissions are messed up

```bash
docker-compose exec wordpress chown -R www-data:www-data /var/www
```
