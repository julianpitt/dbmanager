<?php

namespace JulianPitt\DBManager\Interfaces;

interface QueryInterface
{

    public function getDatabases();
    public function getTablesAndColumns($schema);

}