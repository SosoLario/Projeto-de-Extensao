<?php
class Matricula
{
    private PDO $db;
    public function __construct() { $this->db=Database::conectar(); }

    public function listarTodos(): array
    {
        $this->db->exec("UPDATE mensalidades SET situacao='atrasado' WHERE situacao='pendente' AND vencimento<CURDATE()");
        return $this->db->query("SELECT m.id,m.aluno_id,a.nome aluno_nome,m.horario_id,h.turma,h.dia_semana,TIME_FORMAT(h.hora_inicio,'%H:%i') hora,COALESCE(c.nome,'Curso não definido') curso_nome,m.data_matricula,m.valor_mensalidade,m.situacao FROM matriculas m JOIN alunos a ON a.id=m.aluno_id JOIN horarios h ON h.id=m.horario_id LEFT JOIN cursos c ON c.id=h.curso_id ORDER BY m.id DESC")->fetchAll();
    }

    public function criar(array $dados): int
    {
        $alunoId=(int)$dados['aluno_id']; $horarioId=(int)$dados['horario_id']; $valor=(float)$dados['valor_mensalidade'];
        if ($valor<0 || !is_finite($valor)) throw new InvalidArgumentException('Informe um valor mensal válido.');
        $data=(string)($dados['data_matricula']??date('Y-m-d'));
        $this->db->beginTransaction();
        try {
            $aluno=$this->db->prepare("SELECT id FROM alunos WHERE id=:id AND status='ativo' FOR UPDATE"); $aluno->execute(['id'=>$alunoId]);
            if (!$aluno->fetchColumn()) throw new InvalidArgumentException('Selecione um aluno ativo.');
            $horario=$this->db->prepare("SELECT h.capacidade,c.valor FROM horarios h LEFT JOIN cursos c ON c.id=h.curso_id WHERE h.id=:id AND h.status='ativa' FOR UPDATE"); $horario->execute(['id'=>$horarioId]); $turma=$horario->fetch();
            if (!$turma) throw new InvalidArgumentException('Selecione uma turma ativa.');
            $dup=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE aluno_id=:aluno AND horario_id=:horario AND situacao='ativa'"); $dup->execute(['aluno'=>$alunoId,'horario'=>$horarioId]);
            if ((int)$dup->fetchColumn()>0) throw new InvalidArgumentException('Este aluno já possui matrícula ativa nesta turma.');
            $vagas=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE horario_id=:horario AND situacao='ativa'"); $vagas->execute(['horario'=>$horarioId]);
            if ((int)$vagas->fetchColumn()>=(int)$turma['capacidade']) throw new InvalidArgumentException('A turma atingiu a capacidade máxima.');
            if (!validarDataISO($data)) throw new InvalidArgumentException('Informe uma data de matrícula válida.');
            $stmt=$this->db->prepare("INSERT INTO matriculas (aluno_id,horario_id,data_matricula,valor_mensalidade,situacao) VALUES (:aluno,:horario,:data,:valor,'ativa')");
            $stmt->execute(['aluno'=>$alunoId,'horario'=>$horarioId,'data'=>$data,'valor'=>$valor]);
            $id=(int)$this->db->lastInsertId();
            $legacy=$this->db->prepare('UPDATE alunos SET horario_id=:horario WHERE id=:aluno'); $legacy->execute(['horario'=>$horarioId,'aluno'=>$alunoId]);
            $this->db->commit(); return $id;
        } catch(Throwable $erro) { if($this->db->inTransaction())$this->db->rollBack(); throw $erro; }
    }

    public function transferir(int $id, int $horarioId, string $data): int
    {
        if (!validarDataISO($data)) throw new InvalidArgumentException('Informe uma data de transferência válida.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("SELECT aluno_id,horario_id,valor_mensalidade FROM matriculas WHERE id=:id AND situacao='ativa' FOR UPDATE"); $stmt->execute(['id'=>$id]); $atual=$stmt->fetch();
            if (!$atual) throw new InvalidArgumentException('Matrícula ativa não encontrada.');
            if ((int)$atual['horario_id']===$horarioId) throw new InvalidArgumentException('Escolha uma turma diferente da atual.');
            $destino=$this->db->prepare("SELECT capacidade FROM horarios WHERE id=:id AND status='ativa' FOR UPDATE"); $destino->execute(['id'=>$horarioId]); $capacidade=$destino->fetchColumn();
            if ($capacidade===false) throw new InvalidArgumentException('A turma de destino não está ativa.');
            $vagas=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE horario_id=:horario AND situacao='ativa'"); $vagas->execute(['horario'=>$horarioId]);
            if ((int)$vagas->fetchColumn()>=(int)$capacidade) throw new InvalidArgumentException('A turma de destino atingiu a capacidade máxima.');
            $dup=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE aluno_id=:aluno AND horario_id=:horario AND situacao='ativa'"); $dup->execute(['aluno'=>$atual['aluno_id'],'horario'=>$horarioId]);
            if ((int)$dup->fetchColumn()>0) throw new InvalidArgumentException('O aluno já está ativo nesta turma.');
            $fechar=$this->db->prepare("UPDATE matriculas SET situacao='transferida' WHERE id=:id"); $fechar->execute(['id'=>$id]);
            $nova=$this->db->prepare("INSERT INTO matriculas (aluno_id,horario_id,data_matricula,valor_mensalidade,situacao,observacoes) VALUES (:aluno,:horario,:data,:valor,'ativa',:observacoes)");
            $nova->execute(['aluno'=>$atual['aluno_id'],'horario'=>$horarioId,'data'=>$data,'valor'=>$atual['valor_mensalidade'],'observacoes'=>'Transferência da matrícula #' . $id]);
            $novoId=(int)$this->db->lastInsertId();
            $legacy=$this->db->prepare('UPDATE alunos SET horario_id=:horario WHERE id=:aluno'); $legacy->execute(['horario'=>$horarioId,'aluno'=>$atual['aluno_id']]);
            $this->db->commit(); return $novoId;
        } catch(Throwable $erro) { if($this->db->inTransaction())$this->db->rollBack(); throw $erro; }
    }

    public function cancelar(int $id): bool
    {
        $this->db->beginTransaction();
        try{$q=$this->db->prepare("SELECT aluno_id,horario_id FROM matriculas WHERE id=? AND situacao='ativa' FOR UPDATE");$q->execute([$id]);$m=$q->fetch();if(!$m){$this->db->commit();return false;}$this->db->prepare("UPDATE matriculas SET situacao='cancelada' WHERE id=?")->execute([$id]);$q=$this->db->prepare("SELECT COUNT(*) FROM matriculas WHERE aluno_id=? AND situacao='ativa'");$q->execute([$m['aluno_id']]);if((int)$q->fetchColumn()===0)$this->db->prepare('UPDATE alunos SET horario_id=NULL WHERE id=? AND horario_id=?')->execute([$m['aluno_id'],$m['horario_id']]);$this->db->commit();return true;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
