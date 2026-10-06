const page = document.body;
const groupCount = Number(page.dataset.groups);
let questionCount = Number(page.dataset.total);
const teamNumbers = [1, 2, 3, 4, 5, 6, 8];
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const routes = {
    start: page.dataset.startUrl,
    spin: page.dataset.spinUrl,
    answer: page.dataset.answerUrl,
};

const teamColors = [
    '#B85C43',
    '#527A72',
    '#98734A',
    '#758665',
    '#C17A56',
    '#667C8A',
    '#A58A58',
];
const questionColors = [
    '#607B82',
    '#8A6955',
    '#71816A',
    '#98715F',
    '#64788A',
    '#8B805D',
    '#6F7B72',
];
const score = Array(groupCount).fill(0);
const groupWheel = document.getElementById('rGrupo');
const questionWheel = document.getElementById('rPregunta');
const groupContext = groupWheel.getContext('2d');
const questionContext = questionWheel.getContext('2d');
const spinButton = document.getElementById('girar');
const spinButtonLabel = spinButton.querySelector('.spin-button__label');
const restartButton = document.getElementById('reiniciar');
const soundButton = document.getElementById('sonido');
const soundLabel = document.getElementById('sonidoTexto');
const actions = document.getElementById('acciones');
const answerOptions = document.getElementById('opciones');
const answerBox = document.getElementById('respuesta');
const errorBox = document.getElementById('error');
const questionHeading = document.getElementById('enunciado');
const gameStatus = document.getElementById('estadoPartida');
const groupWheelWrap = groupWheel.closest('.wheel-wrap');
const questionWheelWrap = questionWheel.closest('.wheel-wrap');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

let gruposLibres = Array.from({ length: groupCount }, (_, index) => index);
let slotsLibres = Array.from({ length: questionCount }, (_, index) => index);
let rotationGroups = 0;
let rotationQuestions = 0;
let currentTurn = null;
let currentGroup = null;
let isFinished = false;
let isSpinning = false;
let soundEnabled = localStorage.getItem('ruleta-sound') !== 'off';
let audioContext = null;
let tickTimer = null;
let confettiFrame = null;
let confettiParticles = [];

function getContrastColor(hexColor) {
    const color = hexColor.slice(1);
    const channels = [0, 2, 4].map((offset) => {
        const value =
            Number.parseInt(color.slice(offset, offset + 2), 16) / 255;

        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });
    const luminance =
        channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    const darkTextLuminance = 0.008;
    const contrastWithWhite = 1.05 / (luminance + 0.05);
    const contrastWithDark = (luminance + 0.05) / (darkTextLuminance + 0.05);

    return contrastWithWhite > contrastWithDark ? '#FFFFFF' : '#101724';
}

