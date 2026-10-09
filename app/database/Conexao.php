<?php

class Conexao {
    private static ?PDO $conexao = null;

    private function __construct() {}

    public static function getConexao(): PDO {
        if (!isset(self::$conexao)) {
            try {
                $host = $_ENV["DB_HOST"] ?? "localhost";
                $dbname = $_ENV["DB_NAME"] ?? "monstroguelselnius";
                $charset = $_ENV["DB_CHARSET"] ?? "utf8mb4";
                $usuario = $_ENV["DB_USER"] ?? "root";
                $senha = $_ENV["DB_SENHA"] ?? "";

                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

                self::$conexao = new PDO($dsn, $usuario, $senha);
                self::$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die ("Erro de conexão no banco: " . $e->getMessage());
            }
        }

        return self::$conexao;
    }
}