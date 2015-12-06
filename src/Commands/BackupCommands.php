<?php namespace JulianPitt\DBManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Helpers\FileHelper;
use Symfony\Component\Console\Input\InputOption;
use ZipArchive;

class BackupCommands extends Command
{

    protected $name = 'dbman:backup';

    protected $description = 'Run the backup';

    public function fire()
    {
        $this->info('Starting backup');

        $files = $this->getAllTablesToBeBackedUp();

        if (count($files) <= 0) {
            $this->info('Nothing to backup');
            return true;
        }

        $compress = config('db-manager.output.compress');

        if(!isset($compress) || (isset($compress) && !is_bool($compress))) {
            $compress = true;
        }

        $this->info("Compress output files: " . ($compress ? "True" : "False"));

        //Compress if needed
        if($compress) {

            $backupZipFile = $this->createZip($files);

            if (filesize($backupZipFile) == 0) {
                $this->warn('The zipfile that will be backed up has a filesize of zero.');
            }

        }

        $keepLastOnly = config('db-manager.output.keeplastonly');

        if(!isset($keepLastOnly) || (isset($keepLastOnly) && !is_bool($keepLastOnly))) {
            $keepLastOnly = false;
        }

        $this->info("Kepp last output only: " . ($keepLastOnly ? "True" : "False"));

        //Delete previous backups if needed
        if(!empty($keepLastOnly) && is_bool($keepLastOnly) && $keepLastOnly) {
            foreach ($this->getTargetFileSystems() as $fileSystem) {
                $this->deletePreviousBackups($fileSystem);
            }
        }

        //Copy all the files to your chosen location
        foreach ($this->getTargetFileSystems() as $fileSystem) {
            if($compress) {
                $this->copyFileToFileSystem($backupZipFile, $fileSystem);
            } else {
                foreach($files as $file) {
                    $this->copyFileToFileSystem($file['realFile'], $fileSystem);
                }
            }
        }

        //Unlink the files
        if($compress) {
            unlink($backupZipFile);
        } else {
            foreach($files as $file) {
                unlink($file['realFile']);
            }
        }

        $this->info('Backup successfully completed');

        return true;
    }

    protected function getAllTablesToBeBackedUp()
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

    protected function deleteTargetDirectoryFiles($fileSystem)
    {
        $disk = Storage::disk($fileSystem);

        $destination = config('db-manager.output.location');

        $files =  $disk->allfiles($destination);

        $disk->deleteDirectory($destination);

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
        $backupFilename = $this->getPrefix().$this->getFilename().$this->getSuffix().FileHelper::getOutputFileType();

        return $backupDirectory.'/'.$backupFilename;
    }

    public function getPrefix()
    {
        if ($this->option('prefix') != '') {
            return $this->option('prefix');
        }

        $prefix = $this->getFixSpecialType(config('db-manager.output.prefix'));

        return $prefix;
    }

    public function getFilename()
    {
        if (empty(config('db-manager.output.filename'))) {
            throw new \Exception('Filename not set in config');
        }

        return config('db-manager.output.filename');
    }

    public function getSuffix()
    {
        if ($this->option('suffix') != '') {
            return $this->option('suffix');
        }

        $suffix = $this->getFixSpecialType(config('db-manager.output.suffix'));

        return $suffix;
    }

    public function getFixSpecialType($fix)
    {
        if($fix == 'datetime') {
            return date('YmdHis');
        } else {
            return $fix;
        }
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
            ['prefix', null, InputOption::VALUE_REQUIRED, 'The name of the file will get prefixed with this string.'],
            ['suffix', null, InputOption::VALUE_REQUIRED, 'The name of the file will get suffixed with this string.'],
            ['filename', null, InputOption::VALUE_REQUIRED, 'The name of the file to output.'],
            ['type', null, InputOption::VALUE_REQUIRED, 'The type of dump to perform on the database ("datanadstructure/dataonly/structureonly)'],
            ['keeplastonly', null, InputOption::VALUE_REQUIRED, 'Keep the last backup or delete all previous backups (true/false)'],
            ['compress', null, InputOption::VALUE_REQUIRED, 'Compress the output file to .zip (true/false)'],
        ];
    }

    protected function getDatabaseDump()
    {
        $databaseBackupHandler = app()->make('JulianPitt\DBManager\Helpers\BackupHelper');

        $filesToBeBackedUp = $databaseBackupHandler->getFilesToBeBackedUp($this);

        if (count($filesToBeBackedUp) != 1) {
            throw new \Exception('could not backup db');
        }

        $this->comment('Database dumped');

        return $filesToBeBackedUp[0];
    }

    public function deletePreviousBackups($fileSystem)
    {
        do {

            $name = $this->ask('Are you sure you want to remove all previous backups? [y/n]');
            if(strcasecmp ($name, "y") != 0 && strcasecmp ($name, "n") != 0) {
                $this->info("Invalid response, type 'y' for yes or 'n' for no. Let's try again. This is serious, if it wasn't, I would've use the confirm method to write this");
            }

        } while(strcasecmp ($name, "y") != 0 && strcasecmp ($name, "n") != 0);


        if (strcasecmp ($name, "y") != 0) {
            $this->info("Skipped deleting all previous backups");
            return true;
        }

        $this->info("Deleting all previous backups");

        $deletedFiles = $this->deleteTargetDirectoryFiles($fileSystem);

        foreach($deletedFiles as $file) {
            $this->info("Deleted backup file " . $file);
        }

        return true;

    }

}
