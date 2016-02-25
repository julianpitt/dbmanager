# Laravel Database Manager

A highly configurable database backup and restore solution for laravel 5 projects
## Install

Via Composer

``` bash
$ composer require julianpitt/dbmanager
```

Add the service provider to your config/app.php file's provider array

``` php
...
    'JulianPitt\DBManager\DBManagerServiceProvider',
...
```

Then publish the config after you update composer

``` bash
$ php artisan vendor:publish --provider="JulianPitt\DBManager\DBManagerServiceProvider" --tag="public"
```



## Usage

###Console

For backups
``` bash
php artisan dbman:backup
```

For restores ( currently in development )
``` bash
php artisan dbman:restore
```


###File

``` php
DBManager::hasPermission( {filesystem name} );
DBManager::backup();
DBManager::backup( {options array} );
```



##Config

### Backup
#### Defaults
```
prefix              => 'datetime'
suffix              => ''
filename            => '-db-manager'
compress            => true
keeplastonly        => false
filesystem          => "local"
location            => "/backups/"
useExtendedInsert   => true
timeoutInSeconds    => 60
tables              => ""
backupType          => "dataandstructure"
'individualFiles'   => env('DBMAN_OUTPUT_INDIVIDUAL', false),
'checkPermissions'  => env('DBMAN_CHECK_PERMISSIONS', true),
'failsafeEnabled'   =>  env('DBMAN_OUTPUT_FAILSAFE', true),
'failsafe'          => [
		'location'          => '/dbmanager/',
		'filesystem'        => 'local'
    ]
```


#### Options
``` bash
  -p    --prefix=PREFIX                      The name of the file will get prefixed with this string.
  -s    --suffix=SUFFIX                      The name of the file will get suffixed with this string.
  -f    --filename=FILENAME                  The name of the file to output.
  -t    --type=TYPE                          The type of dump to perform on the database ("datanadstructure/dataonly/structureonly)
  -k    --keeplastonly=KEEPLASTONLY          Keep the last backup or delete all previous backups (true/false)
  -c    --compress=COMPRESS                  Compress the output file to .zip (true/false)
  -d    --checkPermissions=CHECKPERMISSIONS  Enable an initial check to see if the backup will run correctly [default: true]
  -b    --failsafeEnabled=FAILSAFEENABLED    Save a full backup in the failsafe location when performing a backup on some tables only [default: true]
  -i    --individual=INDIVIDUAL              Save each table to an individual file [default: false]
```


#### .env
The following values can be changed inside your .env file
``` bash
 DBMAN_OUTPUT_COMPRESS      - compress (boolean)[true|false]
 DBMAN_OUTPUT_KEEPLASTONLY  - keeplastonly (boolean)[true|false]
 DBMAN_OUTPUT_FILESYSTEM    - filesystem (string or array) e.g ['local','aws'] or 'local'
 DBMAN_OUTPUT_LOCATION      - location (string) e.g '/backups/'
 DBMAN_OUTPUT_TABLES        - tables (string) e.g 'laravel' or '' for the whole database
 DBMAN_OUTPUT_BACKUPTYPE    - backupType (string) [dataonly|structureonly|dataandstructure]
 DBMAN_CHECK_PERMISSIONS    - checkPermissions (boolean)[true|false]
 DBMAN_OUTPUT_FAILSAFE      - failsafeEnabled (boolean)[true|false]
 DBMAN_OUTPUT_INDIVIDUAL    - individualFiles (boolean)[true|false]
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