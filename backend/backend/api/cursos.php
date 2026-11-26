<?php
require_once __DIR__ . '/../bootstrap.php';
exigirSessaoGestao();
$metodo = $_SERVER['REQUEST_METHOD'];
if (in_array($metodo, ['POST','PUT','DELETE'], true)) exigirCsrf();
if (in_array($metodo, ['POST','PUT','DELETE'], true)) exigirPerfilGestao(['administrador']);
$modelo = new Curso();
try {
    if ($metodo === 'GET') Resposta::sucesso(['cursos' => $modelo->listarTodos()]);
    if ($metodo === 'DELETE') {
        $id=(int)($_GET['id']??0); if ($id<1 || !$modelo->buscar($id)) Resposta::erro('Curso não encontrado.',404);
        $modelo->arquivar($id); Resposta::sucesso(['mensagem'=>'Curso arquivado.']);
    }
    if (!in_array($metodo,['POST','PUT'],true)) Resposta::erro('Método não permitido.',405);
    $d=corpoRequisicao(); $nome=trim((string)($d['nome']??'')); $idioma=trim((string)($d['idioma']??'')); $nivel=(string)($d['nivel']??'');
    $duracao=filter_var($d['duracao_meses']??null,FILTER_VALIDATE_INT); $carga=filter_var($d['carga_horaria']??null,FILTER_VALIDATE_INT); $id=$metodo==='PUT'?(int)($_GET['id']??0):null;
    if (mb_strlen($nome)<3 || mb_strlen($nome)>120 || mb_strlen($idioma)<2 || mb_strlen($idioma)>60) Resposta::erro('Informe nome e idioma válidos.',422);
    if (!in_array($nivel,['Iniciante','Básico','Intermediário','Avançado'],true) || !$duracao || $duracao>60 || !$carga || $carga>1000) Resposta::erro('Confira nível, duração e carga horária.',422);
    if ($id!==null && !$modelo->buscar($id)) Resposta::erro('Curso não encontrado.',404);
    $dados=['nome'=>$nome,'idioma'=>$idioma,'nivel'=>$nivel,'duracao_meses'=>$duracao,'carga_horaria'=>$carga,'valor'=>valorMonetario($d['valor']??null),'ativo'=>isset($d['ativo'])?(int)(bool)$d['ativo']:1];
    if(!$dados['ativo']){$pdo=Database::conectar();$q=$pdo->prepare("SELECT COUNT(*) FROM horarios WHERE curso_id=? AND status='ativa'");$q->execute([$id]);if((int)$q->fetchColumn()>0)Resposta::erro('Este curso ainda possui turmas ativas. Encerre as turmas antes de arquivá-lo.',409);}
    $novo=$modelo->salvar($dados,$id); Resposta::sucesso(['id'=>$novo,'mensagem'=>'Curso salvo.'],$metodo==='POST'?201:200);
} catch (InvalidArgumentException $e) { Resposta::erro($e->getMessage(),422); }
