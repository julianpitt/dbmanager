<?php namespace JulianPitt\DBManager\Helpers;

use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Databases\MySQLDatabase;
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

    //TODO make it take in the filesystem and write to that
    public function checkIfUserHasPermissions($fileSystem)
    {
        $disk = Storage::disk($fileSystem);

        $filepath = config('db-manager.output.location') . "/testingPermissions.txt";

        $testString = "Testing to see if the user can write to the output directory";

        //open, write to and close a file
        try {
            $file = fopen($filepath, "w");
            fwrite($file, $testString);
            fclose($file);
        } catch (Exception $e) {
            throw new \Exception("Unable to write to file, make sure you have the correct permissions.\n Tried writing to" .
                $filepath);
        }

        //open, read, and close the file
        try {
            $file = fopen($filepath, "r");
            $savedString = fgets($file);
            fclose($file);
            if(strcasecmp($savedString, $testString) != 0) {
                throw new \Exception("Saved file does not have the expected message, make sure you have the correct permissions");
            }

            //delete the file
            if(!unlink($filepath)) {
                throw new \Exception("Unable to remove the temporary test file, make sure you have the correct permissions");
            }

        } catch (Exception $e) {
            throw new \Exception("Unable to read file, make sure you have the correct permissions" . $filepath);
        }
        return true;
    }


}