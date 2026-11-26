const API_BASE = '../backend/api';

/* ---------------- Estado ---------------- */
let alunosCache = [];
let professoresCache = [];
let horariosCache = [];

/* ---------------- Utilidades ---------------- */
function toast(message, type = 'success') {
  const stack = document.getElementById('toast-stack');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

function openModal(id) {
  const overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.hidden = false;
  // Keep the dialog visible even if another stylesheet overrides the overlay display.
  overlay.style.display = 'flex';
}
function closeModal(id) {
  const overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.hidden = true;
  overlay.style.display = 'none';
}

document.querySelectorAll('.modal-close').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.modal));
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal(overlay.id);
  });
});

async function api(path, options = {}) {
  const method = options.method || 'GET';
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) headers['X-CSRF-Token'] = sessionStorage.getItem('classmanager_csrf') || '';
  const res = await fetch(`${API_BASE}/${path}`, { ...options, method, credentials: 'include', headers });

  if (res.status === 401) {
    window.location.href = 'login.html';
    throw new Error('Sessão expirada');
  }

  const data = await res.json();
  if (!res.ok || data.sucesso === false) {
    throw new Error(data.mensagem || 'Ocorreu um erro. Tente novamente.');
  }
  return data;
}

function initials(name) {
  return (name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase()).join('');
}

/* ---------------- Navegação lateral ---------------- */
const sectionMeta = {
  dashboard: { title: 'Visão geral', sub: 'Resumo da escola em tempo real.' },
  alunos: { title: 'Alunos', sub: 'Cadastre, edite e acompanhe os alunos matriculados.' },
  professores: { title: 'Professores', sub: 'Gerencie o corpo docente da escola.' },
  horarios: { title: 'Horários', sub: 'Organize as turmas e os horários de aula.' }
};

document.querySelectorAll('.nav-link').forEach(link => {
  if (link.dataset.section) link.addEventListener('click', () => switchSection(link.dataset.section));
});

function switchSection(section) {
  document.querySelectorAll('.nav-link').forEach(l => l.classList.toggle('active', l.dataset.section === section));
  document.querySelectorAll('.section-panel').forEach(p => p.hidden = p.id !== `section-${section}`);
  document.getElementById('page-title').textContent = sectionMeta[section].title;
  document.getElementById('page-subtitle').textContent = sectionMeta[section].sub;
  document.getElementById('sidebar').classList.remove('open');

  if (section === 'alunos' && alunosCache.length === 0) loadAlunos();
  if (section === 'professores' && professoresCache.length === 0) loadProfessores();
  if (section === 'horarios' && horariosCache.length === 0) loadHorarios();
}

document.getElementById('sidebar-toggle').addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('open');
});

/* ---------------- Sessão / usuário logado ---------------- */
async function loadSession() {
  try {
    const data = await api('check_session.php');
    if (data.csrf_token) sessionStorage.setItem('classmanager_csrf', data.csrf_token);
    document.getElementById('user-name').textContent = data.usuario.nome;
    document.getElementById('user-avatar').textContent = initials(data.usuario.nome);
    if (data.usuario.perfil === 'secretaria') {
      document.querySelector('[data-section="professores"]')?.closest('li')?.setAttribute('hidden', '');
    }
  } catch (err) {
    window.location.href = 'login.html';
  }
}

document.getElementById('logout-btn').addEventListener('click', async () => {
  try { await api('logout.php', { method: 'POST' }); } catch (e) { /* segue mesmo se falhar */ }
  window.location.href = 'login.html';
});

