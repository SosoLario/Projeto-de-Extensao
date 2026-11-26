<?php
require_once __DIR__ . '/../bootstrap.php';
exigirPerfilGestao(['administrador']);
$db=Database::conectar();$method=$_SERVER['REQUEST_METHOD'];if(in_array($method,['POST','PUT','PATCH','DELETE'],true))exigirCsrf();
try{
 if($method==='GET'){Resposta::sucesso(['usuarios'=>$db->query("SELECT id,nome,email,perfil,status,criado_em FROM gestores ORDER BY nome")->fetchAll()]);}
 if($method==='PATCH'){$id=(int)($_GET['id']??0);$s=$db->prepare("UPDATE gestores SET status='ativo' WHERE id=?");$s->execute([$id]);if(!$s->rowCount()){$q=$db->prepare('SELECT id FROM gestores WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())Resposta::erro('Usuário não encontrado.',404);}Resposta::sucesso(['mensagem'=>'Acesso reativado.']);}
 if($method==='DELETE'){$id=(int)($_GET['id']??0);if($id===(int)$_SESSION['gestor_id'])Resposta::erro('Não é possível desativar a própria conta.',422);$q=$db->prepare("SELECT COUNT(*) FROM gestores WHERE perfil='administrador' AND status='ativo'");$q->execute();if((int)$q->fetchColumn()<=1){$q=$db->prepare("SELECT perfil,status FROM gestores WHERE id=?");$q->execute([$id]);$alvo=$q->fetch();if($alvo&&$alvo['perfil']==='administrador'&&$alvo['status']==='ativo')Resposta::erro('Mantenha pelo menos um administrador ativo.',422);}$s=$db->prepare("UPDATE gestores SET status='inativo' WHERE id=?");$s->execute([$id]);if(!$s->rowCount())Resposta::erro('Usuário não encontrado ou já inativo.',404);Resposta::sucesso(['mensagem'=>'Acesso desativado.']);}
 if(!in_array($method,['POST','PUT'],true))Resposta::erro('Método não permitido.',405);
 $d=corpoRequisicao();$id=$method==='PUT'?(int)($_GET['id']??0):0;$nome=trim((string)($d['nome']??''));$email=strtolower(trim((string)($d['email']??'')));$perfil=(string)($d['perfil']??'');$senha=(string)($d['senha']??'');
 if(mb_strlen($nome)<3||mb_strlen($nome)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($perfil,['administrador','secretaria'],true))Resposta::erro('Confira nome, e-mail e perfil.',422);
 if($method==='POST'&&strlen($senha)<8)Resposta::erro('A senha inicial precisa ter pelo menos 8 caracteres.',422);
 if($method==='PUT'&&$id===(int)$_SESSION['gestor_id']&&$perfil!=='administrador')Resposta::erro('Não é possível remover seu próprio acesso de administrador.',422);
 if($senha!==''&&strlen($senha)<8)Resposta::erro('A senha precisa ter pelo menos 8 caracteres.',422);
 $q=$db->prepare('SELECT id FROM gestores WHERE email=? AND id<>? UNION SELECT id FROM alunos WHERE email=? UNION SELECT id FROM professores WHERE email=? LIMIT 1');$q->execute([$email,$id,$email,$email]);if($q->fetchColumn())Resposta::erro('Este e-mail já está em uso no sistema.',409);
 if($method==='POST'){$s=$db->prepare("INSERT INTO gestores(nome,email,senha_hash,perfil,status) VALUES(?,?,? ,?,'ativo')");$s->execute([$nome,$email,password_hash($senha,PASSWORD_DEFAULT),$perfil]);$id=(int)$db->lastInsertId();}
 else{$s=$db->prepare('SELECT id FROM gestores WHERE id=?');$s->execute([$id]);if(!$s->fetchColumn())Resposta::erro('Usuário não encontrado.',404);$sql='UPDATE gestores SET nome=?,email=?,perfil=?'.($senha!==''?',senha_hash=?':'').' WHERE id=?';$args=[$nome,$email,$perfil];if($senha!=='')$args[]=password_hash($senha,PASSWORD_DEFAULT);$args[]=$id;$db->prepare($sql)->execute($args);}
 Resposta::sucesso(['id'=>$id,'mensagem'=>'Usuário salvo.'],$method==='POST'?201:200);
}catch(InvalidArgumentException $e){Resposta::erro($e->getMessage(),422);}
