<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by auth:web; belt-and-suspenders.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $ignoreId = $category instanceof Category ? $category->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Unique within this tenant's database (ignore self on update).
                Rule::unique('categories', 'name')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
