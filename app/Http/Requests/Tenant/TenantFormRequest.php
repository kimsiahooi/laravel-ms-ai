<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for per-tenant form requests. The routes are already gated by `auth:web`,
 * so this default `authorize()` is belt-and-suspenders — it rejects the request
 * if somehow no user is bound. Subclasses only declare `rules()`.
 */
abstract class TenantFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
