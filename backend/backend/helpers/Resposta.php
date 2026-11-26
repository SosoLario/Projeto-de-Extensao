<?php

/**
 * Resposta
 * Padroniza o formato das respostas JSON enviadas pela API.
 */
class Resposta
{
    public static function sucesso(array $dados = [], int $codigoHttp = 200): void
    {
        http_response_code($codigoHttp);
        echo json_encode(array_merge(['sucesso' => true], $dados));
        exit;
    }

    public static function erro(string $mensagem, int $codigoHttp = 400): void
    {
        http_response_code($codigoHttp);
        echo json_encode(['sucesso' => false, 'mensagem' => $mensagem]);
        exit;
    }
}
