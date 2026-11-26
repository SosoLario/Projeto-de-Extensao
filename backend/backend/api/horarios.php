<?php

require_once __DIR__ . '/../bootstrap.php';

exigirSessaoGestao();
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) { exigirCsrf(); exigirPerfilGestao(['administrador']); }

$horarioModel = new Horario();
$professorModel = new Professor();
$db = Database::conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        Resposta::sucesso(['horarios' => $horarioModel->listarTodos(), 'cursos' => $db->query('SELECT id,nome,valor FROM cursos WHERE ativo=1 ORDER BY nome')->fetchAll()]);
        break;

    case 'POST':
    case 'PUT':
        $dados = corpoRequisicao();
        validarDadosHorario($dados, $professorModel, $db);

        $id=$metodo==='PUT'?(int)($_GET['id']??0):0;
        if($metodo==='PUT'&&($id<1||!$horarioModel->buscarPorId($id)))Resposta::erro('Turma não encontrada.',404);
        if($metodo==='PUT'){$ocupadas=$db->prepare("SELECT COUNT(*) FROM matriculas WHERE horario_id=? AND situacao='ativa'");$ocupadas->execute([$id]);if((int)$dados['capacidade']<(int)$ocupadas->fetchColumn())Resposta::erro('A capacidade não pode ficar abaixo do número de alunos ativos.',409);}
        $conflito=$db->prepare("SELECT COUNT(*) FROM horarios WHERE professor_id=:professor AND dia_semana=:dia AND status='ativa' AND hora_inicio<:fim AND hora_fim>:inicio AND data_inicio<=COALESCE(:fim_turma,'9999-12-31') AND COALESCE(data_fim,'9999-12-31')>=:inicio_turma AND id<>:id");
        $conflito->execute(['professor'=>$dados['professor_id'],'dia'=>$dados['dia_semana'],'fim'=>$dados['hora_fim'],'inicio'=>$dados['hora_inicio'],'fim_turma'=>$dados['data_fim'],'inicio_turma'=>$dados['data_inicio'],'id'=>$id]);
        if ((int)$conflito->fetchColumn()>0) Resposta::erro('Esse professor já possui turma nesse intervalo.',409);

        if($metodo==='PUT'){$horarioModel->atualizar($id,$dados);Resposta::sucesso(['id'=>$id,'mensagem'=>'Turma atualizada.']);}
        $id = $horarioModel->criar($dados);
        Resposta::sucesso(['id' => $id, 'mensagem' => 'Horário cadastrado com sucesso.'], 201);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !$horarioModel->buscarPorId($id)) {
            Resposta::erro('Horário não encontrado.', 404);
        }
        $horarioModel->excluir($id);
        Resposta::sucesso(['mensagem' => 'Turma cancelada; o histórico foi preservado.']);
        break;

    default:
        Resposta::erro('Método não permitido.', 405);
}

function validarDadosHorario(array $dados, Professor $professorModel, PDO $db): void
{
    $diasValidos = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];

    if (empty($dados['turma']) || strlen(trim($dados['turma'])) < 2) {
        Resposta::erro('Informe o nome da turma.', 422);
    }
    if (empty($dados['professor_id']) || !$professorModel->buscarPorId((int) $dados['professor_id'])) {
        Resposta::erro('Selecione um professor válido.', 422);
    }
    if (empty($dados['dia_semana']) || !in_array($dados['dia_semana'], $diasValidos, true)) {
        Resposta::erro('Selecione um dia da semana válido.', 422);
    }
    if (empty($dados['hora_inicio'])) {
        Resposta::erro('Informe o horário de início.', 422);
    }
    $inicio=(string)$dados['hora_inicio'];$fim=(string)($dados['hora_fim']??'');
    if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$inicio)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$fim)||$fim<=$inicio)Resposta::erro('Informe horários válidos e término posterior ao início.',422);
    $curso=(int)($dados['curso_id']??0);$cap=(int)($dados['capacidade']??0);
    if($curso<1||$cap<1||$cap>100)Resposta::erro('Selecione o curso e defina capacidade entre 1 e 100 alunos.',422);
    $q=$db->prepare('SELECT id FROM cursos WHERE id=? AND ativo=1');$q->execute([$curso]);if(!$q->fetchColumn())Resposta::erro('Curso não encontrado ou arquivado.',422);
    $inicioData=(string)($dados['data_inicio']??'');$fimData=(string)($dados['data_fim']??'');
    if(!validarDataISO($inicioData)||($fimData!==''&&(!validarDataISO($fimData)||$fimData<$inicioData)))Resposta::erro('Confira as datas de início e término da turma.',422);
    $prof=$professorModel->buscarPorId((int)$dados['professor_id']);if(!$prof||$prof['status']!=='ativo')Resposta::erro('Selecione um professor ativo.',422);
    $status=(string)($dados['status']??'ativa');if(!in_array($status,['ativa','concluída','cancelada'],true))Resposta::erro('Situação da turma inválida.',422);
    $dados['turma']=trim((string)$dados['turma']);$dados['curso_id']=$curso;$dados['capacidade']=$cap;$dados['hora_fim']=$fim;$dados['data_inicio']=$inicioData;$dados['data_fim']=$fimData?:null;$dados['status']=$status;
}
