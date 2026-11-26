<?php
/**
 * Configuração de acesso ao banco de dados.
 * Ajuste estes valores para o ambiente onde o sistema será executado
 * (XAMPP, WAMP, servidor de hospedagem, etc).
 */

// Configurações podem vir do ambiente ou de um arquivo .env na raiz do projeto.
$arquivoAmbiente = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
if (is_file($arquivoAmbiente) && is_readable($arquivoAmbiente)) {
    foreach (file($arquivoAmbiente, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linhaAmbiente) {
        $linhaAmbiente = trim($linhaAmbiente);
        if ($linhaAmbiente === '' || str_starts_with($linhaAmbiente, '#') || !str_contains($linhaAmbiente, '=')) continue;
        [$chaveAmbiente, $valorAmbiente] = explode('=', $linhaAmbiente, 2);
        $chaveAmbiente = trim($chaveAmbiente);
        $valorAmbiente = trim(trim($valorAmbiente), "\"'");
        if ($chaveAmbiente !== '' && getenv($chaveAmbiente) === false) putenv($chaveAmbiente . '=' . $valorAmbiente);
    }
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'classmanager');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Origem permitida para requisições do front-end (ajuste em produção)
define('APP_ORIGIN', getenv('APP_ORIGIN') ?: ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));

/**
 * Configuração de envio de e-mail (SMTP), usada por backend/helpers/Email.php.
 * Exemplo com Gmail: gere uma "Senha de app" em myaccount.google.com/apppasswords
 * (precisa da verificação em duas etapas ativada) e use-a em SMTP_PASS.
 * NÃO use a senha normal da sua conta.
 */
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NOME', getenv('SMTP_FROM_NOME') ?: 'ClassManager');
