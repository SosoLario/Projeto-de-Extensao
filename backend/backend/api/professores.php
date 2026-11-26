<?php

require_once __DIR__ . '/../bootstrap.php';

exigirPerfilGestao(['administrador']);
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) exigirCsrf();

$professorModel = new Professor();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        if (isset($_GET['id'])) {
            $professor = $professorModel->buscarPorId((int) $_GET['id']);
            if (!$professor) Resposta::erro('Professor não encontrado.', 404);
            unset($professor['senha_hash']);
            Resposta::sucesso(['professor' => $professor]);
        }
        Resposta::sucesso(['professores' => $professorModel->listarTodos()]);
        break;

    case 'POST':
        $dados = corpoRequisicao();
        validarDadosProfessor($dados);

        if (emailEmUsoSistema($dados['email'],'professores')) {
            Resposta::erro('Já existe um professor cadastrado com esse e-mail.', 409);
        }

        $id = $professorModel->criar($dados);
        Resposta::sucesso(['id' => $id, 'mensagem' => 'Professor cadastrado com sucesso.'], 201);
        break;

    case 'PUT':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !$professorModel->buscarPorId($id)) {
            Resposta::erro('Professor não encontrado.', 404);
        }

        $dados = corpoRequisicao();
        validarDadosProfessor($dados);

        if (emailEmUsoSistema($dados['email'],'professores',$id)) {
            Resposta::erro('Já existe outro professor cadastrado com esse e-mail.', 409);
        }

        $professorModel->atualizar($id, $dados);
        Resposta::sucesso(['mensagem' => 'Professor atualizado com sucesso.']);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !$professorModel->buscarPorId($id)) {
            Resposta::erro('Professor não encontrado.', 404);
        }
        $professorModel->excluir($id);
        Resposta::sucesso(['mensagem' => 'Professor arquivado; turmas e registros foram preservados.']);
        break;

    default:
        Resposta::erro('Método não permitido.', 405);
}

function validarDadosProfessor(array &$dados): void
{
    $dados['email']=strtolower(trim((string)($dados['email']??'')));
    $dados['senha']=(string)($dados['senha']??'');
    if ($dados['senha'] !== '' && strlen($dados['senha']) < 6) {
        Resposta::erro('A senha precisa ter pelo menos 6 caracteres.', 422);
    }
    $statusValidos = ['ativo', 'inativo'];

    if (empty($dados['nome']) || mb_strlen(trim($dados['nome'])) < 3 || mb_strlen(trim($dados['nome'])) > 120) {
        Resposta::erro('Informe o nome completo do professor.', 422);
    }
    if (empty($dados['email']) || mb_strlen($dados['email'])>150 || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        Resposta::erro('Informe um e-mail válido.', 422);
    }
    if (empty($dados['especialidade'])) {
        Resposta::erro('Informe a especialidade do professor.', 422);
    }
    $idiomas=trim((string)($dados['idiomas']??''));if($idiomas===''||mb_strlen($idiomas)>255)Resposta::erro('Informe os idiomas que o professor leciona.',422);$dados['idiomas']=$idiomas;
    if (empty($dados['carga_horaria']) || (int) $dados['carga_horaria'] <= 0 || (int) $dados['carga_horaria'] > 60) {
        Resposta::erro('Informe uma carga horária semanal válida (1 a 60 horas).', 422);
    }
    if (empty($dados['status']) || !in_array($dados['status'], $statusValidos, true)) {
        Resposta::erro('Selecione um status válido.', 422);
    }
    $telefoneOriginal=trim((string)($dados['telefone']??''));$telefone=preg_replace('/\D/','',$telefoneOriginal);
    if($telefoneOriginal!==''&&!in_array(strlen($telefone),[10,11],true))Resposta::erro('Informe telefone com DDD e 10 ou 11 dígitos.',422);
    $dados['telefone']=$telefone===''?null:(strlen($telefone)===11?sprintf('(%s) %s-%s',substr($telefone,0,2),substr($telefone,2,5),substr($telefone,7)):sprintf('(%s) %s-%s',substr($telefone,0,2),substr($telefone,2,4),substr($telefone,6)));
    $cpfOriginal=trim((string)($dados['cpf']??''));$cpf=preg_replace('/\D/','',$cpfOriginal);
    if($cpfOriginal!==''&&!cpfProfessorValido($cpf))Resposta::erro('CPF inválido. Confira os números digitados.',422);
    $dados['cpf']=$cpf===''?null:sprintf('%s.%s.%s-%s',substr($cpf,0,3),substr($cpf,3,3),substr($cpf,6,3),substr($cpf,9,2));
    $nascimento=(string)($dados['data_nascimento']??'');if($nascimento!==''&&(!validarDataISO($nascimento)||$nascimento>date('Y-m-d')))Resposta::erro('Informe data de nascimento válida e não futura.',422);
}

function cpfProfessorValido(string $cpf): bool { if(strlen($cpf)!==11||preg_match('/^(\d)\1{10}$/',$cpf))return false;for($t=9;$t<11;$t++){$s=0;for($i=0;$i<$t;$i++)$s+=(int)$cpf[$i]*(($t+1)-$i);if((($s*10)%11)%10!==(int)$cpf[$t])return false;}return true; }
