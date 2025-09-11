<?php
session_start();
require_once '../../dist/assets/conexao.php'; // Conexão PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nomeFantasia = trim($_POST['nome_fantasia']);
    $razaoSocial  = trim($_POST['razao_social']);
    $cnpj         = trim($_POST['cnpj']);
    $telefone     = trim($_POST['telefone']);
    $email        = trim($_POST['email']);
    $endereco     = trim($_POST['endereco']);
    $cidade       = trim($_POST['cidade']);
    $estado       = trim($_POST['estado']);
    $cep          = trim($_POST['cep']);
    $status       = isset($_POST['status']) ? (int) $_POST['status'] : 1;

    try {
        // 1️⃣ Cadastrar a nova empresa
        $sql = "INSERT INTO empresas 
                (nome_fantasia, razao_social, cnpj, telefone, email, endereco, cidade, estado, cep, status)
                VALUES 
                (:nome_fantasia, :razao_social, :cnpj, :telefone, :email, :endereco, :cidade, :estado, :cep, :status)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome_fantasia' => $nomeFantasia,
            ':razao_social'  => $razaoSocial,
            ':cnpj'          => $cnpj,
            ':telefone'      => $telefone,
            ':email'         => $email,
            ':endereco'      => $endereco,
            ':cidade'        => $cidade,
            ':estado'        => $estado,
            ':cep'           => $cep,
            ':status'        => $status
        ]);

        // 2️⃣ Pegar o ID da empresa recém criada
        $empresaId = $pdo->lastInsertId();

        // 3️⃣ Procurar um usuário sem empresa vinculada
        $checkUser = $pdo->query("SELECT id FROM usuarios WHERE empresa_id IS NULL LIMIT 1")->fetch();

        if ($checkUser) {
            // 4️⃣ Atualizar o empresa_id do usuário encontrado
            $update = $pdo->prepare("UPDATE usuarios SET empresa_id = :empresaId WHERE id = :userId");
            $update->execute([
                ':empresaId' => $empresaId,
                ':userId'    => $checkUser['id']
            ]);
        }

        // 5️⃣ Definir empresa_id na sessão
        $_SESSION['empresa_id'] = $empresaId;

        // 6️⃣ Redirecionar após sucesso
        header("Location: ../empresa.php?sucesso=1");
        exit;

    } catch (PDOException $e) {
        echo "Erro ao cadastrar empresa: " . $e->getMessage();
    }

} else {
    echo "Método inválido.";
}
?>