function drawWheel(context, canvas, segments, labelFor, colorFor, accentColor) {
    const radius = canvas.width / 2;
    const center = radius;
    const wheel = canvas.closest('.wheel-wrap');
    const wheelName =
        canvas === groupWheel ? 'Ruleta de grupos' : 'Ruleta de preguntas';

    canvas.dataset.remaining = String(segments.length);
    canvas.setAttribute(
        'aria-label',
        `${wheelName}: ${
            segments.length > 0 ? segments.map(labelFor).join(', ') : 'Fin'
        }`,
    );
    wheel.classList.toggle('is-single', segments.length === 1);
    wheel.classList.toggle('is-empty', segments.length === 0);
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.save();
    context.translate(center, center);
    context.beginPath();
    context.arc(0, 0, radius - 3, 0, Math.PI * 2);
    context.fillStyle = '#233D35';
    context.fill();
    context.lineWidth = 4;
    context.strokeStyle = accentColor;
    context.stroke();

    if (segments.length === 0) {
        context.fillStyle = '#F6F8FC';
        context.shadowColor = accentColor;
        context.shadowBlur = 24;
        context.font = '700 70px "DM Sans", sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText('FIN', 0, 0);
        context.restore();

        return;
    }

    if (segments.length === 1) {
        const fill = colorFor(segments[0], 0);

        context.beginPath();
        context.arc(0, 0, radius - 8, 0, Math.PI * 2);
        context.fillStyle = fill;
        context.fill();
        context.lineWidth = 6;
        context.strokeStyle = accentColor;
        context.stroke();
        context.fillStyle = getContrastColor(fill);
        context.font = '700 78px "DM Sans", sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.shadowColor = 'rgba(0, 0, 0, .42)';
        context.shadowBlur = 8;
        context.fillText(labelFor(segments[0]), 0, 0);
        context.restore();

        return;
    }

    const angle = (Math.PI * 2) / segments.length;

    segments.forEach((segment, index) => {
        const startAngle = -Math.PI / 2 + index * angle;
        const endAngle = startAngle + angle;
        const fill = colorFor(segment, index);

        context.beginPath();
        context.moveTo(0, 0);
        context.arc(0, 0, radius - 8, startAngle, endAngle);
        context.closePath();
        context.fillStyle = fill;
        context.fill();
        context.lineWidth = 5;
        context.strokeStyle = '#F4F0E8';
        context.stroke();
        context.save();
        const midpoint = startAngle + angle / 2;
        const label = labelFor(segment);
        const labelRadius = radius * 0.66;
        const maxTextWidth = 2 * labelRadius * Math.sin(angle / 2) * 0.74;
        let fontSize = Math.min(64, Math.max(34, (radius * angle * 0.45) | 0));

        context.translate(
            Math.cos(midpoint) * labelRadius,
            Math.sin(midpoint) * labelRadius,
        );
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillStyle = getContrastColor(fill);
        context.shadowColor = 'rgba(0, 0, 0, .42)';
        context.shadowBlur = 5;
        context.font = `700 ${fontSize}px "DM Sans", sans-serif`;

        while (
            fontSize > 32 &&
            context.measureText(label).width > maxTextWidth
        ) {
            fontSize -= 2;
            context.font = `700 ${fontSize}px "DM Sans", sans-serif`;
        }

        context.fillText(label, 0, 0);
        context.restore();
    });

    context.beginPath();
    context.arc(0, 0, radius * 0.14, 0, Math.PI * 2);
    context.fillStyle = '#233D35';
    context.fill();
    context.lineWidth = 5;
    context.strokeStyle = '#F4F0E8';
    context.stroke();
    context.restore();
}

function drawGroupWheel() {
    drawWheel(
        groupContext,
        groupWheel,
        gruposLibres,
        (group) => `G${teamNumbers[group]}`,
        (group) => teamColors[group % teamColors.length],
        '#B85C43',
    );
}

function drawQuestionWheel() {
    drawWheel(
        questionContext,
        questionWheel,
        slotsLibres,
        (slot) => String(slot + 1),
        (slot) => questionColors[slot % questionColors.length],
        '#718B81',
    );
}

function drawWheels() {
    [groupWheel, questionWheel].forEach((wheel) => {
        wheel.style.transition = 'none';
        wheel.style.transform = 'rotate(0deg)';
    });
    rotationGroups = 0;
    rotationQuestions = 0;
    drawGroupWheel();
    drawQuestionWheel();
    void groupWheel.offsetWidth;
    groupWheel.style.removeProperty('transition');
    questionWheel.style.removeProperty('transition');
}

function calculateRotation(rotation, index, segmentCount) {
    const segmentAngle = 360 / segmentCount;
    const target = -(index + 0.5) * segmentAngle;
    const remainder = (((target - rotation) % 360) + 360) % 360;
    const fullRotations = reducedMotion.matches ? 2 : 6;

    return rotation + 360 * fullRotations + remainder;
}

