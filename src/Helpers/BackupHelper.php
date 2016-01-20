<?php namespace JulianPitt\DBManager\Helpers;

use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Databases\MySQL\Database;
use JulianPitt\DBManager\Console;
use Config;
use Exception;

class BackupHelper extends FileHelper
{
    protected $console;
    protected $database;

    public function __construct()
    {
        $this->console = new Console();
        $this->prepareTemporaryDir();
    }

    /**
     *
     * Create a new database connection from the config
     *
     * @param array $realConfig
     * @return mixed
     * @throws Exception
     */
    public function getDatabaseConnection(array $realConfig)
    {
        try {
            $this->buildMySQL($realConfig);
        } catch (\Exception $e) {
            throw new \Exception('Whoops, '.$e->getMessage());
        }

        return $this->database;
    }

    /**
     * Returns a new MySQLDatabase object from config settings
     *
     * @param array $config
     * @throws Exception
     */
    protected function buildMySQL(array $config)
    {
        $port = isset($config['port']) ? $config['port'] : 3306;

        $socket = isset($config['unix_socket']) ? $config['unix_socket'] : '';

        $this->database = new Database(
            $this->console,
            $config['database'],
            $config['username'],
            $config['password'],
            $this->determineHost($config),
            $port,
            $socket
        );
    }

    /**
     * Returns the database host by reading the config
     *
     * @param array $config
     * @return mixed
     * @throws Exception
     */
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

    /**
     * Get the database that the application is using and return it's connection class
     *
     * @param string $connectionName
     * @return mixed
     * @throws Exception
     */
    public function getDatabase($connectionName = '')
    {

        $connectionName = $connectionName ?: config('database.default');

        $dbDriver = config("database.connections.{$connectionName}.driver");

        if ($dbDriver != 'mysql') {
            throw new Exception('DBManager currently doesn\'t support your database');
        }

        return $this->getDatabaseConnection(config("database.connections.{$connectionName}"));
    }

    public function getDumpedDatabase($commandClass)
    {

        $tempFile = tempnam(sys_get_temp_dir(), "dbbackup");

        //Determine if you are getting certain tables or all of the database

        if(!empty(config("db-manager.output.tables"))) {

            $passedChecks = $this->getDatabase()->checkBackupIntegrity($commandClass);

        }

        //This means there are specific tables to back up only
        if(!empty($passedChecks)) {

            $success = $this->getDatabase()->dumpTables($tempFile, $passedChecks);

        } else {

            $success = $this->getDatabase()->dumpAll($tempFile);

        }

        //Check if the backup was successful
        if ( !$success || filesize($tempFile) == 0 ) {

            throw new Exception("Could not create backup of db\n" . $success);

        }

        //Write the signature
        $this->prependSignature($tempFile);

        return $tempFile;
    }

    public function getFilesToBeBackedUp($commandClass)
    {
        return [$this->getDumpedDatabase($commandClass)];
    }

    /**
     * Uses the filesystem that is passed in and creates, writes, closes, opens, reads and compares the file contents
     * to make sure the user can perform all the necessary actions i.e. the user has the correct permissions
     *
     * @param $fileSystem
     * @return bool
     * @throws Exception
     */
    public function checkIfUserHasPermissions($fileSystem)
    {
        $disk = Storage::disk($fileSystem);

        $filepath = config('db-manager.output.location') . $this->getTemporaryFileName();

        $testString = "Testing to see if the user can write to the output directory";

        //open, write to and close a file
        try {
            $file = $this->getTemporaryFile();
            fwrite($file['handler'], $testString);
            if(!$this->copyFileToFileSystem($file['handler'], $fileSystem, $filepath)){
                throw new \Exception('Can\'t write to filesystem');
            }
            if(is_resource($file['handler'])) {
                fclose($file['handler']);
            }
        } catch (Exception $e) {
            throw new \Exception("Unable to write to file, make sure you have the correct permissions.\nTried writing to " .
                $filepath . "\n" . $e);
        }

        //open, read, and close the file
        try {

            $savedString = $disk->get($filepath);

            if(strcasecmp($savedString, $testString) != 0) {
                throw new \Exception("Saved file does not have the expected message, make sure you have the correct permissions");
            }

            //delete the file
            if(!$disk->delete($filepath)) {
                throw new \Exception("Unable to remove the temporary test file, make sure you have the correct permissions");
            }

        } catch (Exception $e) {
            throw new \Exception("Unable to read file, make sure you have the correct permissions" . $filepath . "\n" . $e);
        }

        return true;
    }

