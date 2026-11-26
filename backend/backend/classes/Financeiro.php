<?php
class Financeiro
{
    private PDO $db;
    public function __construct() { $this->db=Database::conectar(); }

    public function mensalidades(): array
    {
        $this->db->exec("UPDATE mensalidades SET situacao='atrasado' WHERE situacao='pendente' AND vencimento<CURDATE()");
        return $this->db->query("SELECT f.id,f.matricula_id,f.referencia,f.valor,f.desconto,f.multa,f.juros,f.vencimento,f.situacao,a.nome aluno_nome,h.turma,COALESCE(p.total,0) total_pago,GREATEST(f.valor-f.desconto+f.multa+f.juros-COALESCE(p.total,0),0) saldo FROM mensalidades f JOIN matriculas m ON m.id=f.matricula_id JOIN alunos a ON a.id=m.aluno_id JOIN horarios h ON h.id=m.horario_id LEFT JOIN (SELECT mensalidade_id,SUM(valor) total FROM pagamentos GROUP BY mensalidade_id) p ON p.mensalidade_id=f.id ORDER BY f.vencimento DESC,f.id DESC")->fetchAll();
    }

    public function pagamentos(): array
    {
        return $this->db->query('SELECT p.id,p.mensalidade_id,p.valor,p.data_pagamento,p.forma_pagamento,p.observacoes,a.nome aluno_nome,f.referencia FROM pagamentos p JOIN mensalidades f ON f.id=p.mensalidade_id JOIN matriculas m ON m.id=f.matricula_id JOIN alunos a ON a.id=m.aluno_id ORDER BY p.data_pagamento DESC,p.id DESC')->fetchAll();
    }

    public function criarMensalidade(array $dados): int
    {
        $matricula=(int)($dados['matricula_id']??0); $referencia=(string)($dados['referencia']??''); $valor=(float)($dados['valor']??0); $vencimento=(string)($dados['vencimento']??'');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$referencia) || !validarDataISO($vencimento) || $valor<=0 || !is_finite($valor)) throw new InvalidArgumentException('Confira matrícula, referência, valor e vencimento.');
        $duplicada=$this->db->prepare('SELECT id FROM mensalidades WHERE matricula_id=? AND referencia=?');$duplicada->execute([$matricula,$referencia]);
        if($duplicada->fetchColumn())throw new InvalidArgumentException('Já existe mensalidade para esta matrícula e referência.');
        $check=$this->db->prepare("SELECT id FROM matriculas WHERE id=:id AND situacao='ativa'"); $check->execute(['id'=>$matricula]);
        if (!$check->fetchColumn()) throw new InvalidArgumentException('Selecione uma matrícula ativa.');
        $desconto=(float)($dados['desconto']??0);$multa=(float)($dados['multa']??0);$juros=(float)($dados['juros']??0);
        if($desconto>$valor||$multa>99999999||$juros>99999999)throw new InvalidArgumentException('Confira desconto, multa e juros.');
        $stmt=$this->db->prepare("INSERT INTO mensalidades (matricula_id,referencia,valor,desconto,multa,juros,vencimento,situacao) VALUES (:matricula,:referencia,:valor,:desconto,:multa,:juros,:vencimento,IF(:vencimento_status<CURDATE(),'atrasado','pendente'))");
        $stmt->execute(['matricula'=>$matricula,'referencia'=>$referencia,'valor'=>number_format($valor,2,'.',''),'desconto'=>number_format($desconto,2,'.',''),'multa'=>number_format($multa,2,'.',''),'juros'=>number_format($juros,2,'.',''),'vencimento'=>$vencimento,'vencimento_status'=>$vencimento]);
        return (int)$this->db->lastInsertId();
    }

    public function registrarPagamento(array $dados): int
    {
        $id=(int)($dados['mensalidade_id']??0); $valor=(float)($dados['valor']??0); $forma=(string)($dados['forma_pagamento']??''); $data=(string)($dados['data_pagamento']??date('Y-m-d H:i:s'));
        if ($valor<=0 || !is_finite($valor) || !in_array($forma,['dinheiro','pix','cartão','transferência','outro'],true)) throw new InvalidArgumentException('Confira valor e forma de pagamento.');
        $data=str_replace('T',' ',$data); if(strlen($data)===16)$data.=':00';
        $dataObjeto=DateTime::createFromFormat('!Y-m-d H:i:s',$data);
        if(!$dataObjeto||$dataObjeto->format('Y-m-d H:i:s')!==$data||$data>date('Y-m-d H:i:s'))throw new InvalidArgumentException('Informe data e horário de pagamento válidos, sem usar uma data futura.');
        $this->db->beginTransaction();
        try {
        $stmt=$this->db->prepare("SELECT valor,desconto,multa,juros,vencimento FROM mensalidades WHERE id=:id AND situacao<>'cancelado' FOR UPDATE"); $stmt->execute(['id'=>$id]); $fatura=$stmt->fetch();
            if(!$fatura)throw new InvalidArgumentException('Mensalidade não encontrada ou cancelada.');
            $soma=$this->db->prepare('SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE mensalidade_id=:id'); $soma->execute(['id'=>$id]);
            $saldo=(float)$fatura['valor']-(float)$fatura['desconto']+(float)$fatura['multa']+(float)$fatura['juros']-(float)$soma->fetchColumn();
            if($valor>$saldo+0.005)throw new InvalidArgumentException('O pagamento excede o saldo da mensalidade.');
            $insert=$this->db->prepare('INSERT INTO pagamentos (mensalidade_id,valor,data_pagamento,forma_pagamento,observacoes) VALUES (:id,:valor,:data,:forma,:observacoes)');
            $insert->execute(['id'=>$id,'valor'=>number_format($valor,2,'.',''),'data'=>$data,'forma'=>$forma,'observacoes'=>trim((string)($dados['observacoes']??''))?:null]);
            $restante=$saldo-$valor;
            $status=$restante<=0.005?'pago':((string)($fatura['vencimento']??'')<date('Y-m-d')?'atrasado':'pendente');
            $update=$this->db->prepare('UPDATE mensalidades SET situacao=:status WHERE id=:id'); $update->execute(['status'=>$status,'id'=>$id]);
            $this->db->commit();return (int)$this->db->lastInsertId();
        } catch(Throwable $erro){if($this->db->inTransaction())$this->db->rollBack();throw $erro;}
    }
}