function createScoreRow(points, index, leading) {
    const row = document.createElement('article');
    row.className = [
        'score-row',
        currentGroup === index ? 'is-active' : '',
        leading ? 'is-leader' : '',
    ]
        .filter(Boolean)
        .join(' ');
    row.style.setProperty(
        '--team-color',
        teamColors[index % teamColors.length],
    );

    const avatar = document.createElement('span');
    avatar.className = 'score-row__avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = `G${teamNumbers[index]}`;

    const meta = document.createElement('span');
    meta.className = 'score-row__meta';

    const name = document.createElement('span');
    name.className = 'score-row__name';
    name.append(document.createTextNode(`GRUPO ${teamNumbers[index]}`));

    if (leading) {
        const medal = document.createElement('span');
        medal.className = 'score-row__leader';
        medal.textContent = '♛';
        medal.setAttribute('aria-label', 'Líder');
        name.append(medal);
    }

    const status = document.createElement('span');
    status.className = 'score-row__turn';
    status.textContent =
        currentGroup === index
            ? 'EN TURNO'
            : gruposLibres.includes(index)
              ? 'LISTO PARA JUGAR'
              : 'TURNO COMPLETADO';
    meta.append(name, status);

    const right = document.createElement('span');
    right.className = 'score-row__right';

    const pointsLabel = document.createElement('span');
    pointsLabel.className = 'score-row__points';
    pointsLabel.textContent = String(points);
    pointsLabel.setAttribute('aria-label', `${points} puntos`);

    const controls = document.createElement('span');
    controls.className = 'score-row__controls';

    [
        {
            label: `Restar 10 puntos al grupo ${teamNumbers[index]}`,
            symbol: '−',
            change: -10,
        },
        {
            label: `Sumar 10 puntos al grupo ${teamNumbers[index]}`,
            symbol: '+',
            change: 10,
        },
    ].forEach(({ label, symbol, change }) => {
        const button = document.createElement('button');
        button.className = 'score-control';
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.textContent = symbol;
        button.addEventListener('click', () =>
            changeScore(index, change, button),
        );
        controls.append(button);
    });

    right.append(pointsLabel, controls);
    row.append(avatar, meta, right);

    return row;
}

function renderScoreboard() {
    const scoreboard = document.getElementById('marcador');
    const highestScore = Math.max(...score);
    scoreboard.replaceChildren();
    scoreboard.append(
        ...score.map((points, index) =>
            createScoreRow(
                points,
                index,
                highestScore > 0 && points === highestScore,
            ),
        ),
    );
}

function showPointsAnimation(index, button) {
    const row = button?.closest('.score-row');
    const origin =
        row?.getBoundingClientRect() ??
        document.querySelectorAll('.score-row')[index]?.getBoundingClientRect();
    const pop = document.createElement('span');
    pop.className = 'score-pop';
    pop.textContent = '+10';

    if (origin) {
        pop.style.left = `${origin.left + origin.width * 0.68}px`;
        pop.style.top = `${origin.top + origin.height * 0.24}px`;
    } else {
        pop.style.left = '50%';
        pop.style.top = '50%';
    }

    document.body.append(pop);
    pop.addEventListener('animationend', () => pop.remove(), { once: true });
}

function changeScore(index, change, button = null) {
    score[index] += change;

    if (change > 0) {
        showPointsAnimation(index, button);
        playCorrectSound();
        celebrate();
    }

    renderScoreboard();
}

async function requestJson(url, method = 'GET', body = null) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 15000);
    let response;

    try {
        response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                ...(method === 'POST' ? { 'X-CSRF-TOKEN': csrf } : {}),
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
            signal: controller.signal,
        });
    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error(
                'El servidor tardó demasiado en responder. Comprueba la conexión e inténtalo de nuevo.',
            );
        }

        throw new Error(
            'No se pudo conectar con el servidor. Comprueba la conexión e inténtalo de nuevo.',
        );
    } finally {
        window.clearTimeout(timeout);
    }

    let payload;

    try {
        payload = await response.json();
    } catch {
        throw new Error('El servidor devolvió una respuesta inesperada.');
    }

    if (!response.ok) {
        throw new Error(
            payload.message || `La solicitud falló (${response.status}).`,
        );
    }

    return payload;
}

function setError(message = '') {
    errorBox.textContent = message;
    errorBox.hidden = message.length === 0;
}

function setGameStatus(label, active = false) {
    gameStatus.classList.toggle('is-active', active);
    gameStatus.lastChild.textContent = ` ${label}`;
}

