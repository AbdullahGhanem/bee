# Bee

You can install the package via composer:

```bash
composer require ghanem/bee
```

now you need to publish the config file with:
```bash
php artisan vendor:publish --provider="Ghanem\Bee\BeeServiceProvider" --tag="config"
```

###### Parameters
after get api key and account key As shown in the following link:
https://apidocs.bee.com/#access

### Endpoints

+ [Rentels](#rentels)
	+ [Get Rentals](#get-rentals)

### Rentels

#### Get Rentals

https://apidocs.bee.com/#d3b1bef3-f5bc-9a0b-d2f4-99a92ec2dbbc

Method: Bee::getRentals() 

```php
$rentals = Bee::getRentals();

foreach ($rentals as $rental) {
    // Get email
	$bee['id'];
}
```