<?php

require_once __DIR__ . '/config.php';

/**
 * Database
 * Responsável por fornecer uma única conexão PDO reutilizável
 * durante o ciclo de vida da requisição (padrão Singleton).
 */
class Database
{
    private static ?PDO $instancia = null;

    private function __construct()
    {
        // Construtor privado: impede instanciar diretamente com "new".
    }

    public static function conectar(): PDO
    {
        if (self::$instancia === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            try {
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'sucesso'  => false,
                    'mensagem' => 'Não foi possível conectar ao banco de dados.'
                ]);
                exit;
            }
        }

        return self::$instancia;
    }
}
