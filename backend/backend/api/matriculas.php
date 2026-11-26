<?php
require_once __DIR__ . '/../bootstrap.php';
exigirSessaoGestao();
$metodo=$_SERVER['REQUEST_METHOD']; if (in_array($metodo,['POST','PUT','DELETE'],true)) exigirCsrf();
$modelo=new Matricula();
try {
    if ($metodo==='GET') {
        $db=Database::conectar();
        $alunos=$db->query("SELECT id,nome FROM alunos WHERE status='ativo' ORDER BY nome")->fetchAll();
        $turmas=$db->query("SELECT id,turma,dia_semana,TIME_FORMAT(hora_inicio,'%H:%i') hora FROM horarios WHERE status='ativa' ORDER BY turma")->fetchAll();
        Resposta::sucesso(['matriculas'=>$modelo->listarTodos(),'alunos'=>$alunos,'turmas'=>$turmas]);
    }
    if ($metodo==='POST') {
        $d=corpoRequisicao();
        if (($d['acao']??'')==='transferir') $id=$modelo->transferir((int)($d['matricula_id']??0),(int)($d['horario_id']??0),(string)($d['data_matricula']??date('Y-m-d')));
        else $id=$modelo->criar(['aluno_id'=>(int)($d['aluno_id']??0),'horario_id'=>(int)($d['horario_id']??0),'data_matricula'=>(string)($d['data_matricula']??date('Y-m-d')),'valor_mensalidade'=>valorMonetario($d['valor_mensalidade']??0)]);
        Resposta::sucesso(['id'=>$id,'mensagem'=>'Matrícula registrada.'],201);
    }
    if ($metodo==='DELETE') { $id=(int)($_GET['id']??0); if (!$modelo->cancelar($id)) Resposta::erro('Matrícula ativa não encontrada.',404); Resposta::sucesso(['mensagem'=>'Matrícula cancelada.']); }
    Resposta::erro('Método não permitido.',405);
} catch (InvalidArgumentException $e) { Resposta::erro($e->getMessage(),422); }
