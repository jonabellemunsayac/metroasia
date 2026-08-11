<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'metroasia';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function db(bool $withDatabase = true): PDO
{
    $database = $withDatabase ? ';dbname=' . DB_NAME : '';
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . $database . ';charset=' . DB_CHARSET;

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
