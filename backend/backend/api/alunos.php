<?php

require_once __DIR__ . '/../bootstrap.php';

exigirSessaoGestao();
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) exigirCsrf();

$alunoModel = new Aluno();
$horarioModel = new Horario();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        if (isset($_GET['id'])) {
            $aluno = $alunoModel->buscarPorId((int) $_GET['id']);
            if (!$aluno) Resposta::erro('Aluno não encontrado.', 404);
            unset($aluno['senha_hash']);
            Resposta::sucesso(['aluno' => $aluno]);
        }
        Resposta::sucesso(['alunos' => $alunoModel->listarTodos()]);
        break;

    case 'POST':
        $dados = corpoRequisicao();
        validarDadosAluno($dados, $horarioModel);

        if (emailEmUsoSistema($dados['email'],'alunos')) {
            Resposta::erro('Já existe um aluno cadastrado com esse e-mail.', 409);
        }

        try { $id = $alunoModel->criar($dados); } catch (InvalidArgumentException $e) { Resposta::erro($e->getMessage(), 422); }
        Resposta::sucesso(['id' => $id, 'mensagem' => 'Aluno cadastrado com sucesso.'], 201);
        break;

    case 'PUT':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !$alunoModel->buscarPorId($id)) {
            Resposta::erro('Aluno não encontrado.', 404);
        }

        $dados = corpoRequisicao();
        validarDadosAluno($dados, $horarioModel);

        if (emailEmUsoSistema($dados['email'],'alunos',$id)) {
            Resposta::erro('Já existe outro aluno cadastrado com esse e-mail.', 409);
        }

        try { $alunoModel->atualizar($id, $dados); } catch (InvalidArgumentException $e) { Resposta::erro($e->getMessage(), 422); }
        Resposta::sucesso(['mensagem' => 'Aluno atualizado com sucesso.']);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !$alunoModel->buscarPorId($id)) {
            Resposta::erro('Aluno não encontrado.', 404);
        }
        $alunoModel->excluir($id);
        Resposta::sucesso(['mensagem' => 'Aluno arquivado; o histórico foi preservado.']);
        break;

    default:
        Resposta::erro('Método não permitido.', 405);
}

function validarDadosAluno(array &$dados, Horario $horarioModel): void
{
    $dados['email']=strtolower(trim((string)($dados['email']??'')));
    $dados['senha']=(string)($dados['senha']??'');
    if ($dados['senha'] !== '' && strlen($dados['senha']) < 6) {
        Resposta::erro('A senha precisa ter pelo menos 6 caracteres.', 422);
    }
    $niveisValidos = ['Iniciante', 'Básico', 'Intermediário', 'Avançado'];
    $statusValidos = ['ativo', 'inativo', 'trancado'];

    if (empty($dados['nome']) || mb_strlen(trim($dados['nome'])) < 3 || mb_strlen(trim($dados['nome'])) > 120) {
        Resposta::erro('Informe o nome completo do aluno.', 422);
    }
    if (empty($dados['email']) || mb_strlen($dados['email'])>150 || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        Resposta::erro('Informe um e-mail válido.', 422);
    }
    $responsavelEmail=strtolower(trim((string)($dados['responsavel_email']??'')));
    if($responsavelEmail!==''&&!filter_var($responsavelEmail,FILTER_VALIDATE_EMAIL))Resposta::erro('Informe um e-mail válido para o responsável.',422);
    $dados['responsavel_email']=$responsavelEmail?:null;
    if (empty($dados['nivel']) || !in_array($dados['nivel'], $niveisValidos, true)) {
        Resposta::erro('Selecione um nível de inglês válido.', 422);
    }
    if (empty($dados['status']) || !in_array($dados['status'], $statusValidos, true)) {
        Resposta::erro('Selecione um status válido.', 422);
    }
    foreach (['telefone','responsavel_telefone'] as $campo) {
        $original=trim((string)($dados[$campo]??''));$digitos = preg_replace('/\D/', '', $original);
        if ($original!=='' && !in_array(strlen($digitos), [10,11], true)) Resposta::erro('Informe um telefone com DDD e 10 ou 11 dígitos.', 422);
        $dados[$campo] = $digitos === '' ? null : (strlen($digitos)===11 ? sprintf('(%s) %s-%s',substr($digitos,0,2),substr($digitos,2,5),substr($digitos,7)) : sprintf('(%s) %s-%s',substr($digitos,0,2),substr($digitos,2,4),substr($digitos,6)));
    }
    $cpfOriginal=trim((string)($dados['cpf']??''));$cpf=preg_replace('/\D/','',$cpfOriginal);
    if ($cpfOriginal!=='' && !cpfValido($cpf)) Resposta::erro('CPF inválido. Confira os números digitados.',422);
    $dados['cpf']=$cpf===''?null:sprintf('%s.%s.%s-%s',substr($cpf,0,3),substr($cpf,3,3),substr($cpf,6,3),substr($cpf,9,2));
    $nascimento=(string)($dados['data_nascimento']??'');
    if ($nascimento!=='' && (!validarDataISO($nascimento)||$nascimento>date('Y-m-d'))) Resposta::erro('Informe uma data de nascimento válida e não futura.',422);
    $responsavel=trim((string)($dados['responsavel_nome']??''));
    if ($nascimento!=='' && (int)(new DateTime($nascimento))->diff(new DateTime('today'))->y<18 && $responsavel==='') Resposta::erro('Informe o responsável legal para aluno menor de idade.',422);
    // Turma/horário é opcional, mas se for enviada precisa existir de fato.
    if (!empty($dados['horario_id']) && !$horarioModel->buscarPorId((int) $dados['horario_id'])) {
        Resposta::erro('A turma/horário selecionado não existe.', 422);
    }
}

function cpfValido(string $cpf): bool
{
    if (strlen($cpf)!==11 || preg_match('/^(\d)\1{10}$/',$cpf)) return false;
    for($t=9;$t<11;$t++){$s=0;for($i=0;$i<$t;$i++)$s+=(int)$cpf[$i]*(($t+1)-$i);$d=(($s*10)%11)%10;if((int)$cpf[$t]!==$d)return false;}
    return true;
}
