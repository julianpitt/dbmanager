<?php namespace JulianPitt\DBManager\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Databases\MySQLDatabase;
use League\Flysystem\FileExistsException;

abstract class FileHelper
{

    public function getOutputFileType()
    {
        $compress = Config::get('db-manager.output.compress');

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
        $type = Config::get('db-manager.output.backupType');

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
        $databaseBackedUp = Config::get("database.connections.mysql.database");

        //Get Tables Backed Up
        $tablesBackedUp = Config::get("db-manager.output.tables");
        if(empty($tablesBackedUp)) {
            $tablesBackedUp = "ALL TABLES";
        } else {
            $tablesBackedUp = Config::get("db-manager.tables." . $tablesBackedUp);
            $tablesBackedUp = implode(", ", $tablesBackedUp);
        }

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

        if($ispath && is_resource($file)) {
            fclose($file);
        }
    }

    public function deleteTargetDirectoryFiles($fileSystem)
    {
        $disk = Storage::disk($fileSystem);

        $destination = Config::get('db-manager.output.location');

        $files =  $disk->allfiles($destination);

        $disk->deleteDirectory($destination);

        return $files;
    }

    public function deleteLocalFile($path)
    {
        \File::deleteDirectory($path);
    }

    public function copyFileToFileSystem($file, $fileSystem, &$backupFileName=null)
    {
        $uploadName = pathinfo($backupFileName);
        $inc = '';
        try {
            $done = 0;
            do {
                try {
                    $disk = Storage::disk($fileSystem);
                    $backupFileName = $uploadName['dirname'] . "/" . $uploadName['filename'] . $inc . "." .$uploadName['extension'];
                    $this->copyFile($file, $disk, $backupFileName);
                    $done = 1;
                } catch (FileExistsException $e) {
                    (is_numeric($inc) ? $inc++ : $inc = 1);
                }
            } while ($done < 1);
        } catch (\Exception $e) {
            return $e;
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
                if(file_exists("$dir/$file")) {
                    try {
                        unlink("$dir/$file");
                    } catch (\Exception $e) {
                        try {
                            chmod("$dir/$file", 0777);
                            unlink("$dir/$file");
                        } catch (\Exception $e) {
                            echo ($dir);
                            echo $e;
                        }
                    }
                }
            }
        }

        return rmdir($dir);
    }


}