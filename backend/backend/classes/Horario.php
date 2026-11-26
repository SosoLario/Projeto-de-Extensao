<?php

/**
 * Horario
 * Concentra as operações de CRUD da tabela `horarios` (turmas/aulas).
 */
class Horario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    public function listarTodos(): array
    {
        $sql = "SELECT h.id, h.turma, h.dia_semana, h.hora_inicio, h.hora_fim, h.data_inicio, h.data_fim, h.professor_id,h.curso_id,h.capacidade,h.status,c.nome curso_nome,
                       p.nome AS professor_nome
                FROM horarios h
                JOIN professores p ON p.id = h.professor_id
                LEFT JOIN cursos c ON c.id=h.curso_id
                ORDER BY FIELD(h.dia_semana,'Seg','Ter','Qua','Qui','Sex','Sab'), h.hora_inicio ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM horarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $horario = $stmt->fetch();
        return $horario ?: null;
    }

    public function criar(array $dados): int
    {
        $sql = 'INSERT INTO horarios (turma, professor_id, curso_id, dia_semana, hora_inicio, hora_fim, capacidade, data_inicio, data_fim)
                VALUES (:turma, :professor_id, :curso_id, :dia_semana, :hora_inicio, :hora_fim, :capacidade, :data_inicio, :data_fim)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'turma'        => $dados['turma'],
            'professor_id' => $dados['professor_id'],
            'curso_id' => $dados['curso_id'] ?: null,
            'dia_semana'   => $dados['dia_semana'],
            'hora_inicio'  => $dados['hora_inicio'],
            'hora_fim' => $dados['hora_fim'],
            'capacidade' => $dados['capacidade'],
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => $dados['data_fim'] ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $this->db->beginTransaction();
        try{$stmt=$this->db->prepare('UPDATE horarios SET turma=:turma,professor_id=:professor_id,curso_id=:curso_id,dia_semana=:dia_semana,hora_inicio=:hora_inicio,hora_fim=:hora_fim,capacidade=:capacidade,data_inicio=:data_inicio,data_fim=:data_fim,status=:status WHERE id=:id');
        $stmt->execute(['id'=>$id,'turma'=>$dados['turma'],'professor_id'=>$dados['professor_id'],'curso_id'=>$dados['curso_id'],'dia_semana'=>$dados['dia_semana'],'hora_inicio'=>$dados['hora_inicio'],'hora_fim'=>$dados['hora_fim'],'capacidade'=>$dados['capacidade'],'data_inicio'=>$dados['data_inicio'],'data_fim'=>$dados['data_fim']?:null,'status'=>$dados['status']]);
        if($dados['status']!=='ativa'){$situacao=$dados['status']==='concluída'?'concluída':'cancelada';$this->db->prepare("UPDATE matriculas SET situacao=? WHERE horario_id=? AND situacao='ativa'")->execute([$situacao,$id]);}
        $this->db->commit();return true;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function excluir(int $id): bool
    {
        // Preserva registros vinculados; turmas encerradas deixam de aceitar novas matrículas.
        $this->db->beginTransaction();
        try{$this->db->prepare("UPDATE matriculas SET situacao='cancelada' WHERE horario_id=? AND situacao='ativa'")->execute([$id]);$stmt=$this->db->prepare("UPDATE horarios SET status='cancelada' WHERE id=:id");$stmt->execute(['id'=>$id]);$this->db->commit();return $stmt->rowCount()>0;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function contarTotal(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM horarios WHERE status='ativa'")->fetchColumn();
    }
}
