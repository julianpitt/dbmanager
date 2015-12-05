<?php namespace JulianPitt\DBManager\Helpers;

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
        $string = <<<EOT
/*
Backup created with JulianPitt DBManager
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