<?php namespace JulianPitt\DBManager\Classes;

use JulianPitt\DBManager\Console;
use Config;
use JulianPitt\DBManager\Interfaces\DatabaseInterface;

class MySQLDatabase implements DatabaseInterface
{
    protected $console;
    protected $database;
    protected $user;
    protected $password;
    protected $host;
    protected $port;
    protected $socket;

    protected $queries;

    public function __construct(Console $console, $database, $user, $password, $host, $port, $socket)
    {
        $this->console = $console;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
        $this->socket = $socket;
    }

    public function dump($destinationFile)
    {
        /*
         * Create temporary file with db credentials
         */
        $tempFileHandle = tmpfile();
        fwrite($tempFileHandle,
            "[client]".PHP_EOL.
            "user = '".$this->user."'".PHP_EOL.
            "password = '".$this->password."'".PHP_EOL.
            "host = '".$this->host."'".PHP_EOL.
            "port = '".$this->port."'".PHP_EOL
        );
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];

        $command = sprintf('%smysqldump --defaults-extra-file=%s --skip-comments '.($this->useExtendedInsert() ? '--extended-insert' : '--skip-extended-insert').' %s > %s %s',
            $this->getDumpCommandPath(),
            escapeshellarg($temporaryCredentialsFile),
            escapeshellarg($this->database),
            escapeshellarg($destinationFile),
            escapeshellcmd($this->getSocketArgument())
        );

        return $this->console->run($command, config('laravel-backup.output.timeoutInSeconds'));
    }

    public function getFileExtension()
    {
        return 'sql';
    }

    protected function getDumpCommandPath()
    {
        return config('db-data-manager.mysqlbinloc');
    }

    protected function useExtendedInsert()
    {
        return config('db-data-manager.output.useExtendedInsert');
    }

    protected function getSocketArgument()
    {
        if ($this->socket != '') {
            return '--socket='.$this->socket;
        }

        return '';
    }

    /**
     *
     *
     * QueryHandler methods
     *
     *
     */

    public function checkIntegrity()
    {

    }
}
