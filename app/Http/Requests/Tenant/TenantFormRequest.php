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
    /**
     * Upper bound for decimal(15,4) quantity/price inputs. The column stores at most
     * 99,999,999,999.9999, so a larger value overflows it and returns a 500 — capping the
     * input turns that into a clean validation error instead. Use on every numeric rule.
     */
    protected const DECIMAL_MAX = 99999999999;

    /** Decimal places a decimal(15,4) column stores. */
    protected const DECIMAL_SCALE = 4;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The rules a decimal(15,4) money/quantity column needs.
     *
     * `numeric` alone is not enough. MySQL runs in strict mode, which errors on too
     * many integer digits but **silently rounds** extra decimal places — so
     * `1.12345` is accepted and stored as `1.1235`, and the saved record quietly
     * disagrees with what was submitted. `decimal:0,4` is what refuses it.
     *
     * The same limits are enforced in the browser (resources/js/lib/validation),
     * so a value refused here is refused there too.
     *
     * @return array<int, string>
     */
    protected function decimalRules(string $bound = 'gt:0'): array
    {
        return ['numeric', 'decimal:0,'.self::DECIMAL_SCALE, $bound, 'max:'.self::DECIMAL_MAX];
    }
}
