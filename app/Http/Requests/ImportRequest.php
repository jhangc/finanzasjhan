<?php

namespace App\Http\Requests;

class ImportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:json,csv,txt', 'max:5120'],
        ];
    }
}
