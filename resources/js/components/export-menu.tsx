import { usePage } from '@inertiajs/react';
import { Download, FileSpreadsheet, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * A CSV / Excel export control for a list. Downloads the current filtered dataset
 * server-side (openspout). `resource` must match a key in the backend
 * ExportRegistry. Rendered by DataTable when a page sets `exportResource`.
 */
export function ExportMenu({
    resource,
    search,
}: {
    resource: string;
    search?: string;
}) {
    const { props } = usePage();
    const slug = (props.tenant as { slug: string } | undefined)?.slug ?? '';

    const href = (format: 'csv' | 'xlsx') => {
        const params = new URLSearchParams({ format });
        if (search) {
            params.set('search', search);
        }
        return `/${slug}/export/${resource}?${params.toString()}`;
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="shrink-0">
                    <Download className="size-4" />
                    Export
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    {/* Plain anchors so the browser downloads the file. */}
                    <a href={href('csv')} download>
                        <FileText className="size-4" />
                        CSV
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={href('xlsx')} download>
                        <FileSpreadsheet className="size-4" />
                        Excel
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
