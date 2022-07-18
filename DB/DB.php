<?php

namespace DB;

use PDO;

class DB extends PDO
{
    const HOST = 'localhost';
    const DB_NAME = 'sms';
    const USER = 'dron';
    const PASSWORD = '';
    const PORT = 5433;

    public static function connection(): PDO
    {
        $str = 'pgsql:host='.self::HOST.';port='.self::PORT.';dbname='.self::DB_NAME;

        return new PDO($str, self::USER, self::PASSWORD);
    }
}