<?php

namespace JulianPitt\DBManager;

class LaravelMySqlBackup
{
    /**
     * Create a new LaravelMySqlBackup Instance
     */
    public function __construct()
    {
        // constructor body
        new Commands\BackupCommand();
    }

}
