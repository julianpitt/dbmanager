<?php namespace JulianPitt\DBManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\InputOption;
use ZipArchive;

class RestoreCommands extends Command
{

    protected $name = 'dbman:restore';

    protected $description = 'Restore the last backup';

    public function fire()
    {
        $this->info('Starting Restore');

        $this->restoreLastBackup();

        $this->info('Restore successfully completed');

        return true;
    }

    protected function restoreLastBackup()
    {

    }

    protected function getAllFilesToBeBackedUp()
    {
        $files = [];

        $files[] = ['realFile' => $this->getDatabaseDump($files), 'fileInZip' => 'dump.sql'];

        return $files;
    }

    protected function getTargetFileSystems()
    {
        $fileSystems = config('db-manager.output.filesystem');

        if (is_array($fileSystems)) {
            return $fileSystems;
        }

        return [$fileSystems];
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
