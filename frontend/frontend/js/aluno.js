const A = '../backend/api';
let info;
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function get() {
  const s = await fetch(`${A}/check_session.php`, { credentials: 'include' });
  if (!s.ok) { location = 'login.html'; throw Error('Sessão expirada'); }
  const sd = await s.json();
  sessionStorage.setItem('classmanager_csrf', sd.csrf_token);
  if (window.aplicarPreferenciaSalva) window.aplicarPreferenciaSalva(sd.usuario.tipo, sd.usuario.id);
  const r = await fetch(`${A}/portal.php?acao=resumo`, { credentials: 'include' });
  if (!r.ok) { location = 'login.html'; throw Error('Não foi possível carregar os dados'); }
  return r.json();
}

function formatarData(data) {
  if (!data) return '—';
  const [ano, mes, dia] = String(data).slice(0, 10).split('-').map(Number);
  return new Intl.DateTimeFormat('pt-BR').format(new Date(ano, mes - 1, dia, 12));
}

function tituloMes(chave) {
  const [ano, mes] = chave.split('-').map(Number);
  return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(new Date(ano, mes - 1, 1));
}

function renderFrequencia() {
  const c = document.getElementById('conteudo');
  const f = info.frequencia || { total: 0, faltas: 0, atrasos: 0 };
  const historico = info.historico_frequencia || [];
  const agora = new Date();
  const mesAtual = `${agora.getFullYear()}-${String(agora.getMonth() + 1).padStart(2, '0')}`;
  const meses = [...new Set([mesAtual, ...historico.map(item => String(item.data_aula).slice(0, 7))])].sort().reverse();
  const total = Number(f.total) || 0;
  const faltas = Number(f.faltas) || 0;
  const frequentadas = Math.max(0, total - faltas);
  const percentual = total ? Math.round(frequentadas * 100 / total) : 0;
  c.classList.add('portal-modern');
  c.innerHTML = `
    <section class="portal-title-card"><span class="portal-title-icon blue">▣</span><div><h3>Frequência</h3><p>Acompanhe sua presença nas aulas e veja seu histórico.</p></div></section>
    <div class="attendance-stats">
      <article class="attendance-stat green"><span class="stat-icon">✓</span><div><small>Presença</small><strong>${percentual}%</strong></div></article>
      <article class="attendance-stat blue"><span class="stat-icon">▣</span><div><small>Aulas frequentadas</small><strong>${frequentadas}</strong></div></article>
      <article class="attendance-stat orange"><span class="stat-icon">!</span><div><small>Faltas</small><strong>${faltas}</strong></div></article>
    </div>
    <section class="portal-data-card">
      <div class="portal-card-heading"><div><span class="portal-title-icon blue">▣</span><h3>Histórico de frequência</h3></div><label class="portal-month-filter"><span aria-hidden="true">▦</span><select id="filtro-mes-frequencia" aria-label="Filtrar frequência por mês">${meses.map(mes => `<option value="${esc(mes)}">${esc(tituloMes(mes))}</option>`).join('')}</select></label></div>
      <div class="portal-table-wrap"><table class="portal-attendance-table"><thead><tr><th>Data</th><th>Aula</th><th>Horário</th><th>Status</th></tr></thead><tbody id="linhas-frequencia"></tbody></table></div>
      <div class="portal-info-note"><span>ⓘ</span>A frequência é atualizada pela escola após cada aula.</div>
    </section>`;
  const tabela = document.getElementById('linhas-frequencia');
  const filtro = document.getElementById('filtro-mes-frequencia');
  if (historico.length) filtro.value = String(historico[0].data_aula).slice(0, 7);
  const atualizar = () => {
    const registros = historico.filter(item => String(item.data_aula).slice(0, 7) === filtro.value);
    tabela.innerHTML = registros.length ? registros.map(item => {
      const situacao = item.situacao === 'falta' ? 'Falta' : item.situacao === 'atrasado' ? 'Atrasado' : 'Presente';
      const classe = item.situacao === 'falta' ? 'absent' : item.situacao === 'atrasado' ? 'late' : 'present';
      return `<tr><td>${esc(formatarData(item.data_aula))}</td><td>${esc(item.curso || item.turma)}</td><td>${esc(item.hora_inicio)} – ${esc(item.hora_fim)}</td><td><span class="attendance-badge ${classe}"><i></i>${situacao}</span></td></tr>`;
    }).join('') : `<tr><td colspan="4" class="portal-empty-cell">Nenhum registro de frequência neste mês.</td></tr>`;
  };
  filtro.addEventListener('change', atualizar);
  atualizar();
}

