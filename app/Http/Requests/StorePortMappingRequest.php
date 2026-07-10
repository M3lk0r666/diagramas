<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePortMappingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                  => 'required|string|max:120',
            'ip'                    => 'nullable|ip',
            'origin_config'         => 'required|array',
            'origin_config.type'    => 'required|in:24,48,2x24',
            'origin_config.fiber'   => 'required|integer|in:0,4,6',
            'origin_config.model'   => 'nullable|string|max:80',
            'origin_config.serials' => 'nullable|array',
            'dest_config'           => 'required|array',
            'dest_config.copper'    => 'required|integer|in:24,48',
            'dest_config.fiber'     => 'required|integer|in:0,4,6',
            'dest_config.model'     => 'nullable|string|max:80',
            'dest_config.serial'    => 'nullable|string|max:60',
            'mapping_state'         => 'required|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'El nombre del mapeo es obligatorio.',
            'ip.ip'                  => 'La dirección IP no tiene un formato válido.',
            'origin_config.required' => 'La configuración de origen es requerida.',
            'dest_config.required'   => 'La configuración de destino es requerida.',
            'mapping_state.required' => 'El estado del mapeo es requerido.',
        ];
    }
}
