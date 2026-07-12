<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\CategoryData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CategoryController
{
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        return $this->resourceIndex(
            $request,
            Category::class,
            'tenant/categories/index',
            'categories',
            fn (Category $category): CategoryData => CategoryData::from($category),
        );
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
