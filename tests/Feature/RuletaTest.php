<?php

test('serves the game page and application health check', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('PREGUNTAS DE AWS PARA JUGAR EN EQUIPO')
        ->assertSee('id="girar"', false);

    $this->get('/up')->assertSuccessful();
});

test('starts a game with seven questions from the code bank', function () {
    $this->postJson(route('ruleta.iniciar'))
        ->assertSuccessful()
        ->assertExactJson(['total' => 7]);
});

test('does not allow a turn before starting a game', function () {
    $this->postJson(route('ruleta.girar'))
        ->assertStatus(409)
        ->assertJson(['message' => 'Partida no iniciada']);
});

test('assigns every group and question exactly once per game', function () {
    $this->postJson(route('ruleta.iniciar'))->assertSuccessful();

    $grupos = [];
    $preguntas = [];

    for ($turno = 0; $turno < 7; $turno++) {
        $respuesta = $this->postJson(route('ruleta.girar'))
            ->assertSuccessful()
            ->assertJsonPath('quedan', 6 - $turno);
        $resultado = $respuesta->json();

        $grupos[] = $resultado['grupo'];
        $preguntas[] = $resultado['slot'];

        expect($resultado['opciones'])->toHaveCount(4)
            ->and(array_unique($resultado['opciones']))->toHaveCount(4)
            ->and($resultado)->not->toHaveKey('respuesta')
            ->not->toHaveKey('correcta');
        expect($resultado['gruposUsados'])->toHaveCount($turno + 1);
    }

    expect(array_unique($grupos))->toHaveCount(7)
        ->and(array_unique($preguntas))->toHaveCount(7);

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
