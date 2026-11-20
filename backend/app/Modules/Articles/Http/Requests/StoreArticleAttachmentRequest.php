<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreArticleAttachmentRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:pdf,jpeg,png,jpg,webp', 'max:10240'],
            'kind' => ['nullable', 'string', 'in:image,document,other,manuscript,supplement,figure'],
        ];
    }
}
