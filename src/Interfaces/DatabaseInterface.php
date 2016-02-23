<?php

namespace JulianPitt\DBManager\Interfaces;

interface DatabaseInterface
{

    public function dumpAll($destinationFile);
    public function dumpTables($destinationFile, $tables);
    public function checkBackupIntegrity($callIngClass, $tables);
    public function checkRestoreIntegrity($callIngClass, $tables);
    public static function getFileExtension();

}