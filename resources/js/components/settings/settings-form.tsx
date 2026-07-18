import { useForm } from '@inertiajs/react';
import { Building2, LoaderCircle, Upload, X } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Combobox } from '@/components/combobox';
import InputError from '@/components/input-error';
import { MultiCombobox } from '@/components/multi-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import settingsRoutes from '@/routes/tenant/settings';

/** One field's code-defined metadata, mirrored from App\Settings\Field::toSchema(). */
export type SettingsFieldSchema = {
    key: string;
    type:
        | 'text'
        | 'email'
        | 'textarea'
        | 'number'
        | 'combobox'
        | 'multicombobox'
        | 'toggle'
        | 'file';
    label: string;
    section: string;
    description: string | null;
    options: { value: string; label: string }[];
    placeholder: string | null;
    required: boolean;
};

type SettingsFormProps = {
    category: string;
    tenantSlug: string;
    schema: SettingsFieldSchema[];
    values: Record<string, unknown>;
};

type FormValue = string | number | boolean | string[] | File | null;
type FormData = Record<string, FormValue>;

function initialData(
    schema: SettingsFieldSchema[],
    values: Record<string, unknown>,
) {
    const data: FormData = {};
    for (const field of schema) {
        if (field.type === 'file') {
            data[field.key] = null;
            data[`remove_${field.key}`] = false;
        } else if (field.type === 'multicombobox') {
            data[field.key] = (values[field.key] as string[] | undefined) ?? [];
        } else if (field.type === 'toggle') {
            data[field.key] = Boolean(values[field.key]);
        } else {
            data[field.key] =
                values[field.key] == null ? '' : String(values[field.key]);
        }
    }
    return data;
}

export function SettingsForm({
    category,
    tenantSlug,
    schema,
    values,
}: SettingsFormProps) {
    const form = useForm<FormData>(initialData(schema, values));
    const { data, setData, errors, processing } = form;

    // Sections in first-seen order → one branded Card each.
    const sections = schema.reduce<string[]>((acc, field) => {
        if (!acc.includes(field.section)) acc.push(field.section);
        return acc;
    }, []);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const url = settingsRoutes.update.url({ tenant: tenantSlug, category });
        const options = {
            preserveScroll: true as const,
            onSuccess: () => {
                const reset: FormData = {};
                for (const field of schema) {
                    if (field.type === 'file') {
                        reset[field.key] = null;
                        reset[`remove_${field.key}`] = false;
                    }
                }
                setData((current) => ({ ...current, ...reset }));
            },
        };

        const uploading = schema.some(
            (field) => field.type === 'file' && data[field.key] instanceof File,
        );

        if (uploading) {
            // A File forces multipart, and Inertia never method-spoofs a put() with a
            // File — so send POST + _method:'put' + forceFormData.
            form.transform((current) => ({ ...current, _method: 'put' }));
            form.post(url, { ...options, forceFormData: true });
        } else {
            // JSON PUT: preserves empty arrays (so a cleared multi-select is saved) and
            // booleans without the FormData quirks.
            form.transform((current) => current);
            form.put(url, options);
        }
    };

    const fullWidth = (type: SettingsFieldSchema['type']) =>
        type === 'textarea' ||
        type === 'file' ||
        type === 'toggle' ||
        type === 'multicombobox';

    const renderField = (field: SettingsFieldSchema) => {
        const error = errors[field.key];
        const describedBy = error ? `${field.key}-error` : undefined;

        if (field.type === 'toggle') {
            return (
                <div className="grid gap-2">
                    <div className="flex items-start justify-between gap-4">
                        <FieldLabel field={field} />
                        <Switch
                            id={field.key}
                            checked={Boolean(data[field.key])}
                            onCheckedChange={(checked) =>
                                setData(field.key, checked)
                            }
                            aria-describedby={describedBy}
                        />
                    </div>
                    <InputError id={`${field.key}-error`} message={error} />
                </div>
            );
        }

        if (field.type === 'file') {
            return (
                <FileField
                    field={field}
                    file={data[field.key] as File | null}
                    removed={Boolean(data[`remove_${field.key}`])}
                    hasStored={Boolean(values[field.key])}
                    category={category}
                    tenantSlug={tenantSlug}
                    error={error}
                    onPick={(next) =>
                        setData((current) => ({
                            ...current,
                            [field.key]: next,
                            [`remove_${field.key}`]: next
                                ? false
                                : current[`remove_${field.key}`],
                        }))
                    }
                    onRemove={() =>
                        setData((current) => ({
                            ...current,
                            [field.key]: null,
                            [`remove_${field.key}`]: true,
                        }))
                    }
                />
            );
        }

        let control: ReactNode;
        switch (field.type) {
            case 'combobox':
                control = (
                    <Combobox
                        id={field.key}
                        options={field.options}
                        value={String(data[field.key] ?? '')}
                        onChange={(value) => setData(field.key, value)}
                        placeholder={field.placeholder ?? undefined}
                        allowNone={!field.required}
                        invalid={!!error}
                        describedBy={describedBy}
                    />
                );
                break;
            case 'multicombobox':
                control = (
                    <MultiCombobox
                        id={field.key}
                        options={field.options}
                        value={(data[field.key] as string[]) ?? []}
                        onChange={(value) => setData(field.key, value)}
                        placeholder={field.placeholder ?? undefined}
                        invalid={!!error}
                        describedBy={describedBy}
                    />
                );
                break;
            case 'textarea':
                control = (
                    <Textarea
                        id={field.key}
                        value={String(data[field.key] ?? '')}
                        onChange={(event) =>
                            setData(field.key, event.target.value)
                        }
                        placeholder={field.placeholder ?? undefined}
                        rows={3}
                        aria-invalid={!!error}
                        aria-describedby={describedBy}
                    />
                );
                break;
            default:
                control = (
                    <Input
                        id={field.key}
                        type={field.type === 'email' ? 'email' : field.type}
                        value={String(data[field.key] ?? '')}
                        onChange={(event) =>
                            setData(field.key, event.target.value)
                        }
                        placeholder={field.placeholder ?? undefined}
                        aria-invalid={!!error}
                        aria-describedby={describedBy}
                    />
                );
        }

        return (
            <FieldRow field={field} error={error}>
                {control}
            </FieldRow>
        );
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            {sections.map((section) => (
                <Card key={section}>
                    <CardHeader>
                        <CardTitle>{section}</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-x-6 gap-y-7 sm:grid-cols-2">
                        {schema
                            .filter((field) => field.section === section)
                            .map((field) => (
                                <div
                                    key={field.key}
                                    className={
                                        fullWidth(field.type)
                                            ? 'sm:col-span-2'
                                            : undefined
                                    }
                                >
                                    {renderField(field)}
                                </div>
                            ))}
                    </CardContent>
                </Card>
            ))}

            <div>
                <Button type="submit" disabled={processing}>
                    {processing ? (
                        <>
                            <LoaderCircle className="size-4 animate-spin" />
                            Saving…
                        </>
                    ) : (
                        'Save changes'
                    )}
                </Button>
            </div>
        </form>
    );
}