/* ---------------- Dashboard: cartões e resumo ---------------- */
async function loadDashboardStats() {
  try {
    const data = await api('dashboard_stats.php');
    document.getElementById('stat-alunos').textContent = data.total_alunos;
    document.getElementById('stat-professores').textContent = data.total_professores;
    document.getElementById('stat-turmas').textContent = data.total_turmas;
    document.getElementById('stat-novos').textContent = data.novas_matriculas_30d;
    const moeda = v => Number(v || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
    document.getElementById('stat-recebido').textContent = moeda(data.financeiro.recebido_mes);
    document.getElementById('stat-pendente').textContent = moeda(data.financeiro.pendente);
    document.getElementById('stat-atrasado').textContent = moeda(data.financeiro.atrasado);
    const canvas = document.getElementById('grafico-receitas');
    if (window.Chart && canvas) new Chart(canvas, { type:'line', data:{ labels:data.receitas_mensais.map(x=>x.rotulo), datasets:[{label:'Receita',data:data.receitas_mensais.map(x=>Number(x.total)),borderColor:'#3158dc',backgroundColor:'#3158dc22',fill:true,tension:.3}]}, options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>moeda(v)}}}} });
    const aulas=document.getElementById('proximas-aulas');
    aulas.innerHTML=data.proximas_aulas.length?data.proximas_aulas.map(a=>`<tr><td>${escapeHtml(a.turma)}</td><td>${escapeHtml(a.professor)}</td><td>${escapeHtml(a.data)}</td><td>${escapeHtml(a.hora)}</td></tr>`).join(''):'<tr><td colspan="4">Nenhuma aula futura cadastrada.</td></tr>';

    const body = document.getElementById('dashboard-recent-body');
    if (!data.ultimos_alunos.length) {
      body.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--color-text-muted);">Nenhum aluno cadastrado ainda.</td></tr>`;
      return;
    }
    body.innerHTML = data.ultimos_alunos.map(a => `
      <tr>
        <td>${a.nome}</td>
        <td>${a.nivel}</td>
        <td>${a.data_matricula}</td>
        <td>${statusBadge(a.status)}</td>
      </tr>
    `).join('');
  } catch (err) {
    toast(err.message, 'error');
  }
}

function statusBadge(status) {
  const map = {
    ativo: '<span class="badge badge-teal">Ativo</span>',
    inativo: '<span class="badge badge-red">Inativo</span>',
    trancado: '<span class="badge badge-amber">Trancado</span>'
  };
  return map[status] || status;
}

function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* ---------------- Alunos ---------------- */
async function loadAlunos() {
  try {
    const data = await api('alunos.php');
    alunosCache = data.alunos;
    renderAlunos(alunosCache);
  } catch (err) {
    toast(err.message, 'error');
  }
}

