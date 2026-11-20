<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVotingPeriodRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:128'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
