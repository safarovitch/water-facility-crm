<?php

namespace App\Support;

use App\Models\User;

/**
 * Server-driven navigation for the mobile management app.
 *
 * The mobile app renders whatever this returns: it maps each item's `key`
 * to a locally registered screen and hides items whose key it doesn't know.
 * Which roles see which items is decided HERE (via the same ability flags
 * that gate the web sidebar and the routes), so granting a role new access
 * never requires a mobile release.
 *
 * Item shape:
 *   key      — stable module identifier the app maps to a screen
 *   title    — ['en' => ..., 'ru' => ...] labels
 *   icon     — lucide icon name (app falls back to a default if unknown)
 *   section  — grouping header key for the menu screen
 *   badge    — optional server-computed counter (e.g. pending orders)
 */
class MobileMenu
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $can = UserAbilities::for($user);
        $items = [];

        if (! $can['accessAdmin']) {
            return [];
        }

        $items[] = [
            'key'     => 'dashboard',
            'title'   => $can['viewAdminStats']
                ? ['en' => 'Admin Dashboard', 'ru' => 'Панель управления']
                : ['en' => 'My Dashboard', 'ru' => 'Моя панель'],
            'icon'    => 'layout-grid',
            'section' => 'general',
        ];

        // ── Sales ────────────────────────────────────────────────────────
        $items[] = [
            'key'     => 'orders',
            'title'   => ['en' => 'Orders', 'ru' => 'Заказы'],
            'icon'    => 'shopping-cart',
            'section' => 'sales',
            'badge'   => self::pendingOrdersCount($user),
        ];
        if ($can['manageClients']) {
            $items[] = [
                'key'     => 'clients',
                'title'   => ['en' => 'Clients', 'ru' => 'Клиенты'],
                'icon'    => 'users',
                'section' => 'sales',
            ];
        }
        if ($can['manageProducts']) {
            $items[] = [
                'key'     => 'products',
                'title'   => ['en' => 'Products', 'ru' => 'Товары'],
                'icon'    => 'package',
                'section' => 'sales',
            ];
        }
        if ($can['viewForecasts']) {
            $items[] = [
                'key'     => 'forecasts',
                'title'   => ['en' => 'Forecasts', 'ru' => 'Прогнозы'],
                'icon'    => 'calendar-clock',
                'section' => 'sales',
            ];
        }
        if ($can['manageSubscriptions']) {
            $items[] = [
                'key'     => 'subscriptions',
                'title'   => ['en' => 'Subscriptions', 'ru' => 'Подписки'],
                'icon'    => 'rotate-ccw',
                'section' => 'sales',
            ];
        }

        if ($can['accessCalls']) {
            $items[] = [
                'key'     => 'calls',
                'title'   => ['en' => 'Calls', 'ru' => 'Звонки'],
                'icon'    => 'phone',
                'section' => 'sales',
            ];
        }

        // ── Delivery ─────────────────────────────────────────────────────
        if ($can['assignCurriers']) {
            $items[] = [
                'key'     => 'assignments',
                'title'   => ['en' => 'Currier Assignments', 'ru' => 'Назначения курьеров'],
                'icon'    => 'clipboard-list',
                'section' => 'delivery',
            ];
        }
        if ($can['viewCurrierActivities']) {
            $items[] = [
                'key'     => 'courier-activities',
                'title'   => ['en' => 'Currier Activities', 'ru' => 'Активность курьеров'],
                'icon'    => 'activity',
                'section' => 'delivery',
            ];
        }

        // ── Warehouse ────────────────────────────────────────────────────
        if ($can['manageRawMaterials']) {
            $items[] = [
                'key'     => 'raw-materials',
                'title'   => ['en' => 'Raw Materials', 'ru' => 'Сырьё'],
                'icon'    => 'box',
                'section' => 'warehouse',
            ];
        }
        if ($can['manageInventory']) {
            $items[] = [
                'key'     => 'inventory',
                'title'   => ['en' => 'Inventory', 'ru' => 'Инвентарь'],
                'icon'    => 'wrench',
                'section' => 'warehouse',
            ];
        }

        // ── Finance ──────────────────────────────────────────────────────
        if ($can['accessAccounting']) {
            $items[] = [
                'key'     => 'financial',
                'title'   => $can['manageAccounting']
                    ? ['en' => 'Accounting', 'ru' => 'Бухгалтерия']
                    : ['en' => 'My Expenses', 'ru' => 'Мои расходы'],
                'icon'    => 'wallet',
                'section' => 'finance',
            ];
        }

        // ── Administration ───────────────────────────────────────────────
        if ($can['manageUsers']) {
            $items[] = [
                'key'     => 'users',
                'title'   => ['en' => 'All users', 'ru' => 'Пользователи'],
                'icon'    => 'users-round',
                'section' => 'administration',
            ];
            $items[] = [
                'key'     => 'roles',
                'title'   => ['en' => 'Roles', 'ru' => 'Роли'],
                'icon'    => 'user-cog',
                'section' => 'administration',
            ];
            $items[] = [
                'key'     => 'permissions',
                'title'   => ['en' => 'Permissions', 'ru' => 'Разрешения'],
                'icon'    => 'shield-check',
                'section' => 'administration',
            ];
        }

        return $items;
    }

    /**
     * Section headers, keyed by the section slug used in items.
     *
     * @return array<string, array<string, string>>
     */
    public static function sections(): array
    {
        return [
            'general'        => ['en' => 'General', 'ru' => 'Общее'],
            'sales'          => ['en' => 'Sales', 'ru' => 'Продажи'],
            'delivery'       => ['en' => 'Delivery', 'ru' => 'Доставка'],
            'warehouse'      => ['en' => 'Warehouse', 'ru' => 'Склад'],
            'finance'        => ['en' => 'Finance', 'ru' => 'Финансы'],
            'administration' => ['en' => 'Administration', 'ru' => 'Администрирование'],
        ];
    }

    /**
     * Same scoping rule as the web sidebar badge: plain couriers only see
     * their own pending count, never the global one.
     */
    private static function pendingOrdersCount(User $user): int
    {
        return $user->isCourierOnly()
            ? \App\Models\Order::where('status', 'pending')->where('courier_id', $user->id)->count()
            : \App\Models\Order::where('status', 'pending')->count();
    }
}
