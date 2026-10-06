<?php

test('serves the game page and application health check', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('PREGUNTAS DE AWS PARA JUGAR EN EQUIPO')
        ->assertSee('data-total="14"', false)
        ->assertSee('data-rounds="2"', false)
        ->assertSee('id="temporizador"', false)
        ->assertSee('id="girar"', false);

    $this->get('/up')->assertSuccessful();
});

test('starts a game with fourteen questions for two rounds', function () {
    $this->postJson(route('ruleta.iniciar'))
        ->assertSuccessful()
        ->assertExactJson(['total' => 14]);
});

test('does not allow a turn before starting a game', function () {
    $this->postJson(route('ruleta.girar'))
        ->assertStatus(409)
        ->assertJson(['message' => 'Partida no iniciada']);
});

test('assigns every group once per round and every question once per game', function () {
    $this->postJson(route('ruleta.iniciar'))->assertSuccessful();

    $grupos = [];
    $preguntas = [];

    for ($turno = 0; $turno < 14; $turno++) {
        $turnoRonda = ($turno % 7) + 1;
        $ronda = intdiv($turno, 7) + 1;
        $respuesta = $this->postJson(route('ruleta.girar'))
            ->assertSuccessful()
            ->assertJsonPath('quedan', 13 - $turno)
            ->assertJsonPath('ronda', $ronda)
            ->assertJsonPath('turno', $turno + 1)
            ->assertJsonPath('turnoRonda', $turnoRonda);
        $resultado = $respuesta->json();

        $grupos[] = $resultado['grupo'];
        $preguntas[] = $resultado['slot'];

        expect($resultado['opciones'])->toHaveCount(4)
            ->and(array_unique($resultado['opciones']))->toHaveCount(4)
            ->and($resultado)->not->toHaveKey('respuesta')
            ->not->toHaveKey('correcta');
        expect($resultado['gruposUsados'])->toHaveCount($turnoRonda)
            ->and(array_unique(array_slice($grupos, $turno - ($turno % 7))))->toHaveCount($turnoRonda);
    }

    expect(array_unique(array_slice($grupos, 0, 7)))->toHaveCount(7)
        ->and(array_unique(array_slice($grupos, 7, 7)))->toHaveCount(7)
        ->and(array_unique($preguntas))->toHaveCount(14);

    $this->postJson(route('ruleta.girar'))
        ->assertSuccessful()
        ->assertExactJson(['fin' => true]);
});

test('only grades an answer after its question has been selected', function () {
    $this->postJson(route('ruleta.respuesta', ['slot' => 0]), ['opcion' => 0])
        ->assertForbidden();

    $this->postJson(route('ruleta.iniciar'))->assertSuccessful();
    $turno = $this->postJson(route('ruleta.girar'))->assertSuccessful()->json();
    $partida = $this->app['session.store']->get('ruleta');
    $respuestaCorrecta = $partida['preguntas'][$turno['slot']]['respuesta'];
    $indiceCorrecto = array_search($respuestaCorrecta, $turno['opciones'], true);

    $this->postJson(route('ruleta.respuesta', ['slot' => $turno['slot']]), [
        'opcion' => $indiceCorrecto,
    ])
        ->assertSuccessful()
        ->assertJson([
            'correcta' => true,
            'opcionCorrecta' => $indiceCorrecto,
            'respuesta' => $respuestaCorrecta,
        ]);

    $this->postJson(route('ruleta.respuesta', ['slot' => $turno['slot']]), [
        'opcion' => $indiceCorrecto,
    ])->assertForbidden();
});

test('reports an incorrect choice without awarding it as correct', function () {
    $this->postJson(route('ruleta.iniciar'))->assertSuccessful();
    $turno = $this->postJson(route('ruleta.girar'))->assertSuccessful()->json();
    $partida = $this->app['session.store']->get('ruleta');
    $respuestaCorrecta = $partida['preguntas'][$turno['slot']]['respuesta'];
    $indiceIncorrecto = array_search(
        array_values(array_filter(
            $turno['opciones'],
            static fn (string $opcion): bool => $opcion !== $respuestaCorrecta,
        ))[0],
        $turno['opciones'],
        true,
    );

    $this->postJson(route('ruleta.respuesta', ['slot' => $turno['slot']]), [
        'opcion' => $indiceIncorrecto,
    ])
        ->assertSuccessful()
        ->assertJson([
            'correcta' => false,
            'respuesta' => $respuestaCorrecta,
        ]);
});

test('validates the selected option index', function () {
    $this->postJson(route('ruleta.iniciar'))->assertSuccessful();
    $turno = $this->postJson(route('ruleta.girar'))->assertSuccessful()->json();

    $this->postJson(route('ruleta.respuesta', ['slot' => $turno['slot']]), [
        'opcion' => 4,
    ])->assertUnprocessable();
});
