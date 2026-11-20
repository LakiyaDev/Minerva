<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreArticleDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['required', 'string'],
            'body' => ['required', 'string'],
            'category_id' => ['required', 'ulid', 'exists:article_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category does not exist.',
        ];
    }
}
