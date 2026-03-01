<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Zoom App</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --success: #22c55e;
            --warning: #f59e0b;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.12);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        /* ---- Login ---- */
        #login-screen {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-card {
            background: var(--card);
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 380px;
            text-align: center;
        }

        .login-card h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .login-card p {
            color: var(--muted);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .login-card input {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            margin-bottom: 1rem;
            outline: none;
            transition: border-color 0.15s;
        }

        .login-card input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }

        #login-error {
            color: var(--danger);
            font-size: 0.8125rem;
            min-height: 1.25rem;
            margin-bottom: 0.25rem;
        }

        /* ---- Buttons ---- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s, opacity 0.15s;
        }

        .btn:disabled { opacity: 0.5; cursor: default; }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--primary-hover); }

        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover:not(:disabled) { background: var(--danger-hover); }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
            padding: 0.375rem 0.5rem;
        }
        .btn-ghost:hover { background: var(--bg); color: var(--text); }

        .btn-outline {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-outline:hover { background: var(--bg); }

        .btn-sm { padding: 0.3rem 0.625rem; font-size: 0.8125rem; }

        .btn-block { width: 100%; justify-content: center; }

        /* ---- Dashboard ---- */
        #dashboard { display: none; max-width: 1000px; margin: 0 auto; padding: 1.5rem; }

        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .dash-header h1 { font-size: 1.375rem; }

        .dash-actions { display: flex; gap: 0.5rem; }

        /* ---- Table ---- */
        .table-wrap {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }

        thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
        }

        tbody td {
            padding: 0.625rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }

        tbody tr:hover { background: #f8fafc; }

        .drag-handle {
            cursor: grab;
            color: var(--muted);
            user-select: none;
            font-size: 1.125rem;
            padding: 0.25rem;
        }
        .drag-handle:active { cursor: grabbing; }

        tr.dragging { opacity: 0.4; background: #e0e7ff; }
        tr.drag-over td { border-top: 2px solid var(--primary); }

        .type-badge {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .type-survey     { background: #dbeafe; color: #1e40af; }
        .type-greeting    { background: #fef3c7; color: #92400e; }
        .type-wordcloud   { background: #e0e7ff; color: #3730a3; }
        .type-reactions   { background: #fce7f3; color: #9d174d; }
        .type-quiz        { background: #d1fae5; color: #065f46; }
        .type-scale       { background: #f3e8ff; color: #6b21a8; }

        .badge-active   { color: var(--success); }
        .badge-inactive { color: var(--muted); }

        .row-actions { display: flex; gap: 0.25rem; }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
        }

        /* ---- Modal ---- */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.45);
            z-index: 100;
            align-items: flex-start;
            justify-content: center;
            padding: 3rem 1rem;
            overflow-y: auto;
        }
        .overlay.open { display: flex; }

        .modal {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 560px;
            padding: 1.75rem;
            animation: modalIn 0.15s ease;
        }

        @keyframes modalIn { from { opacity: 0; transform: translateY(-12px); } }

        .modal h2 {
            font-size: 1.125rem;
            margin-bottom: 1.25rem;
        }

        .modal-sm { max-width: 400px; }

        /* ---- Form ---- */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--text);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem 0.625rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }

        .form-group textarea { resize: vertical; min-height: 80px; }

        .form-group input[readonly] {
            background: #f8fafc;
            color: var(--muted);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        /* ---- Dynamic option list ---- */
        .option-list { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }

        .option-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .option-row input[type="text"] {
            flex: 1;
            padding: 0.375rem 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.8125rem;
        }

        .option-row input[type="color"] {
            width: 34px;
            height: 30px;
            padding: 2px;
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
        }

        .option-num {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            width: 1.25rem;
            text-align: center;
        }

        .btn-remove-option {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1.125rem;
            padding: 0 0.25rem;
            line-height: 1;
        }
        .btn-remove-option:hover { opacity: 0.7; }

        /* ---- Emoji list ---- */
        .emoji-list { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.5rem; }

        .emoji-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 0.25rem 0.5rem;
            font-size: 1.125rem;
        }

        .emoji-tag button {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 0.75rem;
            line-height: 1;
        }

        .add-emoji-row {
            display: flex;
            gap: 0.375rem;
            margin-top: 0.5rem;
        }

        .add-emoji-row input {
            width: 60px;
            padding: 0.375rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 1.125rem;
            text-align: center;
        }

        /* ---- Toast ---- */
        #toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: var(--text);
            color: #fff;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            box-shadow: var(--shadow-lg);
            opacity: 0;
            transform: translateY(8px);
            transition: all 0.2s;
            z-index: 200;
            pointer-events: none;
        }
        #toast.show { opacity: 1; transform: translateY(0); }
        #toast.error { background: var(--danger); }

        /* ---- Checkbox toggle ---- */
        .toggle {
            position: relative;
            width: 36px;
            height: 20px;
        }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle .slider {
            position: absolute;
            inset: 0;
            background: #cbd5e1;
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .toggle .slider::before {
            content: '';
            position: absolute;
            left: 2px;
            top: 2px;
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
        }
        .toggle input:checked + .slider { background: var(--success); }
        .toggle input:checked + .slider::before { transform: translateX(16px); }

        /* ---- Correct answer radio ---- */
        .correct-label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: var(--muted);
            cursor: pointer;
            white-space: nowrap;
        }
        .correct-label input[type="radio"] { accent-color: var(--success); }
    </style>
</head>
<body>

<!-- ======== Login ======== -->
<div id="login-screen">
    <div class="login-card">
        <h1>Zoom App</h1>
        <p>Panel de administracion</p>
        <div id="login-error"></div>
        <input type="password" id="login-password" placeholder="Contraseña" autofocus>
        <button class="btn btn-primary btn-block" onclick="login()">Ingresar</button>
    </div>
</div>

<!-- ======== Dashboard ======== -->
<div id="dashboard">
    <div class="dash-header">
        <h1>Secciones</h1>
        <div class="dash-actions">
            <button class="btn btn-primary" onclick="openCreateModal()">+ Nueva seccion</button>
            <button class="btn btn-ghost" onclick="logout()">Cerrar sesion</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th style="width:50px">Icono</th>
                    <th>Titulo</th>
                    <th style="width:110px">Tipo</th>
                    <th style="width:130px">Clave</th>
                    <th style="width:70px">Activa</th>
                    <th style="width:100px">Acciones</th>
                </tr>
            </thead>
            <tbody id="sections-body"></tbody>
        </table>
    </div>
</div>

<!-- ======== Create/Edit Modal ======== -->
<div id="modal-editor" class="overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal">
        <h2 id="modal-title">Nueva seccion</h2>
        <form id="section-form" onsubmit="saveSection(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="f-title">Titulo *</label>
                    <input type="text" id="f-title" required oninput="onTitleInput()">
                </div>
                <div class="form-group">
                    <label for="f-key">Clave *</label>
                    <input type="text" id="f-key" pattern="[a-z0-9_]+" title="Solo letras minusculas, numeros y _" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="f-type">Tipo *</label>
                    <select id="f-type" onchange="renderTypeFields()">
                        <option value="survey">Encuesta</option>
                        <option value="greeting">Saludo</option>
                        <option value="wordcloud">Nube de palabras</option>
                        <option value="reactions">Reacciones</option>
                        <option value="quiz">Quiz</option>
                        <option value="scale">Escala</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="f-icon">Icono</label>
                    <input type="text" id="f-icon" placeholder="Ej: 📊" maxlength="8">
                </div>
            </div>
            <div class="form-group">
                <label class="toggle" style="display:inline-flex;align-items:center;gap:0.5rem;width:auto">
                    <span style="font-size:0.8125rem;font-weight:500">Activa</span>
                    <span style="position:relative;width:36px;height:20px;display:inline-block">
                        <input type="checkbox" id="f-active" checked>
                        <span class="slider"></span>
                    </span>
                </label>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0">

            <!-- Dynamic type-specific fields -->
            <div id="type-fields"></div>

            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ======== Delete Confirmation ======== -->
<div id="modal-delete" class="overlay" onclick="if(event.target===this)closeDeleteModal()">
    <div class="modal modal-sm" style="text-align:center">
        <h2>Eliminar seccion</h2>
        <p style="margin:1rem 0;color:var(--muted)">Se eliminara permanentemente:<br><strong id="delete-name"></strong></p>
        <div class="form-actions" style="justify-content:center">
            <button class="btn btn-outline" onclick="closeDeleteModal()">Cancelar</button>
            <button class="btn btn-danger" onclick="confirmDelete()">Eliminar</button>
        </div>
    </div>
</div>

<!-- ======== Toast ======== -->
<div id="toast"></div>

<script>
const API = 'api.php';
const TYPE_LABELS = {
    survey: 'Encuesta', greeting: 'Saludo', wordcloud: 'Nube de palabras',
    reactions: 'Reacciones', quiz: 'Quiz', scale: 'Escala'
};
const DEFAULT_COLORS = ['#ef4444','#22c55e','#3b82f6','#eab308','#8b5cf6','#06b6d4','#f97316','#ec4899'];

let token = sessionStorage.getItem('admin_token');
let sections = [];
let editingId = null;  // null = create, number = edit
let deletingId = null;

// ---- Init ----
if (token) {
    showDashboard();
} else {
    document.getElementById('login-password').addEventListener('keydown', e => {
        if (e.key === 'Enter') login();
    });
}

// ---- API helper ----
async function api(action, body = {}) {
    const res = await fetch(API, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Admin-Token': token || ''
        },
        body: JSON.stringify({ action, ...body })
    });
    const data = await res.json();
    if (res.status === 401 && action !== 'admin_login') {
        toast('Sesion expirada', true);
        logout();
        throw new Error('Unauthorized');
    }
    if (!res.ok) throw new Error(data.error || 'Error desconocido');
    return data;
}

// ---- Login / Logout ----
async function login() {
    const pw = document.getElementById('login-password').value;
    const errEl = document.getElementById('login-error');
    errEl.textContent = '';
    try {
        const data = await api('admin_login', { password: pw });
        token = data.token;
        sessionStorage.setItem('admin_token', token);
        showDashboard();
    } catch (e) {
        errEl.textContent = e.message;
    }
}

function logout() {
    token = null;
    sessionStorage.removeItem('admin_token');
    document.getElementById('dashboard').style.display = 'none';
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('login-password').value = '';
}

async function showDashboard() {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('dashboard').style.display = 'block';
    await loadSections();
}

// ---- Sections CRUD ----
async function loadSections() {
    try {
        const data = await api('admin_sections');
        sections = data.sections;
        renderTable();
    } catch (e) {
        toast(e.message, true);
    }
}

function renderTable() {
    const tbody = document.getElementById('sections-body');
    if (sections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No hay secciones. Crea la primera.</td></tr>';
        return;
    }
    tbody.innerHTML = sections.map(s => `
        <tr draggable="true" data-id="${s.id}">
            <td><span class="drag-handle" title="Arrastrar para reordenar">&#9776;</span></td>
            <td style="font-size:1.25rem">${esc(s.icon)}</td>
            <td><strong>${esc(s.title)}</strong></td>
            <td><span class="type-badge type-${s.type}">${TYPE_LABELS[s.type] || s.type}</span></td>
            <td><code style="font-size:0.8125rem">${esc(s.section_key)}</code></td>
            <td class="${s.is_active == 1 ? 'badge-active' : 'badge-inactive'}">${s.is_active == 1 ? 'Si' : 'No'}</td>
            <td class="row-actions">
                <button class="btn btn-ghost btn-sm" onclick="openEditModal(${s.id})" title="Editar">&#9998;</button>
                <button class="btn btn-ghost btn-sm" onclick="openDeleteModal(${s.id})" title="Eliminar" style="color:var(--danger)">&#10005;</button>
            </td>
        </tr>
    `).join('');
    initDragAndDrop();
}

// ---- Create / Edit modal ----
function openCreateModal() {
    editingId = null;
    document.getElementById('modal-title').textContent = 'Nueva seccion';
    document.getElementById('section-form').reset();
    document.getElementById('f-key').removeAttribute('readonly');
    document.getElementById('f-active').checked = true;
    document.getElementById('f-type').value = 'survey';
    renderTypeFields();
    document.getElementById('modal-editor').classList.add('open');
    document.getElementById('f-title').focus();
}

function openEditModal(id) {
    const s = sections.find(x => x.id == id);
    if (!s) return;
    editingId = id;
    document.getElementById('modal-title').textContent = 'Editar seccion';
    document.getElementById('f-title').value = s.title;
    document.getElementById('f-key').value = s.section_key;
    document.getElementById('f-key').setAttribute('readonly', true);
    document.getElementById('f-type').value = s.type;
    document.getElementById('f-icon').value = s.icon;
    document.getElementById('f-active').checked = s.is_active == 1;
    renderTypeFields(s.config || {});
    document.getElementById('modal-editor').classList.add('open');
}

function closeModal() {
    document.getElementById('modal-editor').classList.remove('open');
    editingId = null;
}

async function saveSection(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save');
    btn.disabled = true;

    const type = document.getElementById('f-type').value;
    const payload = {
        id: editingId,
        section_key: document.getElementById('f-key').value.trim(),
        title: document.getElementById('f-title').value.trim(),
        type: type,
        icon: document.getElementById('f-icon').value.trim(),
        is_active: document.getElementById('f-active').checked ? 1 : 0,
        config: collectConfig(type)
    };

    try {
        await api('admin_save', payload);
        toast(editingId ? 'Seccion actualizada' : 'Seccion creada');
        closeModal();
        await loadSections();
    } catch (e) {
        toast(e.message, true);
    } finally {
        btn.disabled = false;
    }
}

// ---- Delete ----
function openDeleteModal(id) {
    const s = sections.find(x => x.id == id);
    if (!s) return;
    deletingId = id;
    document.getElementById('delete-name').textContent = s.title;
    document.getElementById('modal-delete').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('modal-delete').classList.remove('open');
    deletingId = null;
}

async function confirmDelete() {
    if (!deletingId) return;
    try {
        await api('admin_delete', { id: deletingId });
        toast('Seccion eliminada');
        closeDeleteModal();
        await loadSections();
    } catch (e) {
        toast(e.message, true);
    }
}

// ---- Drag & Drop reorder ----
function initDragAndDrop() {
    const tbody = document.getElementById('sections-body');
    let dragRow = null;

    tbody.querySelectorAll('tr[draggable]').forEach(row => {
        row.addEventListener('dragstart', e => {
            dragRow = row;
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('dragging');
            tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
            dragRow = null;
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (row !== dragRow) {
                tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
                row.classList.add('drag-over');
            }
        });
        row.addEventListener('drop', e => {
            e.preventDefault();
            if (row === dragRow) return;
            // Insert dragged row before or after target
            const allRows = [...tbody.querySelectorAll('tr')];
            const dragIdx = allRows.indexOf(dragRow);
            const dropIdx = allRows.indexOf(row);
            if (dragIdx < dropIdx) {
                row.after(dragRow);
            } else {
                row.before(dragRow);
            }
            saveOrder();
        });
    });
}

async function saveOrder() {
    const ids = [...document.querySelectorAll('#sections-body tr[data-id]')]
        .map(r => parseInt(r.dataset.id));
    try {
        await api('admin_reorder', { order: ids });
        // Update local sections order
        const idxMap = {};
        ids.forEach((id, i) => idxMap[id] = i);
        sections.sort((a, b) => idxMap[a.id] - idxMap[b.id]);
        toast('Orden guardado');
    } catch (e) {
        toast(e.message, true);
        renderTable(); // revert
    }
}

// ---- Auto-generate key ----
function onTitleInput() {
    if (editingId) return; // don't auto-generate on edit
    const title = document.getElementById('f-title').value;
    document.getElementById('f-key').value = generateKey(title);
}

function generateKey(title) {
    return title
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '')
        .substring(0, 30);
}

