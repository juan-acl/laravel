<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRuletaAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ruleta = session('ruleta');
        $slot = (int) $this->route('slot');

        return $ruleta !== null
            && in_array($slot, $ruleta['usadas'], true)
            && isset($ruleta['opciones'][$slot])
            && ! in_array($slot, $ruleta['respondidas'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'opcion' => ['required', 'integer', 'between:0,3'],
        ];
    }
}