function updateSoundButton() {
    soundButton.setAttribute('aria-pressed', String(soundEnabled));
    soundButton.setAttribute(
        'aria-label',
        soundEnabled ? 'Desactivar sonido' : 'Activar sonido',
    );
    soundLabel.textContent = `SONIDO: ${soundEnabled ? 'ACTIVADO' : 'DESACTIVADO'}`;
}

function getAudioContext() {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;

    if (!AudioContextClass) {
        return null;
    }

    audioContext ??= new AudioContextClass();

    if (audioContext.state === 'suspended') {
        void audioContext.resume();
    }

    return audioContext;
}

function playTone(
    frequency,
    duration,
    type = 'sine',
    volume = 0.08,
    delay = 0,
) {
    if (!soundEnabled) {
        return;
    }

    const audio = getAudioContext();

    if (!audio) {
        return;
    }

    const oscillator = audio.createOscillator();
    const gain = audio.createGain();
    const startAt = audio.currentTime + delay;
    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, startAt);
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
    oscillator.connect(gain);
    gain.connect(audio.destination);
    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.025);
}

function startSpinSound() {
    stopSpinSound();

    if (!soundEnabled) {
        return;
    }

    getAudioContext();
    let tick = 0;
    tickTimer = window.setInterval(() => {
        playTone(520 + (tick % 5) * 36, 0.035, 'square', 0.018);
        tick++;
    }, 170);
}

function stopSpinSound() {
    if (tickTimer !== null) {
        window.clearInterval(tickTimer);
        tickTimer = null;
    }
}

function playStopSound() {
    playTone(440, 0.12, 'sine', 0.09);
    playTone(660, 0.17, 'sine', 0.07, 0.09);
}

function playCorrectSound() {
    playTone(523.25, 0.2, 'sine', 0.09);
    playTone(659.25, 0.2, 'sine', 0.08, 0.11);
    playTone(783.99, 0.28, 'sine', 0.075, 0.22);
}

function launchConfetti() {
    const canvas = document.getElementById('confetti');
    const context = canvas.getContext('2d');
    const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
    const width = window.innerWidth;
    const height = window.innerHeight;
    const colors = [...teamColors, '#FFFFFF'];

    canvas.width = width * pixelRatio;
    canvas.height = height * pixelRatio;
    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    confettiParticles = Array.from({ length: 125 }, () => ({
        x: width * (0.18 + Math.random() * 0.64),
        y: height * (0.19 + Math.random() * 0.21),
        speedX: (Math.random() - 0.5) * 7,
        speedY: -3 - Math.random() * 8,
        gravity: 0.12 + Math.random() * 0.13,
        size: 4 + Math.random() * 6,
        rotation: Math.random() * Math.PI,
        rotationSpeed: (Math.random() - 0.5) * 0.22,
        color: colors[Math.floor(Math.random() * colors.length)],
        opacity: 1,
    }));

    if (confettiFrame !== null) {
        window.cancelAnimationFrame(confettiFrame);
    }

    function renderConfetti() {
        context.clearRect(0, 0, width, height);

        confettiParticles.forEach((particle) => {
            particle.x += particle.speedX;
            particle.y += particle.speedY;
            particle.speedY += particle.gravity;
            particle.rotation += particle.rotationSpeed;
            particle.opacity -= 0.004;
            context.save();
            context.translate(particle.x, particle.y);
            context.rotate(particle.rotation);
            context.globalAlpha = Math.max(0, particle.opacity);
            context.fillStyle = particle.color;
            context.fillRect(
                -particle.size / 2,
                -particle.size / 2,
                particle.size,
                particle.size * 0.64,
            );
            context.restore();
        });

        confettiParticles = confettiParticles.filter(
            (particle) => particle.y < height + 25 && particle.opacity > 0,
        );

        if (confettiParticles.length > 0) {
            confettiFrame = window.requestAnimationFrame(renderConfetti);
        } else {
            context.clearRect(0, 0, width, height);
            confettiFrame = null;
        }
    }

    confettiFrame = window.requestAnimationFrame(renderConfetti);
}

