<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mysql Settings
    |--------------------------------------------------------------------------
    |
    | Set the location of the MySQL bin folder that mysqldump resides
    | Set the absolute path
    | windows MySQL 5.0 -> C:\Program Files\MySQL\(mysqlversion)\
    | linux -> /var/lib/mysql/
    | linux -> /usr/bin/
    | windows xampp -> C:\xampp\mysql\bin\
    | windows wamp -> C:\wamp\bin\mysql\(mysqlversion)\bin\
    |
    */

    'mysqlbinloc'      => env('DBMAN_MYSQLBINLOC', "/var/lib/mysql/"),


    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Set the output file settings for prefix, suffix. can use 'datetime' to add the current datetime
    | Location is specified in the location config
    | Compress uses zip compression
    | datetime appends the datetime to the filename
    | Specify which tables to back up in the tables array or leave blank for full database backup
    | backupType can be dataonly|structureonly|dataandstructure
    |
    */
    'output' => [
        'prefix'        => 'datetime',
        'suffix'        => '',
        'filename'      => '-db-manager',
        'compress'      => env('DBMAN_OUTPUT_COMPRESS', true),
        'keeplastonly'  => env('DBMAN_OUTPUT_KEEPLASTONLY', false),
        'filesystem'    => env('DBMAN_OUTPUT_FILESYSTEM', "local"),
        'location'      => env('DBMAN_OUTPUT_LOCATION', "/backups/"),
        'useExtendedInsert' => true,
        'timeoutInSeconds'  => 60,
        'tables'        => env('DBMAN_OUTPUT_TABLES', "laravel"),
        'backupType'    => env('DBMAN_OUTPUT_BACKUPTYPE', "dataandstructure"),
        'checkPermissions' => env('DBMAN_CHECK_PERMISSIONS', true),
    ],

    'tables' => [
        'october' => [
            'users',
            'system'
        ],
        'laravel' => [
            'users',
            'migrations',
            'password_resets',
        ]
    ],

    'input' => [
        'sameAsOutput'  => env('DBMAN_INPUT_SAMEASOUTPUT', true),
        'filesystem'    => env('DBMAN_INPUT_FILESYSTEM', "local"),
        'location'      => env('DBMAN_INPUT_LOCATION', "/backups"),
    ]

];
