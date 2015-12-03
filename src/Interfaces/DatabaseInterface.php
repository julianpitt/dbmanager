<?php

namespace JulianPitt\DBManager\Interfaces;

interface DatabaseHandler
{

    public function dump($destinationFile);
    public function checkBackupIntegrity($callIngClass);
    public function checkRestoreIntegrity($callIngClass);
    public function getFileExtension();

}