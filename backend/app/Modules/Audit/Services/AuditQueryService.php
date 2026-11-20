<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AuditQueryService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $request->integer('per_page', 25)));

        $query = AuditLog::query()->with('actor:id,name,email');

        if ($request->filled('action')) {
            $action = (string) $request->string('action');
            if (str_ends_with($action, '*')) {
                $query->where('action', 'like', rtrim($action, '*').'%');
            } else {
                $query->where('action', $action);
            }
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', (string) $request->string('actor_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', (string) $request->string('subject_type'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (string) $request->string('subject_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->string('search')).'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('action', 'like', $search)
                    ->orWhere('subject_id', 'like', $search)
                    ->orWhereHas('actor', function (Builder $actorQuery) use ($search): void {
                        $actorQuery->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        if ($request->filled('date_from')) {
            try {
                $from = Carbon::parse((string) $request->string('date_from'))->startOfDay();
                $query->where('created_at', '>=', $from);
            } catch (\Throwable) {
                // Ignore malformed date
            }
        }

        if ($request->filled('date_to')) {
            try {
                $to = Carbon::parse((string) $request->string('date_to'))->endOfDay();
                $query->where('created_at', '<=', $to);
            } catch (\Throwable) {
                // Ignore malformed date
            }
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
