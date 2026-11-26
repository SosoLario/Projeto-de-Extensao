<?php
require_once __DIR__ . '/../bootstrap.php';
$dados = corpoRequisicao();
$acao = $dados['acao'] ?? 'solicitar';
$auth = new Auth();
if ($acao === 'solicitar') {
    $email = trim($dados['email'] ?? '');
    $usuario = $auth->buscarPorEmail($email);
    if (!$usuario) Resposta::erro('Não encontramos uma conta com este e-mail.', 404);
    $codigo = $auth->criarCodigo($usuario['tipo'], $usuario['id']);
    $assunto = 'Código para recuperar sua senha - ClassManager';
    $mensagem = "Olá, {$usuario['nome']}. Seu código de recuperação é: $codigo. Ele expira em 15 minutos.";
    if (!Email::enviar($usuario['email'], $assunto, $mensagem)) Resposta::erro('Não foi possível enviar o e-mail. Verifique a configuração de e-mail do servidor.', 503);
    Resposta::sucesso(['mensagem' => 'Enviamos um código de 5 dígitos para seu e-mail.']);
}
if ($acao === 'verificar') {
    $email = trim($dados['email'] ?? '');
    $codigo = trim($dados['codigo'] ?? '');
    $usuario = $auth->buscarPorEmail($email);
    if (!$usuario || !preg_match('/^\\d{5}$/', $codigo) || !$auth->codigoEhValido($usuario['tipo'], $usuario['id'], $codigo)) Resposta::erro('Código inválido ou expirado. Tente novamente ou solicite outro código.', 422);
    Resposta::sucesso(['mensagem' => 'Código confirmado.']);
}
if ($acao === 'redefinir') {
    $email = trim($dados['email'] ?? '');
    $codigo = trim($dados['codigo'] ?? '');
    $senha = $dados['senha'] ?? '';
    $usuario = $auth->buscarPorEmail($email);
    if (!$usuario || !preg_match('/^\\d{5}$/', $codigo) || !$auth->validarCodigo($usuario['tipo'], $usuario['id'], $codigo)) Resposta::erro('Código inválido ou expirado. Solicite um novo código e tente novamente.', 422);
    if (strlen($senha) < 6) Resposta::erro('A nova senha deve ter pelo menos 6 caracteres.', 422);
    $auth->alterarSenha($usuario['tipo'], $usuario['id'], $senha);
    Resposta::sucesso(['mensagem' => 'Senha alterada com sucesso.']);
}
Resposta::erro('Ação inválida.', 422);
