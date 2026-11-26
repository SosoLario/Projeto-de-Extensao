const A = '../backend/api';
let turmas = [];
const conteudo = document.getElementById('conteudo');
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (options.method === 'POST') headers['X-CSRF-Token'] = sessionStorage.getItem('classmanager_csrf') || '';
  const resposta = await fetch(`${A}/${path}`, { credentials: 'include', ...options, headers });
  const dados = await resposta.json();
  if (!resposta.ok || !dados.sucesso) throw new Error(dados.mensagem || 'Não foi possível concluir a operação.');
  return dados;
}

async function sair() {
  try { await api('logout.php', { method: 'POST' }); } catch (_) { /* a sessão pode já ter expirado */ }
  window.location.href = 'login.html';
}

document.getElementById('sair').addEventListener('click', sair);

function listaTurmas() {
  return `<select id="turma">${turmas.map(t => `<option value="${t.id}">${esc(t.turma)} — ${esc(t.dia_semana)} ${esc(t.hora)}</option>`).join('')}</select>`;
}

function datasDeAula(diaSemana, turmaAtual) {
  const dias = { Dom: 0, Seg: 1, Ter: 2, Qua: 3, Qui: 4, Sex: 5, Sab: 6 };
  const alvo = dias[diaSemana];
  const hoje = new Date(); hoje.setHours(12,0,0,0);
  const hojeISO = isoLocal(hoje);
  const inicioHorario = turmaAtual?.data_inicio ? new Date(`${String(turmaAtual.data_inicio).slice(0, 10)}T12:00:00`) : new Date(hoje);
  if (Number.isNaN(inicioHorario.getTime())) inicioHorario.setTime(hoje.getTime());
  const limitePassado = new Date(hoje); limitePassado.setDate(limitePassado.getDate() - 120);
  const inicioBusca = inicioHorario < limitePassado ? limitePassado : inicioHorario;
  const fimBusca = new Date(hoje); fimBusca.setDate(fimBusca.getDate() + 90);
  const fimHorario = turmaAtual?.data_fim ? new Date(`${String(turmaAtual.data_fim).slice(0, 10)}T12:00:00`) : null;
  let limite = fimHorario && fimHorario < fimBusca ? fimHorario : fimBusca;
  // Se a turma começa depois da janela normal, ainda mostramos as primeiras aulas futuras.
  if (inicioBusca > limite) { limite = new Date(inicioBusca); limite.setDate(limite.getDate() + 13); }
  const passadas = [];
  const futuras = [];
  const data = new Date(inicioBusca);
  while (data <= limite) {
    const iso = isoLocal(data);
    const dataInicioISO = turmaAtual?.data_inicio ? String(turmaAtual.data_inicio).slice(0, 10) : '';
    const dataFimISO = turmaAtual?.data_fim ? String(turmaAtual.data_fim).slice(0, 10) : '';
    if (data.getDay() === alvo && (!dataInicioISO || iso >= dataInicioISO) && (!dataFimISO || iso <= dataFimISO)) {
      (iso <= hojeISO ? passadas : futuras).push(iso);
    }
    data.setDate(data.getDate() + 1);
  }
  // Mantém todas as ocorrências em ordem cronológica: antigas, recentes e futuras.
  return [...passadas, ...futuras].sort((a, b) => a.localeCompare(b));
}

function formatarData(data) {
  return new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(`${data}T12:00:00Z`));
}

