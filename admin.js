var API = 'api.php';
var TYPE_LABELS = {
    survey: 'Encuesta', greeting: 'Saludo', wordcloud: 'Nube de palabras',
    reactions: 'Reacciones', quiz: 'Quiz', scale: 'Escala'
};
var DEFAULT_COLORS = ['#ef4444','#22c55e','#3b82f6','#eab308','#8b5cf6','#06b6d4','#f97316','#ec4899'];

var token = sessionStorage.getItem('admin_token');
var sections = [];
var editingId = null;
var deletingId = null;

// ---- Bind all events ----
document.getElementById('btn-login').addEventListener('click', login);
document.getElementById('login-password').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') login();
});
document.getElementById('btn-create').addEventListener('click', openCreateModal);
document.getElementById('btn-logout').addEventListener('click', logout);
document.getElementById('section-form').addEventListener('submit', saveSection);
document.getElementById('f-title').addEventListener('input', onTitleInput);
document.getElementById('f-type').addEventListener('change', function() { renderTypeFields(); });
document.getElementById('btn-cancel-edit').addEventListener('click', closeModal);
document.getElementById('btn-cancel-delete').addEventListener('click', closeDeleteModal);
document.getElementById('btn-confirm-delete').addEventListener('click', confirmDelete);

// Close modals on overlay click
document.getElementById('modal-editor').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.getElementById('modal-delete').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Event delegation for dynamic buttons in table and forms
document.addEventListener('click', function(e) {
    var target = e.target;
    // Edit button
    if (target.closest('.btn-edit')) {
        var id = parseInt(target.closest('.btn-edit').getAttribute('data-id'));
        openEditModal(id);
        return;
    }
    // Delete button
    if (target.closest('.btn-del')) {
        var id = parseInt(target.closest('.btn-del').getAttribute('data-id'));
        openDeleteModal(id);
        return;
    }
    // Remove option
    if (target.closest('.btn-remove-option')) {
        removeOption(target.closest('.btn-remove-option'));
        return;
    }
    // Remove emoji
    if (target.closest('.btn-remove-emoji')) {
        target.closest('.emoji-tag').remove();
        return;
    }
    // Add survey option
    if (target.closest('#btn-add-survey-opt')) {
        addSurveyOption();
        return;
    }
    // Add quiz option
    if (target.closest('#btn-add-quiz-opt')) {
        addQuizOption();
        return;
    }
    // Add emoji
    if (target.closest('#btn-add-emoji')) {
        addEmoji();
        return;
    }
});

// Init
if (token) {
    showDashboard();
}

// ---- API helper ----
function api(action, body) {
    body = body || {};
    return fetch(API, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Admin-Token': token || ''
        },
        body: JSON.stringify(Object.assign({ action: action }, body))
    }).then(function(res) {
        return res.json().then(function(data) {
            if (res.status === 401 && action !== 'admin_login') {
                toast('Sesion expirada', true);
                logout();
                throw new Error('Unauthorized');
            }
            if (!res.ok) throw new Error(data.error || 'Error desconocido');
            return data;
        });
    });
}

// ---- Login / Logout ----
function login() {
    var pw = document.getElementById('login-password').value;
    var errEl = document.getElementById('login-error');
    errEl.textContent = '';
    api('admin_login', { password: pw }).then(function(data) {
        token = data.token;
        sessionStorage.setItem('admin_token', token);
        showDashboard();
    }).catch(function(e) {
        errEl.textContent = e.message;
    });
}

function logout() {
    token = null;
    sessionStorage.removeItem('admin_token');
    document.getElementById('dashboard').style.display = 'none';
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('login-password').value = '';
}

function showDashboard() {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('dashboard').style.display = 'block';
    loadSections();
}

// ---- Sections CRUD ----
function loadSections() {
    api('admin_sections').then(function(data) {
        sections = data.sections;
        renderTable();
    }).catch(function(e) {
        toast(e.message, true);
    });
}

