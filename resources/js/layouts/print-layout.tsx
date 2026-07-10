import { Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/**
 * A bare, sidebar-less shell for printable order documents. The action bar is
 * hidden when printing (`print:hidden`) so only the sheet reaches the paper; the
 * background drops to white in print. Pages compose a <PrintDocument> inside.
 */
export default function PrintLayout({
    backHref,
    children,
}: {
    backHref: string;
    children: ReactNode;
}) {
    return (
        <div className="min-h-screen bg-muted/40 print:bg-white">
            <div className="mx-auto flex max-w-3xl items-center justify-between gap-2 px-4 py-4 print:hidden">
                <Button variant="ghost" asChild>
                    <Link href={backHref}>
                        <ArrowLeft className="size-4" />
                        Back
                    </Link>
                </Button>
                <Button onClick={() => window.print()}>
                    <Printer className="size-4" />
                    Print
                </Button>
            </div>
            <div className="mx-auto max-w-3xl px-4 pb-12">{children}</div>
        </div>
    );
}
