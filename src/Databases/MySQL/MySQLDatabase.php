<?php namespace JulianPitt\DBManager\Databases;

use JulianPitt\DBManager\Console;
use Config;
use JulianPitt\DBManager\Databases\MySQL\MySqlQueries;
use JulianPitt\DBManager\Interfaces\DatabaseHandler;

class MySQLDatabase implements DatabaseHandler
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
        $this->queries = new MySqlQueries();
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

        return $this->console->run($command, config('db-manager.output.timeoutInSeconds'));
    }

    public function runQuery($query)
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
        $command = sprintf('%mysql --defaults-extra-file=%s -B %s -e "%s"',
            $this->getDumpCommandPath(),
            escapeshellarg($temporaryCredentialsFile),
            escapeshellarg($this->database),
            escapeshellarg($query)
        );

        return $this->console->run($command, config('db-manager.output.timeoutInSeconds'));
    }

    public function getFileExtension()
    {
        return 'sql';
    }

    protected function getDumpCommandPath()
    {
        return config('db-manager.mysqlbinloc');
    }

    protected function useExtendedInsert()
    {
        return config('db-manager.output.useExtendedInsert');
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

    /**
     * Checks the integrity of the database insert before performing any action
     * this will only be used for restores where the backup file contains data only
     *
     * @return int
     * @throws \Exception
     */
    public function checkIntegrity()
    {
        //Check the database exists
        if(!$this->checkDatabase($this->database)) {
            throw new \Exception("Integrity check failed! No " . $this->database . " database found");
        }

        $tables = $this->checkTables($this->database);
        //Check the tables exist
        if(isset($tables["notfound"]) && count($tables["notfound"]) > 0) {
            throw new \Exception("Integrity check failed! Tables are not the same" . var_dump($tables["notfound"]));
        }

        return 1;
    }

    public function checkDatabase($database)
    {
        foreach($this->queries->getDatabases() as $schema) {
            if($database == $schema->Database)
                return true;
        }
        return false;
    }

    public function checkTables($database)
    {
        $tablesToBackUp = $this->getTablesToBackUp();
        $tablesInDatabase = $this->convertObjArr($this->queries->getTablesAndColumns($database));
        $foundTables = [];

        $index = 0;

        foreach($tablesToBackUp as $table) {
            if(isset($tablesInDatabase[$table])) {
                $foundTables[] = $table;
                unset($tablesToBackUp[$index]);
            }
            $index++;
        }

        return [
            "found" => $foundTables,
            "notfound" => $tablesToBackUp
        ];
    }

    public function getTablesToBackUp()
    {
        $backupTables = config('db-manager.output.tables');

        if(empty($backupTables)) {
           return "all";
        }

        $tables = config('db-manager.tables.' . $backupTables);

        if(empty($tables)) {
            return "all";
        }

        return $tables;
    }

    private function convertObjArr($objArr)
    {
        $returnArray = [];

        foreach($objArr as $obj) {
            if(isset($returnArray[$obj->TABLE_NAME])) {
                $returnArray[$obj->TABLE_NAME][] = $obj->COLUMN_NAME;
            } else {
                $returnArray[$obj->TABLE_NAME] = [$obj->COLUMN_NAME];
            }
        }

        return $returnArray;
    }

    private function in2DArray($array, $key, $val)
    {
        $index = 0;
        foreach($array as $item) {
            if(isset($item[$key]) && $item[$key] == $val) {
                return $index;
            }
            $index++;
        }
        return -1;
    }

}
