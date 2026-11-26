<?php
require_once __DIR__ . '/../bootstrap.php';

$usuario = exigirSessao();
$tipo = $usuario['tipo'];
$tabela = ['aluno' => 'alunos', 'professor' => 'professores'][$tipo] ?? null;
if (!$tabela) Resposta::erro('Esta área está disponível apenas para alunos e professores.', 403);

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo === 'GET') {
    $campos = $tipo === 'aluno' ? 'data_matricula' : 'registro_profissional';
    $stmt = Database::conectar()->prepare("SELECT nome,email,telefone,foto_perfil,status,$campos FROM $tabela WHERE id=? LIMIT 1");
    $stmt->execute([$usuario['id']]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dados) Resposta::erro('Conta não encontrada.', 404);

    if ($tipo === 'aluno') {
        $identificadorRotulo = 'Matrícula desde';
        $identificador = $dados['data_matricula'] ? date('d/m/Y', strtotime($dados['data_matricula'])) : 'Sem data cadastrada';
    } else {
        $identificadorRotulo = 'Registro profissional';
        $identificador = trim((string)($dados['registro_profissional'] ?? '')) ?: 'Não informado';
    }

    Resposta::sucesso([
        'perfil' => ['nome' => $dados['nome'], 'email' => $dados['email'], 'telefone' => $dados['telefone'] ?? ''],
        'identificador_rotulo' => $identificadorRotulo,
        'identificador' => $identificador,
        'tipo' => $tipo,
        'id' => $usuario['id'],
        'status' => $dados['status'],
        'foto_url' => $dados['foto_perfil'] ? '../backend/uploads/perfis/' . rawurlencode($dados['foto_perfil']) : null,
    ]);
}

if ($metodo !== 'POST') Resposta::erro('Método não permitido.', 405);
exigirCsrf();
$entrada = corpoRequisicao();
$acao = (string)($entrada['acao'] ?? $_POST['acao'] ?? '');
$db = Database::conectar();

if ($acao === 'perfil') {
    $nome = trim((string)($entrada['nome'] ?? ''));
    $email = strtolower(trim((string)($entrada['email'] ?? '')));
    $telefoneOriginal = trim((string)($entrada['telefone'] ?? ''));
    $digitos = preg_replace('/\D/', '', $telefoneOriginal);

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120) Resposta::erro('Informe um nome entre 3 e 120 caracteres.', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) Resposta::erro('Informe um e-mail válido.', 422);
    if ($telefoneOriginal !== '' && !in_array(strlen($digitos), [10, 11], true)) Resposta::erro('Informe telefone com DDD e 10 ou 11 dígitos.', 422);
    if (emailEmUsoSistema($email, $tabela, $usuario['id'])) Resposta::erro('Este e-mail já está sendo usado em outra conta.', 409);

    $telefone = '';
    if ($digitos !== '') {
        $telefone = strlen($digitos) === 11
            ? sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7))
            : sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6));
    }

    $stmt = $db->prepare("UPDATE $tabela SET nome=?,email=?,telefone=? WHERE id=?");
    $stmt->execute([$nome, $email, $telefone ?: null, $usuario['id']]);
    $_SESSION['usuario_nome'] = $nome;
    Resposta::sucesso(['mensagem' => 'Dados pessoais atualizados.', 'perfil' => ['nome' => $nome, 'email' => $email, 'telefone' => $telefone]]);
}

if ($acao === 'senha') {
    $atual = (string)($entrada['senha_atual'] ?? '');
    $nova = (string)($entrada['nova_senha'] ?? '');
    $confirmacao = (string)($entrada['confirmacao_senha'] ?? '');
    if ($atual === '') Resposta::erro('Informe sua senha atual.', 422);
    if (strlen($nova) < 6) Resposta::erro('A nova senha precisa ter pelo menos 6 caracteres.', 422);
    if (!hash_equals($nova, $confirmacao)) Resposta::erro('A confirmação não corresponde à nova senha.', 422);

    $stmt = $db->prepare("SELECT senha_hash FROM $tabela WHERE id=?");
    $stmt->execute([$usuario['id']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($atual, $hash)) Resposta::erro('A senha atual está incorreta.', 422);

    $stmt = $db->prepare("UPDATE $tabela SET senha_hash=? WHERE id=?");
    $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), $usuario['id']]);
    Resposta::sucesso(['mensagem' => 'Senha atualizada com sucesso.']);
}

if ($acao === 'foto' || $acao === 'remover_foto') {
    $stmt = $db->prepare("SELECT foto_perfil FROM $tabela WHERE id=?");
    $stmt->execute([$usuario['id']]);
    $fotoAnterior = $stmt->fetchColumn();
    $diretorio = __DIR__ . '/../uploads/perfis';

    if ($acao === 'remover_foto') {
        $db->prepare("UPDATE $tabela SET foto_perfil=NULL WHERE id=?")->execute([$usuario['id']]);
        if ($fotoAnterior) {
            $arquivoAnterior = $diretorio . DIRECTORY_SEPARATOR . basename((string)$fotoAnterior);
            if (is_file($arquivoAnterior)) @unlink($arquivoAnterior);
        }
        Resposta::sucesso(['mensagem' => 'Foto removida.', 'foto_url' => null]);
    }

    $arquivo = $_FILES['foto'] ?? null;
    if (!$arquivo || !isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        Resposta::erro('Selecione uma foto válida para enviar.', 422);
    }
    if ((int)$arquivo['size'] < 1 || (int)$arquivo['size'] > 2 * 1024 * 1024) {
        Resposta::erro('A foto deve ter no máximo 2 MB.', 422);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensoes[$mime]) || @getimagesize($arquivo['tmp_name']) === false) {
        Resposta::erro('Use uma imagem JPG, PNG ou WebP.', 422);
    }
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        Resposta::erro('Não foi possível preparar a pasta para salvar a foto.', 500);
    }

    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeArquivo;
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        Resposta::erro('Não foi possível salvar a foto. Verifique a permissão da pasta do projeto.', 500);
    }
    try {
        $db->prepare("UPDATE $tabela SET foto_perfil=? WHERE id=?")->execute([$nomeArquivo, $usuario['id']]);
    } catch (Throwable $erro) {
        @unlink($destino);
        throw $erro;
    }
    if ($fotoAnterior) {
        $arquivoAnterior = $diretorio . DIRECTORY_SEPARATOR . basename((string)$fotoAnterior);
        if (is_file($arquivoAnterior)) @unlink($arquivoAnterior);
    }
    Resposta::sucesso(['mensagem' => 'Foto de perfil atualizada.', 'foto_url' => '../backend/uploads/perfis/' . rawurlencode($nomeArquivo)]);
}

Resposta::erro('Ação inválida.', 422);
