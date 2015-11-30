<?php

namespace JulianPitt\DBManager\Interfaces;

interface DatabaseHandler
{

    public function dump($destinationFile);
    public function checkIntegrity();
    public function getFileExtension();

}