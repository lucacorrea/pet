<?php
session_start();
require_once '../../dist/assets/conexao.php'; // Arquivo com a conexão PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome         = trim($_POST['nome']);
    $email        = trim($_POST['email']);
    $cpf          = trim($_POST['cpf']);
    $telefone     = trim($_POST['telefone']);
    $senha        = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $perfil       = trim($_POST['perfil']);
    $status       = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $nomeEmpresa  = trim($_POST['empresa']);
    $cnpjEmpresa  = trim($_POST['cnpj']);
    $endereco     = trim($_POST['endereco']);

    try {
        // Busca empresa pelo CNPJ
        $stmtEmpresa = $pdo->prepare("SELECT id FROM empresas WHERE cnpj = :cnpj");
        $stmtEmpresa->execute([':cnpj' => $cnpjEmpresa]);
        $empresaId = $stmtEmpresa->fetchColumn();

        if (!$empresaId) {
            // Cria nova empresa se não existir
            $insereEmpresa = $pdo->prepare(
                "INSERT INTO empresas (nome_fantasia, cnpj, endereco) VALUES (:nome, :cnpj, :endereco)"
            );
            $insereEmpresa->execute([
                ':nome'    => $nomeEmpresa,
                ':cnpj'    => $cnpjEmpresa,
                ':endereco'=> $endereco
            ]);
            $empresaId = $pdo->lastInsertId();
        }

        // Cadastra usuário vinculado à empresa
        $sql = "INSERT INTO usuarios (empresa_id, nome, email, cpf, telefone, senha, perfil, status)
                VALUES (:empresaId, :nome, :email, :cpf, :telefone, :senha, :perfil, :status)";

        $params = [
            ':empresaId' => $empresaId,
            ':nome'      => $nome,
            ':email'     => $email,
            ':cpf'       => $cpf,
            ':telefone'  => $telefone,
            ':senha'     => $senha,
            ':perfil'    => $perfil,
            ':status'    => $status
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ../usuarios.php?sucesso=1");
        exit;

    } catch (PDOException $e) {
        echo "Erro ao cadastrar usuário: " . $e->getMessage();
    }
} else {
    echo "Método inválido.";
}
?>
