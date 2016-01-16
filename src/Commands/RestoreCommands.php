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

        $this->restoreLastBackup($this->getTargetFileSystem());

        $this->info('Restore successfully completed');

        return true;
    }

    protected function restoreLastBackup($fileSystem)
    {
        $databaseRestoreHandler = app()->make('JulianPitt\DBManager\Helpers\RestoreHelper');

        $fileToRestore = $databaseRestoreHandler->getFileToRestore($this, $fileSystem);

        if(count($fileToRestore) < 1) {
            throw new \Exception('Could not restore db');
        }

        $this->comment('Database restored');
    }

    protected function getTargetFileSystem()
    {
        $sameAsOutput = config('db-manager.input.sameAsOutput');

        if( !isset($sameAsOutput) || (isset($sameAsOutput) && !is_bool($sameAsOutput))) {
            $sameAsOutput = true;
        }

        $fileSystem = config('db-manager.output.filesystem');

        if(!$sameAsOutput) {
            $fileSystem = config('db-manager.input.filesystem');
        }

        if (is_array($fileSystem)) {
            throw new \Exception("Can only load from one database source");
        }

        return [$fileSystem];
    }

    protected function getBackupDestinationFileName()
    {
        $backupDirectory = config('db-manager.output.location');
        $backupFilename = $this->getPrefix().$this->getFilename().$this->getSuffix().'.zip';

        return $backupDirectory.'/'.$backupFilename;
    }

    public function getPrefix()
    {
        if ($this->option('prefix') != '') {
            return $this->option('prefix');
        }

        return config('db-manager.output.prefix');
    }

    public function getFilename()
    {
        if ($this->option('filename') != '') {
            return $this->option('filename');
        }

        if (config('db-manager.output.filename') != '') {
            throw new \Exception('Filename not set in config');
        }

        return config('db-manager.output.filename');
    }

    public function getSuffix()
    {
        if ($this->option('suffix') != '') {
            return $this->option('suffix');
        }

        return config('db-manager.output.suffix');
    }

    protected function getOptions()
    {
        return [
            ['name', null, InputOption::VALUE_REQUIRED, 'The name of the backup file to restore into the database.'],
        ];
    }

}
