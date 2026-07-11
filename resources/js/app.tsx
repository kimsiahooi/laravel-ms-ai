import { createInertiaApp } from '@inertiajs/react';
import { AppToaster } from '@/components/app-toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// Chromium/Firefox surface a benign "ResizeObserver loop …" as a window error
// whenever an observer callback (Radix/floating-ui popovers, recharts) schedules
// a resize that simply re-runs on the next frame. It breaks nothing but pollutes
// the console and dev error overlay, so swallow ONLY that specific message.
// Guarded for SSR: this module is also evaluated in Node (Inertia SSR), where
// `window` is undefined — registering the listener only in the browser.
if (typeof window !== 'undefined') {
    window.addEventListener('error', (event) => {
        if (
            /ResizeObserver loop (limit exceeded|completed with undelivered notifications)/.test(
                event.message,
            )
        ) {
            event.stopImmediatePropagation();
            event.preventDefault();
        }
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // welcome + the custom central (/admin) and tenant (/{slug}) pages
            // render their own full-page layout, so they get no shared app shell
            // (no sidebar). A dedicated central/tenant shell comes in the UI phase.
            case name === 'welcome':
            case name.startsWith('admin/'):
            case name.startsWith('tenant/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <AppToaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
