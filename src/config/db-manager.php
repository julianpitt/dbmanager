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

    'mysqlloc'      => '/usr/bin/',

    'mysqlbinloc'   => '/usr/bin/',


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
        'compress'      => false,
        'keeplastonly'  => true,
        'filesystem'    => 'local',
        'location'      => "/backups",
        'useExtendedInsert' => false,
        'timeoutInSeconds'  => 60,
        'tables'        => 'laravel',
        'backupType'    => 'structureonly'
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
            'doesnt_exist'
        ]
    ]

];
