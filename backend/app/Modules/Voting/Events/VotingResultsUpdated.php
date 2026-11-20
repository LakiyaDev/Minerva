<?php

declare(strict_types=1);

namespace App\Modules\Voting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VotingResultsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $periodId,
        public readonly string $articleId,
        public readonly int $voteCount,
        public readonly string $updatedAt,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voting-results.'.$this->periodId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'VotingResultsUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'period_id' => $this->periodId,
            'article_id' => $this->articleId,
            'vote_count' => $this->voteCount,
            'updated_at' => $this->updatedAt,
        ];
    }
}