function renderTable() {
    var tbody = document.getElementById('sections-body');
    if (sections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No hay secciones. Crea la primera.</td></tr>';
        return;
    }
    tbody.innerHTML = sections.map(function(s) {
        return '<tr draggable="true" data-id="' + s.id + '">' +
            '<td><span class="drag-handle" title="Arrastrar para reordenar">&#9776;</span></td>' +
            '<td style="font-size:1.25rem">' + esc(s.icon) + '</td>' +
            '<td><strong>' + esc(s.title) + '</strong></td>' +
            '<td><span class="type-badge type-' + s.type + '">' + (TYPE_LABELS[s.type] || s.type) + '</span></td>' +
            '<td><code style="font-size:0.8125rem">' + esc(s.section_key) + '</code></td>' +
            '<td class="' + (s.is_active == 1 ? 'badge-active' : 'badge-inactive') + '">' + (s.is_active == 1 ? 'Si' : 'No') + '</td>' +
            '<td class="row-actions">' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + s.id + '" title="Editar">&#9998;</button>' +
                '<button class="btn btn-ghost btn-sm btn-del" data-id="' + s.id + '" title="Eliminar" style="color:var(--danger)">&#10005;</button>' +
            '</td>' +
        '</tr>';
    }).join('');
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
    var s = null;
    for (var i = 0; i < sections.length; i++) {
        if (sections[i].id == id) { s = sections[i]; break; }
    }
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

function saveSection(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-save');
    btn.disabled = true;

    var type = document.getElementById('f-type').value;
    var payload = {
        id: editingId,
        section_key: document.getElementById('f-key').value.trim(),
        title: document.getElementById('f-title').value.trim(),
        type: type,
        icon: document.getElementById('f-icon').value.trim(),
        is_active: document.getElementById('f-active').checked ? 1 : 0,
        config: collectConfig(type)
    };

    api('admin_save', payload).then(function() {
        toast(editingId ? 'Seccion actualizada' : 'Seccion creada');
        closeModal();
        loadSections();
    }).catch(function(e) {
        toast(e.message, true);
    }).finally(function() {
        btn.disabled = false;
    });
}

// ---- Delete ----
function openDeleteModal(id) {
    var s = null;
    for (var i = 0; i < sections.length; i++) {
        if (sections[i].id == id) { s = sections[i]; break; }
    }
    if (!s) return;
    deletingId = id;
    document.getElementById('delete-name').textContent = s.title;
    document.getElementById('modal-delete').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('modal-delete').classList.remove('open');
    deletingId = null;
}

function confirmDelete() {
    if (!deletingId) return;
    api('admin_delete', { id: deletingId }).then(function() {
        toast('Seccion eliminada');
        closeDeleteModal();
        loadSections();
    }).catch(function(e) {
        toast(e.message, true);
    });
}

// ---- Drag & Drop reorder ----
function initDragAndDrop() {
    var tbody = document.getElementById('sections-body');
    var dragRow = null;

    tbody.querySelectorAll('tr[draggable]').forEach(function(row) {
        row.addEventListener('dragstart', function(e) {
            dragRow = row;
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragend', function() {
            row.classList.remove('dragging');
            tbody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('drag-over'); });
            dragRow = null;
        });
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (row !== dragRow) {
                tbody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('drag-over'); });
                row.classList.add('drag-over');
            }
        });
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            if (row === dragRow) return;
            var allRows = Array.from(tbody.querySelectorAll('tr'));
            var dragIdx = allRows.indexOf(dragRow);
            var dropIdx = allRows.indexOf(row);
            if (dragIdx < dropIdx) {
                row.after(dragRow);
            } else {
                row.before(dragRow);
            }
            saveOrder();
        });
    });
}

function saveOrder() {
    var ids = Array.from(document.querySelectorAll('#sections-body tr[data-id]'))
        .map(function(r) { return parseInt(r.dataset.id); });
    api('admin_reorder', { order: ids }).then(function() {
        var idxMap = {};
        ids.forEach(function(id, i) { idxMap[id] = i; });
        sections.sort(function(a, b) { return idxMap[a.id] - idxMap[b.id]; });
        toast('Orden guardado');
    }).catch(function(e) {
        toast(e.message, true);
        renderTable();
    });
}

