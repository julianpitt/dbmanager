<?php

namespace JulianPitt\DBManager\Interfaces;

interface DatabaseInterface
{

    public function dumpAll($destinationFile);
    public function dumpTables($destinationFile, $tables);
    public function checkBackupIntegrity($callIngClass);
    public function checkRestoreIntegrity($callIngClass);
    public static function getFileExtension();

}