/**
 * Zoom App - Multi-seccion con control del host
 *
 * Flujo:
 * - Al cargar, consulta seccion activa del meeting
 * - Si NO hay seccion activa → es host → muestra menu
 * - Si HAY seccion activa → es participante → muestra seccion directo
 * - Host mantiene isHost=true durante toda la sesion
 */

(function () {
    'use strict';

    // ---- Estado ----
    const state = {
        meetingId: 'standalone',
        userId: 'user_' + Math.random().toString(36).substring(2, 10),
        userName: null,
        isInZoom: false,
        isHost: false,
        currentSection: null,
        selectedAnswer: null,
        sections: window.APP_SECTIONS || {},
    };

    // ---- Elementos DOM fijos ----
    const $ = (id) => document.getElementById(id);
    const $loadingScreen = $('loading-screen');
    const $menuScreen = $('menu-screen');
    const $surveyScreen = $('survey-screen');
    const $resultsScreen = $('results-screen');
    const $greetingScreen = $('greeting-screen');
    const $statusBar = $('status-bar');
    const $statusText = $('status-text');

    // ---- Helpers ----

    function hideAllScreens() {
        [$loadingScreen, $menuScreen, $surveyScreen, $resultsScreen, $greetingScreen].forEach(
            (s) => s.classList.add('hidden')
        );
    }

    function showScreen(screen) {
        hideAllScreens();
        screen.classList.remove('hidden');
    }

    function setStatus(text, type) {
        $statusText.textContent = text;
        $statusBar.className = 'status-bar ' + (type || '');
    }

    // ---- Zoom SDK ----

    async function initZoomSdk() {
        if (typeof zoomSdk === 'undefined') {
            setStatus('Modo standalone (fuera de Zoom)', 'connected');
            return;
        }

        try {
            await zoomSdk.config({
                capabilities: ['getMeetingContext'],
            });

            state.isInZoom = true;
            setStatus('Conectado a Zoom', 'connected');

            try {
                const context = await zoomSdk.getMeetingContext();
                if (context.meetingID) {
                    state.meetingId = context.meetingID;
                }
            } catch (e) {
                console.log('No meeting context available:', e.message);
            }
        } catch (err) {
            setStatus('Zoom SDK no disponible - modo standalone', 'error');
            console.warn('Zoom SDK init failed:', err);
        }
    }

    // ---- Determinar rol (host vs participante) ----

    async function determineRole() {
        try {
            const res = await fetch(
                'api.php?action=get_active&meeting_id=' + encodeURIComponent(state.meetingId)
            );
            const data = await res.json();

            if (data.active) {
                // Hay seccion activa → participante
                state.isHost = false;
                state.currentSection = data.section_id;
                navigateToSection(data.section_id, data.status);
            } else {
                // No hay seccion activa → host
                state.isHost = true;
                showMenu();
            }
        } catch (err) {
            console.error('Error determining role:', err);
            // Fallback: mostrar como host
            state.isHost = true;
            showMenu();
        }
    }

    // ---- Menu de secciones ----

    function showMenu() {
        const grid = $('menu-grid');
        grid.innerHTML = '';

        var keys = Object.keys(state.sections);
        // Debug visible
        setStatus('Secciones encontradas: ' + keys.length + ' | keys: ' + keys.join(', '), 'connected');

        if (keys.length === 0) {
            grid.innerHTML = '<p style="color:#ef4444;text-align:center;">No se encontraron secciones. APP_SECTIONS=' + typeof window.APP_SECTIONS + '</p>';
            showScreen($menuScreen);
            return;
        }

        for (var i = 0; i < keys.length; i++) {
            var id = keys[i];
            var section = state.sections[id];
            var btn = document.createElement('button');
            btn.className = 'section-btn';
            btn.setAttribute('data-section-id', id);
            btn.innerHTML =
                '<span class="section-icon">' + (section.icon || '') + '</span>' +
                '<span class="section-title">' + escapeHtml(section.title) + '</span>' +
                '<span class="section-type">' + typeLabel(section.type) + '</span>';
            btn.addEventListener('click', (function(sectionId) {
                return function() { selectSection(sectionId); };
            })(id));
            grid.appendChild(btn);
        }

        showScreen($menuScreen);
    }

    function typeLabel(type) {
        switch (type) {
            case 'survey': return 'Encuesta';
            case 'greeting': return 'Saludo';
            default: return type;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ---- Seleccionar seccion (host) ----

    async function selectSection(sectionId) {
        const section = state.sections[sectionId];
        if (!section) return;

        state.currentSection = sectionId;
        state.selectedAnswer = null;

        // Guardar como seccion activa en el server
        try {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'set_active',
                    meeting_id: state.meetingId,
                    section_id: sectionId,
                }),
            });
        } catch (err) {
            console.error('Error setting active section:', err);
        }

        navigateToSection(sectionId, 'voting');
    }

    // ---- Navegar a una seccion ----

    function navigateToSection(sectionId, status) {
        const section = state.sections[sectionId];
        if (!section) return;

        state.currentSection = sectionId;

        switch (section.type) {
            case 'survey':
                if (status === 'closed') {
                    loadResults(sectionId);
                } else {
                    renderSurvey(section, sectionId);
                }
                break;
            case 'greeting':
                renderGreeting(section);
                break;
            default:
                renderGreeting(section);
                break;
        }

        // Iniciar polling para participantes
        if (!state.isHost) {
            startPolling();
        }
    }

    // ---- Renderizar encuesta ----

    function renderSurvey(section, sectionId) {
        $('survey-question').textContent = section.title;

        const optionsContainer = $('survey-options');
        optionsContainer.innerHTML = '';

        for (const [value, opt] of Object.entries(section.options)) {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            if (parseInt(value) === state.selectedAnswer) {
                btn.classList.add('selected');
            }
            btn.dataset.answer = value;
            btn.style.setProperty('--btn-color', opt.color);
            btn.innerHTML =
                '<span class="option-emoji">' + opt.emoji + '</span>' +
                '<span class="option-label">' + escapeHtml(opt.label) + '</span>';
            btn.addEventListener('click', () => submitVote(parseInt(value)));
            optionsContainer.appendChild(btn);
        }

        // Host controls
        const hostControls = $('host-controls');
        if (state.isHost) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        showScreen($surveyScreen);
    }

    // ---- Renderizar saludo ----

    function renderGreeting(section) {
        $('greeting-icon').textContent = section.icon || '';
        $('greeting-title').textContent = section.title;
        $('greeting-text').textContent = section.content || '';

        // Host controls
        const hostControls = $('host-controls-greeting');
        if (state.isHost) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        showScreen($greetingScreen);
    }

    // ---- Votar ----

    async function submitVote(answer) {
        const buttons = $('survey-options').querySelectorAll('.option-btn');
        buttons.forEach((btn) => btn.classList.add('loading'));

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'vote',
                    meeting_id: state.meetingId,
                    section_id: state.currentSection,
                    user_id: state.userId,
                    user_name: state.userName,
                    answer: answer,
                }),
            });

            const data = await response.json();

            if (data.success) {
                state.selectedAnswer = answer;
                showResults(data.results);
            } else {
                alert('Error: ' + (data.error || 'desconocido'));
            }
        } catch (err) {
            console.error('Error submitting vote:', err);
            alert('Error de conexion. Intenta de nuevo.');
        } finally {
            buttons.forEach((btn) => btn.classList.remove('loading'));
        }
    }

    // ---- Resultados ----

    function showResults(results) {
        const section = state.sections[state.currentSection];
        if (!section || section.type !== 'survey') return;

        $('results-title').textContent = 'Resultados: ' + section.title;

        const chart = $('results-chart');
        chart.innerHTML = '';

        const total = results.total || 0;
        $('total-votes').textContent = total;

        for (const [value, opt] of Object.entries(section.options)) {
            const count = results.answers[value] || 0;
            const percent = total > 0 ? (count / total) * 100 : 0;

            const row = document.createElement('div');
            row.className = 'result-row';
            row.dataset.answer = value;
            row.innerHTML =
                '<div class="result-label">' +
                    '<span>' + opt.emoji + '</span>' +
                    '<span>' + escapeHtml(opt.label) + '</span>' +
                '</div>' +
                '<div class="result-bar-container">' +
                    '<div class="result-bar" style="--bar-color: ' + opt.color + '; width: ' + percent + '%"></div>' +
                '</div>' +
                '<span class="result-count">' + count + '</span>';
            chart.appendChild(row);
        }

        // Host controls
        const hostControls = $('host-controls-results');
        if (state.isHost) {
            hostControls.classList.remove('hidden');
            // Ocultar "cambiar respuesta" para el host en modo cerrado
            $('btn-change').classList.add('hidden');
        } else {
            hostControls.classList.add('hidden');
            $('btn-change').classList.remove('hidden');
        }

        showScreen($resultsScreen);
    }

    async function loadResults(sectionId) {
        try {
            const res = await fetch(
                'api.php?action=results&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&section_id=' + encodeURIComponent(sectionId)
            );
            const data = await res.json();
            showResults(data.results);
        } catch (err) {
            console.error('Error loading results:', err);
        }
    }

    // ---- Host: cerrar encuesta ----

    async function closeSurvey() {
        try {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'close',
                    meeting_id: state.meetingId,
                }),
            });
            // Cargar y mostrar resultados
            await loadResults(state.currentSection);
        } catch (err) {
            console.error('Error closing survey:', err);
        }
    }

    // ---- Host: reabrir encuesta ----

    async function reopenSurvey() {
        try {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reopen',
                    meeting_id: state.meetingId,
                }),
            });
            // Volver a la pantalla de encuesta
            const section = state.sections[state.currentSection];
            if (section) {
                renderSurvey(section, state.currentSection);
            }
        } catch (err) {
            console.error('Error reopening survey:', err);
        }
    }

    // ---- Host: volver al menu ----

    function backToMenu() {
        stopPolling();
        state.currentSection = null;
        state.selectedAnswer = null;
        showMenu();
    }

    // ---- Participante: polling cada 5 seg ----

    let pollInterval = null;

    function startPolling() {
        stopPolling();
        pollInterval = setInterval(pollStatus, 5000);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    async function pollStatus() {
        try {
            const res = await fetch(
                'api.php?action=poll&meeting_id=' + encodeURIComponent(state.meetingId)
            );
            const data = await res.json();

            if (!data.active) return;

            // Si cambio la seccion activa, navegar
            if (data.section_id !== state.currentSection) {
                state.currentSection = data.section_id;
                navigateToSection(data.section_id, data.status);
                return;
            }

            // Si la encuesta se cerro, mostrar resultados
            if (data.status === 'closed' && data.results) {
                showResults(data.results);
                return;
            }

            // Si la encuesta se reabrio, volver a encuesta
            if (data.status === 'voting') {
                const section = state.sections[state.currentSection];
                if (section && section.type === 'survey' && $resultsScreen && !$resultsScreen.classList.contains('hidden')) {
                    renderSurvey(section, state.currentSection);
                }
            }
        } catch (err) {
            console.error('Poll error:', err);
        }
    }

    // ---- Auto-refresh de resultados (host) ----

    let refreshInterval = null;

    function startAutoRefresh() {
        refreshInterval = setInterval(function () {
            if (!$resultsScreen.classList.contains('hidden') && state.currentSection) {
                loadResults(state.currentSection);
            }
        }, 10000);
    }

    // ---- Event Listeners ----

    // Host: cerrar encuesta
    $('btn-close-survey').addEventListener('click', closeSurvey);

    // Host: reabrir encuesta
    $('btn-reopen-survey').addEventListener('click', reopenSurvey);

    // Host: volver al menu (desde todas las pantallas)
    $('btn-back-menu').addEventListener('click', backToMenu);
    $('btn-back-menu-results').addEventListener('click', backToMenu);
    $('btn-back-menu-greeting').addEventListener('click', backToMenu);

    // Participante: cambiar respuesta
    $('btn-change').addEventListener('click', function () {
        const section = state.sections[state.currentSection];
        if (section) {
            renderSurvey(section, state.currentSection);
        }
    });

    // ---- Init ----

    async function init() {
        await initZoomSdk();
        await determineRole();
        startAutoRefresh();
    }

    init();
})();
