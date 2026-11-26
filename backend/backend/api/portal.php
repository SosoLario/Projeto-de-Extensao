<?php
require_once __DIR__ . '/../bootstrap.php';
$usuario=exigirSessao(); $db=Database::conectar(); $acao=(string)($_GET['acao']??'');
if ($_SERVER['REQUEST_METHOD']==='POST') exigirCsrf();

if ($usuario['tipo']==='aluno' && $acao==='resumo') {
    $q=$db->prepare("SELECT h.id,h.turma,h.dia_semana,TIME_FORMAT(h.hora_inicio,'%H:%i') hora,TIME_FORMAT(h.hora_fim,'%H:%i') hora_fim,h.data_inicio,h.data_fim,p.nome professor,c.nome curso,c.nivel FROM matriculas m JOIN horarios h ON h.id=m.horario_id LEFT JOIN professores p ON p.id=h.professor_id LEFT JOIN cursos c ON c.id=h.curso_id WHERE m.aluno_id=? AND m.situacao='ativa' AND h.status='ativa' ORDER BY FIELD(h.dia_semana,'Seg','Ter','Qua','Qui','Sex','Sab'),h.hora_inicio");$q->execute([$usuario['id']]);$turmas=$q->fetchAll();
    $f=$db->prepare("SELECT COUNT(*) total,COALESCE(SUM(situacao IN ('falta','atrasado')),0) ausencias,COALESCE(SUM(situacao='falta'),0) faltas,COALESCE(SUM(situacao='atrasado'),0) atrasos FROM frequencias WHERE aluno_id=?");$f->execute([$usuario['id']]);
    $historico=$db->prepare("SELECT f.data_aula,f.situacao,h.turma,c.nome curso,TIME_FORMAT(COALESCE(a.hora_inicio,h.hora_inicio),'%H:%i') hora_inicio,TIME_FORMAT(COALESCE(a.hora_fim,h.hora_fim),'%H:%i') hora_fim FROM frequencias f JOIN horarios h ON h.id=f.horario_id LEFT JOIN cursos c ON c.id=h.curso_id LEFT JOIN aulas a ON a.id=f.aula_id WHERE f.aluno_id=? ORDER BY f.data_aula DESC,h.hora_inicio DESC LIMIT 500");$historico->execute([$usuario['id']]);
    $n=$db->prepare("SELECT n.avaliacao,n.nota,DATE_FORMAT(n.criado_em,'%Y-%m-%d') data_lancamento,h.turma,c.nome curso FROM notas n JOIN horarios h ON h.id=n.horario_id LEFT JOIN cursos c ON c.id=h.curso_id WHERE n.aluno_id=? ORDER BY n.criado_em DESC,n.id DESC");$n->execute([$usuario['id']]);
    $m=$db->prepare("SELECT f.referencia,f.valor,f.vencimento,f.situacao FROM mensalidades f JOIN matriculas x ON x.id=f.matricula_id WHERE x.aluno_id=? ORDER BY f.vencimento DESC");$m->execute([$usuario['id']]);
    Resposta::sucesso(['usuario'=>$usuario,'turmas'=>$turmas,'frequencia'=>$f->fetch(),'historico_frequencia'=>$historico->fetchAll(),'notas'=>$n->fetchAll(),'mensalidades'=>$m->fetchAll()]);
}

