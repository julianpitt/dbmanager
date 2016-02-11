<?php namespace JulianPitt\DBManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JulianPitt\DBManager\Classes\ConsoleOutput;
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
        $options = $this->option();

        $this->backupHelper = new BackupHelper($options);

        $this->backupHelper->setOutput(new ConsoleOutput($this));

        $this->backupHelper->backup();

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
            ['prefix', 'p', InputOption::VALUE_REQUIRED, 'The name of the file will get prefixed with this string.'],
            ['suffix', 's', InputOption::VALUE_REQUIRED, 'The name of the file will get suffixed with this string.'],
            ['filename', 'f', InputOption::VALUE_REQUIRED, 'The name of the file to output.'],
            ['type', 't', InputOption::VALUE_REQUIRED, 'The type of dump to perform on the database ("datanadstructure/dataonly/structureonly)'],
            ['keeplastonly', 'k', InputOption::VALUE_REQUIRED, 'Keep the last backup or delete all previous backups (true/false)'],
            ['compress', 'c', InputOption::VALUE_REQUIRED, 'Compress the output file to .zip (true/false)'],
            ['checkPermissions', 'd', InputOption::VALUE_REQUIRED, 'Enable an initial check to see if the backup will run correctly', true],
        ];
    }

}
