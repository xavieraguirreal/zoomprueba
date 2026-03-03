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

        .login-card h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .login-card p { color: var(--muted); margin-bottom: 1.5rem; font-size: 0.875rem; }

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

        #login-error { color: var(--danger); font-size: 0.8125rem; min-height: 1.25rem; margin-bottom: 0.25rem; }

        /* ---- Buttons ---- */
        .btn {
            display: inline-flex; align-items: center; gap: 0.375rem;
            padding: 0.5rem 1rem; border: none; border-radius: var(--radius);
            font-size: 0.875rem; font-weight: 500; cursor: pointer;
            transition: background 0.15s, opacity 0.15s;
        }
        .btn:disabled { opacity: 0.5; cursor: default; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover:not(:disabled) { background: var(--danger-hover); }
        .btn-ghost { background: transparent; color: var(--muted); padding: 0.375rem 0.5rem; }
        .btn-ghost:hover { background: var(--bg); color: var(--text); }
        .btn-outline { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .btn-outline:hover { background: var(--bg); }
        .btn-sm { padding: 0.3rem 0.625rem; font-size: 0.8125rem; }
        .btn-block { width: 100%; justify-content: center; }

        /* ---- Dashboard ---- */
        #dashboard { display: none; max-width: 1000px; margin: 0 auto; padding: 1.5rem; }
        .dash-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .dash-header h1 { font-size: 1.375rem; }
        .dash-actions { display: flex; gap: 0.5rem; }

        /* ---- Table ---- */
        .table-wrap { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted);
            border-bottom: 1px solid var(--border); background: #f8fafc;
        }
        tbody td { padding: 0.625rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.875rem; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        .drag-handle { cursor: grab; color: var(--muted); user-select: none; font-size: 1.125rem; padding: 0.25rem; }
        .drag-handle:active { cursor: grabbing; }
        tr.dragging { opacity: 0.4; background: #e0e7ff; }
        tr.drag-over td { border-top: 2px solid var(--primary); }
        .type-badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500; }
        .type-survey     { background: #dbeafe; color: #1e40af; }
        .type-greeting    { background: #fef3c7; color: #92400e; }
        .type-wordcloud   { background: #e0e7ff; color: #3730a3; }
        .type-reactions   { background: #fce7f3; color: #9d174d; }
        .type-quiz        { background: #d1fae5; color: #065f46; }
        .type-scale       { background: #f3e8ff; color: #6b21a8; }
        .badge-active   { color: var(--success); }
        .badge-inactive { color: var(--muted); }
        .row-actions { display: flex; gap: 0.25rem; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted); }

        /* ---- Modal ---- */
        .overlay {
            display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45);
            z-index: 100; align-items: flex-start; justify-content: center;
            padding: 3rem 1rem; overflow-y: auto;
        }
        .overlay.open { display: flex; }
        .modal {
            background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow-lg);
            width: 100%; max-width: 560px; padding: 1.75rem; animation: modalIn 0.15s ease;
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(-12px); } }
        .modal h2 { font-size: 1.125rem; margin-bottom: 1.25rem; }
        .modal-sm { max-width: 400px; }

        /* ---- Form ---- */
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.8125rem; font-weight: 500; margin-bottom: 0.25rem; color: var(--text); }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 0.5rem 0.625rem; border: 1px solid var(--border);
            border-radius: var(--radius); font-size: 0.875rem; font-family: inherit;
            outline: none; transition: border-color 0.15s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input[readonly] { background: #f8fafc; color: var(--muted); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; }

        /* ---- Dynamic option list ---- */
        .option-list { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
        .option-row {
            display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem;
            background: #f8fafc; border-radius: var(--radius); border: 1px solid var(--border);
        }
        .option-row input[type="text"] { flex: 1; padding: 0.375rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.8125rem; }
        .option-row input[type="color"] { width: 34px; height: 30px; padding: 2px; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; }
        .option-num { font-size: 0.75rem; font-weight: 600; color: var(--muted); width: 1.25rem; text-align: center; }
        .btn-remove-option { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1.125rem; padding: 0 0.25rem; line-height: 1; }
        .btn-remove-option:hover { opacity: 0.7; }

        /* ---- Emoji dropdown for survey/quiz options ---- */
        .opt-emoji-wrap { position: relative; }
        .opt-emoji-btn { width: 42px; height: 34px; border: 1px solid var(--border); border-radius: 4px; background: #fff; font-size: 1.125rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: border-color 0.15s; }
        .opt-emoji-btn:hover { border-color: var(--primary); }
        .opt-emoji-dropdown { display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); z-index: 100; background: #fff; border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 0.5rem; margin-top: 4px; width: 240px; max-height: 200px; overflow-y: auto; }
        .opt-emoji-dropdown.open { display: block; }
        .mini-emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 2px; }
        .mini-emoji-opt { background: none; border: 1px solid transparent; border-radius: 4px; font-size: 1.1rem; cursor: pointer; padding: 3px; line-height: 1; text-align: center; transition: background 0.12s, transform 0.1s; }
        .mini-emoji-opt:hover { background: #e0e7ff; border-color: var(--primary); transform: scale(1.2); }

        /* ---- Emoji list ---- */
        .emoji-list { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.5rem; min-height: 2rem; }
        .emoji-tag { display: inline-flex; align-items: center; gap: 0.25rem; background: #eff6ff; border: 1px solid var(--primary); border-radius: 999px; padding: 0.25rem 0.5rem; font-size: 1.125rem; }
        .emoji-tag button { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 0.75rem; line-height: 1; }

        /* ---- Emoji picker ---- */
        .emoji-picker-wrap { background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.625rem; margin-top: 0.25rem; max-height: 220px; overflow-y: auto; }
        .emoji-picker-cat { font-size: 0.6875rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0.375rem 0 0.25rem; }
        .emoji-picker-cat:first-child { margin-top: 0; }
        .emoji-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); gap: 2px; }
        .emoji-pick-btn { background: none; border: 1px solid transparent; border-radius: 6px; font-size: 1.25rem; cursor: pointer; padding: 4px; line-height: 1; text-align: center; transition: background 0.15s, border-color 0.15s, transform 0.1s; }
        .emoji-pick-btn:hover { background: #e0e7ff; border-color: var(--primary); transform: scale(1.15); }

        /* ---- Toast ---- */
        #toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; background: var(--text); color: #fff;
            padding: 0.625rem 1.25rem; border-radius: var(--radius); font-size: 0.875rem;
            box-shadow: var(--shadow-lg); opacity: 0; transform: translateY(8px);
            transition: all 0.2s; z-index: 200; pointer-events: none;
        }
        #toast.show { opacity: 1; transform: translateY(0); }
        #toast.error { background: var(--danger); }

        /* ---- Toggle ---- */
        .toggle { position: relative; width: 36px; height: 20px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle .slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 999px; cursor: pointer; transition: background 0.2s; }
        .toggle .slider::before { content: ''; position: absolute; left: 2px; top: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform 0.2s; }
        .toggle input:checked + .slider { background: var(--success); }
        .toggle input:checked + .slider::before { transform: translateX(16px); }

        .correct-label { display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: var(--muted); cursor: pointer; white-space: nowrap; }
        .correct-label input[type="radio"] { accent-color: var(--success); }

        /* ---- Toggle inline (table) ---- */
        .toggle-inline { cursor: pointer; }

        /* ---- Stats modal ---- */
        .stats-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.25rem; }
        .stats-card { background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.75rem; text-align: center; }
        .stats-num { display: block; font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stats-label { display: block; font-size: 0.75rem; color: var(--muted); margin-top: 0.125rem; }
        .stats-chart { display: flex; flex-direction: column; gap: 0.375rem; max-height: 350px; overflow-y: auto; }
        .stats-row { display: grid; grid-template-columns: 30px 1fr 120px 40px; align-items: center; gap: 0.5rem; padding: 0.375rem 0.5rem; font-size: 0.8125rem; }
        .stats-row:nth-child(odd) { background: #f8fafc; border-radius: 4px; }
        .stats-pos { color: var(--muted); font-weight: 600; text-align: center; }
        .stats-word { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .stats-bar-wrap { background: #e2e8f0; border-radius: 4px; height: 18px; overflow: hidden; }
        .stats-bar { height: 100%; background: var(--primary); border-radius: 4px; transition: width 0.4s ease; }
        .stats-count { text-align: right; font-weight: 700; color: var(--muted); }
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
        <button class="btn btn-primary btn-block" id="btn-login">Ingresar</button>
    </div>
</div>

<!-- ======== Dashboard ======== -->
<div id="dashboard">
    <div class="dash-header">
        <h1>Secciones</h1>
        <div class="dash-actions">
            <button class="btn btn-primary" id="btn-create">+ Nueva seccion</button>
            <button class="btn btn-ghost" id="btn-logout">Cerrar sesion</button>
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
<div id="modal-editor" class="overlay">
    <div class="modal">
        <h2 id="modal-title">Nueva seccion</h2>
        <form id="section-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="f-title">Titulo *</label>
                    <input type="text" id="f-title" required>
                </div>
                <div class="form-group">
                    <label for="f-key">Clave *</label>
                    <input type="text" id="f-key" pattern="[a-z0-9_]+" title="Solo letras minusculas, numeros y _" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="f-type">Tipo *</label>
                    <select id="f-type">
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

            <div id="type-fields"></div>

            <div class="form-actions">
                <button type="button" class="btn btn-outline" id="btn-cancel-edit">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ======== Delete Confirmation ======== -->
<div id="modal-delete" class="overlay">
    <div class="modal modal-sm" style="text-align:center">
        <h2>Eliminar seccion</h2>
        <p style="margin:1rem 0;color:var(--muted)">Se eliminara permanentemente:<br><strong id="delete-name"></strong></p>
        <div class="form-actions" style="justify-content:center">
            <button class="btn btn-outline" id="btn-cancel-delete">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirm-delete">Eliminar</button>
        </div>
    </div>
</div>

<!-- ======== Stats Modal ======== -->
<div id="modal-stats" class="overlay">
    <div class="modal">
        <h2>Estadisticas - Nube de palabras</h2>
        <div id="stats-content"></div>
        <div class="form-actions">
            <button class="btn btn-outline" id="btn-close-stats">Cerrar</button>
            <button class="btn btn-primary" id="btn-share-results" data-key="">Compartir resultados</button>
        </div>
    </div>
</div>

<!-- ======== Toast ======== -->
<div id="toast"></div>

<script src="admin.js?v=3"></script>
</body>
</html>
