<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['widget', 'full-screen'])],
            'section_id' => [
                'nullable',
                Rule::prohibitedIf(fn (): bool => $this->input('source') !== 'widget'),
                'ulid',
                Rule::exists('sections', 'id'),
            ],
            'message' => ['nullable', 'string', 'max:2000'],
            'auto_title_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'source' => '開始元',
            'section_id' => '教材セクション',
            'message' => '最初の質問',
            'auto_title_enabled' => 'タイトルの自動生成',
        ];
    }
}
