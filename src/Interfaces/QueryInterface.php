<?php

namespace JulianPitt\DBManager\Interfaces;

interface QueryHandler
{

    public function getDatabases();
    public function getTablesAndColumns($schema);

}