/** Label + always-visible humanized description — the clean, consistent field header. */
function FieldLabel({ field }: { field: SettingsFieldSchema }) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={field.key}>{field.label}</Label>
            {field.description ? (
                <p className="text-muted-foreground text-sm">
                    {field.description}
                </p>
            ) : null}
        </div>
    );
}

function FieldRow({
    field,
    error,
    children,
}: {
    field: SettingsFieldSchema;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <FieldLabel field={field} />
            {children}
            <InputError id={`${field.key}-error`} message={error} />
        </div>
    );
}

function FileField({
    field,
    file,
    removed,
    hasStored,
    category,
    tenantSlug,
    error,
    onPick,
    onRemove,
}: {
    field: SettingsFieldSchema;
    file: File | null;
    removed: boolean;
    hasStored: boolean;
    category: string;
    tenantSlug: string;
    error?: string;
    onPick: (file: File | null) => void;
    onRemove: () => void;
}) {
    // Object URLs are created/revoked in an effect so nothing touches `window` at SSR.
    const [filePreview, setFilePreview] = useState<string | null>(null);
    useEffect(() => {
        if (!file) {
            setFilePreview(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setFilePreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const storedUrl =
        hasStored && !removed
            ? settingsRoutes.file.url({
                  tenant: tenantSlug,
                  category,
                  key: field.key,
              })
            : null;
    const previewSrc = filePreview ?? storedUrl;

    return (
        <div className="grid gap-2">
            <FieldLabel field={field} />
            <div className="flex items-center gap-4">
                <div className="flex size-16 items-center justify-center overflow-hidden rounded-md border bg-muted">
                    {previewSrc ? (
                        <img
                            src={previewSrc}
                            alt={field.label}
                            className="size-full object-contain"
                        />
                    ) : (
                        <Building2 className="size-6 text-muted-foreground" />
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" asChild>
                        <label className="cursor-pointer">
                            <Upload className="size-4" />
                            Upload
                            <input
                                id={field.key}
                                type="file"
                                accept="image/*"
                                className="sr-only"
                                onChange={(event) => {
                                    onPick(event.target.files?.[0] ?? null);
                                    // Clear so re-picking the same file still fires change.
                                    event.currentTarget.value = '';
                                }}
                            />
                        </label>
                    </Button>
                    {previewSrc ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onRemove}
                        >
                            <X className="size-4" />
                            Remove
                        </Button>
                    ) : null}
                </div>
            </div>
            <InputError id={`${field.key}-error`} message={error} />
        </div>
    );
}
