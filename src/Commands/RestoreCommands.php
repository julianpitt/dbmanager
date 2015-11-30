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
        $this->info('Start Restore');

        $this->info('App Path' . base_path());

        $this->info('Restore successfully completed');

        return true;
    }

    protected function getAllFilesToBeBackedUp()
    {
        $files = [];

        $files[] = ['realFile' => $this->getDatabaseDump($files), 'fileInZip' => 'dump.sql'];

        return $files;
    }

    protected function createZip($files)
    {
        $this->comment('Start zipping '.count($files).' files...');

        $tempZipFile = tempnam(sys_get_temp_dir(), "db-manager-backup");

        $zip = new ZipArchive();
        $zip->open($tempZipFile, ZipArchive::CREATE);

        foreach ($files as $file) {
            if (file_exists($file['realFile'])) {
                $zip->addFile($file['realFile'], $file['fileInZip']);
            }
        }

        $zip->close();

        $this->comment('Zip created!');

        return $tempZipFile;
    }

    protected function copyFile($file, $disk, $destination)
    {
        $destinationDirectory = dirname($destination);

        $disk->makeDirectory($destinationDirectory);

        /*
         * The file could be quite large. Use a stream to copy it
         * to the target disk to avoid memory problems
         */
        $disk->getDriver()->writeStream($destination, fopen($file, 'r+'));
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

    public function copyFileToFileSystem($file, $fileSystem)
    {
        $this->comment('Start uploading backup to '.$fileSystem.'-filesystem...');

        $disk = Storage::disk($fileSystem);

        $backupFilename = $this->getBackupDestinationFileName();

        $this->copyFile($file, $disk, $backupFilename, $fileSystem == 'local');

        $this->comment('Backup stored on '.$fileSystem.'-filesystem in file "'.$backupFilename.'"');
    }

    protected function getOptions()
    {
        return [
            ['prefix', null, InputOption::VALUE_REQUIRED, 'The name of the zip file will get prefixed with this string.'],
            ['suffix', null, InputOption::VALUE_REQUIRED, 'The name of the zip file will get suffixed with this string.'],
        ];
    }

    protected function getDatabaseDump()
    {
        $databaseBackupHandler = app()->make('JulianPitt\DBManager\Helpers\BackupHelper');

        $filesToBeBackedUp = $databaseBackupHandler->getFilesToBeBackedUp();

        if (count($filesToBeBackedUp) != 1) {
            throw new \Exception('could not backup db');
        }

        $this->comment('Database dumped');

        return $databaseBackupHandler->getFilesToBeBackedUp()[0];
    }

}
