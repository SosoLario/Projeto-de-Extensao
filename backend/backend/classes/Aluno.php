<?php

/**
 * Aluno
 * Concentra as operações de CRUD da tabela `alunos`.
 */
class Aluno
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    public function listarTodos(): array
    {
        $sql = "SELECT a.id, a.nome, a.email, a.nivel, a.status, a.horario_id,
                       DATE_FORMAT(a.data_matricula, '%d/%m/%Y') AS data_matricula,
                       h.turma
                FROM alunos a
                LEFT JOIN horarios h ON h.id = a.horario_id
                ORDER BY a.nome ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM alunos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $aluno = $stmt->fetch();
        return $aluno ?: null;
    }

    public function emailJaExiste(string $email, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM alunos WHERE email = :email';
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
        $this->db->beginTransaction();
        try {
        $sql = 'INSERT INTO alunos (nome,email,senha_hash,nivel,status,horario_id,telefone,cpf,data_nascimento,endereco,responsavel_nome,responsavel_telefone,responsavel_email,data_matricula)
                VALUES (:nome,:email,:senha_hash,:nivel,:status,:horario_id,:telefone,:cpf,:data_nascimento,:endereco,:responsavel_nome,:responsavel_telefone,:responsavel_email,CURDATE())';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome'       => $dados['nome'],
            'email'      => $dados['email'],
            'senha_hash' => password_hash(!empty($dados['senha']) ? $dados['senha'] : '123456', PASSWORD_DEFAULT),
            'nivel'      => $dados['nivel'],
            'status'     => $dados['status'],
            'horario_id' => $dados['horario_id'] ?: null,
            'telefone'=>$dados['telefone']??null,'cpf'=>$dados['cpf']??null,'data_nascimento'=>($dados['data_nascimento']??null)?:null,'endereco'=>$dados['endereco']??null,'responsavel_nome'=>$dados['responsavel_nome']??null,'responsavel_telefone'=>$dados['responsavel_telefone']??null,'responsavel_email'=>$dados['responsavel_email']??null,
        ]);
        $id=(int)$this->db->lastInsertId();
        if(!empty($dados['horario_id']))$this->matricular($id,(int)$dados['horario_id'],$dados['status']);
        $this->db->commit();return $id;
        } catch(Throwable $e) { if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }

    public function atualizar(int $id, array $dados): bool
    {
        $this->db->beginTransaction();
        try {
        $atual=$this->buscarPorId($id);
        $sql = 'UPDATE alunos
                SET nome=:nome,email=:email,nivel=:nivel,status=:status,horario_id=:horario_id,telefone=:telefone,cpf=:cpf,data_nascimento=:data_nascimento,endereco=:endereco,responsavel_nome=:responsavel_nome,responsavel_telefone=:responsavel_telefone,responsavel_email=:responsavel_email';
        $params = [
            'id' => $id,
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'nivel' => $dados['nivel'],
            'status' => $dados['status'],
            'horario_id' => $dados['horario_id'] ?: null,
            'telefone' => $dados['telefone'] ?? null,
            'cpf' => $dados['cpf'] ?? null,
            'data_nascimento' => ($dados['data_nascimento'] ?? null) ?: null,
            'endereco' => $dados['endereco'] ?? null,
            'responsavel_nome' => $dados['responsavel_nome'] ?? null,
            'responsavel_telefone' => $dados['responsavel_telefone'] ?? null,
            'responsavel_email' => $dados['responsavel_email'] ?? null,
        ];
        if (!empty($dados['senha'])) {
            $sql .= ',senha_hash=:senha_hash';
            $params['senha_hash'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id=:id';
        $stmt = $this->db->prepare($sql);
        $resultado=$stmt->execute($params);
        $anterior=(int)($atual['horario_id']??0);$novo=(int)($dados['horario_id']??0);
        if($dados['status']!=='ativo')$this->db->prepare("UPDATE matriculas SET situacao='cancelada' WHERE aluno_id=? AND situacao='ativa'")->execute([$id]);
        if($anterior!==$novo){
            $mat=$this->db->prepare("SELECT id,valor_mensalidade FROM matriculas WHERE aluno_id=? AND horario_id=? AND situacao='ativa' ORDER BY id DESC LIMIT 1 FOR UPDATE");$mat->execute([$id,$anterior]);$matriculaAtual=$anterior?$mat->fetch():false;
            if($matriculaAtual){$this->db->prepare("UPDATE matriculas SET situacao=? WHERE id=?")->execute([$novo?'transferida':'cancelada',$matriculaAtual['id']]);}
            if($novo){$this->matricular($id,$novo,$dados['status'], $matriculaAtual?(float)$matriculaAtual['valor_mensalidade']:null);}
        }
        $this->db->commit();return $resultado;
        } catch(Throwable $e) { if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }

    private function matricular(int $alunoId,int $horarioId,string $status,?float $valor=null): void
    {
        if($status!=='ativo')throw new InvalidArgumentException('Somente alunos ativos podem receber matrícula em uma turma.');
        $turma=$this->db->prepare("SELECT h.capacidade,c.valor FROM horarios h LEFT JOIN cursos c ON c.id=h.curso_id WHERE h.id=? AND h.status='ativa' FOR UPDATE");$turma->execute([$horarioId]);$dadosTurma=$turma->fetch();
        if(!$dadosTurma)throw new InvalidArgumentException('Escolha uma turma ativa.');
        $check=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE horario_id=? AND situacao='ativa'");$check->execute([$horarioId]);
        if((int)$check->fetchColumn()>=(int)$dadosTurma['capacidade'])throw new InvalidArgumentException('A turma atingiu a capacidade máxima.');
        $inserir=$this->db->prepare("INSERT INTO matriculas(aluno_id,horario_id,data_matricula,valor_mensalidade,situacao) VALUES(?,?,CURDATE(),?,'ativa')");
        $inserir->execute([$alunoId,$horarioId,$valor??(float)($dadosTurma['valor']??0)]);
    }

    public function excluir(int $id): bool
    {
        // Arquivar mantém frequência, notas e histórico financeiro.
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("UPDATE alunos SET status='inativo' WHERE id=:id");$stmt->execute(['id'=>$id]);
            $this->db->prepare("UPDATE matriculas SET situacao='cancelada' WHERE aluno_id=? AND situacao='ativa'")->execute([$id]);
            $this->db->commit();return $stmt->rowCount()>0;
        } catch(Throwable $e) { if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }

    public function contarTotal(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM alunos WHERE status='ativo'")->fetchColumn();
    }

    public function contarNovasMatriculas(int $dias = 30): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE situacao='ativa' AND data_matricula >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)");
        $stmt->execute(['dias' => $dias]);
        return (int) $stmt->fetchColumn();
    }

    public function listarUltimosCadastrados(int $limite = 5): array
    {
        $sql = "SELECT nome, nivel, status, DATE_FORMAT(data_matricula, '%d/%m/%Y') AS data_matricula
                FROM alunos WHERE status='ativo' ORDER BY id DESC LIMIT :limite";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
