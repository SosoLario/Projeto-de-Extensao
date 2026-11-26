# ClassManager

Sistema web demonstrativo para administração de uma pequena escola de idiomas. Os registros pré-carregados são exemplos fictícios. O projeto usa HTML, CSS, JavaScript puro, PHP 8.2+, PDO e MySQL; não usa Node, frameworks de front-end ou ORM.

## O que está implementado

- Login de gestão, aluno e professor com sessão PHP e senhas em hash.
- Opção para mostrar ou ocultar a senha na tela de login.
- Gestão de alunos e professores com busca, edição, validações e campos pessoais.
- Redefinição da senha do aluno e do professor pela gestão; senha em branco mantém a senha atual e novas senhas precisam ter pelo menos 6 caracteres.
- Recuperação de senha por e-mail com código de 5 dígitos, confirmação da nova senha e opção de mostrar/ocultar os campos.
- Máscaras de CPF e telefone no formulário; validação também é feita no servidor.
- Cursos, turmas com curso/professor/horário/capacidade, validação de conflito do professor e matrículas com transferência/cancelamento.
- Portal do professor para registrar aula, conteúdo ministrado e presença, falta ou atraso; só permite acessar as próprias turmas e alunos matriculados.
- Painel do aluno com calendário mensal de horários; os dias com aulas matriculadas são marcados e a seleção de uma data mostra suas turmas, horários e professores.
- Painel do aluno com resumo e histórico mensal de frequência, incluindo presença, falta e atraso.
- Notas no painel do aluno com média geral, quantidade de avaliações, filtro por turma e histórico com data e nota.
- Painel do professor com agenda semanal, histórico de frequência das próprias turmas e chamada por aluno; as próximas aulas aparecem no seletor, mas só podem ser salvas na data da aula ou depois.
- Lançamento de notas no painel do professor com resumo e histórico filtrável por turma; as alterações ficam disponíveis no painel do aluno.
- Mensalidades do aluno e pagamentos separados na área da gestão.
- Painel de configurações para alunos e professores com atualização de nome, e-mail, telefone e senha da própria conta.
- Foto de perfil para alunos e professores: selecionar, pré-visualizar, salvar, trocar ou remover imagem JPG, PNG ou WebP de até 2 MB; a foto salva aparece junto ao nome no menu lateral.
- Preferências de notificações e tamanho do texto salvas por conta no navegador; os avisos automáticos ainda dependem de um serviço de notificações.
- Cancelamento de horários pela gestão, retirando-os da grade ativa e preservando o histórico de matrículas e pagamentos.
- Perfis de gestão Administrador e Secretaria, com permissões verificadas na API.
- Painel com indicadores financeiros e gráfico de receita mensal.
- Arquivamento de alunos, professores e cursos, preservando o histórico associado.

## Requisitos locais para demonstração

- Windows com XAMPP (Apache, PHP e MySQL) ou servidor equivalente com PHP 8.2+ e extensão `pdo_mysql` ativa.
- Navegador atualizado e conexão à internet para carregar Chart.js do CDN.
- phpMyAdmin ou MySQL Workbench para importar scripts SQL.

O PHP é executado pelo Apache do XAMPP; não é preciso instalar Node.js. A forma mais simples é copiar a pasta do projeto para `C:\xampp\htdocs\gestao-escola`, iniciar Apache e MySQL no painel do XAMPP e acessar `http://localhost/gestao-escola/`.

### Manter o projeto fora de `htdocs` (opcional)

Também é possível manter a pasta em Downloads e criar um alias no Apache. No arquivo `C:\xampp\apache\conf\httpd.conf`, adicione ao final (ajuste o caminho se a pasta estiver em outro local):

```apache
Alias /gestao-escola "C:/Users/Usuario/Downloads/gestao-escola-atualizado/gestao-escola"

<Directory "C:/Users/Usuario/Downloads/gestao-escola-atualizado/gestao-escola">
    AllowOverride All
    Require all granted
</Directory>
```

Salve o arquivo, reinicie o Apache pelo painel do XAMPP e acesse `http://localhost/gestao-escola/`.

## Preparar o banco pela primeira vez

1. No phpMyAdmin, importe `database/schema.sql`. Esse script cria o banco `classmanager` e os dados demonstrativos iniciais.
2. Depois importe **uma única vez** `database/002_modulos_academicos_financeiros.sql`, selecionando o banco `classmanager`. Essa migração acrescenta cursos, matrículas, aulas, frequência detalhada, mensalidades, pagamentos e perfis da gestão. Ela preserva os alunos, professores, horários, chamadas e notas já cadastrados e relaciona os registros antigos ao novo modelo.
3. Importe **uma única vez** `database/003_fotos_perfil.sql` no banco `classmanager` para habilitar fotos de perfil. Essa migração preserva todos os cadastros existentes.
4. Importe **uma única vez** `database/004_indices_portais.sql`. Ela adiciona índices para acelerar a consulta de frequência e notas nos painéis de aluno e professor; não altera nem apaga os registros.
5. Não execute novamente as migrações 002, 003 ou 004: elas contêm alterações de estrutura de execução única. Se uma execução parar no meio, verifique no phpMyAdmin quais comandos terminaram antes de tentar continuar.
6. (Opcional) Rode o gerador fictício pelo PowerShell após salvar o arquivo `.env`: `C:\xampp\php\php.exe backend\database\seeds\seed_demo.php`. Ele pode ser executado novamente; só cria exemplos ausentes e não apaga nem altera cadastros existentes.