function celebrate() {
    if (!reducedMotion.matches) {
        launchConfetti();
    }

    [groupWheelWrap, questionWheelWrap].forEach((wheel) => {
        wheel.classList.remove('is-stopped');
        void wheel.offsetWidth;
        wheel.classList.add('is-stopped');
        window.setTimeout(() => wheel.classList.remove('is-stopped'), 750);
    });
}

function resetQuestionCard() {
    questionHeading.classList.remove('is-entering');
    void questionHeading.offsetWidth;
    questionHeading.classList.add('is-entering');
}

function setRemainingCount() {
    const remaining = Math.min(gruposLibres.length, slotsLibres.length);
    document.getElementById('quedan').textContent =
        `${remaining} ${remaining === 1 ? 'TURNO' : 'TURNOS'}`;
}

function renderFinalRanking() {
    const sortedGroups = score
        .map((points, index) => ({ points, index }))
        .sort(
            (first, second) =>
                second.points - first.points || first.index - second.index,
        );
    const positions = [
        { index: 1, className: 'podium-card--second', medal: '🥈' },
        { index: 0, className: 'podium-card--first', medal: '🥇' },
        { index: 2, className: 'podium-card--third', medal: '🥉' },
    ];
    const podium = document.getElementById('podio');
    const ranking = document.getElementById('rankingCompleto');
    podium.replaceChildren();
    ranking.replaceChildren();

    positions.forEach(({ index, className, medal }) => {
        const group = sortedGroups[index];
        const card = document.createElement('div');
        card.className = `podium-card ${className}`;
        const medalIcon = document.createElement('span');
        medalIcon.className = 'podium-card__medal';
        medalIcon.setAttribute('aria-hidden', 'true');
        medalIcon.textContent = medal;
        const label = document.createElement('strong');
        label.textContent = `GRUPO ${teamNumbers[group.index]}`;
        const points = document.createElement('span');
        points.className = 'podium-card__points';
        points.textContent = `${group.points} PTS`;
        card.append(medalIcon, label, points);
        podium.append(card);
    });

    sortedGroups.forEach(({ points, index }, position) => {
        const item = document.createElement('li');
        const rank = document.createElement('span');
        rank.className = 'final-ranking__rank';
        rank.textContent = `#${position + 1}`;
        const name = document.createElement('span');
        name.textContent = `GRUPO ${teamNumbers[index]}`;
        const total = document.createElement('span');
        total.className = 'final-ranking__score';
        total.textContent = `${points} PTS`;
        item.append(rank, name, total);
        ranking.append(item);
    });
}

function finishGame() {
    isFinished = true;
    isSpinning = false;
    currentGroup = null;
    renderScoreboard();
    renderFinalRanking();
    actions.hidden = true;
    answerOptions.hidden = true;
    answerOptions.replaceChildren();
    answerBox.hidden = true;
    answerBox.classList.remove('is-correct', 'is-incorrect');
    document.getElementById('finalPanel').hidden = false;
    questionHeading.textContent =
        '¡Ya pasaron todos los grupos! Fin del juego.';
    questionHeading.hidden = true;
    document.querySelector('.question-card__quote').hidden = true;
    document.getElementById('txtGrupo').textContent = '¡Todos jugaron!';
    document.getElementById('txtNum').textContent = 'Fin del juego';
    setGameStatus('MISIÓN COMPLETADA');
    spinButton.disabled = true;
    spinButton.classList.remove('is-ready', 'is-spinning');
    spinButtonLabel.textContent = '¡PARTIDA COMPLETADA!';
    document.getElementById('otraVez').focus();

    if (!reducedMotion.matches) {
        launchConfetti();
    }
}

