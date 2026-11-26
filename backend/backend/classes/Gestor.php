<?php

/**
 * Gestor
 * Representa um usuário da equipe de gestão e concentra a lógica de autenticação.
 */
class Gestor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, email, senha_hash FROM gestores WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $gestor = $stmt->fetch();
        return $gestor ?: null;
    }

    /**
     * Confere a senha em texto puro com o hash salvo no banco.
     */
    public function verificarSenha(string $senha, string $hashSalvo): bool
    {
        return password_verify($senha, $hashSalvo);
    }
}
