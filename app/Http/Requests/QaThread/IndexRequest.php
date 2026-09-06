<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'status' => ['nullable', Rule::enum(QaThreadStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
