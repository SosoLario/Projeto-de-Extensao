<?php
class Auth
{
    private PDO $db;
    public function __construct() { $this->db = Database::conectar(); }
    public function buscarPorEmail(string $email): ?array {
        foreach (['gestores' => 'gestor', 'alunos' => 'aluno', 'professores' => 'professor'] as $tabela => $tipo) {
            $extra = $tabela === 'gestores' ? ', perfil, status conta_status' : ', NULL perfil, status conta_status';
            $stmt = $this->db->prepare("SELECT id, nome, email, senha_hash$extra FROM $tabela WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($usuario = $stmt->fetch()) { if (($usuario['conta_status']??'ativo')!=='ativo') continue; $usuario['tipo'] = $tipo; return $usuario; }
        }
        return null;
    }
    public function alterarSenha(string $tipo, int $id, string $senha): void {
        $tabela = ['gestor' => 'gestores', 'aluno' => 'alunos', 'professor' => 'professores'][$tipo] ?? null;
        if (!$tabela) throw new InvalidArgumentException('Perfil inválido.');
        $stmt = $this->db->prepare("UPDATE $tabela SET senha_hash = :senha WHERE id = :id");
        $stmt->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $id]);
    }
    public function criarCodigo(string $tipo, int $id): string {
        $this->db->prepare('UPDATE codigos_recuperacao SET usado_em = NOW() WHERE tipo = :tipo AND usuario_id = :id AND usado_em IS NULL')->execute(['tipo'=>$tipo,'id'=>$id]);
        $codigo = (string) random_int(10000, 99999);
        $stmt = $this->db->prepare('INSERT INTO codigos_recuperacao (tipo,usuario_id,codigo_hash,expira_em) VALUES (:tipo,:id,:hash,DATE_ADD(NOW(), INTERVAL 15 MINUTE))');
        $stmt->execute(['tipo'=>$tipo,'id'=>$id,'hash'=>password_hash($codigo,PASSWORD_DEFAULT)]);
        return $codigo;
    }
    public function codigoEhValido(string $tipo, int $id, string $codigo): bool {
        $stmt = $this->db->prepare('SELECT * FROM codigos_recuperacao WHERE tipo=:tipo AND usuario_id=:id AND usado_em IS NULL AND expira_em > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute(['tipo'=>$tipo,'id'=>$id]); $registro = $stmt->fetch();
        return (bool) ($registro && password_verify($codigo, $registro['codigo_hash']));
    }
    public function validarCodigo(string $tipo, int $id, string $codigo): bool {
        if (!$this->codigoEhValido($tipo, $id, $codigo)) return false;
        $stmt = $this->db->prepare('UPDATE codigos_recuperacao SET usado_em=NOW() WHERE tipo=:tipo AND usuario_id=:id AND usado_em IS NULL');
        $stmt->execute(['tipo'=>$tipo,'id'=>$id]);
        return true;
    }
}
