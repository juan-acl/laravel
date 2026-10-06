<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#233d35">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    @vite(['resources/css/ruleta.css', 'resources/js/ruleta.js'])
    <title>Ruleta AWS · Preguntas para jugar</title>
</head>
<body
    class="game-page"
    data-groups="{{ $grupos }}"
    data-total="{{ $total }}"
    data-start-url="{{ route('ruleta.iniciar') }}"
    data-spin-url="{{ route('ruleta.girar') }}"
    data-answer-url="{{ route('ruleta.respuesta', ['slot' => '__SLOT__']) }}"
>
    <div class="scene-grid" aria-hidden="true"></div>
    <div class="ambient ambient--orange" aria-hidden="true"></div>
    <div class="ambient ambient--cyan" aria-hidden="true"></div>
    <canvas class="confetti-layer" id="confetti" aria-hidden="true"></canvas>

    <header class="topbar">
        <a class="brand" href="/" aria-label="Ruleta AWS, inicio">
            <span class="brand__mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                    <path d="M15.2 34.5h19.1a8.1 8.1 0 0 0 .7-16.2 11.4 11.4 0 0 0-21.6 2.9 6.7 6.7 0 0 0 1.8 13.3Z" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="m18.5 28 4.2-4.3 3.7 3.3 4.1-4.8" stroke="#D77C52" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="brand__copy">
                <strong>RULETA <span>AWS</span></strong>
                <small>PREGUNTAS DE AWS PARA JUGAR EN EQUIPO</small>
            </span>
        </a>

        <div class="topbar__right">
            <div class="rounds-pill" aria-live="polite">
                <span class="rounds-pill__pulse" aria-hidden="true"></span>
                <span id="quedan">7 TURNOS</span>
            </div>
            <button class="icon-button sound-toggle" id="sonido" type="button" aria-pressed="true" aria-label="Desactivar sonido">
                <svg class="sound-toggle__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M11 5 6 9H3v6h3l5 4V5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path class="sound-wave" d="M15.5 8.5a5 5 0 0 1 0 7m3-10a9 9 0 0 1 0 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path class="sound-mute" d="m16 9 5 6m0-6-5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                <span id="sonidoTexto">SONIDO: ACTIVADO</span>
            </button>
            <button class="icon-button restart-button" id="reiniciar" type="button">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M20 7v5h-5M4.8 16.5A8 8 0 0 0 19 12a8 8 0 0 0-14.2-4.9L4 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>REINICIAR</span>
            </button>
        </div>
    </header>

    <main class="game-layout">
        <section class="game-main" aria-label="Ruletas y partida">
            <section class="roulette-stage" aria-label="Ruletas del juego">
                <article class="roulette-card roulette-card--groups">
                    <div class="section-kicker">
                        <span class="section-kicker__number" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20m6-9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm6-6a3.5 3.5 0 0 1 0 6.8M17 15h1.5a3.5 3.5 0 0 1 3.5 3.5V20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </span>
                        <span class="section-kicker__label">¿QUIÉN JUEGA?</span>
                        <span class="section-kicker__line"></span>
                        <span class="section-kicker__badge">GRUPOS</span>
                    </div>
                    <div class="wheel-wrap">
                        <span class="orbit orbit--outer" aria-hidden="true"></span>
                        <span class="orbit orbit--inner" aria-hidden="true"></span>
                        <span class="pointer" aria-hidden="true"><i></i></span>
                        <div class="wheel-shell">
                            <canvas id="rGrupo" class="wheel-canvas" width="800" height="800" aria-label="Ruleta de grupos"></canvas>
                            <span class="wheel-hub" aria-hidden="true">
                                <svg viewBox="0 0 48 48" fill="none">
                                    <path d="M15.2 34.5h19.1a8.1 8.1 0 0 0 .7-16.2 11.4 11.4 0 0 0-21.6 2.9 6.7 6.7 0 0 0 1.8 13.3Z" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="m18.5 28 4.2-4.3 3.7 3.3 4.1-4.8" stroke="#D77C52" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="wheel-result wheel-result--group" aria-live="polite">
                        <span class="result-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8m0-12.8L5.6 18.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                        </span>
                        <h2 id="txtGrupo">¡Que la suerte elija!</h2>
                    </div>
                </article>

                <article class="roulette-card roulette-card--questions">
                    <div class="section-kicker">
                        <span class="section-kicker__number section-kicker__number--cyan" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M7 3.75h7l4.25 4.5v12H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v5h4m-8 4h5m-5 3h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span class="section-kicker__label">¿QUÉ PREGUNTA?</span>
                        <span class="section-kicker__line"></span>
                        <span class="section-kicker__badge section-kicker__badge--cyan">AWS</span>
                    </div>
                    <div class="wheel-wrap">
                        <span class="orbit orbit--outer orbit--cyan" aria-hidden="true"></span>
                        <span class="orbit orbit--inner orbit--cyan" aria-hidden="true"></span>
                        <span class="pointer pointer--cyan" aria-hidden="true"><i></i></span>
                        <div class="wheel-shell wheel-shell--cyan">
                            <canvas id="rPregunta" class="wheel-canvas" width="800" height="800" aria-label="Ruleta de preguntas"></canvas>
                            <span class="wheel-hub wheel-hub--cyan" aria-hidden="true">
                                <svg viewBox="0 0 48 48" fill="none">
                                    <path d="M24 5v38m19-19H5m32.4-13.4L10.6 37.4m26.8 0L10.6 10.6" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/>
                                    <circle cx="24" cy="24" r="7" fill="#233D35" stroke="#789286" stroke-width="2.5"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="wheel-result wheel-result--question" aria-live="polite">
                        <span class="result-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="m12 3 2.1 6.9L21 12l-6.9 2.1L12 21l-2.1-6.9L3 12l6.9-2.1L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                        </span>
                        <h2 id="txtNum">Reto sorpresa</h2>
                    </div>
                </article>
            </section>

            <button class="spin-button" id="girar" type="button" disabled>
                <span class="spin-button__spark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5v4m0 9v4m8.5-8.5h-4m-9 0h-4m14.5-6-2.8 2.8m-6.4 6.4L5 20m14 0-2.8-2.8m-6.4-6.4L5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.8"/></svg>
                </span>
                <span class="spin-button__label">¡GIRAR LAS DOS RULETAS!</span>
                <svg class="spin-button__arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <section class="question-card" aria-labelledby="enunciado">
                <div class="question-card__top">
                    <div class="section-kicker section-kicker--question">
                        <span class="section-kicker__number section-kicker__number--magenta" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M9 18h6m-5 3h4m-2-19a7 7 0 0 0-4 12.75c.6.4 1 1.05 1 1.75h6c0-.7.4-1.35 1-1.75A7 7 0 0 0 12 2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span class="section-kicker__label" id="turnoActual">LISTOS PARA EMPEZAR</span>
                    </div>
                    <span class="live-badge" id="estadoPartida"><i aria-hidden="true"></i> PREPARANDO LA NUBE</span>
                </div>
                <div class="question-card__body">
                    <span class="question-card__quote" aria-hidden="true">“</span>
                    <h1 id="enunciado" aria-live="polite">Reúne a tu equipo. La nube tiene un reto para ustedes.</h1>
                </div>
                <div
                    class="answer-options"
                    id="opciones"
                    role="group"
                    aria-label="Selecciona la respuesta correcta"
                    hidden
                ></div>
                <div class="answer-box" id="respuesta" aria-live="polite" hidden></div>
                <p class="error-message" id="error" role="alert" hidden></p>
                <div class="action-bar" id="acciones" hidden>
                    <button class="action-button action-button--correct" id="siguienteTurno" type="button">
                        SIGUIENTE TURNO
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="final-panel" id="finalPanel" hidden>
                    <div class="final-panel__heading">
                        <span class="final-panel__emoji" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M8 4h8v4a4 4 0 0 1-8 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4m-4 0v4m-4 4h8m-6-4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div>
                            <p class="eyebrow">MISIÓN COMPLETADA</p>
                            <h2>¡La clase conquistó la nube!</h2>
                        </div>
                    </div>
                    <div class="podium" id="podio" aria-label="Podio de puntajes"></div>
                    <ol class="final-ranking" id="rankingCompleto" aria-label="Clasificación completa"></ol>
                    <button class="play-again-button" id="otraVez" type="button">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7v5h-5M4.8 16.5A8 8 0 0 0 19 12a8 8 0 0 0-14.2-4.9L4 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        JUGAR DE NUEVO
                    </button>
                </div>
            </section>
        </section>

        <aside class="scoreboard" aria-labelledby="marcadorTitulo">
            <div class="scoreboard__header">
                <div>
                    <p class="eyebrow">LA COMPETENCIA</p>
                    <h2 id="marcadorTitulo">Marcador <span>en vivo</span></h2>
                </div>
                <span class="scoreboard__trophy" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M8 4h8v4a4 4 0 0 1-8 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4m-4 0v4m-4 4h8m-6-4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </div>
            <div class="scoreboard__summary">
                <span>7 EQUIPOS</span>
                <span class="scoreboard__dot" aria-hidden="true"></span>
                <span>UNA PREGUNTA POR GRUPO</span>
            </div>
            <div class="score-list" id="marcador" aria-live="polite" aria-relevant="additions text"></div>
            <div class="scoreboard__footer">
                <span class="scoreboard__footer-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="m13 2-9 12h7l-1 8 10-13h-7l1-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <p>¿Respuesta correcta?<br><strong>+10 puntos</strong> para tu equipo</p>
            </div>
        </aside>
    </main>

</body>
</html>