// ---- Auto-generate key ----
function onTitleInput() {
    if (editingId) return;
    var title = document.getElementById('f-title').value;
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
    var type = document.getElementById('f-type').value;
    var container = document.getElementById('type-fields');

    switch (type) {
        case 'survey':
            renderSurveyFields(container, config);
            break;
        case 'greeting':
            container.innerHTML =
                '<div class="form-group">' +
                    '<label>Contenido *</label>' +
                    '<textarea id="cfg-content" placeholder="Mensaje de bienvenida...">' + esc(config.content || '') + '</textarea>' +
                '</div>';
            break;
        case 'wordcloud':
            container.innerHTML =
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label>Placeholder</label>' +
                        '<input type="text" id="cfg-placeholder" value="' + esc(config.placeholder || 'Escribe una palabra...') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Max palabras por usuario</label>' +
                        '<input type="number" id="cfg-max-words" min="1" max="20" value="' + (config.max_words || 3) + '">' +
                    '</div>' +
                '</div>';
            break;
        case 'reactions':
            renderReactionsFields(container, config);
            break;
        case 'quiz':
            renderQuizFields(container, config);
            break;
        case 'scale':
            var minVal = config.min != null ? config.min : 1;
            var maxVal = config.max != null ? config.max : 10;
            container.innerHTML =
                '<div class="form-row">' +
                    '<div class="form-group"><label>Minimo</label><input type="number" id="cfg-min" value="' + minVal + '"></div>' +
                    '<div class="form-group"><label>Maximo</label><input type="number" id="cfg-max" value="' + maxVal + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>Etiqueta minimo</label><input type="text" id="cfg-min-label" value="' + esc(config.min_label || '') + '"></div>' +
                    '<div class="form-group"><label>Etiqueta maximo</label><input type="text" id="cfg-max-label" value="' + esc(config.max_label || '') + '"></div>' +
                '</div>';
            break;
    }
}

function renderSurveyFields(container, config) {
    var options = config.options || {};
    var keys = Object.keys(options);
    var html = '<label style="font-size:0.8125rem;font-weight:500;margin-bottom:0.25rem;display:block">Opciones *</label>';
    html += '<div class="option-list" id="survey-options">';
    if (keys.length > 0) {
        keys.forEach(function(k, i) {
            var opt = options[k];
            html += surveyOptionRow(i + 1, opt.label || '', opt.emoji || '', opt.color || DEFAULT_COLORS[i % DEFAULT_COLORS.length]);
        });
    } else {
        html += surveyOptionRow(1, '', '', DEFAULT_COLORS[0]);
        html += surveyOptionRow(2, '', '', DEFAULT_COLORS[1]);
    }
    html += '</div>';
    html += '<button type="button" class="btn btn-outline btn-sm" style="margin-top:0.5rem" id="btn-add-survey-opt">+ Agregar opcion</button>';
    container.innerHTML = html;
}

function surveyOptionRow(num, label, emoji, color) {
    return '<div class="option-row">' +
        '<span class="option-num">' + num + '</span>' +
        '<input type="text" placeholder="Texto" value="' + esc(label) + '" class="opt-label">' +
        '<input type="text" placeholder="Emoji" value="' + esc(emoji) + '" class="opt-emoji" style="width:50px">' +
        '<input type="color" value="' + color + '" class="opt-color">' +
        '<button type="button" class="btn-remove-option" title="Quitar">&times;</button>' +
    '</div>';
}

function addSurveyOption() {
    var list = document.getElementById('survey-options');
    var num = list.children.length + 1;
    var div = document.createElement('div');
    div.innerHTML = surveyOptionRow(num, '', '', DEFAULT_COLORS[(num - 1) % DEFAULT_COLORS.length]);
    list.appendChild(div.firstElementChild);
}

function renderQuizFields(container, config) {
    var options = config.options || {};
    var keys = Object.keys(options);
    var correct = config.correct || 1;
    var timeLimit = config.time_limit || 30;
    var html = '<div class="form-group"><label>Tiempo limite (segundos)</label>' +
        '<input type="number" id="cfg-time-limit" min="5" max="300" value="' + timeLimit + '"></div>';
    html += '<label style="font-size:0.8125rem;font-weight:500;margin-bottom:0.25rem;display:block">Opciones * <span style="color:var(--muted);font-weight:400">(marca la correcta)</span></label>';
    html += '<div class="option-list" id="quiz-options">';
    if (keys.length > 0) {
        keys.forEach(function(k, i) {
            var opt = options[k];
            html += quizOptionRow(i + 1, opt.label || '', opt.color || DEFAULT_COLORS[i % DEFAULT_COLORS.length], (i + 1) === correct);
        });
    } else {
        html += quizOptionRow(1, '', DEFAULT_COLORS[0], true);
        html += quizOptionRow(2, '', DEFAULT_COLORS[1], false);
    }
    html += '</div>';
    html += '<button type="button" class="btn btn-outline btn-sm" style="margin-top:0.5rem" id="btn-add-quiz-opt">+ Agregar opcion</button>';
    container.innerHTML = html;
}

