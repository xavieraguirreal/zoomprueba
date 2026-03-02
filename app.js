/**
 * Zoom App - Multi-seccion con control del host
 *
 * Flujo:
 * - Al cargar, consulta seccion activa del meeting
 * - Si NO hay seccion activa → es host → muestra menu
 * - Si HAY seccion activa → es participante → muestra seccion directo
 * - Host mantiene isHost=true durante toda la sesion
 *
 * Tipos soportados: survey, greeting, wordcloud, reactions, quiz, scale
 */

(function () {
    'use strict';

    // ---- Estado ----
    const state = {
        meetingId: 'broadcast',
        userId: 'user_' + Math.random().toString(36).substring(2, 10),
        userName: null,
        isInZoom: false,
        isHost: false,
        currentSection: null,
        selectedAnswer: null,
        sections: {},
        quizAnswered: false,
        wordcloudCount: 0,
        reactionLastFetch: null,
    };

    // ---- Estado de estilos de reacciones ----
    var reactionStyle = 'explosive';
    var reactionCounts = {};
    var reactionTotal = 0;
    var matrixInterval = null;
    var energyGoal = 100;
    var lastEnergyMilestone = 0;

    // ---- Elementos DOM fijos ----
    const $ = (id) => document.getElementById(id);
    const $loadingScreen = $('loading-screen');
    const $idleScreen = $('idle-screen');
    const $menuScreen = $('menu-screen');
    const $surveyScreen = $('survey-screen');
    const $resultsScreen = $('results-screen');
    const $greetingScreen = $('greeting-screen');
    const $wordcloudScreen = $('wordcloud-screen');
    const $reactionsScreen = $('reactions-screen');
    const $quizScreen = $('quiz-screen');
    const $quizLeaderboardScreen = $('quiz-leaderboard-screen');
    const $scaleScreen = $('scale-screen');
    const $scaleResultsScreen = $('scale-results-screen');
    const $wordcloudResultsScreen = $('wordcloud-results-screen');
    const $statusBar = $('status-bar');
    const $statusText = $('status-text');

    const allScreens = [
        $loadingScreen, $idleScreen, $menuScreen, $surveyScreen, $resultsScreen, $greetingScreen,
        $wordcloudScreen, $reactionsScreen, $quizScreen, $quizLeaderboardScreen,
        $scaleScreen, $scaleResultsScreen, $wordcloudResultsScreen,
    ];

    // ---- Helpers ----

    function hideAllScreens() {
        allScreens.forEach(function (s) { if (s) s.classList.add('hidden'); });
    }

    function showScreen(screen) {
        hideAllScreens();
        if (screen) screen.classList.remove('hidden');
    }

    function setStatus(text, type) {
        $statusText.textContent = text;
        $statusBar.className = 'status-bar ' + (type || '');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
                var context = await zoomSdk.getMeetingContext();
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
            var res = await fetch(
                'api.php?action=get_active&meeting_id=' + encodeURIComponent(state.meetingId)
            );
            var data = await res.json();

            if (data.active) {
                state.isHost = false;
                state.currentSection = data.section_id;
                navigateToSection(data.section_id, data.status, data);
            } else if (state.isInZoom) {
                // Inside Zoom: host sees the menu
                state.isHost = true;
                showMenu();
            } else {
                // Standalone (browser): show idle/waiting screen + poll for activity
                state.isHost = false;
                showScreen($idleScreen);
                startIdlePolling();
            }
        } catch (err) {
            console.error('Error determining role:', err);
            if (state.isInZoom) {
                state.isHost = true;
                showMenu();
            } else {
                showScreen($idleScreen);
                startIdlePolling();
            }
        }
    }

    // ---- Menu de secciones ----

    function showMenu() {
        var grid = $('menu-grid');
        grid.innerHTML = '';

        var keys = Object.keys(state.sections);
        setStatus('Secciones encontradas: ' + keys.length + ' | keys: ' + keys.join(', '), 'connected');

        if (keys.length === 0) {
            grid.innerHTML = '<p style="color:#ef4444;text-align:center;">No se encontraron secciones.</p>';
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
            btn.addEventListener('click', (function (sectionId) {
                return function () { selectSection(sectionId); };
            })(id));
            grid.appendChild(btn);
        }

        showScreen($menuScreen);
    }

    function typeLabel(type) {
        switch (type) {
            case 'survey': return 'Encuesta';
            case 'greeting': return 'Saludo';
            case 'wordcloud': return 'Nube';
            case 'reactions': return 'Reacciones';
            case 'quiz': return 'Quiz';
            case 'scale': return 'Escala';
            default: return type;
        }
    }

    // ---- Seleccionar seccion (host) ----

    async function selectSection(sectionId) {
        var section = state.sections[sectionId];
        if (!section) return;

        state.currentSection = sectionId;
        state.selectedAnswer = null;
        state.quizAnswered = false;
        state.wordcloudCount = 0;

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

    function navigateToSection(sectionId, status, pollData) {
        var section = state.sections[sectionId];
        if (!section) return;

        state.currentSection = sectionId;
        stopQuizTimer();

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
            case 'wordcloud':
                if (status === 'closed' && !state.isHost) {
                    loadWordCloudResults(sectionId);
                } else {
                    renderWordCloud(section, sectionId, status);
                }
                break;
            case 'reactions':
                renderReactions(section);
                break;
            case 'quiz':
                renderQuiz(section, sectionId, status, pollData);
                break;
            case 'scale':
                if (status === 'closed') {
                    loadScaleResults(sectionId);
                } else {
                    renderScale(section, sectionId);
                }
                break;
            default:
                renderGreeting(section);
                break;
        }

        if (!state.isHost) {
            startPolling();
        }
    }

    // ---- Renderizar encuesta ----

    function renderSurvey(section, sectionId) {
        $('survey-question').textContent = section.title;

        var optionsContainer = $('survey-options');
        optionsContainer.innerHTML = '';

        for (var key in section.options) {
            var opt = section.options[key];
            var value = parseInt(key);
            var btn = document.createElement('button');
            btn.className = 'option-btn';
            if (value === state.selectedAnswer) {
                btn.classList.add('selected');
            }
            btn.dataset.answer = key;
            btn.style.setProperty('--btn-color', opt.color);
            btn.innerHTML =
                '<span class="option-emoji">' + (opt.emoji || '') + '</span>' +
                '<span class="option-label">' + escapeHtml(opt.label) + '</span>';
            btn.addEventListener('click', (function (v) {
                return function () { submitVote(v); };
            })(value));
            optionsContainer.appendChild(btn);
        }

        var hostControls = $('host-controls');
        if (state.isHost && state.isInZoom) {
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

        var hostControls = $('host-controls-greeting');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        showScreen($greetingScreen);
    }

    // ============================================
    // WORD CLOUD
    // ============================================

    function renderWordCloud(section, sectionId, status) {
        $('wordcloud-title').textContent = section.title;
        $('wordcloud-input').placeholder = section.placeholder || 'Escribe una palabra...';
        $('wordcloud-input').value = '';

        var hostControls = $('host-controls-wordcloud');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        var inputArea = $('wordcloud-input-area');
        var closeBtn = $('btn-close-wordcloud');
        var reopenBtn = $('btn-reopen-wordcloud');

        if (status === 'closed') {
            inputArea.classList.add('hidden');
            if (closeBtn) closeBtn.classList.add('hidden');
            if (reopenBtn) reopenBtn.classList.remove('hidden');
        } else {
            inputArea.classList.remove('hidden');
            if (closeBtn) closeBtn.classList.remove('hidden');
            if (reopenBtn) reopenBtn.classList.add('hidden');
        }

        // Remove closed badge if present
        var oldBadge = document.querySelector('.wordcloud-closed-badge');
        if (oldBadge) oldBadge.remove();

        $('wordcloud-remaining').textContent = '';
        $('wordcloud-cloud').innerHTML = '<p style="color:#64748b;text-align:center;">Cargando...</p>';
        wordcloudLastHash = ''; // reset to force render

        showScreen($wordcloudScreen);
        loadWordCloud(sectionId);
    }

    async function loadWordCloud(sectionId) {
        try {
            var res = await fetch(
                'api.php?action=get_words&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&section_id=' + encodeURIComponent(sectionId)
            );
            var data = await res.json();
            renderWordCloudWords(data.words || []);
        } catch (err) {
            console.error('Error loading wordcloud:', err);
        }
    }

    var wordcloudColors = ['#3b82f6', '#ef4444', '#22c55e', '#eab308', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#14b8a6', '#f43f5e'];
    var rotationPool = [0, 0, 0, 90, 0, -8, 0, 5, 0, 0, -5, 90, 0, 8, 0];
    var wordcloudLastHash = ''; // to detect changes
    var wordcloudPrevWords = {}; // track previous words for new-word detection
    var wordcloudLastChangeTime = 0;
    var wordcloudRecentChanges = 0; // how many changes in recent window

    function wordcloudHash(words) {
        var h = '';
        for (var i = 0; i < words.length; i++) {
            h += words[i].word + ':' + words[i].count + ',';
        }
        return h;
    }

    var wordcloudForceRender = false; // set true on submit to force re-layout

    function renderWordCloudWords(words) {
        // On polling: skip if nothing changed. On submit: always re-render.
        var hash = wordcloudHash(words);
        if (hash === wordcloudLastHash && !wordcloudForceRender) return;
        wordcloudForceRender = false;

        // Count how many new words vs previous render
        var newCount = 0;
        var currentWords = {};
        for (var n = 0; n < words.length; n++) {
            currentWords[words[n].word] = parseInt(words[n].count);
            if (!wordcloudPrevWords[words[n].word]) newCount++;
        }

        // Track activity speed
        var now = Date.now();
        if (now - wordcloudLastChangeTime < 5000) {
            wordcloudRecentChanges += newCount;
        } else {
            wordcloudRecentChanges = newCount;
        }
        wordcloudLastChangeTime = now;

        wordcloudPrevWords = currentWords;
        wordcloudLastHash = hash;

        // Update word count
        var totalWords = 0;
        for (var tw = 0; tw < words.length; tw++) totalWords += parseInt(words[tw].count);
        var countEl = $('wordcloud-count');
        if (countEl) countEl.textContent = totalWords + ' palabra' + (totalWords !== 1 ? 's' : '');

        var cloud = $('wordcloud-cloud');
        cloud.innerHTML = '';

        if (words.length === 0) {
            cloud.innerHTML = '<p style="color:#64748b;text-align:center;position:relative;">Aun no hay palabras</p>';
            return;
        }

        var maxCount = 1;
        for (var i = 0; i < words.length; i++) {
            if (parseInt(words[i].count) > maxCount) maxCount = parseInt(words[i].count);
        }

        var cloudWidth = cloud.offsetWidth || 350;
        var cloudHeight = 310; // fixed

        // Sort by count descending
        var sorted = words.slice().sort(function (a, b) { return b.count - a.count; });

        // Auto-scale: gentle shrink when many words
        var scale = 1;
        if (sorted.length > 12) scale = 0.9;
        if (sorted.length > 20) scale = 0.8;
        if (sorted.length > 30) scale = 0.7;

        // Animation speed
        var baseSpeed = wordcloudRecentChanges > 3 ? 0.4 : wordcloudRecentChanges > 1 ? 0.7 : 1.2;
        var stagger = wordcloudRecentChanges > 3 ? 0.05 : 0.1;

        var placed = [];

        for (var j = 0; j < sorted.length; j++) {
            var w = sorted[j];
            var ratio = parseInt(w.count) / maxCount;
            var fontSize = Math.max(0.75, (0.9 + ratio * 2.2) * scale);
            var color = wordcloudColors[j % wordcloudColors.length];

            // Pick rotation: first word always 0
            var angle = j === 0 ? 0 : rotationPool[j % rotationPool.length];
            var angleRad = angle * Math.PI / 180;

            // Measure using a hidden element for accuracy
            var measure = document.createElement('span');
            measure.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;font-weight:700;font-size:' + fontSize + 'rem;font-family:inherit;';
            measure.textContent = w.word;
            document.body.appendChild(measure);
            var measW = measure.offsetWidth + 10;
            var measH = measure.offsetHeight + 6;
            document.body.removeChild(measure);

            // Bounding box for rotated element
            var bboxW = Math.abs(measW * Math.cos(angleRad)) + Math.abs(measH * Math.sin(angleRad));
            var bboxH = Math.abs(measW * Math.sin(angleRad)) + Math.abs(measH * Math.cos(angleRad));

            var span = document.createElement('span');
            span.className = 'wordcloud-word' + (j === 0 ? ' wordcloud-word-top' : '');
            span.setAttribute('data-word', w.word);
            span.textContent = w.word;
            span.style.fontSize = fontSize + 'rem';
            span.style.color = color;
            span.style.setProperty('--rotate', angle + 'deg');
            span.style.animationDuration = baseSpeed + 's';
            span.style.animationDelay = (j * stagger) + 's';

            var pos = placeWord(placed, cloudWidth, cloudHeight, bboxW, bboxH);
            // Adjust left/top to center the actual element within the bounding box
            span.style.left = (pos.x + (bboxW - measW) / 2) + 'px';
            span.style.top = (pos.y + (bboxH - measH) / 2) + 'px';

            placed.push({ x: pos.x, y: pos.y, w: bboxW, h: bboxH });
            cloud.appendChild(span);
        }
    }

    function placeWord(placed, cloudW, cloudH, estW, estH) {
        var margin = 8;
        var cx = (cloudW - estW) / 2;
        var cy = (cloudH - estH) / 2;
        var angle = Math.random() * Math.PI * 2;
        var radius = 0;

        for (var i = 0; i < 500; i++) {
            var x = cx + Math.cos(angle) * radius;
            var y = cy + Math.sin(angle) * radius;

            if (x >= margin && y >= margin && x + estW <= cloudW - margin && y + estH <= cloudH - margin) {
                if (!hasCollision(placed, x, y, estW, estH)) {
                    return { x: x, y: y };
                }
            }

            angle += 0.5;
            radius += 0.8;
        }

        // Fallback: find any gap
        for (var a = 0; a < 80; a++) {
            var fx = Math.random() * Math.max(1, cloudW - estW - margin * 2) + margin;
            var fy = Math.random() * Math.max(1, cloudH - estH - margin * 2) + margin;
            if (!hasCollision(placed, fx, fy, estW, estH)) {
                return { x: fx, y: fy };
            }
        }

        return { x: margin, y: Math.min(cloudH - estH - margin, placed.length * 25 + margin) };
    }

    function hasCollision(placed, x, y, w, h) {
        var pad = 8;
        for (var p = 0; p < placed.length; p++) {
            var r = placed[p];
            if (x < r.x + r.w + pad &&
                x + w + pad > r.x &&
                y < r.y + r.h + pad &&
                y + h + pad > r.y) {
                return true;
            }
        }
        return false;
    }

    async function submitWord() {
        var input = $('wordcloud-input');
        var word = input.value.trim();
        if (!word) return;

        var section = state.sections[state.currentSection];
        var maxWords = (section && section.max_words) ? section.max_words : 3;

        try {
            var response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'submit_word',
                    meeting_id: state.meetingId,
                    section_id: state.currentSection,
                    user_id: state.userId,
                    word: word,
                }),
            });

            var data = await response.json();

            if (data.success) {
                input.value = '';
                state.wordcloudCount++;
                var remaining = maxWords - state.wordcloudCount;
                $('wordcloud-remaining').textContent = remaining > 0
                    ? remaining + ' palabra(s) restante(s)'
                    : 'Limite alcanzado';
                if (remaining <= 0) {
                    $('wordcloud-input-area').classList.add('hidden');
                }
                wordcloudForceRender = true; // force full re-layout on submit
                renderWordCloudWords(data.words || []);
            } else {
                alert(data.error || 'Error');
            }
        } catch (err) {
            console.error('Error submitting word:', err);
        }
    }

    // ---- Word Cloud: resultados (barras) ----

    async function loadWordCloudResults(sectionId) {
        try {
            var res = await fetch(
                'api.php?action=get_words&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&section_id=' + encodeURIComponent(sectionId)
            );
            var data = await res.json();
            showWordCloudResults(data.words || [], sectionId);
        } catch (err) {
            console.error('Error loading wordcloud results:', err);
        }
    }

    function showWordCloudResults(words, sectionId) {
        var section = state.sections[sectionId];
        $('wordcloud-results-title').textContent = 'Resultados: ' + (section ? section.title : '');

        var chart = $('wordcloud-results-chart');
        chart.innerHTML = '';

        var totalWords = 0;
        for (var i = 0; i < words.length; i++) totalWords += parseInt(words[i].count);
        $('wordcloud-total-words').textContent = totalWords;

        if (words.length === 0) {
            chart.innerHTML = '<p style="color:#64748b;text-align:center;">No hay palabras</p>';
            showScreen($wordcloudResultsScreen);
            return;
        }

        var maxCount = parseInt(words[0].count); // already sorted desc
        var barColors = wordcloudColors;

        for (var j = 0; j < words.length; j++) {
            var w = words[j];
            var count = parseInt(w.count);
            var percent = maxCount > 0 ? (count / maxCount) * 100 : 0;
            var color = barColors[j % barColors.length];

            var row = document.createElement('div');
            row.className = 'result-row';
            row.innerHTML =
                '<div class="result-label"><span>' + escapeHtml(w.word) + '</span></div>' +
                '<div class="result-bar-container">' +
                    '<div class="result-bar" style="--bar-color: ' + color + '; width: ' + percent + '%"></div>' +
                '</div>' +
                '<span class="result-count">' + count + '</span>';
            chart.appendChild(row);
        }

        showScreen($wordcloudResultsScreen);
    }

    // ============================================
    // REACTIONS
    // ============================================

    function renderReactions(section) {
        $('reactions-title').textContent = section.title;

        var hostControls = $('host-controls-reactions');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        // Determinar estilo
        reactionStyle = section.reaction_style || 'explosive';
        reactionCounts = {};
        reactionTotal = 0;
        // Inicializar conteos por indice
        var sectionEmojis = section.emojis || [];
        for (var ei = 0; ei < sectionEmojis.length; ei++) {
            reactionCounts[ei] = 0;
        }
        lastEnergyMilestone = 0;
        if (matrixInterval) { clearInterval(matrixInterval); matrixInterval = null; }

        // Limpiar stage
        var stage = $('reactions-stage');
        stage.innerHTML = '';
        stage.className = 'reactions-stage';

        // Setup stage area según estilo
        var stageArea = $('reactions-stage-area');
        if (stageArea) stageArea.remove();

        if (reactionStyle !== 'explosive') {
            var area = document.createElement('div');
            area.id = 'reactions-stage-area';
            // Insertar antes de los botones dentro de la card
            var buttonsEl = $('reactions-buttons');
            buttonsEl.parentElement.insertBefore(area, buttonsEl);

            if (reactionStyle === 'race') {
                area.className = 'race-container';
                setupRace(section);
            } else if (reactionStyle === 'mosaic') {
                area.className = 'mosaic-wrap';
                area.innerHTML = '<div class="mosaic-grid" id="mosaic-grid"></div>' +
                    '<div class="mosaic-total" id="mosaic-total">0 reacciones</div>';
            } else if (reactionStyle === 'energy') {
                setupEnergy(area, section);
            } else if (reactionStyle === 'matrix') {
                setupMatrix(area, section);
            }
        }

        // Crear botones de emojis
        var buttonsContainer = $('reactions-buttons');
        buttonsContainer.innerHTML = '';

        var emojis = section.emojis || [];
        for (var i = 0; i < emojis.length; i++) {
            var btn = document.createElement('button');
            btn.className = 'reaction-btn';
            btn.textContent = emojis[i];
            btn.addEventListener('click', (function (emoji, idx) {
                return function (e) {
                    // Ripple effect on button
                    var rect = e.currentTarget.getBoundingClientRect();
                    var ripple = document.createElement('span');
                    ripple.className = 'reaction-btn-ripple';
                    ripple.style.left = (e.clientX - rect.left) + 'px';
                    ripple.style.top = (e.clientY - rect.top) + 'px';
                    ripple.style.width = ripple.style.height = Math.max(rect.width, rect.height) + 'px';
                    e.currentTarget.appendChild(ripple);
                    setTimeout(function () { if (ripple.parentNode) ripple.remove(); }, 500);
                    sendReaction(emoji, idx);
                };
            })(emojis[i], i));
            buttonsContainer.appendChild(btn);
        }

        state.reactionLastFetch = new Date().toISOString().replace('T', ' ').substring(0, 19);
        showScreen($reactionsScreen);
    }

    // ---- Race setup ----
    function setupRace(section) {
        var area = $('reactions-stage-area');
        var emojis = section.emojis || [];
        var raceColors = ['#3b82f6','#ef4444','#22c55e','#eab308','#8b5cf6','#ec4899','#06b6d4','#f97316'];
        var html = '';
        for (var i = 0; i < emojis.length; i++) {
            reactionCounts[i] = 0;
            html += '<div class="race-row" data-index="' + i + '">' +
                '<span class="race-emoji">' + emojis[i] + '</span>' +
                '<div class="race-bar-wrap"><div class="race-bar" style="width:4px;background:' + raceColors[i % raceColors.length] + '"></div></div>' +
                '<span class="race-count">0</span>' +
            '</div>';
        }
        area.innerHTML = html;
    }

    function updateRace() {
        var rows = document.querySelectorAll('.race-row');
        if (!rows.length) return;
        var maxCount = 1;
        for (var idx in reactionCounts) {
            if (reactionCounts[idx] > maxCount) maxCount = reactionCounts[idx];
        }
        rows.forEach(function (row) {
            var idx = parseInt(row.getAttribute('data-index'));
            var count = reactionCounts[idx] || 0;
            var bar = row.querySelector('.race-bar');
            var countEl = row.querySelector('.race-count');
            var pct = maxCount > 0 ? (count / maxCount) * 100 : 0;
            bar.style.width = Math.max(4, pct) + '%';
            countEl.textContent = count;
        });
    }

    // ---- Mosaic ----
    function addMosaicCell(emoji) {
        var grid = $('mosaic-grid');
        if (!grid) return;
        var cell = document.createElement('div');
        cell.className = 'mosaic-cell';
        cell.textContent = emoji;
        grid.appendChild(cell);
        // Auto-scroll to bottom
        grid.scrollTop = grid.scrollHeight;
        var totalEl = $('mosaic-total');
        if (totalEl) totalEl.textContent = reactionTotal + ' reacciones';
    }

    function syncMosaic(counts) {
        var grid = $('mosaic-grid');
        if (!grid) return;
        var totalEl = $('mosaic-total');
        if (totalEl) totalEl.textContent = reactionTotal + ' reacciones';
    }

    // ---- Energy setup ----
    function setupEnergy(area, section) {
        var circumference = 2 * Math.PI * 75;
        area.className = 'energy-container';
        area.innerHTML =
            '<div class="energy-ring-wrap">' +
                '<svg class="energy-ring" width="180" height="180" viewBox="0 0 180 180">' +
                    '<circle class="energy-ring-bg" cx="90" cy="90" r="75"></circle>' +
                    '<circle class="energy-ring-fill" id="energy-fill" cx="90" cy="90" r="75" ' +
                        'stroke-dasharray="' + circumference + '" stroke-dashoffset="' + circumference + '"></circle>' +
                '</svg>' +
                '<div class="energy-emoji-center" id="energy-emoji">✨</div>' +
                '<div class="energy-confetti-container" id="energy-confetti"></div>' +
            '</div>' +
            '<div class="energy-counter" id="energy-counter">0</div>' +
            '<div class="energy-goal-label" id="energy-goal-label">Meta: ' + energyGoal + '</div>';
    }

    function updateEnergy() {
        var fill = $('energy-fill');
        var counterEl = $('energy-counter');
        var emojiCenter = $('energy-emoji');
        if (!fill) return;

        var circumference = 2 * Math.PI * 75;
        var pct = Math.min(reactionTotal / energyGoal, 1);
        var offset = circumference * (1 - pct);
        fill.style.strokeDashoffset = offset;

        // Color gradient based on progress
        if (pct < 0.33) fill.style.stroke = '#3b82f6';
        else if (pct < 0.66) fill.style.stroke = '#eab308';
        else fill.style.stroke = '#22c55e';

        if (counterEl) counterEl.textContent = reactionTotal;

        // Dominant emoji (by index)
        var section = state.sections[state.currentSection];
        var emojis = section ? (section.emojis || []) : [];
        var dominant = '✨';
        var maxC = 0;
        for (var idx in reactionCounts) {
            if (reactionCounts[idx] > maxC) {
                maxC = reactionCounts[idx];
                dominant = emojis[parseInt(idx)] || '✨';
            }
        }
        if (emojiCenter) emojiCenter.textContent = dominant;

        // Milestones
        var milestones = [10, 25, 50, 75, 100, 150, 200];
        for (var m = 0; m < milestones.length; m++) {
            if (reactionTotal >= milestones[m] && lastEnergyMilestone < milestones[m]) {
                lastEnergyMilestone = milestones[m];
                spawnEnergyConfetti();
            }
        }
    }

    function spawnEnergyConfetti() {
        var container = $('energy-confetti');
        if (!container) return;
        var confettiColors = ['#ef4444','#22c55e','#3b82f6','#eab308','#8b5cf6','#ec4899'];
        for (var i = 0; i < 20; i++) {
            var p = document.createElement('div');
            p.className = 'energy-confetti-particle';
            p.style.background = confettiColors[i % confettiColors.length];
            p.style.left = '50%';
            p.style.top = '50%';
            var angle = (Math.PI * 2 * i) / 20;
            var dist = 60 + Math.random() * 60;
            p.style.setProperty('--cx', (Math.cos(angle) * dist) + 'px');
            p.style.setProperty('--cy', (Math.sin(angle) * dist) + 'px');
            p.style.setProperty('--cr', (Math.random() * 360) + 'deg');
            container.appendChild(p);
            (function (el) {
                setTimeout(function () { if (el.parentNode) el.remove(); }, 1000);
            })(p);
        }
    }

    // ---- Matrix setup ----
    function setupMatrix(area, section) {
        area.className = 'matrix-container';
        var numCols = 8;
        var html = '';
        for (var i = 0; i < numCols; i++) {
            html += '<div class="matrix-col" data-col="' + i + '"></div>';
        }
        area.innerHTML = html;

        // Fade old cells periodically
        matrixInterval = setInterval(function () {
            var cells = area.querySelectorAll('.matrix-cell:not(.matrix-cell-old)');
            for (var c = 0; c < cells.length; c++) {
                var age = parseInt(cells[c].getAttribute('data-age') || '0') + 1;
                cells[c].setAttribute('data-age', age);
                if (age > 8) cells[c].classList.add('matrix-cell-old');
            }
            // Remove very old cells
            var old = area.querySelectorAll('.matrix-cell-old');
            for (var o = 0; o < old.length; o++) {
                var oldAge = parseInt(old[o].getAttribute('data-age') || '0');
                if (oldAge > 15) old[o].remove();
            }
        }, 2000);
    }

    function addMatrixEmoji(emoji) {
        var area = $('reactions-stage-area');
        if (!area || reactionStyle !== 'matrix') return;
        var cols = area.querySelectorAll('.matrix-col');
        if (!cols.length) return;
        var colIdx = Math.floor(Math.random() * cols.length);
        var cell = document.createElement('div');
        cell.className = 'matrix-cell';
        cell.textContent = emoji;
        cell.setAttribute('data-age', '0');
        cols[colIdx].appendChild(cell);
        // Limit cells per column
        if (cols[colIdx].children.length > 20) {
            cols[colIdx].removeChild(cols[colIdx].firstChild);
        }
    }

    async function sendReaction(emoji, idx) {
        console.log('[click] emoji=' + emoji + ' idx=' + idx + ' style=' + reactionStyle);
        // Efecto local inmediato según estilo
        if (reactionStyle === 'explosive') {
            spawnReactionBurst(emoji, true);
        } else if (reactionStyle === 'race') {
            reactionCounts[idx] = (reactionCounts[idx] || 0) + 1;
            reactionTotal++;
            updateRace();
        } else if (reactionStyle === 'mosaic') {
            reactionTotal++;
            addMosaicCell(emoji);
        } else if (reactionStyle === 'energy') {
            reactionCounts[idx] = (reactionCounts[idx] || 0) + 1;
            reactionTotal++;
            updateEnergy();
        } else if (reactionStyle === 'matrix') {
            reactionTotal++;
            addMatrixEmoji(emoji);
        }

        try {
            var res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_reaction',
                    meeting_id: state.meetingId,
                    emoji: emoji,
                    user_id: state.userId,
                }),
            });
            var data = await res.json();
            console.log('[server response] counts:', JSON.stringify(data.reaction_counts), 'total:', data.reaction_total);
            // Sincronizar conteos desde servidor
            if (data.reaction_counts) {
                syncReactionCounts(data.reaction_counts, data.reaction_total);
            }
        } catch (err) {
            console.error('Error sending reaction:', err);
        }
    }

    function normalizeEmoji(s) {
        // Strip variation selectors and zero-width joiners for comparison
        return s.replace(/[\uFE0E\uFE0F\u200D]/g, '').replace(/[\u{1F3FB}-\u{1F3FF}]/gu, '');
    }

    function emojiToIndex(serverEmoji) {
        var section = state.sections[state.currentSection];
        var emojis = section ? (section.emojis || []) : [];
        // 1) Exact match
        for (var i = 0; i < emojis.length; i++) {
            if (emojis[i] === serverEmoji) return i;
        }
        // 2) Strip variation selectors
        var norm = normalizeEmoji(serverEmoji);
        for (var i = 0; i < emojis.length; i++) {
            if (normalizeEmoji(emojis[i]) === norm) return i;
        }
        // 3) First codepoint match (last resort)
        var serverFirst = Array.from(serverEmoji)[0];
        for (var i = 0; i < emojis.length; i++) {
            if (Array.from(emojis[i])[0] === serverFirst) return i;
        }
        return -1;
    }

    function syncReactionCounts(counts, total) {
        if (counts) {
            var section = state.sections[state.currentSection];
            var emojis = section ? (section.emojis || []) : [];
            reactionCounts = {};
            // Initialize all indices to 0
            for (var j = 0; j < emojis.length; j++) {
                reactionCounts[j] = 0;
            }
            // Map server counts to indices
            for (var i = 0; i < counts.length; i++) {
                var idx = emojiToIndex(counts[i].emoji);
                if (idx >= 0) {
                    reactionCounts[idx] = parseInt(counts[i].count);
                }
            }
        }
        if (total !== undefined) reactionTotal = total;

        if (reactionStyle === 'race') updateRace();
        else if (reactionStyle === 'energy') updateEnergy();
        else if (reactionStyle === 'mosaic') syncMosaic(counts);
    }

    var reactionTypes = ['reaction-float-up', 'reaction-float-burst', 'reaction-float-pop', 'reaction-float-rain'];

    function spawnReactionBurst(emoji, isLocal) {
        var stage = $('reactions-stage');
        if (!stage) return;

        // Flash effect
        var flash = $('reactions-flash');
        if (flash) {
            flash.style.setProperty('--fx', (Math.random() * 60 + 20) + '%');
            flash.style.setProperty('--fy', (Math.random() * 40 + 30) + '%');
            flash.classList.add('flash-active');
            setTimeout(function () { flash.classList.remove('flash-active'); }, 150);
        }

        // How many to spawn: local taps get more
        var count = isLocal ? (3 + Math.floor(Math.random() * 3)) : (2 + Math.floor(Math.random() * 2));

        for (var i = 0; i < count; i++) {
            (function (idx) {
                setTimeout(function () {
                    spawnSingleReaction(stage, emoji, idx === 0);
                }, idx * 60);
            })(i);
        }
    }

    function spawnSingleReaction(stage, emoji, isPrimary) {
        var span = document.createElement('span');
        var type = isPrimary
            ? reactionTypes[Math.floor(Math.random() * 2)] // up or burst for primary
            : reactionTypes[Math.floor(Math.random() * reactionTypes.length)];
        span.className = 'reaction-float ' + type;
        span.textContent = emoji;

        // Random size
        var size = isPrimary ? (2.5 + Math.random() * 2) : (1.2 + Math.random() * 2.5);
        span.style.fontSize = size + 'rem';
        span.style.setProperty('--sc', (0.8 + Math.random() * 0.6).toFixed(2));

        // Duration variation
        var dur = (1.5 + Math.random() * 1.5).toFixed(1);
        span.style.setProperty('--dur', dur + 's');

        // Sway direction
        var sway = (Math.random() > 0.5 ? 1 : -1) * (10 + Math.random() * 25);
        span.style.setProperty('--sw', sway + 'deg');

        // Position
        if (type === 'reaction-float-burst') {
            // Start from center area, explode outward
            span.style.left = (35 + Math.random() * 30) + '%';
            span.style.top = (40 + Math.random() * 20) + '%';
            var bx = (Math.random() - 0.5) * 200;
            var by = -30 - Math.random() * 150;
            span.style.setProperty('--bx', bx + 'px');
            span.style.setProperty('--by', by + 'px');
            span.style.setProperty('--br', ((Math.random() - 0.5) * 60) + 'deg');
        } else if (type === 'reaction-float-pop') {
            // Pop in random spot
            span.style.left = (10 + Math.random() * 80) + '%';
            span.style.top = (15 + Math.random() * 50) + '%';
            span.style.setProperty('--sc', (1.5 + Math.random() * 1.5).toFixed(2));
        } else if (type === 'reaction-float-rain') {
            // Start from top
            span.style.left = (5 + Math.random() * 90) + '%';
            span.style.top = (-5 - Math.random() * 10) + '%';
        } else {
            // Float up from bottom area
            span.style.left = (5 + Math.random() * 90) + '%';
            span.style.bottom = '5%';
        }

        stage.appendChild(span);

        var lifetime = parseFloat(dur) * 1000 + 200;
        setTimeout(function () {
            if (span.parentNode) span.parentNode.removeChild(span);
        }, lifetime);
    }

    // Legacy compat: polling uses spawnReactionFloat
    function spawnReactionFloat(emoji) {
        spawnReactionBurst(emoji, false);
    }

    async function fetchReactions() {
        if (!state.reactionLastFetch) return;

        try {
            var res = await fetch(
                'api.php?action=get_reactions&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&since=' + encodeURIComponent(state.reactionLastFetch)
            );
            var data = await res.json();

            if (data.reactions && data.reactions.length > 0) {
                for (var i = 0; i < data.reactions.length; i++) {
                    var r = data.reactions[i];
                    if (r.user_id !== state.userId) {
                        spawnReactionFloat(r.emoji);
                    }
                }
            }
            if (data.server_time) {
                state.reactionLastFetch = data.server_time;
            }
        } catch (err) {
            console.error('Error fetching reactions:', err);
        }
    }

    // ============================================
    // QUIZ
    // ============================================

    var quizTimerInterval = null;

    function stopQuizTimer() {
        if (quizTimerInterval) {
            clearInterval(quizTimerInterval);
            quizTimerInterval = null;
        }
    }

    function renderQuiz(section, sectionId, status, pollData) {
        $('quiz-question').textContent = section.title;

        var hostControls = $('host-controls-quiz');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        var timerEl = $('quiz-timer');
        var startBtn = $('quiz-start');
        var optionsEl = $('quiz-options');

        optionsEl.innerHTML = '';
        timerEl.classList.add('hidden');
        startBtn.classList.add('hidden');
        stopQuizTimer();

        var startedAt = (pollData && pollData.started_at) ? pollData.started_at : null;
        var timeLimit = section.time_limit || 30;

        // Si esta cerrado, mostrar leaderboard
        if (status === 'closed') {
            loadQuizResults(sectionId);
            return;
        }

        // Si no ha iniciado (started_at es null)
        if (!startedAt) {
            // Host ve boton iniciar
            if (state.isHost && state.isInZoom) {
                startBtn.classList.remove('hidden');
            } else {
                // Participante espera
                optionsEl.innerHTML = '<p style="color:#64748b;text-align:center;">Esperando que el host inicie el quiz...</p>';
            }
            showScreen($quizScreen);
            return;
        }

        // Quiz activo - mostrar opciones y timer
        var serverStarted = new Date(startedAt.replace(' ', 'T') + 'Z');
        var now = new Date();
        var elapsedSec = (now.getTime() - serverStarted.getTime()) / 1000;
        var remaining = Math.max(0, Math.ceil(timeLimit - elapsedSec));

        if (remaining <= 0 && !state.quizAnswered) {
            // Tiempo expirado
            optionsEl.innerHTML = '<p style="color:#ef4444;text-align:center;font-size:1.2rem;">Tiempo agotado!</p>';
            showScreen($quizScreen);
            return;
        }

        // Mostrar timer
        timerEl.classList.remove('hidden');
        $('quiz-timer-value').textContent = remaining;
        updateTimerColor(remaining, timeLimit);

        // Iniciar countdown
        quizTimerInterval = setInterval(function () {
            var nowInner = new Date();
            var elapsedInner = (nowInner.getTime() - serverStarted.getTime()) / 1000;
            var rem = Math.max(0, Math.ceil(timeLimit - elapsedInner));
            $('quiz-timer-value').textContent = rem;
            updateTimerColor(rem, timeLimit);

            if (rem <= 0) {
                stopQuizTimer();
                if (!state.quizAnswered) {
                    optionsEl.innerHTML = '<p style="color:#ef4444;text-align:center;font-size:1.2rem;">Tiempo agotado!</p>';
                }
            }
        }, 1000);

        // Renderizar opciones
        if (!state.quizAnswered) {
            renderQuizOptions(section, sectionId, serverStarted);
        } else {
            optionsEl.innerHTML = '<p style="color:#22c55e;text-align:center;font-size:1.2rem;">Respuesta enviada! Esperando resultados...</p>';
        }

        showScreen($quizScreen);
    }

    function updateTimerColor(remaining, timeLimit) {
        var timerEl = $('quiz-timer');
        timerEl.classList.remove('quiz-timer-warning', 'quiz-timer-danger');
        if (remaining <= 5) {
            timerEl.classList.add('quiz-timer-danger');
        } else if (remaining <= 10) {
            timerEl.classList.add('quiz-timer-warning');
        }
    }

    function renderQuizOptions(section, sectionId, serverStarted) {
        var optionsEl = $('quiz-options');
        optionsEl.innerHTML = '';

        for (var key in section.options) {
            var opt = section.options[key];
            var value = parseInt(key);
            var btn = document.createElement('button');
            btn.className = 'option-btn quiz-option-btn';
            btn.style.setProperty('--btn-color', opt.color);
            btn.innerHTML = '<span class="option-label">' + escapeHtml(opt.label) + '</span>';
            btn.addEventListener('click', (function (v, started) {
                return function () {
                    if (state.quizAnswered) return;
                    submitQuizAnswer(v, started);
                };
            })(value, serverStarted));
            optionsEl.appendChild(btn);
        }
    }

    async function submitQuizAnswer(answer, serverStarted) {
        if (state.quizAnswered) return;
        state.quizAnswered = true;

        var timeMs = Math.max(0, new Date().getTime() - serverStarted.getTime());

        // Deshabilitar botones
        var btns = $('quiz-options').querySelectorAll('.option-btn');
        btns.forEach(function (b) { b.classList.add('loading'); });

        try {
            var response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'quiz_answer',
                    meeting_id: state.meetingId,
                    section_id: state.currentSection,
                    user_id: state.userId,
                    user_name: state.userName,
                    answer: answer,
                    time_ms: timeMs,
                }),
            });

            var data = await response.json();
            if (data.success) {
                $('quiz-options').innerHTML = '<p style="color:#22c55e;text-align:center;font-size:1.2rem;">Respuesta enviada! Esperando resultados...</p>';
            } else {
                alert(data.error || 'Error');
                state.quizAnswered = false;
                btns.forEach(function (b) { b.classList.remove('loading'); });
            }
        } catch (err) {
            console.error('Error submitting quiz answer:', err);
            state.quizAnswered = false;
            btns.forEach(function (b) { b.classList.remove('loading'); });
        }
    }

    async function startQuiz() {
        try {
            var response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'start_quiz',
                    meeting_id: state.meetingId,
                }),
            });
            var data = await response.json();
            if (data.success) {
                var section = state.sections[state.currentSection];
                if (section) {
                    renderQuiz(section, state.currentSection, 'voting', { started_at: data.started_at });
                }
            }
        } catch (err) {
            console.error('Error starting quiz:', err);
        }
    }

    async function loadQuizResults(sectionId) {
        try {
            var res = await fetch(
                'api.php?action=quiz_results&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&section_id=' + encodeURIComponent(sectionId)
            );
            var data = await res.json();
            showQuizLeaderboard(data.results, sectionId);
        } catch (err) {
            console.error('Error loading quiz results:', err);
        }
    }

    function showQuizLeaderboard(results, sectionId) {
        var section = state.sections[sectionId];
        stopQuizTimer();

        var hostControls = $('host-controls-leaderboard');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        // Podio (top 3)
        var podium = $('quiz-podium');
        podium.innerHTML = '';
        var leaderboard = results.leaderboard || [];
        var medals = ['🥇', '🥈', '🥉'];
        var podiumOrder = [1, 0, 2]; // 2do, 1ro, 3ro

        for (var p = 0; p < podiumOrder.length; p++) {
            var idx = podiumOrder[p];
            var div = document.createElement('div');
            div.className = 'podium-place podium-place-' + (idx + 1);

            if (idx < leaderboard.length) {
                var entry = leaderboard[idx];
                var name = entry.user_name || entry.user_id;
                if (name.length > 12) name = name.substring(0, 12) + '...';
                div.innerHTML =
                    '<span class="podium-medal">' + medals[idx] + '</span>' +
                    '<span class="podium-name">' + escapeHtml(name) + '</span>' +
                    '<span class="podium-score">' + entry.score + ' pts</span>';
            } else {
                div.innerHTML = '<span class="podium-medal">' + medals[idx] + '</span><span class="podium-name">-</span>';
            }
            podium.appendChild(div);
        }

        // Ranking completo
        var ranking = $('quiz-ranking');
        ranking.innerHTML = '';

        for (var i = 0; i < leaderboard.length; i++) {
            var r = leaderboard[i];
            var row = document.createElement('div');
            row.className = 'ranking-row' + (r.correct ? ' ranking-correct' : ' ranking-wrong');
            var rName = r.user_name || r.user_id;
            var timeStr = r.time_ms > 0 ? (r.time_ms / 1000).toFixed(1) + 's' : '-';
            row.innerHTML =
                '<span class="ranking-pos">#' + (i + 1) + '</span>' +
                '<span class="ranking-name">' + escapeHtml(rName) + '</span>' +
                '<span class="ranking-time">' + timeStr + '</span>' +
                '<span class="ranking-score">' + r.score + '</span>';
            ranking.appendChild(row);
        }

        showScreen($quizLeaderboardScreen);
    }

    // ============================================
    // SCALE
    // ============================================

    function renderScale(section, sectionId) {
        $('scale-title').textContent = section.title;

        var slider = $('scale-slider');
        var minVal = section.min || 1;
        var maxVal = section.max || 10;
        slider.min = minVal;
        slider.max = maxVal;
        slider.value = Math.round((minVal + maxVal) / 2);
        $('scale-value').textContent = slider.value;

        $('scale-min-label').textContent = (section.min_label || minVal) + ' (' + minVal + ')';
        $('scale-max-label').textContent = (section.max_label || maxVal) + ' (' + maxVal + ')';

        var hostControls = $('host-controls-scale');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        showScreen($scaleScreen);
    }

    async function submitScale() {
        var slider = $('scale-slider');
        var answer = parseInt(slider.value);

        try {
            var response = await fetch('api.php', {
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

            var data = await response.json();
            if (data.success) {
                state.selectedAnswer = answer;
                $('scale-submit').textContent = 'Enviado!';
                $('scale-submit').classList.add('loading');
                setTimeout(function () {
                    $('scale-submit').textContent = 'Enviar';
                    $('scale-submit').classList.remove('loading');
                }, 2000);
            } else {
                alert(data.error || 'Error');
            }
        } catch (err) {
            console.error('Error submitting scale:', err);
        }
    }

    async function loadScaleResults(sectionId) {
        try {
            var res = await fetch(
                'api.php?action=results&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&section_id=' + encodeURIComponent(sectionId)
            );
            var data = await res.json();

            // The poll endpoint returns scale results differently, try quiz_results-style
            // Actually we extended the results endpoint to work. Let's fetch via poll or dedicated.
            // Use the scale-specific data from poll
            var pollRes = await fetch(
                'api.php?action=poll&meeting_id=' + encodeURIComponent(state.meetingId)
            );
            var pollData = await pollRes.json();

            if (pollData.results) {
                showScaleResults(pollData.results, sectionId);
            } else {
                // Fallback: build from survey results
                showScaleResultsFromSurvey(data.results, sectionId);
            }
        } catch (err) {
            console.error('Error loading scale results:', err);
        }
    }

    function showScaleResults(results, sectionId) {
        var section = state.sections[sectionId];

        $('scale-results-title').textContent = 'Resultados: ' + (section ? section.title : '');

        // Average
        var avgEl = $('scale-average');
        avgEl.innerHTML = '<div class="scale-avg-number">' + results.average + '</div><div class="scale-avg-label">Promedio</div>';

        // Distribution
        var distEl = $('scale-distribution');
        distEl.innerHTML = '';

        var maxCount = 1;
        var distribution = results.distribution || {};
        for (var k in distribution) {
            if (distribution[k] > maxCount) maxCount = distribution[k];
        }

        var minVal = section ? (section.min || 1) : 1;
        var maxVal = section ? (section.max || 10) : 10;

        for (var v = minVal; v <= maxVal; v++) {
            var count = distribution[v] || 0;
            var percent = maxCount > 0 ? (count / maxCount) * 100 : 0;
            var row = document.createElement('div');
            row.className = 'result-row';
            row.innerHTML =
                '<div class="result-label"><span>' + v + '</span></div>' +
                '<div class="result-bar-container">' +
                    '<div class="result-bar" style="--bar-color: #3b82f6; width: ' + percent + '%"></div>' +
                '</div>' +
                '<span class="result-count">' + count + '</span>';
            distEl.appendChild(row);
        }

        $('scale-total').textContent = results.total || 0;

        var hostControls = $('host-controls-scale-results');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
        } else {
            hostControls.classList.add('hidden');
        }

        showScreen($scaleResultsScreen);
    }

    function showScaleResultsFromSurvey(results, sectionId) {
        // Fallback if poll didn't give scale-specific data
        var section = state.sections[sectionId];
        var minVal = section ? (section.min || 1) : 1;
        var maxVal = section ? (section.max || 10) : 10;

        var distribution = {};
        var total = results.total || 0;
        var sum = 0;
        for (var v = minVal; v <= maxVal; v++) {
            var count = (results.answers && results.answers[v]) ? results.answers[v] : 0;
            distribution[v] = count;
            sum += v * count;
        }
        var avg = total > 0 ? Math.round(sum / total * 10) / 10 : 0;

        showScaleResults({ average: avg, total: total, distribution: distribution }, sectionId);
    }

    // ---- Votar (survey) ----

    async function submitVote(answer) {
        var buttons = $('survey-options').querySelectorAll('.option-btn');
        buttons.forEach(function (btn) { btn.classList.add('loading'); });

        try {
            var response = await fetch('api.php', {
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

            var data = await response.json();

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
            buttons.forEach(function (btn) { btn.classList.remove('loading'); });
        }
    }

    // ---- Resultados (survey) ----

    function showResults(results) {
        var section = state.sections[state.currentSection];
        if (!section || section.type !== 'survey') return;

        $('results-title').textContent = 'Resultados: ' + section.title;

        var chart = $('results-chart');
        chart.innerHTML = '';

        var total = results.total || 0;
        $('total-votes').textContent = total;

        for (var key in section.options) {
            var opt = section.options[key];
            var count = results.answers[key] || 0;
            var percent = total > 0 ? (count / total) * 100 : 0;

            var row = document.createElement('div');
            row.className = 'result-row';
            row.dataset.answer = key;
            row.innerHTML =
                '<div class="result-label">' +
                    '<span>' + (opt.emoji || '') + '</span>' +
                    '<span>' + escapeHtml(opt.label) + '</span>' +
                '</div>' +
                '<div class="result-bar-container">' +
                    '<div class="result-bar" style="--bar-color: ' + opt.color + '; width: ' + percent + '%"></div>' +
                '</div>' +
                '<span class="result-count">' + count + '</span>';
            chart.appendChild(row);
        }

        var hostControls = $('host-controls-results');
        if (state.isHost && state.isInZoom) {
            hostControls.classList.remove('hidden');
            $('btn-change').classList.add('hidden');
        } else {
            hostControls.classList.add('hidden');
            $('btn-change').classList.remove('hidden');
        }

        showScreen($resultsScreen);
    }

    async function loadResults(sectionId) {
        try {
            var res = await fetch(
                'api.php?action=results&meeting_id=' + encodeURIComponent(state.meetingId) +
                '&section_id=' + encodeURIComponent(sectionId)
            );
            var data = await res.json();
            showResults(data.results);
        } catch (err) {
            console.error('Error loading results:', err);
        }
    }

    // ---- Host: cerrar seccion ----

    async function closeSection() {
        try {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'close',
                    meeting_id: state.meetingId,
                }),
            });

            var section = state.sections[state.currentSection];
            if (!section) return;

            if (section.type === 'survey') {
                await loadResults(state.currentSection);
            } else if (section.type === 'quiz') {
                await loadQuizResults(state.currentSection);
            } else if (section.type === 'scale') {
                await loadScaleResults(state.currentSection);
            } else if (section.type === 'wordcloud') {
                // Ocultar input, mostrar estado cerrado
                $('wordcloud-input-area').classList.add('hidden');
                $('wordcloud-remaining').textContent = '';
                $('btn-close-wordcloud').classList.add('hidden');
                $('btn-reopen-wordcloud').classList.remove('hidden');
                // Agregar badge de cerrado
                var badge = document.createElement('div');
                badge.className = 'wordcloud-closed-badge';
                badge.textContent = 'Nube cerrada - No se aceptan mas palabras';
                var cloud = $('wordcloud-cloud');
                if (!cloud.querySelector('.wordcloud-closed-badge')) {
                    cloud.appendChild(badge);
                }
            }
        } catch (err) {
            console.error('Error closing section:', err);
        }
    }

    // ---- Host: reabrir encuesta ----

    async function reopenSection() {
        try {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reopen',
                    meeting_id: state.meetingId,
                }),
            });

            var section = state.sections[state.currentSection];
            if (section) {
                if (section.type === 'survey') {
                    renderSurvey(section, state.currentSection);
                } else if (section.type === 'scale') {
                    renderScale(section, state.currentSection);
                }
            }
        } catch (err) {
            console.error('Error reopening section:', err);
        }
    }

    // ---- Host: volver al menu ----

    function backToMenu() {
        stopPolling();
        stopQuizTimer();
        if (matrixInterval) { clearInterval(matrixInterval); matrixInterval = null; }
        state.currentSection = null;
        state.selectedAnswer = null;
        state.quizAnswered = false;
        state.wordcloudCount = 0;
        showMenu();
    }

    // ---- Idle polling (esperando actividad) ----

    var idlePollInterval = null;

    function startIdlePolling() {
        stopIdlePolling();
        idlePollInterval = setInterval(async function () {
            try {
                var res = await fetch(
                    'api.php?action=get_active&meeting_id=' + encodeURIComponent(state.meetingId)
                );
                var data = await res.json();
                if (data.active) {
                    stopIdlePolling();
                    state.currentSection = data.section_id;
                    navigateToSection(data.section_id, data.status, data);
                }
            } catch (err) {
                console.error('Idle poll error:', err);
            }
        }, 4000);
    }

    function stopIdlePolling() {
        if (idlePollInterval) {
            clearInterval(idlePollInterval);
            idlePollInterval = null;
        }
    }

    // ---- Participante: polling ----

    var pollInterval = null;

    function startPolling() {
        stopPolling();
        var section = state.sections[state.currentSection];
        var sectionType = section ? section.type : '';

        // Polling mas rapido para reactions y wordcloud
        var interval = 5000;
        if (sectionType === 'reactions') interval = 2000;
        if (sectionType === 'wordcloud') interval = 3000;
        if (sectionType === 'quiz') interval = 2000;

        pollInterval = setInterval(pollStatus, interval);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    async function pollStatus() {
        try {
            var res = await fetch(
                'api.php?action=poll&meeting_id=' + encodeURIComponent(state.meetingId)
            );
            var data = await res.json();

            if (!data.active) {
                // Broadcast stopped - go back to idle for non-host
                if (!state.isHost) {
                    stopPolling();
                    state.currentSection = null;
                    showScreen($idleScreen);
                    startIdlePolling();
                }
                return;
            }

            // Si cambio la seccion activa, navegar
            if (data.section_id !== state.currentSection) {
                state.currentSection = data.section_id;
                state.quizAnswered = false;
                state.wordcloudCount = 0;
                navigateToSection(data.section_id, data.status, data);
                return;
            }

            var section = state.sections[state.currentSection];
            if (!section) return;
            var sectionType = section.type;

            // Tipo-especifico
            if (sectionType === 'wordcloud') {
                if (data.status === 'closed' && !state.isHost) {
                    showWordCloudResults(data.words || [], state.currentSection);
                    return;
                }
                if (data.words) {
                    // Force re-layout if words changed (new submissions from others)
                    var pollHash = wordcloudHash(data.words);
                    if (pollHash !== wordcloudLastHash) {
                        wordcloudForceRender = true;
                    }
                    renderWordCloudWords(data.words);
                    if (data.status === 'closed') {
                        $('wordcloud-input-area').classList.add('hidden');
                    }
                }
            } else if (sectionType === 'reactions') {
                // Sincronizar conteos desde poll
                if (data.reaction_counts) {
                    syncReactionCounts(data.reaction_counts, data.reaction_total);
                }
                // Animaciones para reacciones de otros usuarios
                if (data.reactions) {
                    for (var i = 0; i < data.reactions.length; i++) {
                        var r = data.reactions[i];
                        if (r.user_id !== state.userId) {
                            if (reactionStyle === 'explosive') {
                                spawnReactionFloat(r.emoji);
                            } else if (reactionStyle === 'mosaic') {
                                addMosaicCell(r.emoji);
                            } else if (reactionStyle === 'matrix') {
                                addMatrixEmoji(r.emoji);
                            }
                        }
                    }
                }
            } else if (sectionType === 'quiz') {
                // Si quiz inicio (started_at ahora existe)
                if (data.started_at && $quizScreen && !$quizScreen.classList.contains('hidden')) {
                    var optionsEl = $('quiz-options');
                    if (optionsEl && optionsEl.querySelector('p') && !state.quizAnswered) {
                        // Re-render con started_at
                        renderQuiz(section, state.currentSection, data.status, data);
                    }
                }
                // Si quiz cerrado, mostrar leaderboard
                if (data.status === 'closed' && data.quiz_results) {
                    showQuizLeaderboard(data.quiz_results, state.currentSection);
                    return;
                }
            } else if (sectionType === 'scale') {
                if (data.status === 'closed' && data.results) {
                    showScaleResults(data.results, state.currentSection);
                    return;
                }
            } else if (sectionType === 'survey') {
                if (data.status === 'closed' && data.results) {
                    showResults(data.results);
                    return;
                }
                if (data.status === 'voting' && $resultsScreen && !$resultsScreen.classList.contains('hidden')) {
                    if (state.selectedAnswer) {
                        // Ya votó: refrescar resultados en vivo
                        loadResults(state.currentSection);
                    } else {
                        // Host reabrió y no ha votado: volver a encuesta
                        renderSurvey(section, state.currentSection);
                    }
                }
            }
        } catch (err) {
            console.error('Poll error:', err);
        }
    }

    // ---- Auto-refresh de resultados (host) ----

    var refreshInterval = null;

    function startAutoRefresh() {
        refreshInterval = setInterval(function () {
            if (!state.currentSection) return;
            var section = state.sections[state.currentSection];
            if (!section) return;

            // Auto-refresh segun tipo
            if (section.type === 'survey' && $resultsScreen && !$resultsScreen.classList.contains('hidden')) {
                loadResults(state.currentSection);
            } else if (section.type === 'wordcloud' && $wordcloudScreen && !$wordcloudScreen.classList.contains('hidden')) {
                loadWordCloud(state.currentSection);
            } else if (section.type === 'scale' && $scaleResultsScreen && !$scaleResultsScreen.classList.contains('hidden')) {
                loadScaleResults(state.currentSection);
            }
        }, 10000);
    }

    // ---- Event Listeners ----

    // Host: cerrar/reabrir (survey)
    $('btn-close-survey').addEventListener('click', closeSection);
    $('btn-reopen-survey').addEventListener('click', reopenSection);

    // Host: volver al menu (desde todas las pantallas)
    $('btn-back-menu').addEventListener('click', backToMenu);
    $('btn-back-menu-results').addEventListener('click', backToMenu);
    $('btn-back-menu-greeting').addEventListener('click', backToMenu);
    $('btn-back-menu-wordcloud').addEventListener('click', backToMenu);
    $('btn-back-menu-reactions').addEventListener('click', backToMenu);
    $('btn-back-menu-quiz').addEventListener('click', backToMenu);
    $('btn-back-menu-leaderboard').addEventListener('click', backToMenu);
    $('btn-back-menu-scale').addEventListener('click', backToMenu);
    $('btn-back-menu-scale-results').addEventListener('click', backToMenu);

    // Host: cerrar secciones
    $('btn-close-wordcloud').addEventListener('click', closeSection);
    $('btn-close-quiz').addEventListener('click', closeSection);
    $('btn-close-scale').addEventListener('click', closeSection);

    // Host: reabrir scale / wordcloud
    $('btn-reopen-scale').addEventListener('click', reopenSection);
    if ($('btn-reopen-wordcloud')) {
        $('btn-reopen-wordcloud').addEventListener('click', function () {
            reopenSection();
            $('btn-close-wordcloud').classList.remove('hidden');
            $('btn-reopen-wordcloud').classList.add('hidden');
            $('wordcloud-input-area').classList.remove('hidden');
            var badge = document.querySelector('.wordcloud-closed-badge');
            if (badge) badge.remove();
        });
    }

    // Participante: cambiar respuesta (survey)
    $('btn-change').addEventListener('click', function () {
        var section = state.sections[state.currentSection];
        if (section) {
            renderSurvey(section, state.currentSection);
        }
    });

    // Word Cloud: enviar
    $('wordcloud-submit').addEventListener('click', submitWord);
    $('wordcloud-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') submitWord();
    });

    // Quiz: iniciar
    $('quiz-start').addEventListener('click', startQuiz);

    // Scale: slider change
    $('scale-slider').addEventListener('input', function () {
        $('scale-value').textContent = this.value;
    });

    // Scale: enviar
    $('scale-submit').addEventListener('click', submitScale);

    // ---- Init ----

    async function loadSections() {
        try {
            var res = await fetch('api.php?action=sections');
            var data = await res.json();
            if (data.sections) {
                state.sections = data.sections;
            }
        } catch (err) {
            console.error('Error loading sections:', err);
        }
    }

    async function init() {
        await initZoomSdk();
        await loadSections();
        await determineRole();
        startAutoRefresh();
    }

    init();
})();
