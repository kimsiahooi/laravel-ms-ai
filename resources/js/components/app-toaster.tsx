import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

// Branded toaster + the global flash-toast pipeline. Mounts `useFlashToast`
// once (it fires toasts the backend flashes via `Inertia::flash('toast', …)` /
// `$this->toast(...)`, delivered as the `@inertiajs/core` "flash" event). This
// used to live in a customized ui/sonner.tsx; that file is vendored and gets
// overwritten by the shadcn CLI, so the mount lives here instead.
//
// The <Toaster> gets our defaults — top-right placement and `richColors` — plus
// the app's own appearance: stock ui/sonner.tsx reads its theme from next-themes,
// which this app has no provider for, so toasts would otherwise ignore the manual
// light/dark toggle. Props are spread last inside ui/sonner.tsx, so they win.
// Customization lives here, not in components/ui/ — see docs/CODING-STANDARDS.md.
export function AppToaster() {
    const { appearance } = useAppearance();

    useFlashToast();

    return <Toaster theme={appearance} position="top-right" richColors />;
}