async function startGame() {
    if (isSpinning) {
        return;
    }

    spinButton.disabled = true;
    restartButton.disabled = true;
    spinButton.classList.remove('is-ready');
    setError();

    try {
        const game = await requestJson(routes.start, 'POST');
        questionCount = game.total;
        gruposLibres = Array.from({ length: groupCount }, (_, index) => index);
        slotsLibres = Array.from(
            { length: questionCount },
            (_, index) => index,
        );
        currentTurn = null;
        currentGroup = null;
        isFinished = false;
        isSpinning = false;
        score.fill(0);
        drawWheels();
        renderScoreboard();
        setRemainingCount();
        actions.hidden = true;
        answerOptions.hidden = true;
        answerOptions.replaceChildren();
        answerBox.hidden = true;
        answerBox.classList.remove('is-correct', 'is-incorrect');
        answerBox.textContent = '';
        document.getElementById('finalPanel').hidden = true;
        questionHeading.hidden = false;
        questionHeading.textContent =
            'Reúne a tu equipo. La nube tiene un reto para ustedes.';
        document.getElementById('turnoActual').textContent =
            'LISTOS PARA EMPEZAR';
        document.querySelector('.question-card__quote').hidden = false;
        document.getElementById('txtGrupo').textContent =
            '¡Que la suerte elija!';
        document.getElementById('txtNum').textContent = 'Reto sorpresa';
        spinButtonLabel.textContent = '¡GIRAR LAS DOS RULETAS!';
        setGameStatus('PARTIDA LISTA');
        spinButton.disabled = false;
        spinButton.classList.add('is-ready');
        resetQuestionCard();
    } catch (error) {
        setError(error.message);
        setGameStatus('ERROR DE CONEXIÓN', true);
    } finally {
        restartButton.disabled = false;
    }
}

function waitForWheelStop(wheel) {
    const computedStyle = window.getComputedStyle(wheel);
    const durations = computedStyle.transitionDuration
        .split(',')
        .map((value) => {
            const duration = Number.parseFloat(value);

            return value.trim().endsWith('ms') ? duration : duration * 1000;
        });
    const delays = computedStyle.transitionDelay.split(',').map((value) => {
        const delay = Number.parseFloat(value);

        return value.trim().endsWith('ms') ? delay : delay * 1000;
    });
    const waitMs = Math.max(
        ...durations.map((duration, index) => duration + (delays[index] ?? 0)),
        0,
    );

    return new Promise((resolve) => {
        const timeout = window.setTimeout(finish, waitMs + 150);

        function finish() {
            window.clearTimeout(timeout);
            wheel.removeEventListener('transitionend', handleTransitionEnd);
            resolve();
        }

        function handleTransitionEnd(event) {
            if (event.target === wheel && event.propertyName === 'transform') {
                finish();
            }
        }

        wheel.addEventListener('transitionend', handleTransitionEnd);
    });
}

function stopPendingSpin(wheel, reset = false) {
    const transform = window.getComputedStyle(wheel).transform;
    const matrix =
        transform === 'none'
            ? new DOMMatrixReadOnly()
            : new DOMMatrixReadOnly(transform);
    const angle = reset
        ? 0
        : (Math.atan2(matrix.b, matrix.a) * (180 / Math.PI) + 360) % 360;

    wheel.closest('.wheel-wrap').classList.remove('is-pending');
    wheel.style.transition = 'none';
    wheel.style.transform = `rotate(${angle}deg)`;
    void wheel.offsetWidth;
    wheel.style.removeProperty('transition');

    return angle;
}

function removeTurnFromWheels(group, slot) {
    const groupIndex = gruposLibres.indexOf(group);
    const questionIndex = slotsLibres.indexOf(slot);

    if (groupIndex === -1 || questionIndex === -1) {
        throw new Error(
            'No se puede retirar un grupo o pregunta que ya salió.',
        );
    }

    gruposLibres.splice(groupIndex, 1);
    slotsLibres.splice(questionIndex, 1);
    drawWheels();
}

