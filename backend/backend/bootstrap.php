<?php

/**
 * Bootstrap carregado por todos os endpoints da API:
 * inicia a sessão, define os cabeçalhos padrão e carrega as classes.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('classmanager_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: ' . APP_ORIGIN);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

set_exception_handler(static function (Throwable $erro): void {
    error_log('[ClassManager] ' . $erro->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['sucesso'=>false,'mensagem'=>'Ocorreu um erro interno. Confira se as duas etapas do banco foram executadas e consulte o log do PHP.'], JSON_UNESCAPED_UNICODE);
    exit;
});

// Requisição de "preflight" do navegador — apenas confirma e encerra.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/helpers/Resposta.php';
require_once __DIR__ . '/helpers/Email.php';
require_once __DIR__ . '/classes/Gestor.php';
require_once __DIR__ . '/classes/Aluno.php';
require_once __DIR__ . '/classes/Professor.php';
require_once __DIR__ . '/classes/Horario.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Curso.php';
require_once __DIR__ . '/classes/Matricula.php';
require_once __DIR__ . '/classes/Financeiro.php';

function validarDataISO(string $data): bool
{
    $dt = DateTime::createFromFormat('!Y-m-d', $data);
    return $dt !== false && $dt->format('Y-m-d') === $data;
}

function valorMonetario($valor): float
{
    if (is_string($valor)) {
        $valor = trim($valor);
        if (str_contains($valor, ',')) $valor = str_replace(',', '.', str_replace('.', '', $valor));
    }
    if (!is_numeric($valor)) throw new InvalidArgumentException('Informe um valor monetário válido.');
    $numero = round((float)$valor, 2);
    if (!is_finite($numero) || $numero < 0 || $numero > 99999999) throw new InvalidArgumentException('O valor informado está fora do limite permitido.');
    return $numero;
}

/** Impede que o mesmo e-mail identifique perfis diferentes no login único. */
function emailEmUsoSistema(string $email, string $tabelaAtual, ?int $ignorarId = null): bool
{
    $tabelas=['gestores','alunos','professores'];
    if(!in_array($tabelaAtual,$tabelas,true))throw new InvalidArgumentException('Tabela de usuário inválida.');
    $db=Database::conectar();
    foreach($tabelas as $tabela){$sql="SELECT id FROM $tabela WHERE email=:email";$params=['email'=>$email];if($tabela===$tabelaAtual&&$ignorarId!==null){$sql.=' AND id<>:id';$params['id']=$ignorarId;}$sql.=' LIMIT 1';$s=$db->prepare($sql);$s->execute($params);if($s->fetchColumn())return true;}
    return false;
}

/**
 * Garante que existe uma sessão de gestão válida.
 * Deve ser chamada no topo de todo endpoint protegido.
 */
function exigirSessaoGestao(): array
{
    if (empty($_SESSION['gestor_id'])) {
        Resposta::erro('Sessão expirada. Faça login novamente.', 401);
    }
    $stmt=Database::conectar()->prepare("SELECT perfil,status FROM gestores WHERE id=?");
    $stmt->execute([$_SESSION['gestor_id']]);$conta=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$conta||$conta['status']!=='ativo'){$_SESSION=[];session_destroy();Resposta::erro('Conta desativada. Entre em contato com a administração.',401);}
    $_SESSION['gestor_perfil']=$conta['perfil'];
    return [
        'id'   => $_SESSION['gestor_id'],
        'nome' => $_SESSION['gestor_nome'],
        'perfil' => $conta['perfil'],
    ];
}

function exigirPerfilGestao(array $perfis): array
{
    $gestor=exigirSessaoGestao();
    if (!in_array($gestor['perfil'],$perfis,true)) Resposta::erro('Seu perfil não permite esta operação.',403);
    return $gestor;
}

function exigirSessao(): array
{
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['usuario_tipo'])) {
        Resposta::erro('Sessão expirada. Faça login novamente.', 401);
    }
    $tabela=['gestor'=>'gestores','aluno'=>'alunos','professor'=>'professores'][$_SESSION['usuario_tipo']]??null;
    if(!$tabela)Resposta::erro('Perfil inválido.',401);
    $stmt=Database::conectar()->prepare("SELECT status FROM $tabela WHERE id=?");$stmt->execute([$_SESSION['usuario_id']]);
    $status=$stmt->fetchColumn();if($status===false||$status!=='ativo')Resposta::erro('Conta inativa ou sessão inválida.',401);
    return ['id' => (int) $_SESSION['usuario_id'], 'nome' => $_SESSION['usuario_nome'], 'tipo' => $_SESSION['usuario_tipo'], 'perfil' => $_SESSION['gestor_perfil'] ?? null];
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function exigirCsrf(): void
{
    $recebido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($recebido === '' || !hash_equals(tokenCsrf(), $recebido)) {
        Resposta::erro('Sessão expirada. Atualize a página e tente novamente.', 419);
    }
}

/**
 * Lê e decodifica o corpo JSON da requisição.
 */
function corpoRequisicao(): array
{
    $dados = json_decode(file_get_contents('php://input'), true);
    return is_array($dados) ? $dados : [];
}
