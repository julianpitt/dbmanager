<?php

namespace JulianPitt\DBManager\Interfaces;

interface DatabaseHandler
{

    public function dump();
    public function checkIntegrity();
    public function getFileExtension();

}