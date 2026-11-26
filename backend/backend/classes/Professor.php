<?php

/**
 * Professor
 * Concentra as operações de CRUD da tabela `professores`.
 */
class Professor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT id,nome,email,especialidade,idiomas,carga_horaria,status FROM professores ORDER BY nome ASC')->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM professores WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $professor = $stmt->fetch();
        return $professor ?: null;
    }

    public function emailJaExiste(string $email, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM professores WHERE email = :email';
        $params = ['email' => $email];
        if ($ignorarId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignorarId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function criar(array $dados): int
    {
        $sql = 'INSERT INTO professores (nome,email,senha_hash,especialidade,idiomas,carga_horaria,status,telefone,cpf,data_nascimento,endereco,formacao,registro_profissional,documentos_comprovacao)
                VALUES (:nome,:email,:senha_hash,:especialidade,:idiomas,:carga_horaria,:status,:telefone,:cpf,:data_nascimento,:endereco,:formacao,:registro_profissional,:documentos_comprovacao)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome'          => $dados['nome'],
            'email'         => $dados['email'],
            'senha_hash' => password_hash(!empty($dados['senha']) ? $dados['senha'] : '123456', PASSWORD_DEFAULT),
            'especialidade' => $dados['especialidade'],
            'idiomas' => $dados['idiomas'],
            'carga_horaria' => $dados['carga_horaria'],
            'status'        => $dados['status'],
            'telefone'=>$dados['telefone']??null,'cpf'=>$dados['cpf']??null,'data_nascimento'=>($dados['data_nascimento']??null)?:null,'endereco'=>$dados['endereco']??null,'formacao'=>$dados['formacao']??null,'registro_profissional'=>$dados['registro_profissional']??null,'documentos_comprovacao'=>$dados['documentos_comprovacao']??null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $sql = 'UPDATE professores
                SET nome=:nome,email=:email,especialidade=:especialidade,idiomas=:idiomas,carga_horaria=:carga_horaria,status=:status,telefone=:telefone,cpf=:cpf,data_nascimento=:data_nascimento,endereco=:endereco,formacao=:formacao,registro_profissional=:registro_profissional,documentos_comprovacao=:documentos_comprovacao';
        $params = [
            'id' => $id,
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'especialidade' => $dados['especialidade'],
            'idiomas' => $dados['idiomas'],
            'carga_horaria' => $dados['carga_horaria'],
            'status' => $dados['status'],
            'telefone' => $dados['telefone'] ?? null,
            'cpf' => $dados['cpf'] ?? null,
            'data_nascimento' => ($dados['data_nascimento'] ?? null) ?: null,
            'endereco' => $dados['endereco'] ?? null,
            'formacao' => $dados['formacao'] ?? null,
            'registro_profissional' => $dados['registro_profissional'] ?? null,
            'documentos_comprovacao' => $dados['documentos_comprovacao'] ?? null,
        ];
        if (!empty($dados['senha'])) {
            $sql .= ',senha_hash=:senha_hash';
            $params['senha_hash'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id=:id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function excluir(int $id): bool
    {
        // Arquivar evita apagar turmas, chamadas e registros associados.
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("UPDATE professores SET status='inativo' WHERE id=:id");$stmt->execute(['id'=>$id]);
            $this->db->prepare("UPDATE matriculas m JOIN horarios h ON h.id=m.horario_id SET m.situacao='cancelada' WHERE h.professor_id=? AND h.status='ativa' AND m.situacao='ativa'")->execute([$id]);
            $this->db->prepare("UPDATE horarios SET status='cancelada' WHERE professor_id=? AND status='ativa'")->execute([$id]);
            $this->db->commit();return $stmt->rowCount()>0;
        } catch(Throwable $e) { if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }

    public function contarAtivos(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM professores WHERE status = 'ativo'")->fetchColumn();
    }
}
