<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Requests;

use App\Modules\Voting\Models\VotingPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateVotingPeriodRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:128'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }

                /** @var VotingPeriod|null $period */
                $period = $this->route('voting_period');

                $startsRaw = $this->input('starts_at') ?? $period?->starts_at;
                $endsRaw = $this->input('ends_at') ?? $period?->ends_at;

                if ($startsRaw === null || $endsRaw === null) {
                    return;
                }

                $starts = Carbon::parse($startsRaw);
                $ends = Carbon::parse($endsRaw);

                if ($ends->lte($starts)) {
                    $validator->errors()->add('ends_at', 'The ends at field must be a date after starts at.');
                }
            },
        ];
    }
}