// ---- Type-specific fields ----
function renderTypeFields(config) {
    config = config || {};
    const type = document.getElementById('f-type').value;
    const container = document.getElementById('type-fields');

    switch (type) {
        case 'survey':
            renderSurveyFields(container, config);
            break;
        case 'greeting':
            container.innerHTML = `
                <div class="form-group">
                    <label>Contenido *</label>
                    <textarea id="cfg-content" placeholder="Mensaje de bienvenida...">${esc(config.content || '')}</textarea>
                </div>`;
            break;
        case 'wordcloud':
            container.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label>Placeholder</label>
                        <input type="text" id="cfg-placeholder" value="${esc(config.placeholder || 'Escribe una palabra...')}">
                    </div>
                    <div class="form-group">
                        <label>Max palabras por usuario</label>
                        <input type="number" id="cfg-max-words" min="1" max="20" value="${config.max_words || 3}">
                    </div>
                </div>`;
            break;
        case 'reactions':
            renderReactionsFields(container, config);
            break;
        case 'quiz':
            renderQuizFields(container, config);
            break;
        case 'scale':
            container.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimo</label>
                        <input type="number" id="cfg-min" value="${config.min ?? 1}">
                    </div>
                    <div class="form-group">
                        <label>Maximo</label>
                        <input type="number" id="cfg-max" value="${config.max ?? 10}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Etiqueta minimo</label>
                        <input type="text" id="cfg-min-label" value="${esc(config.min_label || '')}">
                    </div>
                    <div class="form-group">
                        <label>Etiqueta maximo</label>
                        <input type="text" id="cfg-max-label" value="${esc(config.max_label || '')}">
                    </div>
                </div>`;
            break;
    }
}