function renderAlunos(list) {
  const body = document.getElementById('alunos-table-body');
  if (!list.length) {
    body.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--color-text-muted); padding:30px;">Nenhum aluno encontrado.</td></tr>`;
    return;
  }
  body.innerHTML = list.map(a => `
    <tr>
      <td>${a.nome}</td>
      <td>${a.email}</td>
      <td>${a.nivel}</td>
      <td>${a.turma || '—'}</td>
      <td>${statusBadge(a.status)}</td>
      <td>
        <div class="row-actions">
          <button class="btn-icon" onclick="editAluno(${a.id})" aria-label="Editar">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.7 4.8l3.5 3.5L8 19.5H4.5V16L15.7 4.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          </button>
          <button class="btn-icon" onclick="askDelete('aluno', ${a.id}, '${a.nome.replace(/'/g, "\\'")}')" aria-label="Excluir">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 7h14M9.5 7V5.2c0-.66.54-1.2 1.2-1.2h2.6c.66 0 1.2.54 1.2 1.2V7M7 7l.7 11.3c.05.9.8 1.7 1.8 1.7h5c1 0 1.75-.8 1.8-1.7L17 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

document.getElementById('alunos-search').addEventListener('input', (e) => {
  const q = e.target.value.toLowerCase();
  renderAlunos(alunosCache.filter(a => a.nome.toLowerCase().includes(q) || a.email.toLowerCase().includes(q)));
});

async function populateHorarioSelect(selectedId) {
  const select = document.getElementById('aluno-horario');

  // Garante que a lista de turmas/horários esteja carregada antes de abrir o modal.
  if (horariosCache.length === 0) {
    try {
      const data = await api('horarios.php');
      horariosCache = data.horarios;
    } catch (err) {
      toast(err.message, 'error');
    }
  }

  const options = horariosCache.filter(h => h.status === 'ativa').map(h =>
    `<option value="${h.id}">${h.turma} — ${DIAS_LABEL[h.dia_semana] || h.dia_semana} ${h.hora_inicio.slice(0,5)} (${h.professor_nome})</option>`
  ).join('');

  select.innerHTML = `<option value="">Sem turma definida ainda</option>${options}`;
  select.value = selectedId ?? '';
}

document.getElementById('btn-novo-aluno').addEventListener('click', async () => {
  document.getElementById('form-aluno').reset();
  document.getElementById('aluno-id').value = '';
  document.getElementById('modal-aluno-title').textContent = 'Novo aluno';
  document.querySelector('label[for="aluno-senha"]').textContent = 'Senha inicial';
  document.getElementById('aluno-senha').placeholder = 'Padrão: 123456';
  await populateHorarioSelect();
  openModal('modal-aluno');
});

async function editAluno(id) {
  const a = alunosCache.find(x => String(x.id) === String(id));
  if (!a) return;
  document.getElementById('form-aluno').reset();
  document.getElementById('aluno-id').value = a.id;
  document.getElementById('modal-aluno-title').textContent = 'Editar aluno';
  document.querySelector('label[for="aluno-senha"]').textContent = 'Nova senha (opcional)';
  document.getElementById('aluno-senha').placeholder = 'Deixe em branco para manter a atual';
  // Open immediately so a slow request cannot leave the page dimmed without a dialog.
  openModal('modal-aluno');
  let detalhes = a;
  try { detalhes = (await api(`alunos.php?id=${id}`)).aluno || a; } catch (err) { toast(err.message, 'error'); }
  document.getElementById('aluno-nome').value = a.nome;
  document.getElementById('aluno-email').value = a.email;
  document.getElementById('aluno-telefone').value = detalhes.telefone || '';
  document.getElementById('aluno-cpf').value = detalhes.cpf || '';
  document.getElementById('aluno-nascimento').value = detalhes.data_nascimento || '';
  document.getElementById('aluno-endereco').value = detalhes.endereco || '';
  document.getElementById('aluno-responsavel').value = detalhes.responsavel_nome || '';
  document.getElementById('aluno-responsavel-telefone').value = detalhes.responsavel_telefone || '';
  document.getElementById('aluno-responsavel-email').value = detalhes.responsavel_email || '';
  document.getElementById('aluno-nivel').value = a.nivel;
  document.getElementById('aluno-status').value = a.status;
  await populateHorarioSelect(a.horario_id);
}

document.getElementById('form-aluno').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('aluno-id').value;
  const payload = {
    nome: document.getElementById('aluno-nome').value.trim(),
    email: document.getElementById('aluno-email').value.trim(),
    nivel: document.getElementById('aluno-nivel').value,
    status: document.getElementById('aluno-status').value,
    horario_id: document.getElementById('aluno-horario').value || null
    ,senha: document.getElementById('aluno-senha').value || undefined, telefone: document.getElementById('aluno-telefone').value, cpf: document.getElementById('aluno-cpf').value, data_nascimento: document.getElementById('aluno-nascimento').value, endereco: document.getElementById('aluno-endereco').value, responsavel_nome: document.getElementById('aluno-responsavel').value, responsavel_telefone: document.getElementById('aluno-responsavel-telefone').value, responsavel_email: document.getElementById('aluno-responsavel-email').value
  };
  try {
    if (id) {
      await api(`alunos.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) });
      toast('Aluno atualizado com sucesso.');
    } else {
      await api('alunos.php', { method: 'POST', body: JSON.stringify(payload) });
      toast('Aluno cadastrado com sucesso.');
    }
    closeModal('modal-aluno');
    loadAlunos();
  } catch (err) {
    toast(err.message, 'error');
  }
});

/* ---------------- Professores ---------------- */
async function loadProfessores() {
  try {
    const data = await api('professores.php');
    professoresCache = data.professores;
    renderProfessores(professoresCache);
  } catch (err) {
    toast(err.message, 'error');
  }
}

