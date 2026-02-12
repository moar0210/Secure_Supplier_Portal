<?php

class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $db = $config["db"];
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $db["user"], $db["pass"], $options);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
