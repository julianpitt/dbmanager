<?php namespace JulianPitt\DBManager\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Databases\MySQLDatabase;
use JulianPitt\DBManager\Console;
use Config;
use Exception;

class RestoreHelper
{
    protected $console;
    protected $database;

    public function __construct()
    {
        $this->console = new Console();
    }

    public function getDatabaseConnection(array $realConfig)
    {
        try {
            $this->buildMySQL($realConfig);
        } catch (\Exception $e) {
            throw new \Exception('Whoops, '.$e->getMessage());
        }

        return $this->database;
    }

    protected function buildMySQL(array $config)
    {
        $port = isset($config['port']) ? $config['port'] : 3306;

        $socket = isset($config['unix_socket']) ? $config['unix_socket'] : '';

        $this->database = new MySQLDatabase(
            $this->console,
            $config['database'],
            $config['username'],
            $config['password'],
            $this->determineHost($config),
            $port,
            $socket
        );
    }

    public function determineHost(array $config)
    {
        if (isset($config['host'])) {
            return $config['host'];
        }

        if (isset($config['read']['host'])) {
            return $config['read']['host'];
        }

        throw new \Exception('could not determine host from config');
    }

    public function getDatabase($connectionName = '')
    {

        $connectionName = $connectionName ?: config('database.default');

        $dbDriver = config("database.connections.{$connectionName}.driver");

        if ($dbDriver != 'mysql') {
            throw new Exception('DBManager currently doesn\'t support your database');
        }

        return $this->getDatabaseConnection(config("database.connections.{$connectionName}"));
    }

    public function getFileToRestore($commandClass, $fileSystem)
    {
        return [$this->getLastBackup($commandClass, $fileSystem)];
    }

    /*TODO*/
    public function getLastBackup($commandClass, $fileSystem)
    {
        //Get the last back up from the file handler
        $disk = Storage::disk($fileSystem[0]);

        $backupDirectory = config('db-manager.output.location') . "/";

        $allFiles = $disk->files($backupDirectory);

        $latest = 0;
        $latestFile = "";

        $latest10 = [];

        foreach($allFiles as $backupFile) {

            if(count($latest10) < 10) {
                //Fill the array if there are less than 10 backup files
                array_push($latest10, [
                    'name'=>$backupFile,
                    'lastModified'=>$disk->lastModified($backupFile)
                ]);
            } else {
                //There are more than 10 backup files, check each to make sure the oldest is changed

            }

            if ($latest < $disk->lastModified($backupFile)) {

                $latestFile = $backupFile;

            }

        }

        $commandClass->info("Please select the backup you wish to restore");

        usort($latest10, function($a, $b) {
            return $b['lastModified'] - $a['lastModified'];
        });

        foreach($latest10 as $key => $backup) {

            $commandClass->info("[" . $key . "] Backup Name: " . $backup["name"] . " Last Modified: " . Carbon::createFromTimestamp($backup["lastModified"])->format("Y-m-d H:i:s"));

        }

        throw new Exception("done");

        //Perform backup checks on the last backup


        $passedChecks = $this->getDatabase()->checkRestoreIntegrity($commandClass);

        if(!$passedChecks) {
            throw new Exception('Restore checks failed');
        }

        //Restore the database

        //return $backupFile;
    }

    public function getFileExtension()
    {
        return 'sql';
    }


}