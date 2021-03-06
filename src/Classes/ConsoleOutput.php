<?php
namespace JulianPitt\DBManager\Classes;

use Illuminate\Console\Command;
use JulianPitt\DBManager\Interfaces\OutputInterface;

class ConsoleOutput implements OutputInterface
{

    protected $cmd = null;

    public function __construct(Command $cmd)
    {
        $this->cmd = $cmd;
    }

    public function info($msg)
    {
        $this->cmd->info($msg);
    }

    public function warn($msg)
    {
        if(method_exists($this->cmd, 'warn')) {
            $this->cmd->warn($msg);
        } else {
            $this->cmd->error($msg);
        }
    }

    public function comment($msg)
    {
        $this->cmd->comment($msg);
    }

    public function ask($msg)
    {
        return $this->cmd->ask($msg);
    }
}
