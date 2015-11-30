<?php

namespace JulianPitt\DBManager;

class DBManager
{
    /**
     * Create a new DBDataManager Instance
     */
    public function __construct()
    {
        // constructor body
        new Commands\BackupCommand();
    }

}
