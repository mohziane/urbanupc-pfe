'use strict';

const api = {
  csrfToken: null,
  async _fetch(path, opts = {}) {
    const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
    if (opts.body && !(opts.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    if (opts.method && opts.method !== 'GET' && this.csrfToken) headers['X-CSRF-Token'] = this.csrfToken;
    const r = await fetch(path, { credentials: 'same-origin', ...opts, headers });
    if (!r.ok) {
      const err = await r.json().catch(() => ({ error: r.statusText }));
      throw Object.assign(new Error(err.error || 'request_failed'), { status: r.status, body: err });
    }
    if (r.headers.get('content-type')?.includes('application/json')) return r.json();
    return r;
  },
  async refreshCsrf() { const r = await this._fetch('api/csrf'); this.csrfToken = r.csrfToken; },
  me()         { return this._fetch('api/auth/me'); },
  signup(b)    { return this._fetch('api/auth/signup', { method: 'POST', body: b }); },
  verify(b)    { return this._fetch('api/auth/verify', { method: 'POST', body: b }); },
  login(b)     { return this._fetch('api/auth/login',  { method: 'POST', body: b }); },
  logout()     { return this._fetch('api/auth/logout', { method: 'POST' }); },
  profile()    { return this._fetch('api/profile'); },
  updateProfile(b) { return this._fetch('api/profile', { method: 'PUT', body: b }); },
  applications()   { return this._fetch('api/applications'); },
  createApp(b)     { return this._fetch('api/applications', { method: 'POST', body: b }); },
  submitApp(id)    { return this._fetch('api/applications/' + encodeURIComponent(id) + '/submit', { method: 'POST' }); },
  documents()      { return this._fetch('api/documents'); },
  uploadDoc(file, category) {
    const fd = new FormData(); fd.append('file', file); fd.append('category', category);
    return this._fetch('api/documents', { method: 'POST', body: fd });
  },
  deleteDoc(id)    { return this._fetch('api/documents/' + encodeURIComponent(id), { method: 'DELETE' }); },
};

const el = (q) => document.querySelector(q);
const show = (id) => { el(id).hidden = false; };
const hide = (id) => { el(id).hidden = true; };
const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

el('#show-login').addEventListener('click', () => {
  el('#show-login').classList.add('active'); el('#show-signup').classList.remove('active');
  show('#login-form'); hide('#signup-form');
});
el('#show-signup').addEventListener('click', () => {
  el('#show-signup').classList.add('active'); el('#show-login').classList.remove('active');
  show('#signup-form'); hide('#login-form');
});

el('#signup-form').addEventListener('submit', async (e) => {
  e.preventDefault(); hide('#signup-err'); hide('#signup-ok');
  try {
    await api.refreshCsrf();
    await api.signup({
      email:     el('#signup-email').value.trim(),
      password:  el('#signup-pw').value,
      firstName: el('#signup-first').value.trim(),
      lastName:  el('#signup-last').value.trim(),
    });
    el('#signup-ok').textContent = 'Compte créé. Un email de vérification a été envoyé (consultez outbox.log côté serveur pour récupérer le token).';
    show('#signup-ok');
  } catch (err) {
    el('#signup-err').textContent = err.body?.issues?.length ? 'Saisie invalide.' : 'Erreur lors de la création.';
    show('#signup-err');
  }
});

el('#login-form').addEventListener('submit', async (e) => {
  e.preventDefault(); hide('#login-err');
  try {
    await api.refreshCsrf();
    const { user } = await api.login({ email: el('#login-email').value.trim(), password: el('#login-pw').value });
    showApp(user);
  } catch (err) {
    el('#login-err').textContent =
      err.status === 423 ? 'Compte temporairement bloqué (trop de tentatives).' :
      err.status === 429 ? 'Trop de tentatives, patientez quelques minutes.' :
      'Identifiants invalides.';
    show('#login-err');
  }
});

el('#logout-btn').addEventListener('click', async () => { try { await api.logout(); } catch {} location.reload(); });

el('#verify-btn').addEventListener('click', async () => {
  const token = el('#verify-token').value.trim();
  if (!token) return;
  try { await api.refreshCsrf(); await api.verify({ token }); location.reload(); }
  catch { alert('Token invalide ou expiré.'); }
});

async function renderProfile() {
  try {
    const { profile } = await api.profile();
    el('#tab-profile').innerHTML = `
      <form id="profile-form">
        <label>Prénom <input name="firstName" value="${escape(profile.firstName)}" required maxlength="60"></label>
        <label>Nom <input name="lastName" value="${escape(profile.lastName)}" required maxlength="60"></label>
        <label>Date de naissance <input type="date" name="dateOfBirth" value="${profile.dateOfBirth ? new Date(profile.dateOfBirth).toISOString().slice(0,10) : ''}"></label>
        <label>Nationalité <input name="nationality" value="${escape(profile.nationality || '')}" maxlength="60"></label>
        <label>Téléphone <input name="phone" value="${escape(profile.phone || '')}" maxlength="20" pattern="^\\+?[0-9 .-]{6,20}$"></label>
        <button type="submit" ${profile.emailVerified ? '' : 'disabled'}>Enregistrer</button>
        ${profile.emailVerified ? '' : '<p class="hint">Vérifiez votre email pour modifier votre profil.</p>'}
      </form>`;
    el('#profile-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const body = {};
      for (const [k, v] of fd.entries()) { if (v) body[k] = v; }
      if (body.dateOfBirth) body.dateOfBirth = new Date(body.dateOfBirth).toISOString();
      try { await api.updateProfile(body); alert('Profil mis à jour.'); }
      catch (err) { alert('Erreur: ' + err.message); }
    });
  } catch (e) { el('#tab-profile').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

async function renderApplications() {
  try {
    const { applications } = await api.applications();
    el('#tab-applications').innerHTML = `
      <div class="card">
        <h3>Nouvelle candidature</h3>
        <form id="new-app">
          <label>Programme <select name="programCode">
            <option value="M1-CYBER">M1 Cybersécurité</option>
            <option value="M2-CYBER">M2 Cybersécurité</option>
          </select></label>
          <label>Diplôme actuel <input name="prevDegree" required maxlength="200"></label>
          <label>Établissement <input name="prevInstitution" required maxlength="200"></label>
          <label>Motivation (50 caractères min.) <textarea name="motivation" required minlength="50" maxlength="4000"></textarea></label>
          <button type="submit">Créer (brouillon)</button>
        </form>
      </div>
      ${applications.map((a) => `
        <div class="card">
          <div class="row">
            <div>
              <strong>${escape(a.programCode)}</strong>
              <span class="status ${escape(a.status)}">${escape(a.status)}</span>
            </div>
            ${a.status === 'DRAFT' ? `<button data-submit="${escape(a.id)}">Soumettre</button>` : ''}
          </div>
          <p class="hint">Créée le ${escape(new Date(a.createdAt).toLocaleString('fr-FR'))}</p>
          ${a.decisionNote ? `<p>${escape(a.decisionNote)}</p>` : ''}
        </div>`).join('')}
    `;
    el('#new-app').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      try { await api.createApp(Object.fromEntries(fd)); await renderApplications(); }
      catch (err) { alert('Erreur: ' + err.message); }
    });
    document.querySelectorAll('[data-submit]').forEach((b) => b.addEventListener('click', async () => {
      try { await api.submitApp(b.dataset.submit); await renderApplications(); }
      catch (err) { alert(err.body?.error === 'documents_missing' ? 'Téléversez d\'abord votre CV et votre lettre.' : 'Erreur: ' + err.message); }
    }));
  } catch (e) { el('#tab-applications').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

async function renderDocuments() {
  try {
    const { documents } = await api.documents();
    el('#tab-documents').innerHTML = `
      <div class="upload-area">
        <select id="doc-category">
          <option value="CV">CV</option>
          <option value="LETTER">Lettre de motivation</option>
          <option value="TRANSCRIPT">Relevés de notes</option>
          <option value="ID_PROOF">Pièce d'identité</option>
          <option value="OTHER">Autre</option>
        </select>
        <input type="file" id="file-input" accept="application/pdf,image/png,image/jpeg">
        <button id="upload-btn" type="button">Téléverser</button>
      </div>
      ${documents.map((d) => `
        <div class="card row">
          <div>
            <strong>[${escape(d.category)}] ${escape(d.filename)}</strong>
            <small>${escape(d.mimeType)} · ${(d.sizeBytes / 1024).toFixed(0)} Ko · ${escape(new Date(d.createdAt).toLocaleString('fr-FR'))}</small>
          </div>
          <div>
            <a href="api/documents/${encodeURIComponent(d.id)}" download>Télécharger</a>
            <button data-del="${escape(d.id)}">Supprimer</button>
          </div>
        </div>`).join('') || '<p class="hint">Aucun document.</p>'}
    `;
    el('#upload-btn').addEventListener('click', async () => {
      const f = el('#file-input').files[0];
      if (!f) return;
      try { await api.uploadDoc(f, el('#doc-category').value); await renderDocuments(); }
      catch (err) { alert('Échec: ' + (err.body?.error || err.message)); }
    });
    document.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', async () => {
      if (!confirm('Supprimer ce document ?')) return;
      try { await api.deleteDoc(b.dataset.del); await renderDocuments(); }
      catch (err) { alert('Échec: ' + err.message); }
    }));
  } catch (e) { el('#tab-documents').innerHTML = `<p class="error">${escape(e.message)}</p>`; }
}

function showApp(user) {
  hide('#auth-view'); show('#app-view'); show('#user-info');
  el('#user-name').textContent = (user.firstName || user.email) + ' (' + user.role + ')';
  if (!(user.verified ?? user.emailVerified)) show('#verify-banner'); else hide('#verify-banner');
  renderProfile();
  document.querySelectorAll('.tabs button').forEach((b) => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.tabs button').forEach((x) => x.classList.toggle('active', x === b));
      document.querySelectorAll('.tab').forEach((t) => t.classList.toggle('active', t.id === 'tab-' + b.dataset.tab));
      ({ profile: renderProfile, applications: renderApplications, documents: renderDocuments }[b.dataset.tab])();
    });
  });
}

(async () => {
  try {
    await api.refreshCsrf();
    const { user } = await api.me();
    showApp(user);
  } catch { /* not logged in, show auth view (default) */ }
})();
