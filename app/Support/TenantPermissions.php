<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\AuthorizeTenantRoute;

/**
 * The tenant permission catalog — one place that defines every CRUD permission a
 * role can grant, the plain-language label users see (never the raw
 * "{screen}.{action}" name), and the route → permission map the
 * {@see AuthorizeTenantRoute} middleware enforces.
 *
 * Adding a screen or action here (and re-running the seeders) flows everywhere:
 * the seeded permission, the role-editor matrix, and route gating.
 */
final class TenantPermissions
{
    /**
     * Each manageable screen → its plain label + the CRUD actions that apply.
     * Append-only screens omit update/delete; read-only screens are view-only.
     *
     * @var array<string, array{label: string, actions: list<string>}>
     */
    private const SCREENS = [
        'categories' => ['label' => 'Categories', 'actions' => ['view', 'create', 'update', 'delete']],
        'suppliers' => ['label' => 'Suppliers', 'actions' => ['view', 'create', 'update', 'delete']],
        'customers' => ['label' => 'Customers', 'actions' => ['view', 'create', 'update', 'delete']],
        'raw-materials' => ['label' => 'Raw materials', 'actions' => ['view', 'create', 'update', 'delete']],
        'products' => ['label' => 'Products', 'actions' => ['view', 'create', 'update', 'delete']],
        'locations' => ['label' => 'Locations', 'actions' => ['view', 'create', 'update', 'delete']],
        'warehouses' => ['label' => 'Warehouses', 'actions' => ['view', 'create', 'update', 'delete']],
        'stock-movements' => ['label' => 'Stock movements', 'actions' => ['view', 'create']],
        'stock-transfers' => ['label' => 'Stock transfers', 'actions' => ['view', 'create']],
        'stock-takes' => ['label' => 'Stock takes', 'actions' => ['view', 'create', 'delete']],
        'purchase-orders' => ['label' => 'Purchase orders', 'actions' => ['view', 'create', 'update', 'delete']],
        'purchase-returns' => ['label' => 'Purchase returns', 'actions' => ['view', 'create', 'update', 'delete']],
        'sales-orders' => ['label' => 'Sales orders', 'actions' => ['view', 'create', 'update', 'delete']],
        'sales-returns' => ['label' => 'Sales returns', 'actions' => ['view', 'create', 'update', 'delete']],
        'production-orders' => ['label' => 'Production orders', 'actions' => ['view', 'create', 'delete']],
        'reports' => ['label' => 'Reports', 'actions' => ['view']],
        'activity' => ['label' => 'Activity', 'actions' => ['view']],
        'users' => ['label' => 'Users', 'actions' => ['view', 'create', 'update', 'delete']],
        'roles' => ['label' => 'Roles', 'actions' => ['view', 'create', 'update', 'delete']],
        'settings' => ['label' => 'Business settings', 'actions' => ['view', 'update']],
    ];

    /** @var array<string, string> */
    private const ACTION_VERB = [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
    ];

    /** @var array<string, string> action → the resource route suffix it guards */
    private const ACTION_ROUTE = [
        'view' => 'index',
        'create' => 'store',
        'update' => 'update',
        'delete' => 'destroy',
    ];

    /**
     * Lifecycle / custom routes that aren't plain resource CRUD → the permission
     * that governs them (route name without the 'tenant.' prefix). Lifecycle
     * actions map to the resource's edit permission, or its create permission for
     * screens that have no edit (stock takes, production orders).
     *
     * @var array<string, string>
     */
    private const ROUTE_OVERRIDES = [
        'products.bom' => 'products.update',
        'warehouses.reorder-levels.update' => 'warehouses.update',
        'stock-takes.post' => 'stock-takes.create',
        'stock-takes.cancel' => 'stock-takes.create',
        'purchase-orders.receive' => 'purchase-orders.update',
        'purchase-orders.cancel' => 'purchase-orders.update',
        'purchase-returns.complete' => 'purchase-returns.update',
        'purchase-returns.cancel' => 'purchase-returns.update',
        'sales-orders.fulfill' => 'sales-orders.update',
        'sales-orders.cancel' => 'sales-orders.update',
        'sales-returns.complete' => 'sales-returns.update',
        'sales-returns.cancel' => 'sales-returns.update',
        'production-orders.complete' => 'production-orders.create',
        'production-orders.cancel' => 'production-orders.create',
        'users.restore' => 'users.update',
        'settings.edit' => 'settings.view',
    ];

    /**
     * Every permission name to seed.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];
        foreach (self::SCREENS as $screen => $meta) {
            foreach ($meta['actions'] as $action) {
                $names[] = "{$screen}.{$action}";
            }
        }

        return $names;
    }

    /**
     * The catalog grouped by screen for the role-editor matrix, with plain labels.
     *
     * @return list<array{key: string, label: string, permissions: list<array{name: string, action: string, label: string}>}>
     */
    public static function matrix(): array
    {
        $groups = [];
        foreach (self::SCREENS as $screen => $meta) {
            $permissions = [];
            foreach ($meta['actions'] as $action) {
                $permissions[] = [
                    'name' => "{$screen}.{$action}",
                    'action' => $action,
                    'label' => self::ACTION_VERB[$action].' '.lcfirst($meta['label']),
                ];
            }
            $groups[] = ['key' => $screen, 'label' => $meta['label'], 'permissions' => $permissions];
        }

        return $groups;
    }

    /**
     * Route name (without the 'tenant.' prefix) → the permission it requires.
     * Unmapped routes (dashboard, media, on-hand lookup, logout) are open to any
     * signed-in user. `export` is handled dynamically by the middleware.
     *
     * @return array<string, string>
     */
    public static function routeMap(): array
    {
        $map = [];
        foreach (self::SCREENS as $screen => $meta) {
            foreach ($meta['actions'] as $action) {
                $map["{$screen}.".self::ACTION_ROUTE[$action]] = "{$screen}.{$action}";
            }
            // Every screen has a view permission; a show route (where one exists)
            // shares it.
            $map["{$screen}.show"] = "{$screen}.view";
        }

        return [...$map, ...self::ROUTE_OVERRIDES];
    }
}