Não importe o `schema.sql` novamente em um banco que já tenha dados que queira preservar: esse arquivo é apenas a criação inicial. A migração também cria uma mensalidade demonstrativa para cada matrícula que já existia.

O nome padrão do banco é `classmanager`. Se você já tinha uma instalação usando outro nome de banco, alterar `DB_NAME` ou importar o `schema.sql` não transfere os dados existentes. Faça um backup e migre os dados antes de apontar uma instalação existente para o novo banco.

Se a migração 002 já foi executada nesta instalação, importe 003 para habilitar fotos e depois 004 para adicionar índices dos portais. Se a 003 já foi aplicada, importe somente 004. O Apache precisa ter permissão de escrita na pasta do projeto para salvar arquivos em `backend/uploads/perfis`.

## Credenciais do banco

O arquivo `.env.example` é um modelo. Copie-o para `.env` na pasta raiz e preencha `DB_PASSWORD` com a senha que você definiu no MySQL. Para a instalação padrão do XAMPP, o usuário costuma ser `root` e a senha pode ficar vazia, mas use a configuração real do seu computador. O `.env` está no `.gitignore` e não deve ser enviado ao GitHub ou ao grupo.

```env
DB_HOST=127.0.0.1
DB_NAME=classmanager
DB_USER=root
DB_PASSWORD=sua_senha_mysql
DB_CHARSET=utf8mb4
```

Não coloque a senha do banco em arquivos do frontend nem em mensagens compartilhadas. Em hospedagem futura, as credenciais devem ser configuradas como variáveis protegidas do servidor, sem publicar o `.env`.

## Acessos de demonstração

O script inicial cria a conta principal de administrador e os usuários de exemplo fictícios abaixo, todos utilizando a senha padrão **`admin123`**:

| Conta | E-mail | Senha |
| :--- | :--- | :--- |
| **Administrador** | `admin@classmanager.com` | `admin123` |
| **Usuário de exemplo** | `bruno.alves@classmanager.com` | `admin123` |
| **Usuário de exemplo** | `camila.souza@classmanager.com` | `admin123` |
| **Usuário de exemplo** | `joao.lima@email.com` | `admin123` |
| **Usuário de exemplo** | `ana.ferreira@email.com` | `admin123` |

Troque ou recrie as senhas antes de compartilhar a aplicação fora de apresentação acadêmica. Administradores podem cadastrar contas de Secretaria na tela **Cursos e matrículas → Usuários**.

### Contas do gerador opcional

O gerador opcional também cria as seguintes contas fictícias:

* **Secretaria:** `secretaria.demo@classmanager.test` / `Secretaria@123`
* **Professor:** `professor.demo@classmanager.test` / `Professor@123`
* **Aluno:** `aluno.demo@classmanager.test` / `Aluno@123`

## Estrutura

```text
gestao-escola/
├── backend/
│   ├── api/          # endpoints JSON e regras de acesso
│   ├── classes/      # acesso às entidades com PDO
│   ├── config/       # configuração e conexão com banco
│   ├── helpers/      # respostas e e-mail opcional
│   └── bootstrap.php # sessão, segurança e carregamento
├── database/
│   ├── schema.sql
│   ├── 002_modulos_academicos_financeiros.sql
│   ├── 003_fotos_perfil.sql
│   └── 004_indices_portais.sql
└── frontend/
    ├── css/
    ├── js/
    ├── login.html
    ├── dashboard.html
    ├── gestao.html
    ├── financeiro.html
    ├── aluno.html
    └── professor.html
```

## E-mail de recuperação (opcional)

Sem SMTP configurado, o restante do sistema funciona normalmente, mas o envio de código de recuperação não estará disponível. Para habilitá-lo:

1. Instale o Composer para Windows e, durante a instalação, selecione o PHP do XAMPP (`C:\xampp\php\php.exe`).
2. Se o Composer informar que ZIP e Unzip estão ausentes, habilite `extension=zip` em `C:\xampp\php\php.ini` (remova o `;` inicial da linha `;extension=zip`), salve e abra um novo terminal.
3. Abra o PowerShell na pasta raiz do projeto e execute `composer install` para instalar o PHPMailer.
4. Preencha as variáveis abaixo no `.env` da pasta raiz com os dados SMTP do provedor. Para Gmail, o host é `smtp.gmail.com` e a porta é `587` (STARTTLS); o usuário e o remetente são o endereço Gmail usado para envio.

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=seu-email@gmail.com
SMTP_PASS=sua-senha-de-aplicativo
SMTP_FROM_EMAIL=seu-email@gmail.com
SMTP_FROM_NOME=ClassManager
```

Use uma senha de aplicativo do provedor de e-mail, nunca a senha normal da conta. Não compartilhe a senha SMTP nem envie o `.env` ao GitHub ou ao grupo.

## Hospedagem futura

O GitHub armazena e versiona o código, mas não executa PHP. Para disponibilizar o sistema externamente, será necessário contratar hospedagem com PHP 8.2+, HTTPS e acesso ao MySQL; depois configurar as variáveis de ambiente e atualizar `APP_ORIGIN`. A escolha do provedor fica para decisão do grupo. Os arquivos atuais não contêm credenciais de hospedagem.
