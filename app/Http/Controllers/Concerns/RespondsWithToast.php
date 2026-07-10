<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Inertia\Inertia;

/**
 * Flash a toast for the next Inertia response. The global `useFlashToast` hook
 * (mounted once in `<Toaster>`) renders it, so controllers need no per-page
 * wiring — just `$this->toast('Saved.')` before returning the redirect.
 */
trait RespondsWithToast
{
    /**
     * @param  'success'|'info'|'warning'|'error'  $type
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
