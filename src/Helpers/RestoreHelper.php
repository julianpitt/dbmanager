<?php namespace JulianPitt\DBManager\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Databases\MySQLDatabase;
use JulianPitt\DBManager\Console;
use Config;
use Exception;
use ZipArchive;

class RestoreHelper extends FileHelper
{
    protected $console;
    protected $database;
    protected $command;

    public function __construct()
    {
        $this->console = new Console();
    }

    public function getDatabaseConnection(array $realConfig)
    {
        try {
            $this->buildMySQL($realConfig);
        } catch (\Exception $e) {
            throw new \Exception('Whoops, ' . $e->getMessage());
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

        $this->command = $commandClass;

        //Get the last back up from the file handler
        $disk = Storage::disk($fileSystem[0]);

        $backupDirectory = config('db-manager.output.location') . "/";

        $allFiles = $disk->files($backupDirectory);

        $latest = 0;
        $latestFile = "";

        $latest10 = [];

        foreach ($allFiles as $backupFile) {

            if (count($latest10) < 10) {
                //Fill the array if there are less than 10 backup files
                array_push($latest10, [
                    'name' => $backupFile,
                    'lastModified' => $disk->lastModified($backupFile)
                ]);
            } else {
                //There are more than 10 backup files, check each to make sure the oldest is changed

            }

            if ($latest < $disk->lastModified($backupFile)) {

                $latestFile = $backupFile;

            }

        }

        $this->command->info("Please select the backup you wish to restore");

        usort($latest10, function ($a, $b) {
            return $b['lastModified'] - $a['lastModified'];
        });

        foreach ($latest10 as $key => $backup) {

            $this->command->info("Option: [" . ($key + 1) . "] \nBackup Name: " . $backup["name"] . " \nLast Modified: " . Carbon::createFromTimestamp($backup["lastModified"])->format("Y-m-d H:i:s") . "\n");

        }

        $latest10Size = count($latest10);

        if($latest10Size > 1) {
            $option = $this->promptForChoice(
                "Which option would you like to restore? [ 1 - " . $latest10Size . "]",
                $latest10Size
            );
        } else {
            $option = 1;
        }

        $backupName = $latest10[$option - 1]["name"];

        $this->command->info("You chose to back up " . $backupName);

        //Check if it is compressed
        $tempBackupFile = tempnam($this->getTemporaryFileDir(), "db-manager-backup");
        $ext = pathinfo($backupName, PATHINFO_EXTENSION);

        file_put_contents($tempBackupFile, $disk->get($backupName));

        if ($ext == "zip") {

            $backupFile = $this->uncompress($backupName, $tempBackupFile);

        } else if ($ext != "sql") {

            throw new Exception("Incorrect filetype");

        } else {

            $backupFile = $tempBackupFile;

        }

        $this->command->warn(var_dump($backupFile));

        if (filesize($backupFile) == 0) {
            $this->command->warn('The zipfile that will be backed up has a filesize of zero.');
        }


        //Perform backup checks on the last backup

/*        $passedChecks = $this->getDatabase()->checkRestoreIntegrity($this->command);

        if (!$passedChecks) {
            throw new Exception('Restore checks failed');
        }*/

        //Restore the database

        //Delete the temporary backup file
        //Change to use BackupHelper deleteTargetDirectoryFiles
        $this->deleteLocalFile($this->getTemporaryFileDir());
        //check if this should be here
        $deletedFiles = $this->restoreHelper->deleteTargetDirectoryFiles($fileSystem);

        return $backupFile;
    }

    public function getFileExtension()
    {
        return 'sql';
    }

    public function uncompress($backupName, $tempBackupFile)
    {
        $this->command->info("Uncompressing backup file " . $backupName);

        $zip = new ZipArchive();

        $res = $zip->open($tempBackupFile);

        if ($res === TRUE) {

            $sqlFilesInArchive = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {

                $fileName = $zip->getNameIndex($i);
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                if ($ext == "sql") {
                    $sqlFilesInArchive[] = $fileName;
                }

            }

            if (count($sqlFilesInArchive) <= 0) {

                throw new Exception('No sql files in compressed backup file');

            } else if (count($sqlFilesInArchive) == 1) {

                //extract one
                $backupFile = $zip->extractTo($this->getTemporaryFileDir(), $sqlFilesInArchive[0]);

                if($backupFile) {
                    $backupFile = $sqlFilesInArchive[0];
                }

            } else {
                //prompt which one to extract
                $this->command->info("Multiple sql files found in archive");

                $option = $this->promptForChoice(
                    "Which option would you like to restore? [ 1 - " . $zip->numFiles . "]",
                    $zip->numFiles
                );

                $extractFile = $sqlFilesInArchive[($option - 1)];

                $backupFile = $zip->extractTo($this->getTemporaryFileDir(), $extractFile);

                if($backupFile) {
                    $backupFile = $extractFile;
                }

            }

            if($backupFile) {

                $backupFile = $this->getTemporaryFileDir() . $backupFile;

            }  else {

                throw new Exception('Decompression failed');

            }

        } else {

            throw new Exception('Unable to decompress the chosen backup file');

        }

        $zip->close();

        return $backupFile;

    }

    public function promptForChoice($question, $amountOfChoices)
    {
        do {
            $option = $this->command->ask($question);
            if (!is_numeric($option)) {
                $this->command->warn("Please provide a numeric option");
            } else if ($option > $amountOfChoices || $option < 1) {
                $this->command->warn("The selected option does not exist, please try again");
            }
        } while (!is_numeric($option) || is_numeric($option) && ($option > $amountOfChoices || $option < 1));

        return $option;
    }


}