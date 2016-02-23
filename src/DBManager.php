<?php

namespace JulianPitt\DBManager;

use Illuminate\Support\Facades\Facade;

class DBManager extends Facade {

    protected static function getFacadeAccessor() {
        return 'dbmanager';
    }

}