<?php
// conexao.php — Conexão com MySQL usando PDO (procedural simples)

// Ajuste conforme seu ambiente:
$host = "localhost";
$db   = "u920914488_smth";
$user = "u920914488_smth";
$pass = "pX0V>i8~T";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // define timezone na sessão SQL
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
