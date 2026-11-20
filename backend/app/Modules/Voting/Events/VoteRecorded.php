<?php

declare(strict_types=1);

namespace App\Modules\Voting\Events;

use App\Modules\Voting\Models\Vote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VoteRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Vote $vote,
        public readonly int $voteCount,
    ) {}
}
