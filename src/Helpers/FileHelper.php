<?php namespace JulianPitt\DBManager\Helpers;

use Carbon\Carbon;
use JulianPitt\DBManager\Databases\MySQLDatabase;

class FileHelper
{

    public static function getOutputFileType()
    {

        $compress = config('db-manager.output.compress');

        if (!isset($compress) || (isset($compress) && !is_bool($compress)) || (isset($compress) && is_bool($compress) && $compress)) {
            return ".zip";
        }

        return "." . MySQLDatabase::getFileExtension();

    }

    public static function prependSignature($filename)
    {
        //Get Current Date
        $now = Carbon::now();

        //Get Backup Type
        $type = config('db-manager.output.backupType');

        if (empty($type)) {
            $backupType = "Data and Structure";
        }

        if ($type == "dataonly") {
            $backupType = "Data Only";
        } else if ($type == "structureonly") {
            $backupType = "Structure Only";
        }

        $backupType = "Data and Structure";

        //Get Database Backed Up
        $databaseBackedUp = config("database.connections.mysql.database");

        //Get Tables Backed Up
        $tablesBackedUp = config("db-manager.output.tables");
        $tablesBackedUp = config("db-manager.tables.".$tablesBackedUp);
        $tablesBackedUp = implode(", ", $tablesBackedUp);

        $string = <<<EOT
/*

Backup created with JulianPitt DBManager

Created on: $now
Backup type: $backupType
Database Backed Up: $databaseBackedUp
Tables Backed Up: $tablesBackedUp

*/
EOT;

        $context = stream_context_create();
        $fp = fopen($filename, 'r', 1, $context);
        $tmpname = md5($string);
        file_put_contents($tmpname, $string);
        file_put_contents($tmpname, $fp, FILE_APPEND);
        fclose($fp);
        unlink($filename);
        rename($tmpname, $filename);
    }

}