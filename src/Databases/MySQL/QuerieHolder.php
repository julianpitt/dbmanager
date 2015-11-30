<?php

namespace JulianPitt\DBManager\Databases\MySQL;

use DB;
use JulianPitt\DBManager\Interfaces\QueryInterface;

class MySqlQueries implements QueryInterface
{


    public function getDatabases()
    {
        return DB::select(DB::raw(
            "SHOW SCHEMAS"
        ));
    }


    public function getTablesAndColumns($schema)
    {
        return DB::select(DB::raw(
            "select TABLE_NAME, COLUMN_NAME
            from information_schema.columns
            where table_schema = ".$schema."
            order by table_name,ordinal_position"
        ));
    }

}