    /**
     * Get the file from the database dump
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getDatabaseDump()
    {
        $filesToBeBackedUp = $this->backupHelper->getFilesToBeBackedUp($this);

        if (count($filesToBeBackedUp) != 1) {
            throw new \Exception('could not backup db');
        }

        $this->comment('Database dumped');

        return $filesToBeBackedUp[0];
    }

    /**
     * Delete all previous backups from the selected filesystem
     *
     * @param $fileSystem
     * @return bool
     */
    public function deletePreviousBackups($fileSystem)
    {
        do {
            $name = $this->ask('Are you sure you want to remove all previous backups from the filesystem '.$fileSystem.'? [y/n]');
            if(strcasecmp ($name, "y") != 0 && strcasecmp ($name, "n") != 0) {
                $this->info("Invalid response, type 'y' for yes or 'n' for no. Let's try again. This is serious, if it wasn't, I would've use the confirm method to write this");
            }

        } while(strcasecmp ($name, "y") != 0 && strcasecmp ($name, "n") != 0);


        if (strcasecmp ($name, "y") != 0) {
            $this->info("Skipped deleting all previous backups");
            return true;
        }

        $this->info("Deleting all previous backups");

        $deletedFiles = $this->backupHelper->deleteTargetDirectoryFiles($fileSystem);

        foreach($deletedFiles as $file) {
            $this->info("Deleted backup file " . $file);
        }

        return true;

    }

    /**
     * Provides a list of all the database tables to be backed up
     *
     * @return array
     * @throws \Exception
     */
    protected function getAllTablesToBeBackedUp()
    {
        $files = [];

        $files[] = ['realFile' => $this->getDatabaseDump($files), 'fileInZip' => 'dump.sql'];

        return $files;
    }

    /**
     * Creates a zip archive from the file supplied
     *
     * @param $files
     * @return string
     */
    protected function createZip($files)
    {
        $this->comment('Start zipping '.count($files).' files...');

        $tempZipFile = $this->backupHelper->getTemporaryFileDir() . $this->backupHelper->getTemporaryFileName();

        $zip = new ZipArchive();
        $zip->open($tempZipFile, ZipArchive::CREATE);

        foreach ($files as $file) {
            if (file_exists($file['realFile'])) {
                $zip->addFile($file['realFile'], $file['fileInZip']);
            }
        }

        $zip->close();

        chmod($tempZipFile, 0777);

        $this->comment('Zip created!');

        return $tempZipFile;
    }


    /**
     * Get the filesystem in use from the config
     *
     * @return array|mixed
     */
    protected function getTargetFileSystems()
    {
        $fileSystems = config('db-manager.output.filesystem');

        if (is_array($fileSystems)) {
            return $fileSystems;
        }

        $arrayString = $this->is_array_string($fileSystems);

        if (is_array($arrayString)) {
            return $arrayString;
        }

        return [$fileSystems];
    }

    protected function is_array_string($string)
    {
        if(strlen($string) <= 2) {
            return false;
        }

        if( $string[0] != '[' ||
            $string[strlen($string)-1] != ']') {
            return false;
        }

        try {
            $arr = explode(',', substr($string, 1, strlen($string) - 2));
            if(count($arr) <= 0) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        $arr = array_map(function($obj) {
            $bad = ['\'', '"'];
            return trim(str_replace($bad, "", $obj));
        }, $arr);

        return $arr;
    }

    /**
     * Get the backup location destination full path
     *
     * @return string
     * @throws \Exception
     */
    protected function getBackupDestinationFileName()
    {
        $backupDirectory = config('db-manager.output.location');
        $backupFilename = $this->getPrefix().$this->getFilename().$this->getSuffix().$this->backupHelper->getOutputFileType();

        return $backupDirectory.'/'.$backupFilename;
    }

    /**
     * Get the prefix of the output filename from the config
     *
     * @return array|bool|string
     */
    public function getPrefix()
    {
        if ($this->option('prefix') != '') {
            return $this->option('prefix');
        }

        $prefix = $this->getFixSpecialType(config('db-manager.output.prefix'));

        return $prefix;
    }

    /**
     * Get the filename from the config for the output file name
     *
     * @return mixed
     * @throws \Exception
     */
    public function getFilename()
    {
        if (empty(config('db-manager.output.filename'))) {
            throw new \Exception('Filename not set in config');
        }

        return config('db-manager.output.filename');
    }

    /**
     * Get the suffix of the output file
     *
     * @return array|bool|string
     *
     */
    public function getSuffix()
    {
        if ($this->option('suffix') != '') {
            return $this->option('suffix');
        }

        $suffix = $this->getFixSpecialType(config('db-manager.output.suffix'));

        return $suffix;
    }

    /**
     * A method that returns a special variable for the file name supplied
     *
     * @param $fix
     * @return bool|string
     */
    public function getFixSpecialType($fix)
    {
        if($fix == 'datetime') {
            return date('YmdHis');
        } else {
            return $fix;
        }
    }

    /**
     * Copy a supplied file to the filesystem passed through in the destination specified in the config
     *
     * @param $file
     * @param $fileSystem
     */
    public function copyFileToFileSystem($file, $fileSystem)
    {
        $this->comment('Start uploading backup to '.$fileSystem.'-filesystem...');

        $backupFilename = $this->getBackupDestinationFileName();

        if($this->backupHelper->copyFileToFileSystem($file, $fileSystem, $backupFilename)) {
            $this->comment('Backup stored on '.$fileSystem.'-filesystem in file "'.$backupFilename.'"');
        } else {
            $this->warn('Unable to send file "'.$backupFilename.'" to '.$fileSystem.'-filesystem');
        }
    }


}