function renderProfessores(list) {
  const body = document.getElementById('professores-table-body');
  if (!list.length) {
    body.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--color-text-muted); padding:30px;">Nenhum professor encontrado.</td></tr>`;
    return;
  }
  body.innerHTML = list.map(p => `
    <tr>
      <td>${p.nome}</td>
      <td>${p.email}</td>
      <td>${escapeHtml(p.idiomas)}</td>
      <td>${p.carga_horaria}h/semana</td>
      <td>${statusBadge(p.status)}</td>
      <td>
        <div class="row-actions">
          <button class="btn-icon" onclick="editProfessor(${p.id})" aria-label="Editar">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.7 4.8l3.5 3.5L8 19.5H4.5V16L15.7 4.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          </button>
          <button class="btn-icon" onclick="askDelete('professor', ${p.id}, '${p.nome.replace(/'/g, "\\'")}')" aria-label="Excluir">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 7h14M9.5 7V5.2c0-.66.54-1.2 1.2-1.2h2.6c.66 0 1.2.54 1.2 1.2V7M7 7l.7 11.3c.05.9.8 1.7 1.8 1.7h5c1 0 1.75-.8 1.8-1.7L17 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

document.getElementById('professores-search').addEventListener('input', (e) => {
  const q = e.target.value.toLowerCase();
  renderProfessores(professoresCache.filter(p => p.nome.toLowerCase().includes(q) || p.email.toLowerCase().includes(q)));
});

document.getElementById('btn-novo-professor').addEventListener('click', () => {
  document.getElementById('form-professor').reset();
  document.getElementById('professor-id').value = '';
  document.querySelector('label[for="professor-senha"]').textContent = 'Senha inicial';
  document.getElementById('professor-senha').placeholder = 'Padrão: 123456';
  document.getElementById('modal-professor-title').textContent = 'Novo professor';
  openModal('modal-professor');
});

async function editProfessor(id) {
  const p = professoresCache.find(x => String(x.id) === String(id));
  if (!p) return;
  document.getElementById('form-professor').reset();
  let detalhes = p;
  try { detalhes = (await api(`professores.php?id=${id}`)).professor; } catch (err) { toast(err.message, 'error'); return; }
  document.getElementById('professor-id').value = p.id;
  document.getElementById('professor-nome').value = p.nome;
  document.getElementById('professor-email').value = p.email;
  document.getElementById('professor-telefone').value = detalhes.telefone || '';
  document.getElementById('professor-cpf').value = detalhes.cpf || '';
  document.getElementById('professor-nascimento').value = detalhes.data_nascimento || '';
  document.getElementById('professor-endereco').value = detalhes.endereco || '';
  document.getElementById('professor-formacao').value = detalhes.formacao || '';
  document.getElementById('professor-registro').value = detalhes.registro_profissional || '';
  document.getElementById('professor-documentos').value = detalhes.documentos_comprovacao || '';
  document.getElementById('professor-especialidade').value = p.especialidade;
  document.getElementById('professor-idiomas').value = detalhes.idiomas || '';
  document.getElementById('professor-carga').value = p.carga_horaria;
  document.getElementById('professor-status').value = p.status;
  document.querySelector('label[for="professor-senha"]').textContent = 'Nova senha (opcional)';
  document.getElementById('professor-senha').placeholder = 'Deixe em branco para manter a atual';
  document.getElementById('modal-professor-title').textContent = 'Editar professor';
  openModal('modal-professor');
}

document.getElementById('form-professor').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('professor-id').value;
  const payload = {
    nome: document.getElementById('professor-nome').value.trim(),
    email: document.getElementById('professor-email').value.trim(),
    especialidade: document.getElementById('professor-especialidade').value.trim(),
    carga_horaria: Number(document.getElementById('professor-carga').value),
    status: document.getElementById('professor-status').value
    ,idiomas: document.getElementById('professor-idiomas').value.trim(), senha: document.getElementById('professor-senha').value || undefined, telefone: document.getElementById('professor-telefone').value, cpf: document.getElementById('professor-cpf').value, data_nascimento: document.getElementById('professor-nascimento').value, endereco: document.getElementById('professor-endereco').value, formacao: document.getElementById('professor-formacao').value, registro_profissional: document.getElementById('professor-registro').value, documentos_comprovacao: document.getElementById('professor-documentos').value
  };
  try {
    if (id) {
      await api(`professores.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) });
      toast('Professor atualizado com sucesso.');
    } else {
      await api('professores.php', { method: 'POST', body: JSON.stringify(payload) });
      toast('Professor cadastrado com sucesso.');
    }
    closeModal('modal-professor');
    loadProfessores();
  } catch (err) {
    toast(err.message, 'error');
  }
});

/* ---------------- Horários ---------------- */
const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
const DIAS_LABEL = { Seg: 'Seg', Ter: 'Ter', Qua: 'Qua', Qui: 'Qui', Sex: 'Sex', Sab: 'Sáb' };

