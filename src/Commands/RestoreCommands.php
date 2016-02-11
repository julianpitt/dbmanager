<?php namespace JulianPitt\DBManager\Commands;

use Illuminate\Console\Command;
use JulianPitt\DBManager\Helpers\RestoreHelper;
use Symfony\Component\Console\Input\InputOption;

class RestoreCommands extends Command
{

    protected $name = 'dbman:restore';

    protected $description = 'Restore the last backup';

    protected $restoreHelper = null;

    public function fire()
    {
        $this->restoreHelper = new RestoreHelper();

        $this->info('Starting Restore');

        try {
            $fileToRestore = $this->restoreHelper->getFileToRestore($this, $this->getTargetFileSystem());

            if (count($fileToRestore) < 1) {
                throw new \Exception('Could not restore db');
            }

            $this->comment('Database restored');

            $this->info('Restore successfully completed');

        } catch (\Exception $e) {
            $this->warn('An Error occurred');
            $this->warn("Code: " . $e->getCode());
            $this->warn("Message: \n". $e->getMessage());
            $this->warn("Starck Trace: \n" . $e->getTraceAsString());
        }

        return true;
    }

    protected function getTargetFileSystem()
    {
        $sameAsOutput = Config::get('db-manager.input.sameAsOutput');

        if( !isset($sameAsOutput) || (isset($sameAsOutput) && !is_bool($sameAsOutput))) {
            $sameAsOutput = true;
        }

        $fileSystem = Config::get('db-manager.output.filesystem');

        if(!$sameAsOutput) {
            $fileSystem = Config::get('db-manager.input.filesystem');
        }

        if (is_array($fileSystem)) {
            throw new \Exception("Can only load from one database source");
        }

        return [$fileSystem];
    }

    protected function getBackupDestinationFileName()
    {
        $backupDirectory = Config::get('db-manager.output.location');
        $backupFilename = $this->getPrefix().$this->getFilename().$this->getSuffix().'.zip';

        return $backupDirectory.'/'.$backupFilename;
    }

    public function getPrefix()
    {
        if ($this->option('prefix') != '') {
            return $this->option('prefix');
        }

        return Config::get('db-manager.output.prefix');
    }

    public function getFilename()
    {
        if ($this->option('filename') != '') {
            return $this->option('filename');
        }

        if (Config::get('db-manager.output.filename') != '') {
            throw new \Exception('Filename not set in config');
        }

        return Config::get('db-manager.output.filename');
    }

    public function getSuffix()
    {
        if ($this->option('suffix') != '') {
            return $this->option('suffix');
        }

        return Config::get('db-manager.output.suffix');
    }

    protected function getOptions()
    {
        return [
            ['name', null, InputOption::VALUE_REQUIRED, 'The name of the backup file to restore into the database.'],
        ];
    }

}
