<?php

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Resposta::erro('Método não permitido.', 405);
}

$dados = corpoRequisicao();
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

if ($email === '' || $senha === '') {
    Resposta::erro('Informe e-mail e senha.', 422);
}

$auth = new Auth();
$gestor = $auth->buscarPorEmail($email);

if (!$gestor || !password_verify($senha, $gestor['senha_hash'])) {
    Resposta::erro('E-mail ou senha incorretos.', 401);
}

// Renova o identificador de sessão para evitar fixação de sessão.
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$_SESSION['usuario_id'] = $gestor['id']; $_SESSION['usuario_nome'] = $gestor['nome']; $_SESSION['usuario_tipo'] = $gestor['tipo'];
if ($gestor['tipo'] === 'gestor') { $_SESSION['gestor_id'] = $gestor['id']; $_SESSION['gestor_nome'] = $gestor['nome']; $_SESSION['gestor_perfil'] = $gestor['perfil'] ?: 'administrador'; }

Resposta::sucesso([
    'csrf_token' => $_SESSION['csrf_token'],
    'usuario' => [
        'id'   => $gestor['id'],
        'nome' => $gestor['nome'], 'tipo' => $gestor['tipo'], 'perfil' => $gestor['perfil'] ?? null,
    ],
]);