async function loadHorarios() {
  try {
    const [dataHorarios, dataProfessores] = await Promise.all([
      api('horarios.php'),
      professoresCache.length ? Promise.resolve({ professores: professoresCache }) : api('professores.php')
    ]);
    horariosCache = dataHorarios.horarios;
    professoresCache = dataProfessores.professores;
    populateProfessorSelect();
    document.getElementById('horario-curso').innerHTML = (dataHorarios.cursos || []).map(c => `<option value="${c.id}">${c.nome}</option>`).join('');
    renderTimetable(horariosCache.filter(h => h.status === 'ativa'));
  } catch (err) {
    toast(err.message, 'error');
  }
}

function populateProfessorSelect() {
  const select = document.getElementById('horario-professor');
  select.innerHTML = professoresCache.map(p => `<option value="${p.id}">${p.nome}</option>`).join('');
}

function renderTimetable(list) {
  const grid = document.getElementById('timetable-grid');
  const horarios = [...new Set(list.map(h => h.hora_inicio))].sort();
  const horariosFixos = horarios.length ? horarios : ['08:00', '10:00', '14:00', '16:00', '19:00'];

  let html = `<div class="tt-cell tt-head"></div>`;
  DIAS.forEach(d => html += `<div class="tt-cell tt-head">${DIAS_LABEL[d]}</div>`);

  horariosFixos.forEach(hora => {
    html += `<div class="tt-cell tt-time">${hora.slice(0,5)}</div>`;
    DIAS.forEach(dia => {
      const aulas = list.filter(h => h.dia_semana === dia && h.hora_inicio === hora);
      html += `<div class="tt-cell tt-slot">`;
      aulas.forEach((a, i) => {
        const colorClass = ['', 'violet', 'amber'][i % 3];
        html += `<button type="button" class="tt-pill ${colorClass}" data-editar-horario="${a.id}">${a.turma}<br>${a.professor_nome}<br>${a.hora_inicio.slice(0,5)}–${a.hora_fim.slice(0,5)} · ${a.curso_nome || 'Curso'}</button>`;
      });
      html += `</div>`;
    });
  });

  if (!list.length) {
    grid.innerHTML = `<div style="grid-column: 1 / -1;" class="empty-state">
      <svg class="icon-lg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="5" width="17" height="15" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 9.5h17" stroke="currentColor" stroke-width="1.7"/></svg>
      <p>Nenhum horário cadastrado ainda. Clique em "Novo horário" para começar.</p>
    </div>`;
  } else {
    grid.innerHTML = html;
  }
}

document.getElementById('btn-novo-horario').addEventListener('click', async () => {
  // Carrega professores mesmo quando o primeiro clique já abre o modal.
  if (professoresCache.length === 0) {
    try {
      const data = await api('professores.php');
      professoresCache = data.professores;
    } catch (err) {
      toast(err.message, 'error');
      return;
    }
  }
  populateProfessorSelect();
  if (!professoresCache.length) {
    toast('Cadastre ao menos um professor ativo antes de criar um horário.', 'error');
    return;
  }
  document.getElementById('form-horario').reset();
  document.getElementById('horario-id').value = '';
  document.getElementById('horario-data-inicio').value = new Date().toISOString().slice(0,10);
  document.getElementById('horario-status').disabled = true;
  document.getElementById('cancelar-horario').hidden = true;
  document.querySelector('#modal-horario .modal-head h3').textContent = 'Novo horário';
  openModal('modal-horario');
});

document.getElementById('timetable-grid').addEventListener('click', e => {
  const btn=e.target.closest('[data-editar-horario]'); if(!btn)return;
  const h=horariosCache.find(x=>String(x.id)===btn.dataset.editarHorario); if(!h)return;
  document.getElementById('horario-id').value=h.id;document.getElementById('horario-turma').value=h.turma;document.getElementById('horario-professor').value=h.professor_id;document.getElementById('horario-curso').value=h.curso_id;document.getElementById('horario-dia').value=h.dia_semana;document.getElementById('horario-inicio').value=h.hora_inicio.slice(0,5);document.getElementById('horario-fim').value=h.hora_fim.slice(0,5);document.getElementById('horario-capacidade').value=h.capacidade;document.getElementById('horario-data-inicio').value=h.data_inicio||'';document.getElementById('horario-data-fim').value=h.data_fim||'';document.getElementById('horario-status').value=h.status;document.getElementById('horario-status').disabled=false;document.getElementById('cancelar-horario').hidden=h.status!=='ativa';document.querySelector('#modal-horario .modal-head h3').textContent='Editar horário';openModal('modal-horario');
});