function renderSurveyFields(container, config) {
    const options = config.options || {};
    const keys = Object.keys(options);

    let html = '<label style="font-size:0.8125rem;font-weight:500;margin-bottom:0.25rem;display:block">Opciones *</label>';
    html += '<div class="option-list" id="survey-options">';
    if (keys.length > 0) {
        keys.forEach((k, i) => {
            const opt = options[k];
            html += surveyOptionRow(i + 1, opt.label || '', opt.emoji || '', opt.color || DEFAULT_COLORS[i % DEFAULT_COLORS.length]);
        });
    } else {
        html += surveyOptionRow(1, '', '', DEFAULT_COLORS[0]);
        html += surveyOptionRow(2, '', '', DEFAULT_COLORS[1]);
    }
    html += '</div>';
    html += '<button type="button" class="btn btn-outline btn-sm" style="margin-top:0.5rem" onclick="addSurveyOption()">+ Agregar opcion</button>';
    container.innerHTML = html;
}

function surveyOptionRow(num, label, emoji, color) {
    return `<div class="option-row">
        <span class="option-num">${num}</span>
        <input type="text" placeholder="Texto" value="${esc(label)}" class="opt-label">
        <input type="text" placeholder="Emoji" value="${esc(emoji)}" class="opt-emoji" style="width:50px">
        <input type="color" value="${color}" class="opt-color">
        <button type="button" class="btn-remove-option" onclick="removeOption(this)" title="Quitar">&times;</button>
    </div>`;
}

