<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\ActivityData;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController
{
    use ResolvesPerPage;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->latest('id') // id is monotonic — deterministic reverse-chronological
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Activity $activity): ActivityData => ActivityData::from($activity));

        return Inertia::render('tenant/activity/index', [
            'activities' => $activities,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Search by event, the record type, or the person who made the change.
     *
     * @param  Builder<Activity>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('event', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('subject_type', 'like', $like)
                ->orWhereHasMorph(
                    'causer',
                    [User::class],
                    fn (Builder $user) => $user->where('name', 'like', $like),
                );
        });
    }
}
