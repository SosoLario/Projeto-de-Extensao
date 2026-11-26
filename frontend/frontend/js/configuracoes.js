(() => {
  const API = '../backend/api';
  const el = id => document.getElementById(id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const preferenciasPadrao = { frequencia: true, notas: true, horarios: true, matriculas: true, lembretes: true, texto: 'normal' };
  let chavePreferencias = '';
  let fotoAtual = null;
  let temporizadorMensagem;

  function atualizarAvatar(fotoUrl, nome) {
    const avatar = el('user-avatar');
    if (!avatar) return;
    avatar.replaceChildren();
    if (fotoUrl) {
      const imagem = document.createElement('img');
      imagem.src = fotoUrl;
      imagem.alt = `Foto de perfil de ${nome || 'usuário'}`;
      avatar.appendChild(imagem);
    } else {
      avatar.textContent = (nome || '?').trim().split(/\s+/).slice(0, 2).map(parte => parte[0]?.toUpperCase()).join('') || '?';
    }
  }

  window.atualizarAvatarConta = atualizarAvatar;
  window.carregarAvatarConta = async function (nome = '') {
    try {
      const conta = await requisicao('minha_conta.php');
      fotoAtual = conta.foto_url;
      atualizarAvatar(fotoAtual, conta.perfil.nome || nome);
      return conta;
    } catch (_) {
      atualizarAvatar(null, nome);
      return null;
    }
  };

  function lerPreferencias() {
    try { return { ...preferenciasPadrao, ...JSON.parse(localStorage.getItem(chavePreferencias) || '{}') }; }
    catch (_) { return { ...preferenciasPadrao }; }
  }

  function salvarPreferencias(preferencias) {
    try { localStorage.setItem(chavePreferencias, JSON.stringify(preferencias)); }
    catch (_) { /* A tela continua utilizável se o navegador bloquear armazenamento local. */ }
  }

  function mensagem(texto, tipo = 'success') {
    const alvo = el('configuracoes-mensagem');
    if (!alvo) return;
    clearTimeout(temporizadorMensagem);
    alvo.textContent = texto;
    alvo.className = `settings-message ${tipo}`;
    if (texto && tipo !== 'error') {
      temporizadorMensagem = setTimeout(() => {
        alvo.textContent = '';
        alvo.className = 'settings-message';
      }, 4000);
    }
  }

  async function requisicao(path, options = {}) {
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (options.method === 'POST') headers['X-CSRF-Token'] = sessionStorage.getItem('classmanager_csrf') || '';
    const resposta = await fetch(`${API}/${path}`, { credentials: 'include', ...options, headers });
    const dados = await resposta.json();
    if (!resposta.ok || !dados.sucesso) throw new Error(dados.mensagem || 'Não foi possível concluir a operação.');
    return dados;
  }

  function aplicarAcessibilidade(valor) {
    document.documentElement.classList.toggle('texto-maior', valor === 'maior');
  }

  window.aplicarPreferenciaSalva = function (tipo, id) {
    chavePreferencias = `classmanager.preferencias.${tipo}.${id}`;
    aplicarAcessibilidade(lerPreferencias().texto);
  };

  window.renderConfiguracoes = async function () {
    const container = el('conteudo');
    if (!container) return;
    container.classList.add('settings-host');
    container.innerHTML = '<p>Carregando suas configurações…</p>';

    try {
      const dados = await requisicao('minha_conta.php');
      const perfil = dados.perfil;
      fotoAtual = dados.foto_url;
      atualizarAvatar(fotoAtual, perfil.nome);
      chavePreferencias = `classmanager.preferencias.${dados.tipo}.${dados.id}`;
      const preferencias = lerPreferencias();
      aplicarAcessibilidade(preferencias.texto);
      const opcoesNotificacao = dados.tipo === 'professor'
        ? [
            { chave: 'horarios', titulo: 'Alterações de horários e turmas', descricao: 'Saiba quando a gestão mudar os horários ou as turmas.' },
            { chave: 'matriculas', titulo: 'Novas matrículas e cancelamentos', descricao: 'Receba avisos sobre entradas e saídas de alunos nas suas turmas.' },
            { chave: 'lembretes', titulo: 'Lembretes de aula', descricao: 'Receba lembretes das próximas aulas da sua agenda.' },
          ]
        : [
            { chave: 'frequencia', titulo: 'Avisos de frequência', descricao: 'Receba notificações sobre sua presença nas aulas.' },
            { chave: 'notas', titulo: 'Novas notas', descricao: 'Seja avisado quando novas notas forem publicadas.' },
            { chave: 'horarios', titulo: 'Alterações de horários', descricao: 'Receba alertas sobre mudanças nos horários de aula.' },
          ];
      const notificacoesHtml = opcoesNotificacao.map(opcao => `
        <label class="settings-toggle-row"><span><strong>${opcao.titulo}</strong><small>${opcao.descricao}</small></span><input type="checkbox" data-preferencia="${opcao.chave}" ${preferencias[opcao.chave] ? 'checked' : ''}><span class="settings-switch" aria-hidden="true"></span></label>
      `).join('');
      container.innerHTML = `
        <div class="settings-page">
          <header class="settings-heading"><span class="settings-heading-icon">♙</span><div><h2>Meu Perfil</h2><p>Visualize e gerencie seus dados pessoais.</p></div></header>
          <div id="configuracoes-mensagem" class="settings-message" role="status" aria-live="polite"></div>
          <section class="settings-profile-banner">
            <div class="settings-profile-avatar-wrap"><div class="settings-photo-preview" id="foto-perfil-preview"></div><button type="button" class="settings-camera-button" id="selecionar-foto" aria-label="Escolher foto de perfil" title="Escolher foto"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h3l1.5-2h7l1.5 2h3a1.5 1.5 0 0 1 1.5 1.5v9a1.5 1.5 0 0 1-1.5 1.5h-16A1.5 1.5 0 0 1 2.5 18v-9A1.5 1.5 0 0 1 4 7.5Z"/><circle cx="12" cy="13" r="3.5"/></svg></button></div>
            <div class="settings-profile-summary"><h2 id="perfil-nome">${esc(perfil.nome)}</h2><p>${dados.tipo === 'aluno' ? 'Aluno(a)' : 'Professor(a)'}</p><span>${esc(dados.identificador_rotulo)}: ${esc(dados.identificador)}</span></div>
            <span class="settings-profile-status">${dados.status === 'ativo' ? 'Ativo' : 'Inativo'}</span>
          </section>
          <input type="file" id="foto-perfil" accept="image/jpeg,image/png,image/webp" hidden>
          <div class="settings-photo-actions" id="acoes-foto" ${fotoAtual ? '' : 'hidden'}><button type="button" class="btn btn-primary" id="salvar-foto" hidden>Salvar foto</button><button type="button" class="btn btn-ghost" id="remover-foto">Remover foto</button><button type="button" class="btn btn-ghost" id="escolher-outra-foto">Escolher outra</button></div>
          <section class="settings-card settings-profile">
            <div class="settings-card-heading"><span class="settings-icon blue">♙</span><div><h3>Dados pessoais</h3><p>Mantenha suas informações sempre atualizadas.</p></div></div>
            <form id="configuracoes-perfil-form" class="settings-profile-grid">
              <label>Nome completo<input id="config-nome" maxlength="120" required value="${esc(perfil.nome)}"></label>
              <label>E-mail<input id="config-email" type="email" maxlength="150" required value="${esc(perfil.email)}"></label>
              <label>Telefone<input id="config-telefone" type="tel" maxlength="20" value="${esc(perfil.telefone)}" placeholder="(11) 98765-4321"></label>
              <label class="settings-locked">${esc(dados.identificador_rotulo)}<input readonly value="${esc(dados.identificador)}"><span>Este dado é somente para consulta.</span></label>
              <div class="settings-form-actions"><button type="submit" class="btn btn-primary">Salvar alterações</button></div>
            </form>
          </section>
          <div class="settings-columns">
            <div class="settings-stack">
              <section class="settings-card">
                <div class="settings-card-heading"><span class="settings-icon violet">♢</span><div><h3>Segurança da conta</h3><p>Mantenha sua conta protegida.</p></div></div>
                <div class="settings-row"><div><strong>Alterar senha</strong><p>Use uma senha com pelo menos 6 caracteres.</p></div><button type="button" class="btn btn-primary" id="mostrar-form-senha">Atualizar senha</button></div>
                <form id="configuracoes-senha-form" class="settings-password-form" hidden>
                  <label>Senha atual<div class="settings-password-field"><input type="password" id="senha-atual" autocomplete="current-password" required><button type="button" data-ver-senha="senha-atual">Mostrar</button></div></label>
                  <label>Nova senha<div class="settings-password-field"><input type="password" id="nova-senha" minlength="6" autocomplete="new-password" required><button type="button" data-ver-senha="nova-senha">Mostrar</button></div></label>
                  <label>Confirme a nova senha<div class="settings-password-field"><input type="password" id="confirmacao-senha" minlength="6" autocomplete="new-password" required><button type="button" data-ver-senha="confirmacao-senha">Mostrar</button></div></label>
                  <div class="settings-form-actions"><button class="btn btn-primary" type="submit">Salvar nova senha</button><button class="btn btn-ghost" type="button" id="cancelar-form-senha">Cancelar</button></div>
                </form>
              </section>
              <section class="settings-card">
                <div class="settings-card-heading"><span class="settings-icon green">◉</span><div><h3>Acessibilidade</h3><p>Ajuste a visualização do sistema.</p></div></div>
                <fieldset class="settings-text-size"><legend>Tamanho do texto</legend><label><input type="radio" name="tamanho-texto" value="normal" ${preferencias.texto === 'normal' ? 'checked' : ''}>Padrão</label><label><input type="radio" name="tamanho-texto" value="maior" ${preferencias.texto === 'maior' ? 'checked' : ''}>Maior</label></fieldset>
              </section>
            </div>
            <section class="settings-card settings-notifications">
              <div class="settings-card-heading"><span class="settings-icon orange">♧</span><div><h3>Preferências de notificação</h3><p>Escolha quais avisos deseja receber.</p></div></div>
              ${notificacoesHtml}
              <p class="settings-note">As preferências ficam salvas neste navegador. Os avisos automáticos dependem de recursos de notificação que ainda não estão ativos no sistema.</p>
            </section>
          </div>
        </div>`;
      const previewFoto = el('foto-perfil-preview');
      const acoesFoto = el('acoes-foto');
      const mostrarPreview = url => {
        previewFoto.replaceChildren();
        if (url) {
          const img = document.createElement('img');
          img.src = url;
          img.alt = `Prévia da foto de ${perfil.nome}`;
          previewFoto.appendChild(img);
        } else {
          const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
          svg.setAttribute('viewBox', '0 0 48 48');
          svg.setAttribute('aria-hidden', 'true');
          const head = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
          head.setAttribute('cx', '24'); head.setAttribute('cy', '16'); head.setAttribute('r', '8');
          const body = document.createElementNS('http://www.w3.org/2000/svg', 'path');
          body.setAttribute('d', 'M8 42c1-10 7-15 16-15s15 5 16 15');
          svg.append(head, body);
          previewFoto.appendChild(svg);
        }
      };
      mostrarPreview(fotoAtual);
      const restaurarAcoesFoto = () => {
        el('salvar-foto').hidden = true;
        el('remover-foto').hidden = !fotoAtual;
        el('escolher-outra-foto').hidden = !fotoAtual;
        acoesFoto.hidden = !fotoAtual;
      };
      el('selecionar-foto').addEventListener('click', () => el('foto-perfil').click());
      el('escolher-outra-foto').addEventListener('click', () => el('foto-perfil').click());
      el('foto-perfil').addEventListener('change', () => {
        const arquivo = el('foto-perfil').files[0];
        if (!arquivo) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(arquivo.type) || arquivo.size > 2 * 1024 * 1024) {
          el('foto-perfil').value = '';
          mostrarPreview(fotoAtual);
          restaurarAcoesFoto();
          mensagem('Escolha uma imagem JPG, PNG ou WebP de até 2 MB.', 'error');
          return;
        }
        mostrarPreview(URL.createObjectURL(arquivo));
        acoesFoto.hidden = false;
        el('salvar-foto').hidden = false;
        el('remover-foto').hidden = true;
        el('escolher-outra-foto').hidden = true;
        mensagem('Prévia pronta. Salve para atualizar sua foto.');
      });
      el('salvar-foto').addEventListener('click', async () => {
        const arquivo = el('foto-perfil').files[0];
        if (!arquivo) return mensagem('Escolha uma foto antes de salvar.', 'error');
        if (arquivo.size > 2 * 1024 * 1024) return mensagem('A foto deve ter no máximo 2 MB.', 'error');
        const dadosFoto = new FormData();
        dadosFoto.append('acao', 'foto');
        dadosFoto.append('foto', arquivo);
        try {
          const resposta = await fetch(`${API}/minha_conta.php`, { method: 'POST', credentials: 'include', headers: { 'X-CSRF-Token': sessionStorage.getItem('classmanager_csrf') || '' }, body: dadosFoto });
          const resultado = await resposta.json();
          if (!resposta.ok || !resultado.sucesso) throw new Error(resultado.mensagem || 'Não foi possível salvar a foto.');
          fotoAtual = resultado.foto_url;
          atualizarAvatar(fotoAtual, perfil.nome);
          mostrarPreview(fotoAtual);
          el('foto-perfil').value = '';
          el('salvar-foto').hidden = true;
          el('remover-foto').hidden = false;
          el('escolher-outra-foto').hidden = false;
          acoesFoto.hidden = false;
          mensagem(resultado.mensagem);
        } catch (erro) { mensagem(erro.message, 'error'); }
      });
      el('remover-foto').addEventListener('click', async () => {
        if (!fotoAtual || !window.confirm('Remover sua foto de perfil?')) return;
        try {
          const resultado = await requisicao('minha_conta.php', { method: 'POST', body: JSON.stringify({ acao: 'remover_foto' }) });
          fotoAtual = null;
          atualizarAvatar(null, perfil.nome);
          mostrarPreview(null);
          el('acoes-foto').hidden = true;
          mensagem(resultado.mensagem);
        } catch (erro) { mensagem(erro.message, 'error'); }
      });

      el('configuracoes-perfil-form').addEventListener('submit', async event => {
        event.preventDefault();
        try {
          const atualizados = await requisicao('minha_conta.php', { method: 'POST', body: JSON.stringify({ acao: 'perfil', nome: el('config-nome').value, email: el('config-email').value, telefone: el('config-telefone').value }) });
          perfil.nome = atualizados.perfil.nome;
          perfil.email = atualizados.perfil.email;
          perfil.telefone = atualizados.perfil.telefone;
          const nome = document.getElementById('user-name'); if (nome) nome.textContent = perfil.nome;
          const saudacao = document.getElementById('nome'); if (saudacao) saudacao.textContent = perfil.nome;
          const nomePerfil = el('perfil-nome'); if (nomePerfil) nomePerfil.textContent = perfil.nome;
          atualizarAvatar(fotoAtual, perfil.nome);
          mostrarPreview(fotoAtual);
          mensagem('Dados pessoais salvos.');
        } catch (erro) { mensagem(erro.message, 'error'); }
      });

      el('mostrar-form-senha').addEventListener('click', () => {
        el('configuracoes-senha-form').hidden = false;
        el('senha-atual').focus();
      });
      el('cancelar-form-senha').addEventListener('click', () => {
        el('configuracoes-senha-form').reset();
        el('configuracoes-senha-form').hidden = true;
        mensagem('');
      });
      document.querySelectorAll('[data-ver-senha]').forEach(botao => botao.addEventListener('click', () => {
        const input = el(botao.dataset.verSenha);
        const mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
      }));
      el('configuracoes-senha-form').addEventListener('submit', async event => {
        event.preventDefault();
        if (el('nova-senha').value !== el('confirmacao-senha').value) return mensagem('A nova senha e a confirmação precisam ser iguais.', 'error');
        try {
          const resultado = await requisicao('minha_conta.php', { method: 'POST', body: JSON.stringify({ acao: 'senha', senha_atual: el('senha-atual').value, nova_senha: el('nova-senha').value, confirmacao_senha: el('confirmacao-senha').value }) });
          el('configuracoes-senha-form').reset();
          el('configuracoes-senha-form').hidden = true;
          mensagem(resultado.mensagem);
        } catch (erro) { mensagem(erro.message, 'error'); }
      });

      document.querySelectorAll('[data-preferencia]').forEach(controle => controle.addEventListener('change', () => {
        const estado = lerPreferencias();
        estado[controle.dataset.preferencia] = controle.checked;
        salvarPreferencias(estado);
        mensagem('Preferências salvas neste navegador.');
      }));
      document.querySelectorAll('[name="tamanho-texto"]').forEach(controle => controle.addEventListener('change', () => {
        if (!controle.checked) return;
        const estado = lerPreferencias();
        estado.texto = controle.value;
        salvarPreferencias(estado);
        aplicarAcessibilidade(estado.texto);
        mensagem('Preferência de texto salva neste navegador.');
      }));
    } catch (erro) {
      container.innerHTML = `<p class="settings-message error">Não foi possível carregar suas configurações: ${esc(erro.message)}</p>`;
    }
  };
})();