function quizOptionRow(num, label, color, isCorrect) {
    return '<div class="option-row">' +
        '<label class="correct-label" title="Respuesta correcta">' +
            '<input type="radio" name="quiz-correct" value="' + num + '"' + (isCorrect ? ' checked' : '') + '>' +
            '<span class="option-num">' + num + '</span>' +
        '</label>' +
        '<input type="text" placeholder="Texto" value="' + esc(label) + '" class="opt-label">' +
        '<input type="color" value="' + color + '" class="opt-color">' +
        '<button type="button" class="btn-remove-option" title="Quitar">&times;</button>' +
    '</div>';
}

function addQuizOption() {
    var list = document.getElementById('quiz-options');
    var num = list.children.length + 1;
    var div = document.createElement('div');
    div.innerHTML = quizOptionRow(num, '', DEFAULT_COLORS[(num - 1) % DEFAULT_COLORS.length], false);
    list.appendChild(div.firstElementChild);
}

function renderReactionsFields(container, config) {
    var emojis = config.emojis || [];
    var html = '<label style="font-size:0.8125rem;font-weight:500;margin-bottom:0.25rem;display:block">Emojis *</label>';
    html += '<div class="emoji-list" id="emoji-list">';
    emojis.forEach(function(em) {
        html += '<span class="emoji-tag">' + em + '<button type="button" class="btn-remove-emoji">&times;</button></span>';
    });
    html += '</div>';
    html += '<div class="add-emoji-row">' +
        '<input type="text" id="new-emoji" placeholder="\uD83D\uDE00" maxlength="4">' +
        '<button type="button" class="btn btn-outline btn-sm" id="btn-add-emoji">Agregar</button>' +
    '</div>';
    container.innerHTML = html;

    setTimeout(function() {
        var inp = document.getElementById('new-emoji');
        if (inp) inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); addEmoji(); }
        });
    }, 0);
}

function addEmoji() {
    var inp = document.getElementById('new-emoji');
    var val = inp.value.trim();
    if (!val) return;
    var list = document.getElementById('emoji-list');
    var span = document.createElement('span');
    span.className = 'emoji-tag';
    span.innerHTML = val + '<button type="button" class="btn-remove-emoji">&times;</button>';
    list.appendChild(span);
    inp.value = '';
    inp.focus();
}

function removeOption(btn) {
    var row = btn.closest('.option-row');
    var list = row.parentElement;
    row.remove();
    list.querySelectorAll('.option-row').forEach(function(r, i) {
        r.querySelector('.option-num').textContent = i + 1;
        var radio = r.querySelector('input[type="radio"]');
        if (radio) radio.value = i + 1;
    });
}

// ---- Collect config from form ----
function collectConfig(type) {
    var opts, emojis, correctRadio;
    switch (type) {
        case 'survey':
            opts = {};
            document.querySelectorAll('#survey-options .option-row').forEach(function(row, i) {
                opts[String(i + 1)] = {
                    label: row.querySelector('.opt-label').value.trim(),
                    emoji: row.querySelector('.opt-emoji').value.trim(),
                    color: row.querySelector('.opt-color').value
                };
            });
            return { options: opts };
        case 'greeting':
            return { content: document.getElementById('cfg-content').value.trim() };
        case 'wordcloud':
            return {
                placeholder: document.getElementById('cfg-placeholder').value.trim(),
                max_words: parseInt(document.getElementById('cfg-max-words').value) || 3
            };
        case 'reactions':
            emojis = [];
            document.querySelectorAll('#emoji-list .emoji-tag').forEach(function(tag) {
                var text = tag.firstChild.textContent.trim();
                if (text) emojis.push(text);
            });
            return { emojis: emojis };
        case 'quiz':
            opts = {};
            correctRadio = document.querySelector('input[name="quiz-correct"]:checked');
            document.querySelectorAll('#quiz-options .option-row').forEach(function(row, i) {
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
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function toast(msg, isError) {
    var el = document.getElementById('toast');
    el.textContent = msg;
    el.className = isError ? 'show error' : 'show';
    clearTimeout(el._t);
    el._t = setTimeout(function() { el.className = ''; }, 2500);
}
