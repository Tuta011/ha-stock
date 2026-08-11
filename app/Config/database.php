<?php

$host = '127.0.0.1';
$port = '3307';
$dbname = 'ha_stock';
$user = 'ha_stock_app';
$password = 'Isa160407@@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        $options
    );

} catch (PDOException $e) {

    die('Erro ao conectar com o banco de dados.');

}