<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrafficBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('node');
    }

    public function rules(): array
    {
        return [
            'batch_uuid' => ['required', 'uuid'],
            'records' => ['required', 'array', 'min:1', 'max:'.config('node_agent.max_batch_records')],
            'records.*' => ['required', 'array:user_id,route_id,uplink,downlink'],
            'records.*.user_id' => ['required', 'numeric', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'records.*.route_id' => ['required', 'numeric', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'records.*.uplink' => ['required', 'numeric', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
            'records.*.downlink' => ['required', 'numeric', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
        ];
    }
}
