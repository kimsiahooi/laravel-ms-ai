<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

/** Resolve a scanned barcode / QR / SKU to a stock item. */
class StockResolveItemRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => trim((string) $this->input('code'))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
