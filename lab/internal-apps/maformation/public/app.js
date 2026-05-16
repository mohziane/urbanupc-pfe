// MaFormation — client. All requests go through /api/* on the same origin.
// CSRF: GET /api/csrf at login time, send X-CSRF-Token on state-changing calls.
'use strict';

const api = {
  csrfToken: null,

  async _fetch(path, opts = {}) {
    const headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
    if (opts.body && !(opts.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    if (opts.method && opts.method !== 'GET' && this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }
    const r = await fetch(path, { credentials: 'same-origin', ...opts, headers });
    if (!r.ok) {
      const err = await r.json().catch(() => ({ error: r.statusText }));
      throw Object.assign(new Error(err.error || 'request_failed'), { status: r.status, body: err });
    }
    if (r.headers.get('content-type')?.includes('application/json')) return r.json();
    return r;
  },
  async refreshCsrf() { const r = await this._fetch('api/csrf'); this.csrfToken = r.csrfToken; },
  me()        { return this._fetch('api/auth/me'); },
  login(b)    { return this._fetch('api/auth/login',  { method: 'POST', body: b }); },
  logout()    { return this._fetch('api/auth/logout', { method: 'POST' }); },
  courses()   { return this._fetch('api/courses'); },
  schedule()  { return this._fetch('api/schedule'); },
  documents() { return this._fetch('api/documents'); },
  grades()    { return this._fetch('api/grades'); },
  uploadDoc(file) {
    const fd = new FormData(); fd.append('file', file);
    return this._fetch('api/documents', { method: 'POST', body: fd });
  },
  deleteDoc(id) { return this._fetch('api/documents/' + encodeURIComponent(id), { method: 'DELETE' }); },
};

// ──────────── view helpers ────────────
const el = (q) => document.querySelector(q);
const show = (id) => { el(id).hidden = false; };
const hide = (id) => { el(id).hidden = true; };

function escape(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

async function renderCourses() {
  try {
    const { courses } = await api.courses();
    el('#tab-courses').innerHTML = courses.length
      ? courses.map((c) => `
          <div class="card">
            <h3>${escape(c.code)} — ${escape(c.title)}</h3>
            <p>${escape(c.description)}</p>
            <small>${escape(c.semester)} · ${escape(c.ects)} ECTS</small>
          </div>`).join('')
      : '<p class="hint">Aucun cours pour le moment. Demandez à votre responsable pédagogique d\'enregistrer vos inscriptions.</p>';
  } catch (e) { el('#tab-courses').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

async function renderSchedule() {
  const days = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
  try {
    const { slots } = await api.schedule();
    el('#tab-schedule').innerHTML = slots.length
      ? `<table><thead><tr><th>Jour</th><th>Heure</th><th>Cours</th><th>Salle</th></tr></thead>
         <tbody>${slots.map((s) => `<tr>
           <td>${escape(days[s.dayOfWeek])}</td>
           <td>${escape(s.startTime)} – ${escape(s.endTime)}</td>
           <td>${escape(s.course.code)} ${escape(s.course.title)}</td>
           <td>${escape(s.room)}</td>
         </tr>`).join('')}</tbody></table>`
      : '<p class="hint">Pas de cours planifiés.</p>';
  } catch (e) { el('#tab-schedule').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

async function renderDocuments() {
  try {
    const { documents } = await api.documents();
    const items = documents.map((d) => `
      <div class="card row">
        <div>
          <strong>${escape(d.filename)}</strong>
          <small>${escape(d.mimeType)} · ${(d.sizeBytes / 1024).toFixed(0)} Ko · ${escape(new Date(d.createdAt).toLocaleString('fr-FR'))}</small>
        </div>
        <div>
          <a href="api/documents/${encodeURIComponent(d.id)}" download>Télécharger</a>
          <button data-del="${escape(d.id)}">Supprimer</button>
        </div>
      </div>`).join('');
    el('#tab-documents').innerHTML = `
      <div class="upload-area">
        <input type="file" id="file-input" accept="application/pdf,image/png,image/jpeg">
        <button id="upload-btn" type="button">Envoyer</button>
      </div>
      ${items || '<p class="hint">Aucun document.</p>'}
    `;
    el('#upload-btn').addEventListener('click', async () => {
      const f = el('#file-input').files[0];
      if (!f) return;
      try { await api.uploadDoc(f); await renderDocuments(); }
      catch (e) { alert('Upload échoué: ' + e.message); }
    });
    document.querySelectorAll('[data-del]').forEach((b) => {
      b.addEventListener('click', async () => {
        if (!confirm('Supprimer ce document ?')) return;
        try { await api.deleteDoc(b.dataset.del); await renderDocuments(); }
        catch (e) { alert('Suppression échouée: ' + e.message); }
      });
    });
  } catch (e) { el('#tab-documents').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

async function renderGrades() {
  try {
    const { grades } = await api.grades();
    el('#tab-grades').innerHTML = grades.length
      ? `<table><thead><tr><th>Code</th><th>Cours</th><th>Note /20</th><th>Date</th></tr></thead>
         <tbody>${grades.map((g) => `<tr>
           <td>${escape(g.course.code)}</td>
           <td>${escape(g.course.title)}</td>
           <td><strong>${g.value.toFixed(2)}</strong></td>
           <td>${escape(new Date(g.postedAt).toLocaleDateString('fr-FR'))}</td>
         </tr>`).join('')}</tbody></table>`
      : '<p class="hint">Aucune note pour le moment.</p>';
  } catch (e) { el('#tab-grades').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

function showApp(user) {
  hide('#login-view'); show('#app-view'); show('#user-info');
  el('#user-name').textContent = user.displayName + ' (' + user.role + ')';
  renderCourses();
  document.querySelectorAll('.tabs button').forEach((b) => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.tabs button').forEach((x) => x.classList.toggle('active', x === b));
      document.querySelectorAll('.tab').forEach((t) => t.classList.toggle('active', t.id === 'tab-' + b.dataset.tab));
      ({ courses: renderCourses, schedule: renderSchedule, documents: renderDocuments, grades: renderGrades }[b.dataset.tab])();
    });
  });
}

el('#login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  hide('#login-err');
  const samAccount = el('#login-user').value.trim();
  const password = el('#login-pw').value;
  try {
    await api.refreshCsrf();
    const { user } = await api.login({ samAccount, password });
    showApp(user);
  } catch (err) {
    el('#login-err').textContent = err.status === 429
      ? 'Trop de tentatives, réessayez dans quelques minutes.'
      : 'Identifiants invalides.';
    show('#login-err');
  }
});

el('#logout-btn').addEventListener('click', async () => {
  try { await api.logout(); } catch {}
  location.reload();
});

(async () => {
  try {
    await api.refreshCsrf();
    const { user } = await api.me();
    showApp(user);
  } catch { /* not logged in, show login form (default) */ }
})();
