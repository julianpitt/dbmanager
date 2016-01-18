# Laravel Database Manager

A highly configurable database backup and restore solution for laravel 5 projects
## Install

Via Composer

``` bash
$ composer require julianpitt/dbmanager
```

Then publish the config after you update composer

``` bash
$ php artisan config:publish julianpitt/dbmanager
```


## Usage

###From the project directory

For backups
``` bash
php artisan dbman:backup
```

For restores ( currently in production )
``` bash
php artisan dbman:restore
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email julian.pittas@gmail.com instead of using the issue tracker.

## Credits

- Julian Pittas

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information