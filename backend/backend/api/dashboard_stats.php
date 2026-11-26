<?php

require_once __DIR__ . '/../bootstrap.php';

exigirSessaoGestao();

$alunoModel = new Aluno();
$professorModel = new Professor();
$horarioModel = new Horario();
$db=Database::conectar();
$financeiro=$db->query("SELECT COALESCE((SELECT SUM(valor) FROM pagamentos WHERE YEAR(data_pagamento)=YEAR(CURDATE()) AND MONTH(data_pagamento)=MONTH(CURDATE())),0) recebido_mes,COALESCE((SELECT SUM(GREATEST(m.valor-m.desconto+m.multa+m.juros-COALESCE(p.total,0),0)) FROM mensalidades m LEFT JOIN (SELECT mensalidade_id,SUM(valor) total FROM pagamentos GROUP BY mensalidade_id) p ON p.mensalidade_id=m.id WHERE m.situacao IN ('pendente','atrasado') AND m.vencimento>=CURDATE()),0) pendente,COALESCE((SELECT SUM(GREATEST(m.valor-m.desconto+m.multa+m.juros-COALESCE(p.total,0),0)) FROM mensalidades m LEFT JOIN (SELECT mensalidade_id,SUM(valor) total FROM pagamentos GROUP BY mensalidade_id) p ON p.mensalidade_id=m.id WHERE m.situacao IN ('pendente','atrasado') AND m.vencimento<CURDATE()),0) atrasado")->fetch();
$receitas=$db->query("SELECT DATE_FORMAT(d.mes,'%m/%Y') rotulo,COALESCE(SUM(p.valor),0) total FROM (SELECT DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL n MONTH) mes FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) meses) d LEFT JOIN pagamentos p ON YEAR(p.data_pagamento)=YEAR(d.mes) AND MONTH(p.data_pagamento)=MONTH(d.mes) GROUP BY d.mes ORDER BY d.mes")->fetchAll();
$proximas=[];$dias=['Dom'=>0,'Seg'=>1,'Ter'=>2,'Qua'=>3,'Qui'=>4,'Sex'=>5,'Sab'=>6];$hoje=new DateTimeImmutable('today');
foreach($db->query("SELECT h.turma,h.dia_semana,h.hora_inicio,h.data_inicio,h.data_fim,p.nome professor FROM horarios h JOIN professores p ON p.id=h.professor_id WHERE h.status='ativa'")->fetchAll() as $a){$alvo=$dias[$a['dia_semana']]??null;if($alvo===null)continue;$base=$hoje<new DateTimeImmutable($a['data_inicio'])?new DateTimeImmutable($a['data_inicio']):$hoje;$offset=((int)$alvo-(int)$base->format('w')+7)%7;$proxima=$base->modify('+' . $offset . ' days');if($proxima<$hoje||($proxima==$hoje&&substr($a['hora_inicio'],0,5)<=date('H:i')))$proxima=$proxima->modify('+7 days');if($a['data_fim']&&$proxima>new DateTimeImmutable($a['data_fim']))continue;$proximas[]=['turma'=>$a['turma'],'professor'=>$a['professor'],'data'=>$proxima->format('d/m/Y'),'hora'=>substr($a['hora_inicio'],0,5),'ordem'=>$proxima->format('Y-m-d')];}
usort($proximas,fn($a,$b)=>[$a['ordem'],$a['hora']]<=>[$b['ordem'],$b['hora']]);$proximas=array_slice($proximas,0,5);

Resposta::sucesso([
    'total_alunos'           => $alunoModel->contarTotal(),
    'total_professores'      => $professorModel->contarAtivos(),
    'total_turmas'           => $horarioModel->contarTotal(),
    'novas_matriculas_30d'   => $alunoModel->contarNovasMatriculas(30),
    'ultimos_alunos'         => $alunoModel->listarUltimosCadastrados(5),
    'financeiro' => $financeiro,
    'receitas_mensais' => $receitas,
    'proximas_aulas' => $proximas,
]);
