<?php namespace JulianPitt\DBManager\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Classes\FileOutput;
use JulianPitt\DBManager\Databases\MySQL\Database;
use JulianPitt\DBManager\Console;
use Exception;
use JulianPitt\DBManager\Interfaces\OutputInterface;
use ZipArchive;

class BackupHelper extends FileHelper
{
    protected $console;
    protected $database;
    protected $options;
    protected $out = null;

    public function __construct($options = null)
    {
        $this->console = new Console();
        $this->prepareTemporaryDir();
        $this->initOptions($options);

        // Defaults to file output
        $this->setOutput(new FileOutput);
    }

    //TODO Remove on deployment
    private function debugThis($something)
    {
        throw new \Exception(var_dump($something));
        die;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->out = $output;
    }

    //TODO get the config and array merge the options passed in with the config
    private function initOptions($options = null)
    {
        //Set the default options since they are required
        $this->options = [
            'prefix'        => 'datetime',
            'suffix'        => '',
            'filename'      => '-db-manager',
            'compress'      => true,
            'keeplastonly'  => false,
            'filesystem'    => "local",
            'location'      => "/backups/",
            'useExtendedInsert' => true,
            'timeoutInSeconds'  => 60,
            'tables'        => "laravel",
            'backupType'    => "dataandstructure",
            'checkPermissions' => true,
        ];

        //Then get the config options and merge it with the defaults
        $config = (array) Config::get('db-manager.output', []);

        if(!empty($config) && is_array($config)) {
            $this->options = array_replace_recursive($this->options, ($config));
        }

        //Finally merge the config with the passed in options
        if(!empty($options) && is_array($options)) {
            $this->options = array_replace_recursive($this->options, $options);
        }
    }

    /**
     *
     * Create a new database connection from the config
     *
     * @param array $realConfig
     * @return mixed
     * @throws Exception
     */
    private function getDatabaseConnection(array $realConfig)
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

        $connectionName = $connectionName ?: Config::get('database.default');

        $dbDriver = Config::get("database.connections.{$connectionName}.driver");

        if ($dbDriver != 'mysql') {
            throw new Exception('DBManager currently doesn\'t support your database');
        }