function addSurveyOption() {
    const list = document.getElementById('survey-options');
    const num = list.children.length + 1;
    const div = document.createElement('div');
    div.innerHTML = surveyOptionRow(num, '', '', DEFAULT_COLORS[(num - 1) % DEFAULT_COLORS.length]);
    list.appendChild(div.firstElementChild);
}

function renderQuizFields(container, config) {
    const options = config.options || {};
    const keys = Object.keys(options);
    const correct = config.correct || 1;
    const timeLimit = config.time_limit || 30;

    let html = `<div class="form-group">
        <label>Tiempo limite (segundos)</label>
        <input type="number" id="cfg-time-limit" min="5" max="300" value="${timeLimit}">
    </div>`;
    html += '<label style="font-size:0.8125rem;font-weight:500;margin-bottom:0.25rem;display:block">Opciones * <span style="color:var(--muted);font-weight:400">(marca la correcta)</span></label>';
    html += '<div class="option-list" id="quiz-options">';
    if (keys.length > 0) {
        keys.forEach((k, i) => {
            const opt = options[k];
            html += quizOptionRow(i + 1, opt.label || '', opt.color || DEFAULT_COLORS[i % DEFAULT_COLORS.length], (i + 1) === correct);
        });
    } else {
        html += quizOptionRow(1, '', DEFAULT_COLORS[0], true);
        html += quizOptionRow(2, '', DEFAULT_COLORS[1], false);
    }
    html += '</div>';
    html += '<button type="button" class="btn btn-outline btn-sm" style="margin-top:0.5rem" onclick="addQuizOption()">+ Agregar opcion</button>';
    container.innerHTML = html;
}

