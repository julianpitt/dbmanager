<?php namespace JulianPitt\DBManager\Helpers;

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
        return [$this->getLastBackup($commandClass)];
    }

    public function getLastBackup($commandClass)
    {
        $passedChecks = $this->getDatabase()->checkRestoreIntegrity($commandClass);

    }

    public function getFileExtension()
    {
        return 'sql';
    }


}