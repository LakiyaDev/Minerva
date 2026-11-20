<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CastVoteRequest extends FormRequest
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
        return [];
    }
}