function quizOptionRow(num, label, color, isCorrect) {
    return `<div class="option-row">
        <label class="correct-label" title="Respuesta correcta">
            <input type="radio" name="quiz-correct" value="${num}" ${isCorrect ? 'checked' : ''}>
            <span class="option-num">${num}</span>
        </label>
        <input type="text" placeholder="Texto" value="${esc(label)}" class="opt-label">
        <input type="color" value="${color}" class="opt-color">
        <button type="button" class="btn-remove-option" onclick="removeOption(this)" title="Quitar">&times;</button>
    </div>`;
}

function addQuizOption() {
    const list = document.getElementById('quiz-options');
    const num = list.children.length + 1;
    const div = document.createElement('div');
    div.innerHTML = quizOptionRow(num, '', DEFAULT_COLORS[(num - 1) % DEFAULT_COLORS.length], false);
    list.appendChild(div.firstElementChild);
}

function renderReactionsFields(container, config) {
    const emojis = config.emojis || [];

    let html = '<label style="font-size:0.8125rem;font-weight:500;margin-bottom:0.25rem;display:block">Emojis *</label>';
    html += '<div class="emoji-list" id="emoji-list">';
    emojis.forEach(em => {
        html += `<span class="emoji-tag">${em}<button type="button" onclick="removeEmoji(this)">&times;</button></span>`;
    });
    html += '</div>';
    html += `<div class="add-emoji-row">
        <input type="text" id="new-emoji" placeholder="😀" maxlength="4">
        <button type="button" class="btn btn-outline btn-sm" onclick="addEmoji()">Agregar</button>
    </div>`;
    container.innerHTML = html;

    // Enter key in emoji input
    setTimeout(() => {
        const inp = document.getElementById('new-emoji');
        if (inp) inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addEmoji(); } });
    }, 0);
}

