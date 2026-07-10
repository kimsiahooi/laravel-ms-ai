<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\CategoryData;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController
{
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $categories = Category::query()
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Category $category): CategoryData => CategoryData::from($category));

        return Inertia::render('tenant/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        $this->toast('Category created.');

        return back();
    }

    public function update(
        CategoryRequest $request,
        Category $category,
    ): RedirectResponse {
        $category->update($request->validated());

        $this->toast('Category updated.');

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        $this->toast('Category deleted.');

        return back();
    }
}
