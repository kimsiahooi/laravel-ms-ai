import type { ComboboxOption } from '@/components/combobox';

/**
 * Map a list of `{ id, name }` records to combobox `{ value, label }` options — the
 * shape every resource picker needs. `value` is stringified because form inputs and
 * the Combobox work in strings.
 */
export function toOptions(
    items: ReadonlyArray<{ id: number | string; name: string }>,
): ComboboxOption[] {
    return items.map((item) => ({ value: String(item.id), label: item.name }));
}
