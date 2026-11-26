<?php
require_once __DIR__ . '/../bootstrap.php';
exigirSessaoGestao();
$metodo=$_SERVER['REQUEST_METHOD']; if (in_array($metodo,['POST','PUT','DELETE'],true)) exigirCsrf();
$db=Database::conectar(); $modelo=new Financeiro();
try {
    if ($metodo==='GET') {
        $matriculas=$db->query("SELECT m.id,a.nome aluno,h.turma FROM matriculas m JOIN alunos a ON a.id=m.aluno_id JOIN horarios h ON h.id=m.horario_id WHERE m.situacao='ativa' ORDER BY a.nome")->fetchAll();
        Resposta::sucesso(['mensalidades'=>$modelo->mensalidades(),'pagamentos'=>$modelo->pagamentos(),'matriculas'=>$matriculas]);
    }
    $d=corpoRequisicao();
    if ($metodo==='POST' && ($d['acao']??'')==='mensalidade') {
        $d['valor']=valorMonetario($d['valor']??null); $d['desconto']=valorMonetario($d['desconto']??0);$d['multa']=valorMonetario($d['multa']??0);$d['juros']=valorMonetario($d['juros']??0);
        if ($d['desconto']>$d['valor']) Resposta::erro('O desconto não pode superar o valor da mensalidade.',422);
        $id=$modelo->criarMensalidade($d); Resposta::sucesso(['id'=>$id,'mensagem'=>'Mensalidade criada.'],201);
    }
    if ($metodo==='POST' && ($d['acao']??'')==='pagamento') {
        $d['valor']=valorMonetario($d['valor']??null); $id=$modelo->registrarPagamento($d); Resposta::sucesso(['id'=>$id,'mensagem'=>'Pagamento registrado.'],201);
    }
    Resposta::erro('Ação ou método não permitido.',405);
} catch (InvalidArgumentException $e) { Resposta::erro($e->getMessage(),422); }
