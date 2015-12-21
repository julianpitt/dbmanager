<?php namespace JulianPitt\DBManager\Helpers;

use JulianPitt\DBManager\Databases\MySQLDatabase;
use JulianPitt\DBManager\Console;
use Config;
use Exception;

class BackupHelper
{
    protected $console;
    protected $database;
    protected $fileHelper;

    public function __construct()
    {
        $this->console = new Console();
        $this->fileHelper = new FileHelper();
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
        $this->fileHelper->prependSignature($tempFile);

        return $tempFile;
    }

    public function getFilesToBeBackedUp($commandClass)
    {
        return [$this->getDumpedDatabase($commandClass)];
    }

    /*TODO*/
    public function checkIfUserHasPermissions()
    {

    }


}