async function spinWheels() {
    if (
        isSpinning ||
        isFinished ||
        gruposLibres.length === 0 ||
        slotsLibres.length === 0
    ) {
        return;
    }

    isSpinning = true;
    spinButton.disabled = true;
    spinButton.classList.remove('is-ready');
    spinButton.classList.add('is-spinning');
    spinButtonLabel.textContent = 'SORTEANDO GRUPO Y PREGUNTA…';
    actions.hidden = true;
    answerOptions.hidden = true;
    answerOptions.replaceChildren();
    answerBox.hidden = true;
    answerBox.classList.remove('is-correct', 'is-incorrect');
    answerBox.textContent = '';
    groupWheelWrap.classList.add('is-pending');
    questionWheelWrap.classList.add('is-pending');
    setError();
    setGameStatus('¡RULETAS GIRANDO!', true);
    startSpinSound();

    try {
        const turn = await requestJson(routes.spin, 'POST');

        if (turn.fin) {
            stopPendingSpin(groupWheel, true);
            stopPendingSpin(questionWheel, true);
            stopSpinSound();
            playStopSound();
            finishGame();

            return;
        }

        const groupIndex = gruposLibres.indexOf(turn.grupo);
        const questionIndex = slotsLibres.indexOf(turn.slot);

        if (
            !Number.isInteger(turn.grupo) ||
            !Number.isInteger(turn.slot) ||
            groupIndex < 0 ||
            questionIndex < 0 ||
            !Array.isArray(turn.opciones) ||
            turn.opciones.length !== 4 ||
            turn.opciones.some((option) => typeof option !== 'string') ||
            new Set(turn.opciones).size !== turn.opciones.length
        ) {
            throw new Error(
                'El servidor devolvió un turno u opciones de respuesta inválidas.',
            );
        }

        rotationGroups = calculateRotation(
            stopPendingSpin(groupWheel),
            groupIndex,
            gruposLibres.length,
        );
        rotationQuestions = calculateRotation(
            stopPendingSpin(questionWheel),
            questionIndex,
            slotsLibres.length,
        );
        groupWheel.style.transform = `rotate(${rotationGroups}deg)`;
        questionWheel.style.transform = `rotate(${rotationQuestions}deg)`;
        await Promise.all([
            waitForWheelStop(groupWheel),
            waitForWheelStop(questionWheel),
        ]);
        stopSpinSound();
        playStopSound();
        removeTurnFromWheels(turn.grupo, turn.slot);
        currentTurn = { ...turn, responded: false };
        currentGroup = turn.grupo;
        const turnoActual = groupCount - gruposLibres.length;
        document.getElementById('txtGrupo').textContent =
            `TURNO ${turnoActual}: GRUPO G${teamNumbers[turn.grupo]}`;
        document.getElementById('turnoActual').textContent =
            `TURNO ${turnoActual} DE ${groupCount}`;
        document.getElementById('txtNum').textContent =
            `PREGUNTA ${turn.slot + 1}`;
        questionHeading.textContent = turn.pregunta;
        questionHeading.hidden = false;
        document.querySelector('.question-card__quote').hidden = false;
        document.getElementById('finalPanel').hidden = true;
        answerBox.hidden = true;
        actions.hidden = true;
        renderAnswerOptions(turn.opciones);
        renderScoreboard();
        setRemainingCount();
        setGameStatus('EQUIPO EN JUEGO');
        spinButton.classList.remove('is-spinning');
        spinButtonLabel.textContent = '¡GIRAR LAS DOS RULETAS!';
        isSpinning = false;
        restartButton.disabled = false;
        resetQuestionCard();
        celebrate();
    } catch (error) {
        stopPendingSpin(groupWheel, true);
        stopPendingSpin(questionWheel, true);
        rotationGroups = 0;
        rotationQuestions = 0;
        stopSpinSound();
        spinButton.classList.remove('is-spinning');
        spinButtonLabel.textContent = '¡GIRAR LAS DOS RULETAS!';
        isSpinning = false;
        spinButton.disabled = false;
        spinButton.classList.add('is-ready');
        restartButton.disabled = false;
        setError(error.message);
        setGameStatus('INTÉNTALO DE NUEVO', true);
    }
}

