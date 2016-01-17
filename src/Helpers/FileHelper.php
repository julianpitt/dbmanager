<?php namespace JulianPitt\DBManager\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Databases\MySQLDatabase;

abstract class FileHelper
{

    public function getOutputFileType()
    {
        $compress = config('db-manager.output.compress');

        if (!isset($compress) || (isset($compress) && !is_bool($compress)) || (isset($compress) && is_bool($compress) && $compress)) {
            return ".zip";
        }

        return "." . MySQLDatabase::getFileExtension();
    }

    public function prependSignature($filename)
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

        //Get Signature code
        $code = $this->getSignatureCode($type, $databaseBackedUp);

        $string = <<<EOT
/*
CODE($code)

Backup created with JulianPitt DBManager, please do not modify signature

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


    /**
     * Generates a 3 part resotre code to help this package identify the backup type
     *
     * @return string
     */
    public function getSignatureCode($type, $database)
    {
        $code = "";
        $separator = "|";

        if ($type == "dataonly") {
            $code = "data";
        } else if ($type == "structureonly") {
            $code = "structure";
        } else {
            $code = "both";
        }

        $code.=$separator.$database;


        return $code;
    }

    /**
     * Copies a file using a a disk driver, file handler or string and a destination file path using streams
     *
     * @param $file
     * @param $disk
     * @param $destination
     */
    public function copyFile($file, $disk, $destination)
    {
        $destinationDirectory = dirname($destination);

        $disk->makeDirectory($destinationDirectory);

        /*
         * The file could be quite large. Use a stream to copy it
         * to the target disk to avoid memory problems
         */

        $ispath = is_string($file);
        if ($ispath) {
            $file = fopen($file, 'r+');
        }

        $disk->getDriver()->writeStream($destination, $file);

        if($ispath) {
            fclose($file);
        }
    }

    public function deleteTargetDirectoryFiles($fileSystem)
    {
        $disk = Storage::disk($fileSystem);

        $destination = config('db-manager.output.location');

        $files =  $disk->allfiles($destination);

        $disk->deleteDirectory($destination);

        return $files;
    }

    public function deleteLocalFile($path)
    {
        \File::deleteDirectory($path);
    }

    public function copyFileToFileSystem($file, $fileSystem, $backupFileName)
    {
        try {
            $disk = Storage::disk($fileSystem);

            $this->copyFile($file, $disk, $backupFileName);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    /*************************************************************
     * Temporary file methods
     *************************************************************/

    public function prepareTemporaryDir()
    {
        if (!is_dir($this->getTemporaryFileDir())) {
            mkdir($this->getTemporaryFileDir(), 0777, true);
        } else {
            chmod($this->getTemporaryFileDir(), 0777);
        }
    }

    public function getTemporaryFile()
    {
        $tempFileName = $this->getTemporaryFileDir() . $this->getTemporaryFileName();

        $file = fopen($tempFileName, 'w+');

        chmod($tempFileName, 0777);

        return [
            'handler'   => $file,
            'path'      => $tempFileName
        ];
    }

    public function getTemporaryFileDir()
    {
        return storage_path('temp-db-manager/');
    }

    public function getTemporaryFileName()
    {
        return uniqid('db-manager-temp', true);
    }

    public function cleanUpTemporaryFiles()
    {
        return $this->delTree($this->getTemporaryFileDir());
    }

    /*************************************************************
     * Generic file methods
     *************************************************************/

    /**
     * Removes a directory and all nested files and nested directories.
     * Source: http://php.net/manual/en/function.rmdir.php#110489
     *
     * @param $dir
     * @return bool
     */
    private function delTree($dir) {

        $files = array_diff(scandir($dir), array('.','..'));

        foreach ($files as $file) {
            if(is_dir("$dir/$file")) {
                $this->delTree("$dir/$file");
            } else {
                try {
                    unlink("$dir/$file");
                } catch (\Exception $e) {
                    chmod("$dir/$file", 0777);
                    unlink("$dir/$file");
                }
            }
        }

        return rmdir($dir);
    }


}