        return $this->getDatabaseConnection(Config::get("database.connections.{$connectionName}"));
    }

    /**
     * Performs a database export using the mysqldump command
     *
     * @param $commandClass
     * @return string
     * @throws Exception
     */
    public function getDumpedDatabase($commandClass)
    {

        $tempFile = tempnam(sys_get_temp_dir(), "dbbackup");

        //Determine if you are getting certain tables or all of the database
        if(!empty(Config::get("db-manager.output.tables"))) {
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
        $this->out->info("Testing for permissions: started");

        $disk = Storage::disk($fileSystem);

        $filepath = Config::get('db-manager.output.location') . $this->getTemporaryFileName();

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
                $filepath . " on filesystem " . $fileSystem . "\n" . $e);
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

        $this->out->info("Testing for permissions: finished");

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
        $filesToBeBackedUp = $this->getFilesToBeBackedUp($this);

        if (count($filesToBeBackedUp) != 1) {
            throw new \Exception('could not backup db');
        }

        $this->out->info('Database dumped');

        return $filesToBeBackedUp[0];
    }

    protected function option($option)
    {
        try {
            return $this->options[$option];
        } catch (\Exception $e) {
            throw new \Exception("No option named: " . $option);
        }
    }

    /**********************************************************************
     * From BackupCommand
     **********************************************************************/

    /**
     * Delete all previous backups from the selected filesystem
     *
     * @param $fileSystem
     * @return bool
     */
    public function deletePreviousBackups($fileSystem)
    {
        if(method_exists($this->out, 'ask')){
            do {
                $name = $this->out->ask('Are you sure you want to remove all previous backups from the filesystem ' . $fileSystem . '? [y/n]');
                if (strcasecmp($name, "y") != 0 && strcasecmp($name, "n") != 0) {
                    $this->out->warn($name . " is no a valid response");
                    $this->out->info("Invalid response, type 'y' for yes or 'n' for no. Let's try again. This is serious, if it wasn't, I would've use the confirm method to write this");
                }
            } while (strcasecmp($name, "y") != 0 && strcasecmp($name, "n") != 0);
        } else {
            $name = "y";
        }

        if (strcasecmp ($name, "y") != 0) {
            $this->out->info("Skipped deleting all previous backups");
            return true;
        }

        $this->out->info("Deleting all previous backups");

        $deletedFiles = $this->deleteTargetDirectoryFiles($fileSystem);

        foreach($deletedFiles as $file) {
            $this->out->info("Deleted backup file " . $file);
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

        $files[] = ['realFile' => $this->getDatabaseDump(), 'fileInZip' => 'dump.sql'];

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
        $this->out->comment('Start zipping '.count($files).' files...');

        $tempZipFile = $this->getTemporaryFileDir() . $this->getTemporaryFileName();

        $zip = new ZipArchive();
        $zip->open($tempZipFile, ZipArchive::CREATE);

        foreach ($files as $file) {
            if (file_exists($file['realFile'])) {
                $zip->addFile($file['realFile'], $file['fileInZip']);
            }
        }

        $zip->close();

        $this->out->comment('Zip created!');

        return $tempZipFile;
    }


    /**
     * Get the filesystem in use from the config
     *
     * @return array|mixed
     */
    protected function getTargetFileSystems()
    {
        $fileSystems = Config::get('db-manager.output.filesystem');

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
        $backupDirectory = Config::get('db-manager.output.location');
        $backupFilename = $this->getPrefix().$this->getFilename().$this->getSuffix().$this->getOutputFileType();

        return $backupDirectory.'/'.$backupFilename;
    }

    /**
     * Get the prefix of the output filename from the config
     *
     * @return array|bool|string
     */
    public function getPrefix()
    {
        $prefix = $this->option('prefix');

        $prefix = $this->getFixSpecialType($prefix);

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
        return $this->option('filename');
    }

    /**
     * Get the suffix of the output file
     *
     * @return array|bool|string
     *
     */
    public function getSuffix()
    {
        $suffix = $this->option('suffix');

        $suffix = $this->getFixSpecialType($suffix);

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
        //TODO add more special types and change date to accept a format
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
     * @param $backupFileName
     * @return bool|void
     */
    public function copyFileToFileSystem($file, $fileSystem, &$backupFileName = null)
    {
        $this->out->comment('Start uploading backup to '.$fileSystem.'-filesystem...');

        if($backupFileName === null) {
            $backupFileName = $this->getBackupDestinationFileName();
        }

        $result = parent::copyFileToFileSystem($file, $fileSystem, $backupFileName);
        if($result === true) {
            $this->out->comment('Backup stored on '.$fileSystem.'-filesystem in file "'.$backupFileName.'"');
            return true;
        } else {
            $this->out->warn('Unable to send file "'.$backupFileName.'" to '.$fileSystem.'-filesystem'."\n".$result);
        }
        return false;
    }

    //This method need to be able to perform the whole backup
    public function backup($optionsArr = null)
    {

        $this->out->info('Starting backup');

        try {
            /**
             * Check if the user has access to read and write files, does the user have permission?
             */

            if(filter_var($this->option('checkPermissions'), FILTER_VALIDATE_BOOLEAN)) {
                foreach ($this->getTargetFileSystems() as $fileSystem) {
                    $this->checkIfUserHasPermissions($fileSystem);
                }
            }


            /**
             * Get all the tables that need to be backed up into their filenames from the dump
             */

            $files = $this->getAllTablesToBeBackedUp();

            if (count($files) <= 0) {
                $this->out->info('Nothing to backup');
                return true;
            }


            /**
             * Compress the backup file if needed
             */

            $compress = Config::get('db-manager.output.compress');

            if (!isset($compress) || (isset($compress) && !is_bool($compress))) {
                $compress = true;
            }

            $this->out->info("Compress output files: " . ($compress ? "True" : "False"));

            if ($compress) {

                $backupZipFile = $this->createZip($files);

                if (filesize($backupZipFile) == 0) {
                    $this->out->warn('The zipfile that will be backed up has a filesize of zero.');
                }

            }


            /**
             * Delete previous backups if needed
             */

            $keepLastOnly = Config::get('db-manager.output.keeplastonly');

            if(!isset($keepLastOnly) || (isset($keepLastOnly) && !is_bool($keepLastOnly))) {
                $keepLastOnly = false;
            }

            $this->out->info("Keep last output only: " . ($keepLastOnly ? "True" : "False"));

            if(!empty($keepLastOnly) && is_bool($keepLastOnly) && $keepLastOnly) {
                foreach ($this->getTargetFileSystems() as $fileSystem) {
                    $this->deletePreviousBackups($fileSystem);
                }
            }


            /**
             * Copy all the files to your chosen location
             */

            foreach ($this->getTargetFileSystems() as $fileSystem) {
                if($compress) {
                    $this->copyFileToFileSystem($backupZipFile, $fileSystem);
                } else {
                    foreach($files as $file) {
                        $this->copyFileToFileSystem($file['realFile'], $fileSystem);
                    }
                }
            }

        } catch (\Exception $e) {
            $this->out->warn('An Error occurred');
            $this->out->warn("Code: " . $e->getCode());
            $this->out->warn("Message: \n". $e->getMessage());
            $this->out->warn("Starck Trace: \n" . $e->getTraceAsString());
        }

        /**
         * Delete the temporary files
         */

        $this->out->info("Cleaning up temporary files");

        if(!$this->cleanUpTemporaryFiles()) {
            $this->out->warn('Unable to remove temporary files, may need to be manually removed');
        }

        $this->out->info('Backup successfully completed');

        return true;
    }
}