function renderNotas() {
  const c = document.getElementById('conteudo');
  const notas = info.notas || [];
  const media = notas.length ? notas.reduce((soma, nota) => soma + Number(nota.nota || 0), 0) / notas.length : null;
  const cursos = [...new Set(notas.map(nota => nota.curso || nota.turma))];
  c.classList.add('portal-modern');
  c.innerHTML = `
    <div class="portal-page-heading"><span class="portal-title-icon violet">★</span><div><h2>Notas</h2><p>Acompanhe suas avaliações e seu desempenho nas turmas.</p></div></div>
    <div class="attendance-stats grade-stats">
      <article class="attendance-stat blue"><span class="stat-icon">★</span><div><small>Média geral</small><strong>${media === null ? '—' : `${media.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} / 10`}</strong></div></article>
      <article class="attendance-stat green"><span class="stat-icon">▤</span><div><small>Avaliações lançadas</small><strong>${notas.length}</strong></div></article>
      <article class="attendance-stat orange"><span class="stat-icon">▣</span><div><small>Turmas com avaliação</small><strong>${cursos.length}</strong></div></article>
    </div>
    <section class="portal-data-card">
      <div class="portal-card-heading"><div><span class="portal-title-icon violet">★</span><h3>Histórico de notas</h3></div><label class="portal-month-filter grade-filter"><span aria-hidden="true">⌕</span><select id="filtro-curso-notas" aria-label="Filtrar notas por turma"><option value="todas">Todas as turmas</option>${cursos.map(curso => `<option value="${esc(curso)}">${esc(curso)}</option>`).join('')}</select></label></div>
      <div class="portal-table-wrap"><table class="portal-attendance-table grade-table"><thead><tr><th>Avaliação</th><th>Curso / turma</th><th>Data</th><th>Nota</th></tr></thead><tbody id="linhas-notas"></tbody></table></div>
      <div class="portal-info-note"><span>ⓘ</span>As notas são lançadas pelo professor responsável pela turma e apresentadas em uma escala de 0 a 10.</div>
    </section>`;
  const filtro = document.getElementById('filtro-curso-notas');
  const corpo = document.getElementById('linhas-notas');
  const atualizar = () => {
    const filtradas = notas.filter(nota => filtro.value === 'todas' || (nota.curso || nota.turma) === filtro.value);
    corpo.innerHTML = filtradas.length ? filtradas.map(nota => {
      const valor = Number(nota.nota) || 0;
      const valorFormatado = valor.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
      const turma = nota.curso && nota.curso !== nota.turma ? `${nota.curso} · ${nota.turma}` : (nota.curso || nota.turma);
      return `<tr><td>${esc(nota.avaliacao)}</td><td>${esc(turma)}</td><td>${esc(formatarData(nota.data_lancamento))}</td><td><div class="grade-score"><strong>${esc(valorFormatado)} / 10</strong><span class="grade-meter"><i style="width:${Math.max(0, Math.min(valor, 10)) * 10}%"></i></span></div></td></tr>`;
    }).join('') : `<tr><td colspan="4" class="portal-empty-cell">${notas.length ? 'Não há notas para esta turma.' : 'Nenhuma avaliação foi lançada ainda.'}</td></tr>`;
  };
  filtro.addEventListener('change', atualizar);
  atualizar();
}