if ($usuario['tipo']==='professor') {
    if ($acao==='turmas' && $_SERVER['REQUEST_METHOD']==='GET') {
        $s=$db->prepare("SELECT h.id,h.turma,h.dia_semana,TIME_FORMAT(h.hora_inicio,'%H:%i') hora,TIME_FORMAT(h.hora_fim,'%H:%i') hora_fim,h.data_inicio,h.data_fim,c.nome curso,c.nivel FROM horarios h LEFT JOIN cursos c ON c.id=h.curso_id WHERE h.professor_id=? AND h.status='ativa' ORDER BY FIELD(h.dia_semana,'Seg','Ter','Qua','Qui','Sex','Sab'),h.hora_inicio");$s->execute([$usuario['id']]);$turmas=$s->fetchAll();
        foreach($turmas as &$t){$a=$db->prepare("SELECT a.id,a.nome,a.email FROM matriculas m JOIN alunos a ON a.id=m.aluno_id WHERE m.horario_id=? AND m.situacao='ativa' ORDER BY a.nome");$a->execute([$t['id']]);$t['alunos']=$a->fetchAll();}unset($t);
        Resposta::sucesso(['usuario'=>$usuario,'turmas'=>$turmas]);
    }
    if ($acao==='historico_frequencia' && $_SERVER['REQUEST_METHOD']==='GET') {
        $q=$db->prepare("SELECT f.data_aula,f.situacao,a.nome aluno,h.turma,c.nome curso,TIME_FORMAT(COALESCE(au.hora_inicio,h.hora_inicio),'%H:%i') hora_inicio,TIME_FORMAT(COALESCE(au.hora_fim,h.hora_fim),'%H:%i') hora_fim FROM frequencias f JOIN alunos a ON a.id=f.aluno_id JOIN horarios h ON h.id=f.horario_id LEFT JOIN cursos c ON c.id=h.curso_id LEFT JOIN aulas au ON au.id=f.aula_id WHERE h.professor_id=? ORDER BY f.data_aula DESC,h.hora_inicio DESC,a.nome LIMIT 1000");$q->execute([$usuario['id']]);
        Resposta::sucesso(['historico'=>$q->fetchAll()]);
    }
    if ($acao==='notas_professor' && $_SERVER['REQUEST_METHOD']==='GET') {
        $q=$db->prepare("SELECT n.id,n.avaliacao,n.nota,DATE_FORMAT(n.criado_em,'%Y-%m-%d') data_lancamento,a.id aluno_id,a.nome aluno,h.id horario_id,h.turma,c.nome curso FROM notas n JOIN alunos a ON a.id=n.aluno_id JOIN horarios h ON h.id=n.horario_id LEFT JOIN cursos c ON c.id=h.curso_id WHERE h.professor_id=? ORDER BY n.criado_em DESC,n.id DESC LIMIT 500");$q->execute([$usuario['id']]);
        Resposta::sucesso(['notas'=>$q->fetchAll()]);
    }
    if ($acao==='aulas' && $_SERVER['REQUEST_METHOD']==='GET') {
        $h=(int)($_GET['horario_id']??0);$q=$db->prepare('SELECT id FROM horarios WHERE id=? AND professor_id=?');$q->execute([$h,$usuario['id']]);if(!$q->fetchColumn())Resposta::erro('Turma não autorizada.',403);
        $q=$db->prepare("SELECT a.id,a.data_aula,a.conteudo,COUNT(f.id) total,COALESCE(SUM(f.situacao='presente'),0) presentes,COALESCE(SUM(f.situacao='falta'),0) faltas,COALESCE(SUM(f.situacao='atrasado'),0) atrasos FROM aulas a LEFT JOIN frequencias f ON f.aula_id=a.id WHERE a.horario_id=? GROUP BY a.id ORDER BY a.data_aula DESC LIMIT 30");$q->execute([$h]);Resposta::sucesso(['aulas'=>$q->fetchAll()]);
    }
    if ($acao==='frequencia' && $_SERVER['REQUEST_METHOD']==='GET') {
        $h=(int)($_GET['horario_id']??0);$data=(string)($_GET['data']??'');$q=$db->prepare('SELECT id FROM horarios WHERE id=? AND professor_id=?');$q->execute([$h,$usuario['id']]);if(!$q->fetchColumn())Resposta::erro('Turma não autorizada.',403);if(!validarDataISO($data))Resposta::erro('Data inválida.',422);
        $q=$db->prepare('SELECT id,conteudo FROM aulas WHERE horario_id=? AND data_aula=?');$q->execute([$h,$data]);$aula=$q->fetch();if(!$aula)Resposta::sucesso(['aula'=>null,'frequencias'=>[]]);
        $q=$db->prepare('SELECT aluno_id,situacao FROM frequencias WHERE aula_id=?');$q->execute([$aula['id']]);Resposta::sucesso(['aula'=>$aula,'frequencias'=>$q->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && $acao==='frequencia') {
        $d=corpoRequisicao();$h=(int)($d['horario_id']??0);$data=(string)($d['data']??'');$conteudo=trim((string)($d['conteudo']??''));$alunos=$d['alunos']??[];
        if(!validarDataISO($data)||$data>date('Y-m-d')||mb_strlen($conteudo)>1000||!is_array($alunos)||count($alunos)>100)Resposta::erro('Confira data, conteúdo e lista de presença.',422);
        $q=$db->prepare("SELECT id,hora_inicio,hora_fim FROM horarios WHERE id=? AND professor_id=? AND status='ativa'");$q->execute([$h,$usuario['id']]);$horario=$q->fetch();if(!$horario)Resposta::erro('Turma não autorizada.',403);
        $db->beginTransaction();try{
            $aula=$db->prepare('INSERT INTO aulas (horario_id,data_aula,hora_inicio,hora_fim,conteudo) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE conteudo=VALUES(conteudo)');$aula->execute([$h,$data,$horario['hora_inicio'],$horario['hora_fim'],$conteudo]);
            $qAula=$db->prepare('SELECT id FROM aulas WHERE horario_id=? AND data_aula=?');$qAula->execute([$h,$data]);$aulaId=(int)$qAula->fetchColumn();
            $mat=$db->prepare("SELECT 1 FROM matriculas WHERE aluno_id=? AND horario_id=? AND situacao='ativa'");
            $stmt=$db->prepare('INSERT INTO frequencias (aula_id,aluno_id,horario_id,data_aula,presente,situacao) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE presente=VALUES(presente),situacao=VALUES(situacao)');
            foreach($alunos as $aluno){$alunoId=(int)($aluno['id']??0);$status=(string)($aluno['situacao']??(!empty($aluno['presente'])?'presente':'falta'));if(!in_array($status,['presente','falta','atrasado'],true))throw new InvalidArgumentException('Status de frequência inválido.');$mat->execute([$alunoId,$h]);if(!$mat->fetchColumn())throw new InvalidArgumentException('Há aluno que não pertence a esta turma.');$stmt->execute([$aulaId,$alunoId,$h,$data,$status==='falta'?0:1,$status]);}
            $db->commit();Resposta::sucesso(['mensagem'=>'Aula e frequência registradas.']);
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof InvalidArgumentException)Resposta::erro($e->getMessage(),422);throw $e;}
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && $acao==='nota') {
        $d=corpoRequisicao();$h=(int)($d['horario_id']??0);$aluno=(int)($d['aluno_id']??0);$avaliacao=trim((string)($d['avaliacao']??''));$nota=filter_var($d['nota']??null,FILTER_VALIDATE_FLOAT);
        $q=$db->prepare("SELECT id FROM horarios WHERE id=? AND professor_id=? AND status='ativa'");$q->execute([$h,$usuario['id']]);if(!$q->fetchColumn())Resposta::erro('Turma não autorizada.',403);
        $q=$db->prepare("SELECT 1 FROM matriculas WHERE aluno_id=? AND horario_id=? AND situacao='ativa'");$q->execute([$aluno,$h]);if(!$q->fetchColumn())Resposta::erro('Aluno não pertence a esta turma.',422);
        if(mb_strlen($avaliacao)<2||mb_strlen($avaliacao)>120||$nota===false||$nota<0||$nota>10)Resposta::erro('Informe avaliação e nota entre 0 e 10.',422);
        $stmt=$db->prepare('INSERT INTO notas (aluno_id,horario_id,avaliacao,nota) VALUES (?,?,?,?)');$stmt->execute([$aluno,$h,$avaliacao,$nota]);Resposta::sucesso(['mensagem'=>'Nota lançada.']);
    }
}
Resposta::erro('Ação não disponível para este perfil.',404);