function isoLocal(data) { return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`; }
function semanaAtual(data = new Date()) {
  const segunda = new Date(data.getFullYear(), data.getMonth(), data.getDate());
  segunda.setDate(segunda.getDate() - ((segunda.getDay() + 6) % 7));
  const quinta = new Date(segunda); quinta.setDate(quinta.getDate() + 3);
  const ano = quinta.getFullYear(); const primeira = new Date(ano, 0, 4);
  primeira.setDate(primeira.getDate() - ((primeira.getDay() + 6) % 7));
  return `${ano}-W${String(1 + Math.round((segunda - primeira) / 604800000)).padStart(2, '0')}`;
}
function segundaDaSemana(valor) {
  const [ano, semana] = valor.split('-W').map(Number); const diaQuatro = new Date(ano, 0, 4);
  const segunda = new Date(diaQuatro); segunda.setDate(diaQuatro.getDate() - ((diaQuatro.getDay() + 6) % 7) + (semana - 1) * 7); return segunda;
}

function mostrarHorarios() {
  conteudo.classList.remove('settings-host'); conteudo.classList.add('portal-modern');
  conteudo.innerHTML = `<div class="portal-page-heading"><span class="portal-title-icon orange">◷</span><div><h2>Horários</h2><p>Confira as aulas das suas turmas na semana selecionada.</p></div></div><section class="portal-data-card schedule-card"><div class="schedule-toolbar"><label class="portal-week-filter"><span class="portal-title-icon blue">▦</span><span><small>Semana de</small><strong id="rotulo-semana-professor"></strong><input type="week" id="filtro-semana-professor" aria-label="Escolher semana"></span></label><div class="portal-info-note compact"><span>ⓘ</span>Horários exibidos no horário local.</div></div><div id="lista-horarios-professor" class="schedule-list"></div></section>`;
  const seletor = document.getElementById('filtro-semana-professor'); seletor.value = semanaAtual();
  const lista = document.getElementById('lista-horarios-professor');
  const indices = { Seg: 0, Ter: 1, Qua: 2, Qui: 3, Sex: 4, Sab: 5 };
  const desenhar = () => {
    if (!seletor.value) return;
    const inicio = segundaDaSemana(seletor.value); const fimSemana = new Date(inicio); fimSemana.setDate(inicio.getDate() + 6);
    document.getElementById('rotulo-semana-professor').textContent = `${formatarData(isoLocal(inicio))} – ${formatarData(isoLocal(fimSemana))}`;
    const cards = Array.from({ length: 6 }, (_, dia) => {
      const data = new Date(inicio); data.setDate(inicio.getDate() + dia); const iso = isoLocal(data);
      return turmas.filter(t => indices[t.dia_semana] === dia && (!t.data_inicio || iso >= t.data_inicio) && (!t.data_fim || iso <= t.data_fim)).map(t => `<article class="schedule-day"><div class="schedule-date"><strong>${['Seg','Ter','Qua','Qui','Sex','Sáb'][dia]}</strong><span>${formatarData(iso).slice(0, 5)}</span></div><div class="schedule-lesson"><span class="schedule-lesson-icon">☏</span><div class="schedule-lesson-info"><strong>${esc(t.curso || t.turma)}</strong><small>${esc(t.nivel ? `Nível: ${t.nivel}` : t.turma)}</small><span>◷ ${esc(t.hora)} – ${esc(t.hora_fim || '—')}</span><small>${t.alunos.length} aluno(s) ativo(s)</small></div><button type="button" class="btn btn-ghost schedule-open-class" data-agenda-turma="${t.id}">Abrir turma</button></div></article>`).join('');
    }).join('');
    lista.innerHTML = cards || '<div class="portal-empty-state">Não há aulas das suas turmas nesta semana.</div>';
    lista.querySelectorAll('[data-agenda-turma]').forEach(botao => botao.addEventListener('click', () => abrirChamada(botao.dataset.agendaTurma)));
  };
  seletor.addEventListener('change', desenhar); desenhar();
}

async function mostrarFrequencia() {
  conteudo.classList.remove('settings-host'); conteudo.classList.add('portal-modern');
  conteudo.innerHTML = '<p>Carregando histórico de frequência…</p>';
  try {
    const dados = await api('portal.php?acao=historico_frequencia'); const historico = dados.historico || [];
    const agora = new Date(); const atual = `${agora.getFullYear()}-${String(agora.getMonth() + 1).padStart(2, '0')}`;
    const meses = [...new Set([atual, ...historico.map(item => String(item.data_aula).slice(0, 7))])].sort().reverse();
    const total = historico.length; const faltas = historico.filter(item => item.situacao === 'falta').length;
    const presentes = historico.filter(item => item.situacao === 'presente').length;
    const taxa = total ? Math.round((total - faltas) * 100 / total) : 0;
    conteudo.innerHTML = `<section class="portal-title-card"><span class="portal-title-icon blue">✓</span><div><h3>Frequência das turmas</h3><p>Acompanhe os registros de presença e faltas dos seus alunos.</p></div></section><div class="attendance-stats"><article class="attendance-stat green"><span class="stat-icon">✓</span><div><small>Presença geral</small><strong>${taxa}%</strong></div></article><article class="attendance-stat blue"><span class="stat-icon">▣</span><div><small>Presenças registradas</small><strong>${presentes}</strong></div></article><article class="attendance-stat orange"><span class="stat-icon">!</span><div><small>Faltas registradas</small><strong>${faltas}</strong></div></article></div><section class="portal-data-card"><div class="portal-card-heading"><div><span class="portal-title-icon blue">▣</span><h3>Histórico de frequência</h3></div><label class="portal-month-filter"><span aria-hidden="true">▦</span><select id="filtro-mes-professor" aria-label="Filtrar frequência por mês">${meses.map(mes => {const [ano, numero] = mes.split('-').map(Number);return `<option value="${mes}">${new Intl.DateTimeFormat('pt-BR',{month:'long',year:'numeric'}).format(new Date(ano,numero-1,1))}</option>`}).join('')}</select></label></div><div class="portal-table-wrap"><table class="portal-attendance-table"><thead><tr><th>Data</th><th>Aluno</th><th>Turma / curso</th><th>Horário</th><th>Status</th></tr></thead><tbody id="linhas-frequencia-professor"></tbody></table></div><div class="portal-info-note"><span>ⓘ</span>Os dados são atualizados quando a chamada da turma é salva.</div></section>`;
    const seletor = document.getElementById('filtro-mes-professor'); if (historico.length) seletor.value = String(historico[0].data_aula).slice(0, 7);
    const corpo = document.getElementById('linhas-frequencia-professor');
    const desenhar = () => {
      const linhas = historico.filter(item => String(item.data_aula).slice(0, 7) === seletor.value);
      corpo.innerHTML = linhas.length ? linhas.map(item => {const situacao = item.situacao === 'falta' ? 'Falta' : item.situacao === 'atrasado' ? 'Atrasado' : 'Presente';const classe = item.situacao === 'falta' ? 'absent' : item.situacao === 'atrasado' ? 'late' : 'present';return `<tr><td>${formatarData(item.data_aula)}</td><td>${esc(item.aluno)}</td><td>${esc(item.curso || item.turma)}</td><td>${esc(item.hora_inicio)} – ${esc(item.hora_fim)}</td><td><span class="attendance-badge ${classe}"><i></i>${situacao}</span></td></tr>`}).join('') : '<tr><td colspan="5" class="portal-empty-cell">Nenhuma chamada registrada neste mês.</td></tr>';
    };
    seletor.addEventListener('change', desenhar); desenhar();
  } catch (erro) { conteudo.innerHTML = `<p class="settings-message error">Não foi possível carregar a frequência: ${esc(erro.message)}</p>`; }
}

function abrirChamada(id) {
  const turma = turmas.find(t => String(t.id) === String(id));
  if (!turma) return;
  document.querySelectorAll('[data-mode]').forEach(item => item.classList.toggle('active', item.dataset.mode === 'turmas'));
  const datas = datasDeAula(turma.dia_semana, turma);
  const hojeISO = isoLocal(new Date());
  const dataInicial = datas.filter(data => data <= hojeISO).sort((a, b) => b.localeCompare(a))[0]
    || datas.filter(data => data > hojeISO).sort((a, b) => a.localeCompare(b))[0]
    || '';
  const temAulaPassada = Boolean(dataInicial && dataInicial <= hojeISO);
  conteudo.classList.remove('settings-host', 'portal-modern');
  conteudo.innerHTML = `<button class="btn btn-ghost" id="voltar-turmas">← Voltar para turmas</button><div class="class-title"><p class="eyebrow-word">Chamada e conteúdo</p><h2>${esc(turma.turma)}</h2><p class="sub">${esc(turma.dia_semana)}, ${esc(turma.hora)}–${esc(turma.hora_fim || '—')} • Registre o conteúdo ministrado e a frequência.</p></div>${datas.length ? '' : '<p class="portal-info-note"><span>ⓘ</span>Não há ocorrência disponível no período desta turma.</p>'}<form class="chamada" data-id="${turma.id}"><div class="field"><label for="data-chamada">Data da aula</label><select id="data-chamada">${datas.length ? datas.map(data => `<option value="${data}" ${data === dataInicial ? 'selected' : ''}>${formatarData(data)}${data > hojeISO ? ' · próxima aula' : ''}</option>`).join('') : '<option value="">Nenhuma data disponível</option>'}</select>${datas.some(data => data > hojeISO) ? '<small class="call-date-note">As próximas aulas aparecem aqui, mas a chamada só pode ser salva no dia da aula ou depois.</small>' : ''}</div><div class="field"><label for="conteudo-aula">Conteúdo ministrado</label><textarea id="conteudo-aula" maxlength="1000" rows="3" placeholder="Tópicos abordados nesta aula"></textarea></div><div class="attendance-list">${turma.alunos.length ? turma.alunos.map(a => `<div class="attendance-row"><span>${esc(a.nome)}</span><select data-aluno="${a.id}"><option value="presente">Presente</option><option value="falta">Falta</option><option value="atrasado">Atrasado</option></select></div>`).join('') : '<p>Sem alunos nesta turma.</p>'}</div><button type="submit" class="btn btn-primary" ${temAulaPassada ? '' : 'disabled'}>Salvar aula e chamada</button></form><section id="historico-aulas" style="margin-top:24px"></section>`;
  document.getElementById('voltar-turmas').addEventListener('click', mostrarTurmas);
  document.getElementById('data-chamada').addEventListener('change', event => {
    document.querySelector('.chamada button[type="submit"]').disabled = !event.target.value || event.target.value > hojeISO;
    carregarFrequencia(turma.id);
  });
  carregarFrequencia(turma.id);
  carregarHistorico(turma.id);
  document.querySelector('.chamada').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const alunos = [...form.querySelectorAll('[data-aluno]')].map(input => ({ id: input.dataset.aluno, situacao: input.value }));
    try { await api('portal.php?acao=frequencia', { method: 'POST', body: JSON.stringify({ horario_id: turma.id, data: document.getElementById('data-chamada').value, conteudo: document.getElementById('conteudo-aula').value, alunos }) }); alert('Aula e chamada salvas.'); carregarHistorico(turma.id); }
    catch (erro) { alert(erro.message); }
  });
}

async function carregarHistorico(horarioId){const container=document.getElementById('historico-aulas');if(!container)return;try{const d=await api(`portal.php?acao=aulas&horario_id=${horarioId}`);container.innerHTML=`<h3>Últimas aulas registradas</h3>${d.aulas.map(a=>`<article class="card" style="padding:14px;margin:8px 0"><strong>${formatarData(a.data_aula)}</strong><p>${a.conteudo||'Sem conteúdo informado.'}</p><small>${a.presentes} presente(s) · ${a.faltas} falta(s) · ${a.atrasos} atraso(s)</small></article>`).join('')||'<p>Ainda não há aulas registradas.</p>'}`}catch(e){container.textContent=e.message}}
async function carregarFrequencia(horarioId){const data=document.getElementById('data-chamada')?.value;if(!data)return;try{const d=await api(`portal.php?acao=frequencia&horario_id=${horarioId}&data=${data}`);document.getElementById('conteudo-aula').value=d.aula?.conteudo||'';const status=new Map((d.frequencias||[]).map(x=>[String(x.aluno_id),x.situacao]));document.querySelectorAll('[data-aluno]').forEach(select=>{select.value=status.get(select.dataset.aluno)||'presente'})}catch(e){alert(e.message)}}

function mostrarTurmas() {
  conteudo.classList.remove('settings-host', 'portal-modern');
  conteudo.innerHTML = `<div class="panel-heading"><div><p class="eyebrow-word">Minhas turmas</p><h2>Escolha uma turma</h2><p class="sub">Clique no nome da turma para abrir a chamada.</p></div></div><div class="turmas-grid">${turmas.map(t => `<button class="turma-card" data-turma-id="${t.id}"><span class="turma-icon">◷</span><strong>${esc(t.turma)}</strong><span>${esc(t.dia_semana)}, ${esc(t.hora)}</span><small>${t.alunos.length} aluno(s)</small></button>`).join('') || '<p>Nenhuma turma vinculada.</p>'}</div>`;
  document.querySelectorAll('[data-turma-id]').forEach(botao => botao.addEventListener('click', () => abrirChamada(botao.dataset.turmaId)));
}

async function mostrarNotas() {
  conteudo.classList.remove('settings-host'); conteudo.classList.add('portal-modern');
  conteudo.innerHTML = '<p>Carregando notas lançadas…</p>';
  try {
    const resposta = await api('portal.php?acao=notas_professor');
    const notas = resposta.notas || [];
    const media = notas.length ? notas.reduce((soma, nota) => soma + Number(nota.nota || 0), 0) / notas.length : null;
    const alunosAvaliados = new Set(notas.map(nota => String(nota.aluno_id))).size;
    const turmasComNotas = [...new Map(notas.map(nota => [String(nota.horario_id), nota])).values()];
    const formHtml = turmas.length ? `
      <section class="portal-data-card teacher-grade-entry">
        <div class="portal-card-heading"><div><span class="portal-title-icon violet">＋</span><h3>Lançar nota</h3></div><span class="teacher-grade-scale">Escala de 0 a 10</span></div>
        <form id="nota" class="teacher-grade-form">
          <label>Turma ${listaTurmas()}</label>
          <label>Aluno<select id="aluno" required></select></label>
          <label>Avaliação<input id="avaliacao" maxlength="120" placeholder="Ex.: Prova de unidade" required></label>
          <label>Nota<input id="valor" type="number" min="0" max="10" step="0.1" placeholder="0 a 10" required></label>
          <button class="btn btn-primary" type="submit">Salvar nota</button>
        </form>
      </section>` : '<div class="portal-empty-state">Nenhuma turma ativa está vinculada a você.</div>';
    conteudo.innerHTML = `
      <div class="portal-page-heading"><span class="portal-title-icon violet">★</span><div><h2>Notas</h2><p>Lance avaliações e acompanhe os resultados dos seus alunos.</p></div></div>
      <div class="attendance-stats grade-stats">
        <article class="attendance-stat blue"><span class="stat-icon">★</span><div><small>Média das notas lançadas</small><strong>${media === null ? '—' : `${media.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} / 10`}</strong></div></article>
        <article class="attendance-stat green"><span class="stat-icon">▤</span><div><small>Avaliações lançadas</small><strong>${notas.length}</strong></div></article>
        <article class="attendance-stat orange"><span class="stat-icon">♙</span><div><small>Alunos avaliados</small><strong>${alunosAvaliados}</strong></div></article>
      </div>
      ${formHtml}
      <section class="portal-data-card">
        <div class="portal-card-heading"><div><span class="portal-title-icon violet">★</span><h3>Histórico de notas lançadas</h3></div><label class="portal-month-filter grade-filter"><span aria-hidden="true">⌕</span><select id="filtro-turma-notas" aria-label="Filtrar por turma"><option value="todas">Todas as turmas</option>${turmasComNotas.map(turma => `<option value="${turma.horario_id}">${esc(turma.turma)}</option>`).join('')}</select></label></div>
        <div class="portal-table-wrap"><table class="portal-attendance-table grade-table teacher-grade-table"><thead><tr><th>Aluno</th><th>Avaliação</th><th>Curso / turma</th><th>Data</th><th>Nota</th></tr></thead><tbody id="linhas-notas-professor"></tbody></table></div>
        <div class="portal-info-note"><span>ⓘ</span>As notas são apresentadas na escala de 0 a 10 e ficam disponíveis no painel do aluno.</div>
      </section>`;

    if (turmas.length) {
      const selectTurma = document.getElementById('turma');
      const selectAluno = document.getElementById('aluno');
      const atualizarAlunos = () => {
        const turma = turmas.find(item => String(item.id) === selectTurma.value);
        selectAluno.innerHTML = (turma?.alunos || []).map(aluno => `<option value="${aluno.id}">${esc(aluno.nome)}</option>`).join('');
        selectAluno.disabled = !turma?.alunos?.length;
        document.querySelector('#nota button[type="submit"]').disabled = !turma?.alunos?.length;
      };
      selectTurma.addEventListener('change', atualizarAlunos); atualizarAlunos();
      document.getElementById('nota').addEventListener('submit', async event => {
        event.preventDefault();
        try {
          await api('portal.php?acao=nota', { method: 'POST', body: JSON.stringify({ horario_id: selectTurma.value, aluno_id: selectAluno.value, avaliacao: document.getElementById('avaliacao').value, nota: document.getElementById('valor').value }) });
          await mostrarNotas();
        } catch (erro) { alert(erro.message); }
      });
    }

    const filtro = document.getElementById('filtro-turma-notas');
    const corpo = document.getElementById('linhas-notas-professor');
    const atualizarTabela = () => {
      const filtradas = notas.filter(nota => filtro.value === 'todas' || String(nota.horario_id) === filtro.value);
      corpo.innerHTML = filtradas.length ? filtradas.map(nota => {
        const valor = Number(nota.nota) || 0;
        const notaFormatada = valor.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
        const turma = nota.curso && nota.curso !== nota.turma ? `${nota.curso} · ${nota.turma}` : (nota.curso || nota.turma);
        return `<tr><td>${esc(nota.aluno)}</td><td>${esc(nota.avaliacao)}</td><td>${esc(turma)}</td><td>${esc(formatarData(nota.data_lancamento))}</td><td><div class="grade-score"><strong>${esc(notaFormatada)} / 10</strong><span class="grade-meter"><i style="width:${Math.max(0, Math.min(valor, 10)) * 10}%"></i></span></div></td></tr>`;
      }).join('') : '<tr><td colspan="5" class="portal-empty-cell">Nenhuma nota lançada para esta turma ainda.</td></tr>';
    };
    filtro.addEventListener('change', atualizarTabela); atualizarTabela();
  } catch (erro) {
    conteudo.innerHTML = `<p class="settings-message error">Não foi possível carregar as notas: ${esc(erro.message)}</p>`;
  }
}

document.querySelectorAll('[data-mode]').forEach(botao => botao.addEventListener('click', () => {
  document.querySelectorAll('[data-mode]').forEach(item => item.classList.toggle('active', item === botao));
  if (botao.dataset.mode === 'horarios') mostrarHorarios();
  else if (botao.dataset.mode === 'frequencia') mostrarFrequencia();
  else if (botao.dataset.mode === 'turmas') mostrarTurmas();
  else if (botao.dataset.mode === 'notas') mostrarNotas();
  else { conteudo.classList.add('settings-host'); window.renderConfiguracoes(); }
}));

(async () => {
  try {
    const sessao=await api('check_session.php');
    if(sessao.csrf_token)sessionStorage.setItem('classmanager_csrf',sessao.csrf_token);
    if(window.aplicarPreferenciaSalva)window.aplicarPreferenciaSalva(sessao.usuario.tipo,sessao.usuario.id);
    const dados = await api('portal.php?acao=turmas');
    turmas = dados.turmas || [];
    document.getElementById('nome').textContent = dados.usuario?.nome || 'Professor(a)';
    document.getElementById('user-name').textContent = dados.usuario?.nome || 'Professor(a)';
    if (window.carregarAvatarConta) await window.carregarAvatarConta(dados.usuario?.nome || 'Professor(a)');
    mostrarHorarios();
  } catch (erro) {
    conteudo.innerHTML = `<p style="color:#b42318">Não foi possível carregar seus dados: ${erro.message}</p>`;
    if (/sessão/i.test(erro.message)) window.location.href = 'login.html';
  }
})();
