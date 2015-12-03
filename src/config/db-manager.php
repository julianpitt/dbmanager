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
    | windows xampp -> C:\xampp\mysql\bin\
    | windows wamp -> C:\wamp\bin\mysql\(mysqlversion)\bin\
    |
    */

    'mysqlloc'      => 'C:\xampp\mysql\bin\\',

    'mysqlbinloc'   => 'C:\xampp\mysql\bin\\',


    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Set the output file settings for prefix, suffix. can use 'datetime' to add the current datetime
    | Location starts from Storage/app/
    | Compress uses zip compression
    | datetime appends the datetime to the filename
    |
    */
    'output' => [
        'prefix'        => 'datetime',
        'suffix'        => '',
        'filename'      => '-db-manager',
        'compress'      => false,
        'keeplastonly'  => true,
        'filesystem'    => 'local',
        'location'      => "/backups",
        'useExtendedInsert' => false,
        'timeoutInSeconds'  => 60,
        'tables'        => 'october',
    ],

    'tables' => [
        'october' => [
            'users',
            'system'
        ],
        'laravel' => [
            'users',
            'migrations',
            'password_resets'
        ]
    ]

];
