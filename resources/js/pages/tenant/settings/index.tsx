import { Head } from '@inertiajs/react';
import {
    type SettingsFieldSchema,
    SettingsForm,
} from '@/components/settings/settings-form';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { dashboard } from '@/routes/tenant';
import settingsRoutes from '@/routes/tenant/settings';
import type { TenantPageProps } from '@/types';

const TITLES: Record<string, string> = {
    business: 'Business settings',
};

const DESCRIPTIONS: Record<string, string> = {
    business:
        'Your company profile and tax details. These appear on invoices and other documents.',
};

type PageProps = TenantPageProps & {
    category: string;
    schema: SettingsFieldSchema[];
    values: Record<string, unknown>;
};

export default function SettingsIndex() {
    const { category, schema, values, tenant } = usePageProps<PageProps>();
    const title = TITLES[category] ?? 'Settings';

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                {
                    title,
                    href: settingsRoutes.edit.url({
                        tenant: tenant.slug,
                        category,
                    }),
                },
            ]}
        >
            <Head title={title} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {title}
                </h1>
                {DESCRIPTIONS[category] ? (
                    <p className="text-muted-foreground text-sm">
                        {DESCRIPTIONS[category]}
                    </p>
                ) : null}
            </div>

            <SettingsForm
                category={category}
                tenantSlug={tenant.slug}
                schema={schema}
                values={values}
            />
        </TenantLayout>
    );
}
