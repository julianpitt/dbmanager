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
    private $backupClass;

    public function __construct()
    {
        // constructor body
        $this->backupClass = new BackupHelper();

    }

    public function hasPermission()
    {
        $result = $this->backupClass->checkIfUserHasPermissions('local');
        $this->backupClass->cleanUpTemporaryFiles();
        return $result;
    }

    public function backup()
    {
        $this->backupClass->backup();
    }

}