function renderAnswerOptions(options) {
    answerOptions.replaceChildren();

    options.forEach((option, index) => {
        const button = document.createElement('button');
        button.className = 'answer-option';
        button.type = 'button';
        button.dataset.option = String(index);

        const letter = document.createElement('span');
        letter.className = 'answer-option__letter';
        letter.setAttribute('aria-hidden', 'true');
        letter.textContent = String.fromCharCode(65 + index);

        const text = document.createElement('span');
        text.className = 'answer-option__text';
        text.textContent = option;

        button.append(letter, text);
        button.addEventListener('click', () => submitAnswer(index));
        answerOptions.append(button);
    });

    answerOptions.hidden = false;
}

async function submitAnswer(optionIndex) {
    if (!currentTurn || currentTurn.responded) {
        return;
    }

    const options = [...answerOptions.querySelectorAll('.answer-option')];
    options.forEach((option) => {
        option.disabled = true;
    });
    setError();

    try {
        const answerUrl = routes.answer.replace(
            '__SLOT__',
            String(currentTurn.slot),
        );
        const result = await requestJson(answerUrl, 'POST', {
            opcion: optionIndex,
        });

        if (
            typeof result.correcta !== 'boolean' ||
            !Number.isInteger(result.opcionCorrecta) ||
            result.opcionCorrecta < 0 ||
            result.opcionCorrecta >= options.length ||
            typeof result.respuesta !== 'string'
        ) {
            throw new Error(
                'El servidor devolvió una evaluación de respuesta inválida.',
            );
        }

        currentTurn.responded = true;
        options[result.opcionCorrecta]?.classList.add('is-correct');

        if (result.correcta) {
            options[optionIndex]?.classList.add('is-selected-correct');
            changeScore(currentTurn.grupo, 10);
            answerBox.classList.add('is-correct');
            answerBox.textContent = `¡Correcto! El grupo gana 10 puntos. Respuesta: ${result.respuesta}`;
            setGameStatus('¡RESPUESTA CORRECTA!');
        } else {
            options[optionIndex]?.classList.add('is-selected-wrong');
            answerBox.classList.add('is-incorrect');
            answerBox.textContent = `No es correcto. La respuesta era: ${result.respuesta}`;
            setGameStatus('RESPUESTA INCORRECTA');
        }

        answerBox.hidden = false;
        actions.hidden = false;
        document.getElementById('siguienteTurno').focus();
    } catch (error) {
        options.forEach((option) => {
            option.disabled = false;
        });
        setError(error.message);
    }
}

function nextTurn() {
    if (!currentTurn || !currentTurn.responded) {
        return;
    }

    actions.hidden = true;
    answerOptions.hidden = true;
    answerBox.hidden = true;

    if (currentTurn.quedan === 0 || gruposLibres.length === 0) {
        finishGame();

        return;
    }

    currentGroup = null;
    renderScoreboard();
    spinButton.disabled = false;
    spinButton.classList.add('is-ready');
    setGameStatus('SIGUIENTE TURNO');
}

spinButton.addEventListener('click', spinWheels);

document.getElementById('siguienteTurno').addEventListener('click', nextTurn);

soundButton.addEventListener('click', () => {
    soundEnabled = !soundEnabled;
    localStorage.setItem('ruleta-sound', soundEnabled ? 'on' : 'off');
    updateSoundButton();

    if (soundEnabled) {
        playTone(660, 0.1, 'sine', 0.06);
    } else {
        stopSpinSound();
    }
});

restartButton.addEventListener('click', () => {
    const hasPlayed = currentTurn !== null || gruposLibres.length < groupCount;

    if (
        !hasPlayed ||
        isFinished ||
        window.confirm(
            '¿Reiniciar la partida? Se perderán los puntos actuales.',
        )
    ) {
        void startGame();
    }
});

document
    .getElementById('otraVez')
    .addEventListener('click', () => void startGame());

window.addEventListener('resize', () => {
    if (confettiParticles.length === 0) {
        return;
    }

    if (confettiFrame !== null) {
        window.cancelAnimationFrame(confettiFrame);
        confettiFrame = null;
    }

    confettiParticles = [];
    const context = document.getElementById('confetti').getContext('2d');
    context.clearRect(0, 0, window.innerWidth, window.innerHeight);
});

updateSoundButton();
drawGroupWheel();
drawQuestionWheel();
renderScoreboard();
void startGame();
