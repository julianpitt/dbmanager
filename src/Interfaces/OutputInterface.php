<?php

namespace JulianPitt\DBManager\Interfaces;

interface OutputInterface
{
    public function info($msg);

    public function warn($msg);

    public function comment($msg);
}