<?php

namespace JulianPitt\DBManager\Classes;

use JulianPitt\DBManager\Interfaces\OutputInterface;

class FileOutput implements OutputInterface
{

    public function __construct()
    {
        //TODO instantiate a file in storage/logs/db-manager
    }

    public function info($msg)
    {
        \Log::info($msg);
    }

    public function warn($msg)
    {
        \Log::warning($msg);
    }

    public function comment($msg)
    {
        \Log::notice($msg);
    }
}