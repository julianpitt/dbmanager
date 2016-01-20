<?php namespace JulianPitt\DBManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Helpers\BackupHelper;
use Symfony\Component\Console\Input\InputOption;
use ZipArchive;

class BackupCommands extends Command
{

    protected $name = 'dbman:backup';

    protected $description = 'Run the backup';

    protected $backupHelper = null;

    public function fire()
    {

        $this->backupHelper = new BackupHelper();

        $this->info('Starting backup');

        try {
            /**
             * Check if the user has access to read and write files, does the user have permission?
             */

        foreach ($this->getTargetFileSystems() as $fileSystem) {
            $this->backupHelper->checkIfUserHasPermissions($fileSystem);
        }


        /**
         * Get all the tables that need to be backed up into their filenames from the dump
         */

        $files = $this->getAllTablesToBeBackedUp();

        if (count($files) <= 0) {
            $this->info('Nothing to backup');
            return true;
        }


        /**
         * Compress the backup file if needed
         */

        $compress = config('db-manager.output.compress');

        if (!isset($compress) || (isset($compress) && !is_bool($compress))) {
            $compress = true;
        }

        $this->info("Compress output files: " . ($compress ? "True" : "False"));

        if ($compress) {

            $backupZipFile = $this->createZip($files);

            if (filesize($backupZipFile) == 0) {
                $this->warn('The zipfile that will be backed up has a filesize of zero.');
            }

        }


        /**
         * Delete previous backups if needed
         */

        $keepLastOnly = config('db-manager.output.keeplastonly');

        if(!isset($keepLastOnly) || (isset($keepLastOnly) && !is_bool($keepLastOnly))) {
            $keepLastOnly = false;
        }

        $this->info("Keep last output only: " . ($keepLastOnly ? "True" : "False"));

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
            $this->warn('An Error occurred');
            $this->warn("Code: " . $e->getCode());
            $this->warn("Message: \n". $e->getMessage());
            $this->warn("Starck Trace: \n" . $e->getTraceAsString());
        }

        /**
         * Delete the temporary files
         */

        $this->info("Cleaning up temporary files");

        if(!$this->backupHelper->cleanUpTemporaryFiles()) {
            $this->warn('Unable to remove temporary files, may need to be manually removed');
        }

        $this->info('Backup successfully completed');

        return true;
    }

    /**
     * Extra parameters that can be used to change certain settings of the database backup or restore
     *
     * @return array
     */
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

}
