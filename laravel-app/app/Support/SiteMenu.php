<?php

namespace App\Support;

use App\SiteSetting;

/**
 * Canonical definitions and saved ordering for the public landing menu and the
 * admin side menu. Used by the Site Content admin screen and both layouts.
 */
class SiteMenu
{
    /** Public site header items: key => label (default order). */
    public static function landingItems()
    {
        return [
            'home'         => 'Home',
            'trainings'    => 'Training',
            'events'       => 'Events',
            'rentals'      => 'Rentals',
            'register'     => 'Register Now',
            'apply'        => 'Apply Now',
            'permissions'  => 'Permissions',
            'about'        => 'About Us',
            'gallery'      => 'Gallery',
            'shareholders' => 'Shareholders',
            // Contact is merged into About Us (#contact) — not a separate nav item
        ];
    }

    /** Admin sidebar top-level items: key => label (default order). Keys match
     *  the sidebar collapse targets (#product, #purchase, ...). */
    public static function sideItems()
    {
        return [
            'dashboard'    => 'Dashboard',
            'site-content' => 'Site Content',
            'product'      => 'Product',
            'purchase'     => 'Purchase',
            'sale'         => 'Sale',
            'booking'      => 'Rental Module',
            'events'       => 'Events',
            'tasks'        => 'Task Manager',
            'jobs'         => 'Job Board',
            'permissions'  => 'Permissions',
            'announcements'=> 'Announcements',
            'courses'      => 'Courses',
            'timesheets'   => 'TimeSheets (Employee)',
            'timesheet-admin' => 'TimeSheet Admin',
            'shop'         => 'Shops',
            'order'        => 'Online Order',
            'payments'     => 'Payments',
            'letter'       => 'Letters',
            'expense'      => 'Expense',
            'quotation'    => 'Quotation',
            'assets'       => 'Fixed Assets',
            'transfer'     => 'Transfer',
            'return'       => 'Return',
            'account'      => 'Accounting',
            'hrm'          => 'HRM',
            'people'       => 'People',
            'report'       => 'Reports',
            'setting'      => 'Settings',
        ];
    }

    /**
     * Merge the saved order with the canonical items: saved keys first (only if
     * still valid), then any new/unsaved keys appended in their default order.
     */
    public static function ordered($settingKey, array $items)
    {
        $saved = SiteSetting::getValue($settingKey, []);
        if (! is_array($saved)) {
            $saved = [];
        }

        $ordered = [];
        foreach ($saved as $k) {
            if (isset($items[$k]) && ! in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        foreach (array_keys($items) as $k) {
            if (! in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }

        return $ordered;
    }

    public static function landingOrder()
    {
        return self::ordered('landing_menu_order', self::landingItems());
    }

    public static function sideOrder()
    {
        return self::ordered('side_menu_order', self::sideItems());
    }

    /** Settings submenu items inside #setting (key => label). */
    public static function settingsItems()
    {
        return [
            'role'               => 'Role Permission',
            'notification'       => 'Send Notification',
            'warehouse'          => 'Warehouse',
            'biller-list'        => 'Biller List',
            'customer-group'     => 'Customer Group',
            'brand'              => 'Brand',
            'unit'               => 'Unit',
            'currency'           => 'Currency',
            'tax'                => 'Tax',
            'user'               => 'User Profile',
            'my-transactions'    => 'My Transactions',
            'my-holiday'         => 'My Holiday',
            'create-sms'         => 'Create SMS',
            'backup-database'    => 'Backup Database',
            'empty-database'     => 'Empty Database',
            'general-setting'    => 'General Setting',
            'env-setting'        => '.env Settings',
            'mail-setting'       => 'Mail Setting',
            'reward-point-setting' => 'Reward Point Setting',
            'messaging-setting'  => 'Messaging Settings',
            'sms-setting'        => 'SMS Setting',
            'pos-setting'        => 'POS Settings',
            'hrm-setting'        => 'HRM Setting',
        ];
    }

    public static function settingsOrder()
    {
        return self::ordered('settings_menu_order', self::settingsItems());
    }

    /** Map settings submenu <li id="..."> to stable reorder keys. */
    public static function settingsLiKeyMap()
    {
        return [
            'role-menu'               => 'role',
            'notification-menu'         => 'notification',
            'warehouse-menu'          => 'warehouse',
            'biller-list-menu'        => 'biller-list',
            'customer-group-menu'     => 'customer-group',
            'brand-menu'              => 'brand',
            'unit-menu'               => 'unit',
            'currency-menu'           => 'currency',
            'tax-menu'                => 'tax',
            'user-menu'               => 'user',
            'my-transactions-menu'    => 'my-transactions',
            'my-holiday-menu'         => 'my-holiday',
            'create-sms-menu'         => 'create-sms',
            'backup-database-menu'    => 'backup-database',
            'empty-database-menu'     => 'empty-database',
            'general-setting-menu'    => 'general-setting',
            'env-setting-menu'        => 'env-setting',
            'mail-setting-menu'       => 'mail-setting',
            'reward-point-setting-menu' => 'reward-point-setting',
            'messaging-setting-menu'  => 'messaging-setting',
            'sms-setting-menu'        => 'sms-setting',
            'pos-setting-menu'        => 'pos-setting',
            'hrm-setting-menu'        => 'hrm-setting',
        ];
    }
}
