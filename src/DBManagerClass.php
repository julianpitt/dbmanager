<?php

namespace JulianPitt\DBManager;

use JulianPitt\DBManager\Commands\BackupCommands;
use JulianPitt\DBManager\Commands\RestoreCommands;
use JulianPitt\DBManager\Helpers\BackupHelper;

class DBManagerClass
{
    /**
     * Create a new DBManagerClass Instance
     */

    public function __construct()
    {
        // constructor body
        $this->backupClass = new BackupHelper();
    }

    public function hasPermission()
    {
        return $this->backupClass->checkIfUserHasPermissions('local');
    }

}
