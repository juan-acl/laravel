<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitRuletaAnswerRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class RuletaController extends Controller
{
    private const GRUPOS = 7;

    private const RONDAS = 2;

    private const PREGUNTAS = self::GRUPOS * self::RONDAS;

    private const BANCO = [
        [
            'pregunta' => '¿Qué ofrece AWS bajo demanda por Internet?',
            'respuesta' => 'Recursos de tecnología de la información (TI).',
        ],
        [
            'pregunta' => '¿Qué cambio económico permite la computación en la nube frente a comprar infraestructura propia?',
            'respuesta' => 'Pasar de gastos de capital (CapEx) a gastos operativos (OpEx), pagando según el consumo.',
        ],
        [
            'pregunta' => '¿Qué ventaja permite crear recursos de AWS en minutos?',
            'respuesta' => 'La agilidad.',
        ],
        [
            'pregunta' => '¿Qué servicio ofrece servidores virtuales configurables?',
            'respuesta' => 'Amazon EC2.',
        ],
        [
            'pregunta' => '¿Qué servicio ejecuta código sin que el cliente administre servidores?',
            'respuesta' => 'AWS Lambda.',
        ],
        [
            'pregunta' => '¿Qué servicio ofrece almacenamiento escalable de objetos?',
            'respuesta' => 'Amazon S3.',
        ],
        [
            'pregunta' => '¿Qué servicio proporciona volúmenes de almacenamiento en bloque para instancias EC2?',
            'respuesta' => 'Amazon EBS.',
        ],
        [
            'pregunta' => '¿Qué servicio permite crear una red virtual aislada en AWS?',
            'respuesta' => 'Amazon VPC.',
        ],
        [
            'pregunta' => '¿Qué servicio administra identidades, roles y permisos de acceso?',
            'respuesta' => 'AWS IAM.',
        ],
        [
            'pregunta' => '¿Qué describe una Región de AWS?',
            'respuesta' => 'Una ubicación geográfica donde AWS agrupa su infraestructura.',
        ],
        [
            'pregunta' => '¿Qué es una Zona de Disponibilidad (AZ)?',
            'respuesta' => 'Uno o más centros de datos aislados dentro de una Región.',
        ],
        [
            'pregunta' => '¿Qué factores recomienda considerar la presentación al elegir una Región?',
            'respuesta' => 'Latencia, requisitos de datos, costo y servicios disponibles.',
        ],
        [
            'pregunta' => '¿Qué protege AWS como parte de la seguridad de la nube?',
            'respuesta' => 'Las instalaciones, el hardware, la red y la virtualización.',
        ],
        [
            'pregunta' => 'Según el modelo de responsabilidad compartida, ¿qué protege el cliente?',
            'respuesta' => 'Sus datos, identidades, aplicaciones y configuración.',
        ],
        [
            'pregunta' => '¿Qué componente permite que una subred pública tenga conexión a Internet?',
            'respuesta' => 'Un Internet Gateway.',
        ],
        [
            'pregunta' => '¿Qué controla un Security Group en la red de AWS?',
            'respuesta' => 'El tráfico de red permitido, incluidos los puertos.',
        ],
        [
            'pregunta' => '¿Qué permite hacer un snapshot de Amazon EBS?',
            'respuesta' => 'Crear una copia puntual de un volumen.',
        ],
        [
            'pregunta' => '¿Qué servicio reúne métricas, registros y alarmas casi en tiempo real?',
            'respuesta' => 'Amazon CloudWatch.',
        ],
        [
            'pregunta' => '¿Qué hace AWS Budgets según la presentación?',
            'respuesta' => 'Compara el gasto con límites y envía alertas; no ofrece datos en tiempo real.',
        ],
        [
            'pregunta' => '¿Cuál es el primer paso del despliegue de entorno descrito en la presentación?',
            'respuesta' => 'Preparar la cuenta: activar MFA, evitar usar root, crear un rol y definir un presupuesto.',
        ],
        [
            'pregunta' => '¿Qué temas incluye el curso Cloud Essentials?',
            'respuesta' => 'Fundamentos de nube, servicios, seguridad y costos.',
        ],
        [
            'pregunta' => '¿En qué se enfoca la ruta Solutions Architect que aparece en la presentación?',
            'respuesta' => 'Diseño de arquitectura, redes VPC, datos y resiliencia.',
        ],
        [
            'pregunta' => '¿Qué temas incluye la ruta Developer de AWS?',
            'respuesta' => 'Aplicaciones, Lambda, SDK, CI/CD y monitoreo.',
        ],
        [
            'pregunta' => '¿En qué se enfoca la ruta DevOps descrita en la presentación?',
            'respuesta' => 'Automatización, monitoreo y entrega continua.',
        ],
        [
            'pregunta' => '¿Cómo se puede presentar en línea el examen de certificación según la presentación?',
            'respuesta' => 'Con supervisión remota a través de Pearson VUE en línea.',
        ],
        [
            'pregunta' => 'Según la presentación, ¿cuánto cuesta el examen Foundational y cuánto dura?',
            'respuesta' => '100 dólares; dura 90 minutos y tiene 65 preguntas.',
        ],
    ];

    public function index(): View
    {
        return view('ruleta', [
            'grupos' => self::GRUPOS,
            'total' => self::PREGUNTAS,
            'rondas' => self::RONDAS,
        ]);
    }

    public function iniciar(): JsonResponse
    {
        $indices = range(0, count(self::BANCO) - 1);
        shuffle($indices);

        $preguntas = array_map(
            static fn (int $indice): array => self::BANCO[$indice],
            array_slice($indices, 0, self::PREGUNTAS),
        );

        session(['ruleta' => [
            'preguntas' => $preguntas,
            'usadas' => [],
            'grupos_usados' => [],
            'opciones' => [],
            'respondidas' => [],
            'turnos_jugados' => 0,
        ]]);

        return response()->json(['total' => count($preguntas)]);
    }

    public function girar(): JsonResponse
    {
        $ruleta = session('ruleta');
        abort_if(! $ruleta, 409, 'Partida no iniciada');

        $turnosJugados = $ruleta['turnos_jugados'];
        if ($turnosJugados >= self::PREGUNTAS) {
            return response()->json(['fin' => true]);
        }

        $gruposLibres = array_values(array_diff(
            range(0, self::GRUPOS - 1),
            $ruleta['grupos_usados'],
        ));
        $preguntasLibres = array_values(array_diff(
            array_keys($ruleta['preguntas']),
            $ruleta['usadas'],
        ));

        if (! $gruposLibres || ! $preguntasLibres) {
            return response()->json(['fin' => true]);
        }

        $grupo = $gruposLibres[array_rand($gruposLibres)];
        $slot = $preguntasLibres[array_rand($preguntasLibres)];
        $respuestaCorrecta = $ruleta['preguntas'][$slot]['respuesta'];
        $respuestasIncorrectas = array_values(array_diff(
            array_column($ruleta['preguntas'], 'respuesta'),
            [$respuestaCorrecta],
        ));
        shuffle($respuestasIncorrectas);
        $opciones = array_slice($respuestasIncorrectas, 0, 3);
        $opciones[] = $respuestaCorrecta;
        shuffle($opciones);

        $gruposUsados = [...$ruleta['grupos_usados'], $grupo];
        $ruleta['usadas'][] = $slot;
        $ruleta['opciones'][$slot] = $opciones;
        $ruleta['turnos_jugados']++;
        $ruleta['grupos_usados'] = count($gruposUsados) === self::GRUPOS
            ? []
            : $gruposUsados;
        session(['ruleta' => $ruleta]);

        return response()->json([
            'grupo' => $grupo,
            'slot' => $slot,
            'ronda' => intdiv($turnosJugados, self::GRUPOS) + 1,
            'turno' => $turnosJugados + 1,
            'turnoRonda' => ($turnosJugados % self::GRUPOS) + 1,
            'pregunta' => $ruleta['preguntas'][$slot]['pregunta'],
            'opciones' => $opciones,
            'quedan' => self::PREGUNTAS - $ruleta['turnos_jugados'],
            'gruposUsados' => $gruposUsados,
        ]);
    }

    public function respuesta(SubmitRuletaAnswerRequest $request, int $slot): JsonResponse
    {
        $ruleta = session('ruleta');
        $opciones = $ruleta['opciones'][$slot];
        $opcionSeleccionada = (int) $request->validated('opcion');
        $respuestaCorrecta = $ruleta['preguntas'][$slot]['respuesta'];
        $opcionCorrecta = array_search($respuestaCorrecta, $opciones, true);

        $ruleta['respondidas'][] = $slot;
        session(['ruleta' => $ruleta]);

        return response()->json([
            'correcta' => $opciones[$opcionSeleccionada] === $respuestaCorrecta,
            'opcionCorrecta' => $opcionCorrecta,
            'respuesta' => $respuestaCorrecta,
        ]);
    }
}
