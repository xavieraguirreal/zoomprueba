/**
 * Zoom App Survey - Frontend Logic
 *
 * Inicializa el Zoom Apps SDK, maneja la encuesta y muestra resultados.
 */

(function () {
    'use strict';

    // ---- Estado ----
    const state = {
        meetingId: 'standalone',
        userId: 'user_' + Math.random().toString(36).substring(2, 10),
        userName: null,
        selectedAnswer: null,
        isInZoom: false,
    };

    // ---- Elementos DOM ----
    const $surveyScreen = document.getElementById('survey-screen');
    const $resultsScreen = document.getElementById('results-screen');
    const $statusBar = document.getElementById('status-bar');
    const $statusText = document.getElementById('status-text');
    const $totalVotes = document.getElementById('total-votes');
    const $btnChange = document.getElementById('btn-change');
    const $optionBtns = document.querySelectorAll('.option-btn');

    // ---- Inicializacion Zoom SDK ----
    async function initZoomSdk() {
        if (typeof zoomSdk === 'undefined') {
            setStatus('Modo standalone (fuera de Zoom)', 'connected');
            return;
        }

        try {
            const configResponse = await zoomSdk.config({
                capabilities: ['getMeetingContext'],
            });

            state.isInZoom = true;
            setStatus('Conectado a Zoom', 'connected');

            // Intentar obtener contexto de la reunion
            try {
                const context = await zoomSdk.getMeetingContext();
                if (context.meetingID) {
                    state.meetingId = context.meetingID;
                }
            } catch (e) {
                // Puede fallar si no estamos en una reunion activa
                console.log('No meeting context available:', e.message);
            }

        } catch (err) {
            setStatus('Zoom SDK no disponible - modo standalone', 'error');
            console.warn('Zoom SDK init failed:', err);
        }
    }

    // ---- Funciones de la encuesta ----

    async function submitVote(answer) {
        // Deshabilitar botones mientras se envia
        $optionBtns.forEach(btn => btn.classList.add('loading'));

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    meeting_id: state.meetingId,
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
                alert('Error al enviar respuesta: ' + (data.error || 'desconocido'));
            }
        } catch (err) {
            console.error('Error submitting vote:', err);
            alert('Error de conexion. Intenta de nuevo.');
        } finally {
            $optionBtns.forEach(btn => btn.classList.remove('loading'));
        }
    }

    async function loadResults() {
        try {
            const response = await fetch(
                'api.php?meeting_id=' + encodeURIComponent(state.meetingId)
            );
            const data = await response.json();
            showResults(data.results);
        } catch (err) {
            console.error('Error loading results:', err);
        }
    }

    function showResults(results) {
        $surveyScreen.classList.add('hidden');
        $resultsScreen.classList.remove('hidden');

        const total = results.total || 0;
        $totalVotes.textContent = total;

        for (let i = 1; i <= 4; i++) {
            const count = results.answers[i] || 0;
            const percent = total > 0 ? (count / total) * 100 : 0;
            const $row = document.querySelector(`.result-row[data-answer="${i}"]`);

            if ($row) {
                const $bar = $row.querySelector('.result-bar');
                const $count = $row.querySelector('.result-count');
                $bar.style.width = percent + '%';
                $count.textContent = count;
            }
        }
    }

    function showSurvey() {
        $resultsScreen.classList.add('hidden');
        $surveyScreen.classList.remove('hidden');

        // Marcar la opcion seleccionada previamente
        $optionBtns.forEach(btn => {
            const answer = parseInt(btn.dataset.answer);
            btn.classList.toggle('selected', answer === state.selectedAnswer);
        });
    }

    function setStatus(text, type) {
        $statusText.textContent = text;
        $statusBar.className = 'status-bar ' + (type || '');
    }

    // ---- Event Listeners ----

    $optionBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const answer = parseInt(this.dataset.answer);
            submitVote(answer);
        });
    });

    $btnChange.addEventListener('click', function () {
        showSurvey();
    });

    // ---- Auto-refresh de resultados ----
    let refreshInterval = null;

    function startAutoRefresh() {
        // Actualizar resultados cada 10 segundos cuando se muestran
        refreshInterval = setInterval(function () {
            if (!$resultsScreen.classList.contains('hidden')) {
                loadResults();
            }
        }, 10000);
    }

    // ---- Iniciar app ----
    async function init() {
        await initZoomSdk();
        startAutoRefresh();
    }

    init();
})();
