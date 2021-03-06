<?php namespace JulianPitt\DBManager\Databases\MySQL;

use Illuminate\Support\Facades\Config;
use JulianPitt\DBManager\Console;
use JulianPitt\DBManager\Interfaces\DatabaseInterface;
use Mockery\CountValidator\Exception;

class Database implements DatabaseInterface
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
        $this->queries = new Queries();
    }

    /**
     * Performs the database dump for a specific schema on all of it's tables, then saves it to an output file
     *
     * @param $destinationFile
     * @return bool|string
     */
    public function dumpAll($destinationFile)
    {
        $tempFileHandle = tmpfile();

        if ($tempFileHandle === false) {
            throw new \Exception("Unable to make temporary file");
        }

        fwrite($tempFileHandle,
            "[client]" . PHP_EOL .
            "user = '" . $this->user . "'" . PHP_EOL .
            "password = '" . $this->password . "'" . PHP_EOL .
            "host = '" . $this->host . "'" . PHP_EOL .
            "port = '" . $this->port . "'" . PHP_EOL
        );

        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];

        $command = sprintf('%smysqldump --defaults-extra-file=%s --skip-comments ' .
            ($this->useExtendedInsert() ? '--extended-insert' : '--skip-extended-insert') .
            ' %s %s > %s %s',

            $this->getDumpCommandPath(),
            escapeshellarg($temporaryCredentialsFile),
            $this->dumpType(),
            escapeshellarg($this->database),
            escapeshellarg($destinationFile),
            escapeshellcmd($this->getSocketArgument())
        );

        return $this->console->run($command, Config::get('db-manager.output.timeoutInSeconds'));
    }

    /**
     * Performs an export on a specified set of tables on the specified database from the config and
     * saves the result to a file
     *
     * @param $destinationFile
     * @param $tablesToBackUp
     * @return bool|string
     */
    public function dumpTables($destinationFile, $tablesToBackUp)
    {

        $tempFileHandle = tmpfile();

        if ($tempFileHandle === false) {
            throw new Exception("Unable to make temporary file");
        }

        fwrite($tempFileHandle,
            "[client]" . PHP_EOL .
            "user = '" . $this->user . "'" . PHP_EOL .
            "password = '" . $this->password . "'" . PHP_EOL .
            "host = '" . $this->host . "'" . PHP_EOL .
            "port = '" . $this->port . "'" . PHP_EOL
        );

        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];

        $tables = (is_array($tablesToBackUp) ? implode("\" \"", $tablesToBackUp) : $tablesToBackUp);

        $command = sprintf('%smysqldump --defaults-extra-file=%s --skip-comments ' . ($this->useExtendedInsert() ? '--extended-insert' : '--skip-extended-insert') . ' %s %s "%s" > %s %s',
            $this->getDumpCommandPath(),
            escapeshellarg($temporaryCredentialsFile),
            $this->dumpType(),
            escapeshellarg($this->database),
            $tables,
            escapeshellarg($destinationFile),
            escapeshellcmd($this->getSocketArgument())
        );

        return $this->console->run($command, Config::get('db-manager.output.timeoutInSeconds'));
    }

    public function runQuery($query)
    {
        /*
         * Create temporary file with db credentials
         */
        $tempFileHandle = tmpfile();

        fwrite($tempFileHandle,
            "[client]" . PHP_EOL .
            "user = '" . $this->user . "'" . PHP_EOL .
            "password = '" . $this->password . "'" . PHP_EOL .
            "host = '" . $this->host . "'" . PHP_EOL .
            "port = '" . $this->port . "'" . PHP_EOL
        );
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
        $command = sprintf('%mysql --defaults-extra-file=%s -B %s -e "%s"',
            $this->getDumpCommandPath(),
            escapeshellarg($temporaryCredentialsFile),
            escapeshellarg($this->database),
            escapeshellarg($query)
        );

        return $this->console->run($command, Config::get('db-manager.output.timeoutInSeconds'));
    }

    /**
     * Gets the file extension of the output file for the database dump
     *
     * @return string
     */
    public static function getFileExtension()
    {
        return 'sql';
    }

    protected function getDumpCommandPath()
    {
        $output = Config::get('db-manager.mysqlbinloc');

        if(empty($output)) {
            throw new \Exception('The mysql path is not set');
        } else if(!file_exists($output)) {
            throw new \Exception('The mysql path set ('.$output.') does not exist');
        }

        return $output;
    }

    protected function useExtendedInsert()
    {
        return Config::get('db-manager.output.useExtendedInsert');
    }

    /**
     * Gets the command for the type of dump specified from the config, can be dataonly,
     * structure only or both
     *
     * @return string
     */
    protected function dumpType()
    {
        $type = Config::get('db-manager.output.backupType');

        if (empty($type)) {
            return "";
        }

        if ($type == "dataonly") {
            return "--skip-triggers --compact --no-create-info";
        } else if ($type == "structureonly") {
            return "-d";
        }

        return "";
    }

    /**
     * Gets the socket arguement for the mysql queries
     *
     * @return string
     */
    protected function getSocketArgument()
    {
        if ($this->socket != '') {
            return '--socket=' . $this->socket;
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
     * @param boolean
     * @return int
     * @throws \Exception
     */
    public function checkBackupIntegrity($commandClass, $tables)
    {
        //Check the database exists
        if (!$this->checkDatabase($this->database)) {
            throw new \Exception("Integrity check failed! No " . $this->database . " database found");
        }

        return $this->checkTablesForBackup($this->database, $commandClass, $tables);
    }

    public function checkRestoreIntegrity($commandClass, $tables)
    {
        return false; //diabled until implemented correctly

        //Check the database exists
        if (!$this->checkDatabase($this->database)) {
            throw new \Exception("Integrity check failed! No " . $this->database . " database found");
        }

        return $this->checkTablesForInsert($this->database, $commandClass, $tables);
    }

    /**
     * Checks to see if the database that you would like to back up currently exists in a list of
     * existing schemas
     *
     * @param $database
     * @return bool
     */
    public function checkDatabase($database)
    {
        foreach ($this->queries->getDatabases() as $schema) {
            if ($database == $schema->Database)
                return true;
        }
        return false;
    }

    /**
     * Returns an associative array with all the tables as keys and an array of all the column as the value from the passed in database
     *
     * @param $database
     * @return array
     */
    public function getAllTables()
    {
        return $this->convertObjArr($this->queries->getTablesAndColumns($this->database));
    }

    /**
     * Checks the schema and tables in the schema of the table you would like to back up
     * to make sure everything runs smoothly
     *
     * @param $database
     * @return array
     */
    public function checkTables($database, $tables)
    {
        $tablesToBackUp = $this->getTablesToBackUp($tables);
        $tablesInDatabase = $this->getAllTables($database);

        $foundTables = [];

        $index = 0;

        foreach ($tablesToBackUp as $table) {
            if (isset($tablesInDatabase[$table])) {
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

    public function checkTablesForInsert($database, $commandClass, $tables)
    {
        $tables = $this->checkTables($database, $tables);

        $commandClass->info("The following tables were found: \n-" . implode("\n-", $tables["found"]));

        if (count($tables["found"]) <= 0) {
            throw new \Exception("Integrity check failed! No tables to back up were found in the database");
        }

        return true;
    }

    /**
     * Checks that there are tables in the selected database and displays if there are any tables that
     * are not found from the supplied tables list in th config.
     * returns an array of found tables
     *
     * @param $database
     * @param $commandClass
     * @return mixed
     * @throws \Exception
     */
    public function checkTablesForBackup($database, $commandClass, $tables)
    {
        $tables = $this->checkTables($database, $tables);

        $commandClass->info("The following tables were found: \n-" . implode("\n-", $tables["found"]));

        if (count($tables["found"]) <= 0) {
            throw new \Exception("Integrity check failed! No tables to back up were found in the database");
        }

        //Check the tables exist
        if (isset($tables["notfound"]) && count($tables["notfound"]) > 0) {
            $commandClass->info("\nThe following tables were not found:\n-" . implode("\n-", $tables["notfound"]));
            if (!$commandClass->confirm("Would you like to back up the tables that have been found?")) {
                throw new \Exception("Integrity check failed! Some tables were not found");
            }
        }

        return $tables["found"];
    }

    /**
     * Gets a list of tables to back up from the config
     *
     * @return Config|string
     */
    public function getTablesToBackUp($tables)
    {
        $backupTables = $tables;

        if (empty($backupTables)) {
            return "all";
        }

        $tables = Config::get('db-manager.tables.' . $backupTables);

        if (empty($tables)) {
            return "all";
        }

        return $tables;
    }

    /**
     * A helper method that converts an array of objects from the MySQL infoschema table into an
     * associative array
     *
     * @param $objArr
     * @return array
     */
    private function convertObjArr($objArr)
    {
        $returnArray = [];

        foreach ($objArr as $obj) {
            if (isset($returnArray[$obj->TABLE_NAME])) {
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
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] == $val) {
                return $index;
            }
            $index++;
        }
        return -1;
    }

}
