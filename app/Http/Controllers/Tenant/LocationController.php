<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\LocationData;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController
{
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $locations = Location::query()
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Location $location): LocationData => LocationData::from($location));

        return Inertia::render('tenant/locations/index', [
            'locations' => $locations,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        $this->toast('Location created.');

        return back();
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        $this->toast('Location updated.');

        return back();
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        $this->toast('Location deleted.');

        return back();
    }
}
