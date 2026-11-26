<?php
class Curso
{
    private PDO $db;
    public function __construct() { $this->db = Database::conectar(); }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT id,nome,idioma,nivel,duracao_meses,carga_horaria,valor,ativo FROM cursos ORDER BY idioma,nivel')->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM cursos WHERE id=:id'); $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public function salvar(array $dados, ?int $id=null): int
    {
        $campos=['nome'=>$dados['nome'],'idioma'=>$dados['idioma'],'nivel'=>$dados['nivel'],'duracao_meses'=>(int)$dados['duracao_meses'],'carga_horaria'=>(int)$dados['carga_horaria'],'valor'=>number_format((float)$dados['valor'],2,'.',''),'ativo'=>(int)$dados['ativo']];
        if ($id===null) {
            $stmt=$this->db->prepare('INSERT INTO cursos (nome,idioma,nivel,duracao_meses,carga_horaria,valor,ativo) VALUES (:nome,:idioma,:nivel,:duracao_meses,:carga_horaria,:valor,:ativo)');
            $stmt->execute($campos); return (int)$this->db->lastInsertId();
        }
        $campos['id']=$id;
        $stmt=$this->db->prepare('UPDATE cursos SET nome=:nome,idioma=:idioma,nivel=:nivel,duracao_meses=:duracao_meses,carga_horaria=:carga_horaria,valor=:valor,ativo=:ativo WHERE id=:id');
        $stmt->execute($campos); return $id;
    }

    /** Cursos usados em turmas são arquivados, preservando referências e histórico. */
    public function arquivar(int $id): bool
    {
        $q=$this->db->prepare("SELECT COUNT(*) FROM horarios WHERE curso_id=? AND status='ativa'");$q->execute([$id]);
        if((int)$q->fetchColumn()>0)throw new InvalidArgumentException('Este curso ainda possui turmas ativas. Encerre as turmas antes de arquivá-lo.');
        $stmt=$this->db->prepare('UPDATE cursos SET ativo=0 WHERE id=:id'); $stmt->execute(['id'=>$id]);
        if ($stmt->rowCount()>0) return true;
        return $this->buscar($id)!==null;
    }
}