document.getElementById('cancelar-horario').addEventListener('click', async () => {
  const id = document.getElementById('horario-id').value;
  if (!id || !confirm('Cancelar este horário? As matrículas ativas da turma serão encerradas e o histórico será preservado.')) return;
  try {
    await api(`horarios.php?id=${id}`, { method: 'DELETE' });
    closeModal('modal-horario');
    await loadHorarios();
    toast('Horário cancelado; o histórico foi preservado.');
  } catch (err) {
    toast(err.message, 'error');
  }
});

document.getElementById('form-horario').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id=document.getElementById('horario-id').value;
  const payload = {
    turma: document.getElementById('horario-turma').value.trim(),
    professor_id: Number(document.getElementById('horario-professor').value),
    dia_semana: document.getElementById('horario-dia').value,
    hora_inicio: document.getElementById('horario-inicio').value,
    hora_fim: document.getElementById('horario-fim').value,
    curso_id: Number(document.getElementById('horario-curso').value),
    capacidade: Number(document.getElementById('horario-capacidade').value)
    ,data_inicio: document.getElementById('horario-data-inicio').value,
    data_fim: document.getElementById('horario-data-fim').value || null,
    status: document.getElementById('horario-status').value
  };
  try {
    await api(`horarios.php${id?`?id=${id}`:''}`, { method: id?'PUT':'POST', body: JSON.stringify(payload) });
    toast(id?'Turma atualizada.':'Horário cadastrado com sucesso.');
    closeModal('modal-horario');
    loadHorarios();
  } catch (err) {
    toast(err.message, 'error');
  }
});

/* ---------------- Exclusão (aluno/professor) ---------------- */
let pendingDelete = null;

function askDelete(type, id, name) {
  pendingDelete = { type, id };
  document.getElementById('confirm-title').textContent = `Arquivar ${type === 'aluno' ? 'aluno' : 'professor'}?`;
  document.getElementById('confirm-text').textContent = `Deseja arquivar "${name}"? O histórico acadêmico será preservado e o cadastro poderá ser reativado editando a situação.`;
  openModal('modal-confirm');
}

document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
  if (!pendingDelete) return;
  const endpoint = pendingDelete.type === 'aluno' ? 'alunos.php' : 'professores.php';
  try {
    await api(`${endpoint}?id=${pendingDelete.id}`, { method: 'DELETE' });
    toast('Registro excluído com sucesso.');
    closeModal('modal-confirm');
    if (pendingDelete.type === 'aluno') loadAlunos(); else loadProfessores();
  } catch (err) {
    toast(err.message, 'error');
  }
  pendingDelete = null;
});

// As máscaras ajudam na digitação; a API também valida os valores recebidos.
function aplicarMascara(input, tipo) {
  input.addEventListener('input', () => {
    let n = input.value.replace(/\D/g, '');
    if (tipo === 'cpf') n = n.slice(0, 11);
    else n = n.slice(0, 11);
    if (tipo === 'cpf') {
      if (n.length > 9) input.value = n.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
      else if (n.length > 6) input.value = n.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
      else if (n.length > 3) input.value = n.replace(/(\d{3})(\d{1,3})/, '$1.$2');
      else input.value = n;
    } else if (n.length > 10) input.value = n.replace(/(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
    else if (n.length > 6) input.value = n.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
    else if (n.length > 2) input.value = n.replace(/(\d{2})(\d{1,5})/, '($1) $2');
    else input.value = n ? `(${n}` : '';
  });
}
['aluno-cpf','professor-cpf'].forEach(id => aplicarMascara(document.getElementById(id), 'cpf'));
['aluno-telefone','aluno-responsavel-telefone','professor-telefone'].forEach(id => aplicarMascara(document.getElementById(id), 'telefone'));

/* ---------------- Inicialização ---------------- */
loadSession();
loadDashboardStats();
