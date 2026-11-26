<?php
/**
 * Dados demonstrativos fictícios e idempotentes.
 * Execute somente pelo terminal, depois de aplicar schema.sql e a migração 002.
 * O script só cria itens ausentes: não atualiza nem apaga registros existentes.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Este gerador só pode ser executado pelo terminal.\n");
}

require_once __DIR__ . '/../../config/Database.php';
$db = Database::conectar();
$db->beginTransaction();

function encontrarId(PDO $db, string $sql, array $parametros): ?int
{
    $stmt=$db->prepare($sql);$stmt->execute($parametros);$id=$stmt->fetchColumn();
    return $id===false?null:(int)$id;
}

function criarSeAusente(PDO $db, string $sql, array $parametros): int
{
    $stmt=$db->prepare($sql);$stmt->execute($parametros);return (int)$db->lastInsertId();
}

try {
    // Contas de demonstração adicionais para ilustrar os perfis de acesso.
    $gestor=encontrarId($db,'SELECT id FROM gestores WHERE email=?',['secretaria.demo@classmanager.test']);
    if(!$gestor){$gestor=criarSeAusente($db,"INSERT INTO gestores(nome,email,senha_hash,perfil,status) VALUES(?,?,?,'secretaria','ativo')",['Secretaria Demo','secretaria.demo@classmanager.test',password_hash('Secretaria@123',PASSWORD_DEFAULT)]);}

    $professor=encontrarId($db,'SELECT id FROM professores WHERE email=?',['professor.demo@classmanager.test']);
    if(!$professor){$professor=criarSeAusente($db,"INSERT INTO professores(nome,email,senha_hash,especialidade,carga_horaria,status) VALUES(?,?,?,'Inglês geral',20,'ativo')",['Professor Demo','professor.demo@classmanager.test',password_hash('Professor@123',PASSWORD_DEFAULT)]);}

    $curso=encontrarId($db,'SELECT id FROM cursos WHERE nome=? AND idioma=? AND nivel=?',['Inglês Básico','Inglês','Básico']);
    if(!$curso){$curso=criarSeAusente($db,'INSERT INTO cursos(nome,idioma,nivel,duracao_meses,carga_horaria,valor,ativo) VALUES(?,?,?,?,?,?,1)',['Inglês Básico','Inglês','Básico',10,80,280]);}

    $diaNumero=(int)date('w');if($diaNumero===0)$diaNumero=1;
    $dias=[1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sab'];$dia=$dias[$diaNumero];
    $turma=encontrarId($db,'SELECT id FROM horarios WHERE turma=?',['Turma Demonstrativa — Inglês Básico']);
    if(!$turma){$turma=criarSeAusente($db,"INSERT INTO horarios(turma,professor_id,curso_id,dia_semana,hora_inicio,hora_fim,capacidade,data_inicio,data_fim,status) VALUES(?,?,?,?, '08:00','09:30',15,DATE_SUB(CURDATE(),INTERVAL 1 MONTH),DATE_ADD(CURDATE(),INTERVAL 1 YEAR),'ativa')",['Turma Demonstrativa — Inglês Básico',$professor,$curso,$dia]);}

    $aluno=encontrarId($db,'SELECT id FROM alunos WHERE email=?',['aluno.demo@classmanager.test']);
    if(!$aluno){$aluno=criarSeAusente($db,"INSERT INTO alunos(nome,email,senha_hash,nivel,status,horario_id,telefone,data_nascimento,responsavel_nome,responsavel_telefone,data_matricula) VALUES(?,?,?,'Básico','ativo',?,'(11) 90000-0001',DATE_SUB(CURDATE(),INTERVAL 14 YEAR),'Responsável Demo','(11) 90000-0002',CURDATE())",['Aluno Demo','aluno.demo@classmanager.test',password_hash('Aluno@123',PASSWORD_DEFAULT),$turma]);}

    $matricula=encontrarId($db,"SELECT id FROM matriculas WHERE aluno_id=? AND horario_id=? AND situacao='ativa'",[$aluno,$turma]);
    if(!$matricula){$matricula=criarSeAusente($db,"INSERT INTO matriculas(aluno_id,horario_id,data_matricula,valor_mensalidade,situacao) VALUES(?,?,CURDATE(),280,'ativa')",[$aluno,$turma]);}
    $db->prepare('UPDATE alunos SET horario_id=? WHERE id=? AND horario_id IS NULL')->execute([$turma,$aluno]);

    $referenciaAnterior=date('Y-m',strtotime('first day of last month'));
    $mensalidadeAnterior=encontrarId($db,'SELECT id FROM mensalidades WHERE matricula_id=? AND referencia=?',[$matricula,$referenciaAnterior]);
    if(!$mensalidadeAnterior){$mensalidadeAnterior=criarSeAusente($db,"INSERT INTO mensalidades(matricula_id,referencia,valor,vencimento,situacao) VALUES(?,?,280,LAST_DAY(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)),'pago')",[$matricula,$referenciaAnterior]);}
    $pagamento=encontrarId($db,'SELECT id FROM pagamentos WHERE mensalidade_id=?',[$mensalidadeAnterior]);
    if(!$pagamento){$db->prepare("INSERT INTO pagamentos(mensalidade_id,valor,data_pagamento,forma_pagamento,observacoes) VALUES(?,280,DATE_SUB(NOW(),INTERVAL 20 DAY),'pix','Registro demonstrativo fictício')")->execute([$mensalidadeAnterior]);}
    $db->prepare("UPDATE mensalidades SET situacao='pago' WHERE id=? AND situacao<>'cancelado'")->execute([$mensalidadeAnterior]);

    $referenciaAtual=date('Y-m');$mensalidadeAtual=encontrarId($db,'SELECT id FROM mensalidades WHERE matricula_id=? AND referencia=?',[$matricula,$referenciaAtual]);
    if(!$mensalidadeAtual){$db->prepare("INSERT INTO mensalidades(matricula_id,referencia,valor,vencimento,situacao) VALUES(?,?,280,DATE_ADD(CURDATE(),INTERVAL 10 DAY),'pendente')")->execute([$matricula,$referenciaAtual]);}

    $alvos=['Dom'=>0,'Seg'=>1,'Ter'=>2,'Qua'=>3,'Qui'=>4,'Sex'=>5,'Sab'=>6];$diasDesde=((int)date('w')-$alvos[$dia]+7)%7;if($diasDesde===0)$diasDesde=7;$dataAula=date('Y-m-d',strtotime('-'.$diasDesde.' days'));
    $aula=encontrarId($db,'SELECT id FROM aulas WHERE horario_id=? AND data_aula=?',[$turma,$dataAula]);
    if(!$aula){$aula=criarSeAusente($db,'INSERT INTO aulas(horario_id,data_aula,hora_inicio,hora_fim,conteudo) VALUES(?,?,\'08:00\',\'09:30\',?)',[$turma,$dataAula,'Apresentações e vocabulário cotidiano']);}
    $frequencia=encontrarId($db,'SELECT id FROM frequencias WHERE aula_id=? AND aluno_id=?',[$aula,$aluno]);
    if(!$frequencia){$db->prepare("INSERT INTO frequencias(aula_id,aluno_id,horario_id,data_aula,presente,situacao) VALUES(?,?,?, ?,1,'presente')")->execute([$aula,$aluno,$turma,$dataAula]);}
    $nota=encontrarId($db,'SELECT id FROM notas WHERE aluno_id=? AND horario_id=? AND avaliacao=?',[$aluno,$turma,'Atividade demonstrativa']);
    if(!$nota){$db->prepare('INSERT INTO notas(aluno_id,horario_id,avaliacao,nota) VALUES(?,?,?,?)')->execute([$aluno,$turma,'Atividade demonstrativa',8.5]);}

    $db->commit();
    echo "Dados demonstrativos verificados/criados sem apagar registros existentes.\n";
    echo "Gestão Secretaria: secretaria.demo@classmanager.test / Secretaria@123\n";
    echo "Professor: professor.demo@classmanager.test / Professor@123\n";
    echo "Aluno: aluno.demo@classmanager.test / Aluno@123\n";
} catch (Throwable $erro) {
    if($db->inTransaction())$db->rollBack();
    fwrite(STDERR,"Não foi possível gerar os exemplos: {$erro->getMessage()}\n");
    exit(1);
}
