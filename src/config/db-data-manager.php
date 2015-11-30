<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mysql Settings
    |--------------------------------------------------------------------------
    |
    | Set the location of the MySQL bin folder that mysqldump resides
    | Default locations that can be used 'windows'|'linux'|'windowsxampp' or set the absolute path
    | windows ->
    | linux ->
    | windowsxampp -> C:\xampp\mysql\bin
    |
    */

    'mysqlbinloc' => 'windowsxampp',


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
        'compress'      => false,
        'keeplastonly'  => true,
        'filesystem'    => 'local',
        'location'      => "/backups",
        'useExtendedInsert' => false,
        'timeoutInSeconds'  => 60
    ],

];
