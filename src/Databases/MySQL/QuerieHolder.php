<?php

namespace JulianPitt\DBManager\Databases\MySQL;

use \DB;
use JulianPitt\DBManager\Interfaces\QueryHandler;

class MySqlQueries implements QueryHandler
{

    public function getDatabases()
    {
        return \DB::select("SHOW SCHEMAS");
    }


    public function getTablesAndColumns($schema)
    {
        /*return DB::select("select TABLE_NAME, COLUMN_NAME
            from information_schema.columns
            where table_schema = '".$schema."'
            order by table_name,ordinal_position")->groupBy('table_name');*/

        return DB::table("information_schema.columns")
            ->select('TABLE_NAME', 'COLUMN_NAME')
            ->where('table_schema', $schema)
            ->orderBy('TABLE_NAME', 'ORDINAL_POSITION')
            ->get();
    }

}