function addEmoji() {
    const inp = document.getElementById('new-emoji');
    const val = inp.value.trim();
    if (!val) return;
    const list = document.getElementById('emoji-list');
    const span = document.createElement('span');
    span.className = 'emoji-tag';
    span.innerHTML = `${val}<button type="button" onclick="removeEmoji(this)">&times;</button>`;
    list.appendChild(span);
    inp.value = '';
    inp.focus();
}

function removeEmoji(btn) {
    btn.parentElement.remove();
}

function removeOption(btn) {
    const row = btn.closest('.option-row');
    const list = row.parentElement;
    row.remove();
    // Re-number
    list.querySelectorAll('.option-row').forEach((r, i) => {
        r.querySelector('.option-num').textContent = i + 1;
        const radio = r.querySelector('input[type="radio"]');
        if (radio) radio.value = i + 1;
    });
}

// ---- Collect config from form ----
function collectConfig(type) {
    switch (type) {
        case 'survey': {
            const opts = {};
            document.querySelectorAll('#survey-options .option-row').forEach((row, i) => {
                opts[String(i + 1)] = {
                    label: row.querySelector('.opt-label').value.trim(),
                    emoji: row.querySelector('.opt-emoji').value.trim(),
                    color: row.querySelector('.opt-color').value
                };
            });
            return { options: opts };
        }
        case 'greeting':
            return { content: document.getElementById('cfg-content').value.trim() };
        case 'wordcloud':
            return {
                placeholder: document.getElementById('cfg-placeholder').value.trim(),
                max_words: parseInt(document.getElementById('cfg-max-words').value) || 3
            };
        case 'reactions': {
            const emojis = [];
            document.querySelectorAll('#emoji-list .emoji-tag').forEach(tag => {
                const text = tag.firstChild.textContent.trim();
                if (text) emojis.push(text);
            });
            return { emojis };
        }
        case 'quiz': {
            const opts = {};
            const correctRadio = document.querySelector('input[name="quiz-correct"]:checked');
            document.querySelectorAll('#quiz-options .option-row').forEach((row, i) => {
                opts[String(i + 1)] = {
                    label: row.querySelector('.opt-label').value.trim(),
                    color: row.querySelector('.opt-color').value
                };
            });
            return {
                time_limit: parseInt(document.getElementById('cfg-time-limit').value) || 30,
                correct: correctRadio ? parseInt(correctRadio.value) : 1,
                options: opts
            };
        }
        case 'scale':
            return {
                min: parseInt(document.getElementById('cfg-min').value) || 1,
                max: parseInt(document.getElementById('cfg-max').value) || 10,
                min_label: document.getElementById('cfg-min-label').value.trim(),
                max_label: document.getElementById('cfg-max-label').value.trim()
            };
    }
    return {};
}

// ---- Helpers ----
function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function toast(msg, isError) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = isError ? 'show error' : 'show';
    clearTimeout(el._t);
    el._t = setTimeout(() => el.className = '', 2500);
}
</script>
</body>
</html>
