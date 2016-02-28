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

    /**
     * Performs a backup and changes the default config with the config array passed in
     *
     * @param null $options
     * @return bool
     */
    public function backup($options = null)
    {
        if(is_array($options) && !empty($options)) {
            $this->backupClass->setOptions($options);
        }
        return $this->backupClass->backup($options);
    }

    public function getConfig()
    {
        return $this->backupClass->getConfig();
    }

}