function isoLocal(data) {
  return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`;
}

function renderHorarios() {
  const c = document.getElementById('conteudo');
  const turmas = info.turmas || [];
  const diaIndice = { Seg: 0, Ter: 1, Qua: 2, Qui: 3, Sex: 4, Sab: 5 };
  const nomesDias = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
  const hoje = new Date();
  let mesVisivel = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  let dataSelecionada = isoLocal(hoje);
  c.classList.add('portal-modern');
  c.innerHTML = `
    <div class="portal-page-heading"><span class="portal-title-icon orange">◷</span><div><h2>Horários</h2><p>Selecione uma data para consultar suas aulas.</p></div></div>
    <section class="student-calendar-layout">
      <div class="portal-data-card student-calendar-card"><div class="calendar-section-heading"><span>▦</span><h3>Datas</h3></div><div class="calendar-month-nav"><button type="button" id="mes-anterior" aria-label="Mês anterior">‹</button><strong id="mes-calendario"></strong><button type="button" id="mes-proximo" aria-label="Próximo mês">›</button></div><div class="calendar-weekdays">${nomesDias.map(dia => `<span>${dia[0]}</span>`).join('')}</div><div class="calendar-days" id="dias-calendario"></div></div>
      <div class="portal-data-card student-lessons-card"><div class="calendar-section-heading"><span>▣</span><h3>Aulas</h3></div><p class="calendar-selected-date" id="data-aulas-selecionada"></p><div id="aulas-da-data" class="student-calendar-events"></div></div>
    </section>
    <div class="portal-info-note"><span>ⓘ</span>Os horários e as datas seguem as turmas em que você está matriculado(a).</div>`;

  const isoDoDia = data => isoLocal(data);
  const aulasNaData = iso => {
    const [ano, mes, dia] = iso.split('-').map(Number);
    const weekday = (new Date(ano, mes - 1, dia, 12).getDay() + 6) % 7;
    return turmas.filter(turma => diaIndice[turma.dia_semana] === weekday && (!turma.data_inicio || iso >= turma.data_inicio) && (!turma.data_fim || iso <= turma.data_fim));
  };
  const renderizarAulas = () => {
    const [ano, mes, dia] = dataSelecionada.split('-').map(Number);
    const data = new Date(ano, mes - 1, dia, 12);
    document.getElementById('data-aulas-selecionada').textContent = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full' }).format(data);
    const aulas = aulasNaData(dataSelecionada);
    document.getElementById('aulas-da-data').innerHTML = aulas.length ? aulas.map(turma => `<article class="student-calendar-event"><span class="schedule-lesson-icon">☏</span><div class="schedule-lesson-info"><strong>${esc(turma.curso || turma.turma)}</strong><small>${esc(turma.nivel ? `Nível: ${turma.nivel}` : turma.turma)}</small><span>◷ ${esc(turma.hora)} – ${esc(turma.hora_fim || '—')}</span>${turma.professor ? `<small>Professor(a): ${esc(turma.professor)}</small>` : ''}</div></article>`).join('') : '<div class="portal-empty-state">Não há aulas matriculadas nesta data.</div>';
  };
  const renderizarMes = () => {
    const ano = mesVisivel.getFullYear(); const mes = mesVisivel.getMonth();
    document.getElementById('mes-calendario').textContent = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(mesVisivel);
    const primeiroDia = new Date(ano, mes, 1); const deslocamento = (primeiroDia.getDay() + 6) % 7; const totalDias = new Date(ano, mes + 1, 0).getDate();
    const celulas = Array.from({ length: 42 }, (_, indice) => {
      const numero = indice - deslocamento + 1;
      if (numero < 1 || numero > totalDias) return '<span class="calendar-day-empty" aria-hidden="true"></span>';
      const data = new Date(ano, mes, numero, 12); const iso = isoDoDia(data); const temAula = aulasNaData(iso).length > 0;
      const classes = ['calendar-day-button', temAula ? 'has-lessons' : '', iso === dataSelecionada ? 'selected' : '', iso === isoDoDia(hoje) ? 'today' : ''].filter(Boolean).join(' ');
      return `<button type="button" class="${classes}" data-calendario-data="${iso}" aria-label="${numero} de ${mes + 1} de ${ano}${temAula ? ', há aula' : ''}" ${iso === dataSelecionada ? 'aria-pressed="true"' : 'aria-pressed="false"'}><span>${numero}</span>${temAula ? '<i></i>' : ''}</button>`;
    }).join('');
    document.getElementById('dias-calendario').innerHTML = celulas;
    document.querySelectorAll('[data-calendario-data]').forEach(botao => botao.addEventListener('click', () => {
      dataSelecionada = botao.dataset.calendarioData;
      document.querySelectorAll('[data-calendario-data]').forEach(item => { item.classList.toggle('selected', item.dataset.calendarioData === dataSelecionada); item.setAttribute('aria-pressed', String(item.dataset.calendarioData === dataSelecionada)); });
      renderizarAulas();
    }));
  };
  const mudarMes = diferenca => {
    mesVisivel = new Date(mesVisivel.getFullYear(), mesVisivel.getMonth() + diferenca, 1);
    const ano = mesVisivel.getFullYear(); const mes = mesVisivel.getMonth(); const totalDias = new Date(ano, mes + 1, 0).getDate();
    const datasComAula = Array.from({ length: totalDias }, (_, indice) => isoLocal(new Date(ano, mes, indice + 1, 12))).filter(iso => aulasNaData(iso).length);
    const hojeIso = isoLocal(hoje);
    dataSelecionada = datasComAula.find(iso => iso >= hojeIso) || datasComAula[0] || isoLocal(new Date(ano, mes, 1, 12));
    renderizarMes(); renderizarAulas();
  };
  document.getElementById('mes-anterior').addEventListener('click', () => mudarMes(-1));
  document.getElementById('mes-proximo').addEventListener('click', () => mudarMes(1));
  if (!aulasNaData(dataSelecionada).length) {
    const primeiroDiaComAula = Array.from({ length: new Date(mesVisivel.getFullYear(), mesVisivel.getMonth() + 1, 0).getDate() }, (_, indice) => isoLocal(new Date(mesVisivel.getFullYear(), mesVisivel.getMonth(), indice + 1, 12))).find(iso => aulasNaData(iso).length);
    if (primeiroDiaComAula) {
      const hojeIso = isoLocal(hoje);
      dataSelecionada = Array.from({ length: new Date(mesVisivel.getFullYear(), mesVisivel.getMonth() + 1, 0).getDate() }, (_, indice) => isoLocal(new Date(mesVisivel.getFullYear(), mesVisivel.getMonth(), indice + 1, 12))).find(iso => iso >= hojeIso && aulasNaData(iso).length) || primeiroDiaComAula;
    }
  }
  renderizarMes(); renderizarAulas();
}

function show(p) {
  const c = document.getElementById('conteudo');
  if (p === 'configuracoes') { c.classList.remove('portal-modern'); window.renderConfiguracoes(); return; }
  c.classList.remove('settings-host');
  if (p === 'frequencia') { renderFrequencia(); return; }
  if (p === 'horario') { renderHorarios(); return; }
  c.classList.remove('portal-modern');
  if (p === 'notas') { renderNotas(); return; }
  if (p === 'financeiro') c.innerHTML = `<h2>Mensalidades</h2><div class="list">${info.mensalidades.length ? info.mensalidades.map(m => `<article><strong>${esc(m.referencia)}</strong> · R$ ${Number(m.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}<br>Vencimento: ${esc(m.vencimento)} · ${esc(m.situacao)}</article>`).join('') : '<p>Nenhuma mensalidade registrada.</p>'}</div>`;
}

(async () => {
  try {
    info = await get();
    document.getElementById('nome').textContent = info.usuario.nome;
    if (window.carregarAvatarConta) await window.carregarAvatarConta(info.usuario.nome);
    document.getElementById('user-name').textContent = info.usuario.nome;
    document.querySelectorAll('[data-panel]').forEach(button => button.onclick = () => {
      document.querySelectorAll('[data-panel]').forEach(item => item.classList.toggle('active', item === button));
      show(button.dataset.panel);
    });
    show('frequencia');
    document.getElementById('sair').onclick = async () => {
      await fetch(`${A}/logout.php`, { method: 'POST', credentials: 'include', headers: { 'X-CSRF-Token': sessionStorage.getItem('classmanager_csrf') || '' } });
      sessionStorage.removeItem('classmanager_csrf');
      location = 'login.html';
    };
  } catch (e) { console.error(e); }
})();
