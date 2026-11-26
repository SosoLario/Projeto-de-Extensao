<?php

require_once __DIR__ . '/../bootstrap.php';

$gestor = exigirSessao();

Resposta::sucesso(['usuario' => $gestor, 'csrf_token' => tokenCsrf()]);
