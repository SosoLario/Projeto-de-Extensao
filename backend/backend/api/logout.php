<?php

require_once __DIR__ . '/../bootstrap.php';

exigirSessao();
exigirCsrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'] ?? '', (bool) $cookie['secure'], (bool) $cookie['httponly']);
}
session_destroy();

Resposta::sucesso();
