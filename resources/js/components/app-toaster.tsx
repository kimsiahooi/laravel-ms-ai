import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-appearance';

// Branded toaster: shadcn's <Toaster> (vendored, untouched) with our defaults —
// top-right placement and `richColors` (tinted success / error / warning
// toasts). We also feed it the app's own appearance: stock ui/sonner.tsx reads
// its theme from next-themes, which this app has no provider for, so toasts
// would otherwise ignore the manual light/dark toggle and sit on "system". All
// props are spread last inside ui/sonner.tsx, so they win over its stock
// defaults. Customization lives here, not in components/ui/ — see
// docs/CODING-STANDARDS.md.
export function AppToaster() {
    const { appearance } = useAppearance();

    return <Toaster theme={appearance} position="top-right" richColors />;
}
