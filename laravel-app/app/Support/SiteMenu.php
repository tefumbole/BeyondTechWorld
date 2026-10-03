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
            'subscriptions'=> 'Subscriptions',
            'apply'        => 'Apply Now',
            'permissions'  => 'Permissions',
            'about'        => 'About Us',
            'gallery'      => 'Gallery',
            // Register Now removed — Training already covers course signup
            // Shareholders hidden from public nav (routes remain available)
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
            'leaders'      => 'About Us Leaders',
            'product'      => 'Product',
            'purchase'     => 'Purchase',
            'sale'         => 'Sale',
            'booking'      => 'Rental Module',
            'events'       => 'Events',
            'invitations'  => 'Digital Invitations',
            'internship'   => 'Internships',
            'supervisor'   => 'Supervisor',
            'properties'   => 'Properties',
            'tasks'        => 'Task Manager',
            'jobs'         => 'Job Board',
            'contracts'    => 'Contracts',
            'permissions'  => 'Permissions',
            'announcements'=> 'Announcements',
            'whatsapp'     => 'WhatsApp Hub',
            'courses'      => 'Courses',
            'timesheets'   => 'TimeSheets (Employee)',
            'timesheet-admin' => 'TimeSheet Admin',
            'shop'         => 'Shops',
            'order'        => 'Online Order',
            'payments'     => 'Payments',
            'letter'       => 'Letters',
            'wealth'       => 'Wealth Manager',
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
            if ($k === 'expense' && isset($items['wealth'])) {
                $k = 'wealth';
            }
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
            'customer-group'     => 'Customer Group',
            'brand'              => 'Brand',
            'unit'               => 'Unit',
            'currency'           => 'Currency',
            'tax'                => 'Tax',
            'user'               => 'User Profile',
            'my-transactions'    => 'My Transactions',
            'backup-database'    => 'Backup Database',
            'empty-database'     => 'Empty Database',
            'general-setting'    => 'General Setting',
            'activity-logs'      => 'Activity Logs',
            'env-setting'        => '.env Settings',
            'mail-setting'       => 'Mail Setting',
            'reward-point-setting' => 'Reward Point Setting',
            'pos-setting'        => 'POS Settings',
        ];
    }

    public static function settingsOrder()
    {
        return self::ordered('settings_menu_order', self::settingsItems());
    }

    /** People submenu items inside #people (key => label). */
    public static function peopleItems()
    {
        return [
            'customer-list'   => 'Customer List',
            'user-list'       => 'User List',
            'supervisor-list' => 'Supervisor List',
            'intern-list'     => 'Intern List',
            'biller-list'     => 'Biller List',
        ];
    }

    public static function peopleOrder()
    {
        return self::ordered('people_menu_order', self::peopleItems());
    }

    /** WhatsApp Hub submenu items (key => label). */
    public static function whatsappItems()
    {
        return [
            'command' => 'Command Center',
            'conversations' => 'Conversations',
            'leads' => 'Leads',
            'groups' => 'Groups',
            'rentals' => 'Rentals',
            'internship' => 'Internship',
            'attendance' => 'Attendance',
            'documents' => 'Documents',
            'tenants' => 'Tenant Operations',
            'bills' => 'Bill Payments',
            'appointments' => 'Appointments',
            'tracking' => 'Message Tracking',
            'calls' => 'Calls',
            'diagnostics' => 'Diagnostics',
            'assistant' => 'AI Assistant',
            'brief' => 'AI brief',
            'people' => 'People I know',
            'settings' => 'Settings',
        ];
    }

    public static function whatsappOrder()
    {
        return self::ordered('whatsapp_menu_order', self::whatsappItems());
    }

    public static function whatsappLiKeyMap()
    {
        return [
            'whatsapp-command-menu' => 'command',
            'whatsapp-conversations-menu' => 'conversations',
            'whatsapp-leads-menu' => 'leads',
            'whatsapp-groups-menu' => 'groups',
            'whatsapp-rentals-menu' => 'rentals',
            'whatsapp-internship-menu' => 'internship',
            'whatsapp-attendance-menu' => 'attendance',
            'whatsapp-documents-menu' => 'documents',
            'whatsapp-tenants-menu' => 'tenants',
            'whatsapp-bills-menu' => 'bills',
            'whatsapp-appointments-menu' => 'appointments',
            'whatsapp-tracking-menu' => 'tracking',
            'whatsapp-calls-menu' => 'calls',
            'whatsapp-diagnostics-menu' => 'diagnostics',
            'whatsapp-assistant-menu' => 'assistant',
            'whatsapp-brief-menu' => 'brief',
            'whatsapp-people-menu' => 'people',
            'whatsapp-settings-menu' => 'settings',
        ];
    }

    /** Map people submenu <li id="..."> to stable reorder keys. */
    public static function peopleLiKeyMap()
    {
        return [
            'customer-list-menu'   => 'customer-list',
            'user-list-menu'       => 'user-list',
            'supervisor-list-menu' => 'supervisor-list',
            'intern-list-menu'     => 'intern-list',
            'biller-list-menu'     => 'biller-list',
        ];
    }

    /** Map settings submenu <li id="..."> to stable reorder keys. */
    public static function settingsLiKeyMap()
    {
        return [
            'role-menu'               => 'role',
            'notification-menu'         => 'notification',
            'warehouse-menu'          => 'warehouse',
            'customer-group-menu'     => 'customer-group',
            'brand-menu'              => 'brand',
            'unit-menu'               => 'unit',
            'currency-menu'           => 'currency',
            'tax-menu'                => 'tax',
            'user-menu'               => 'user',
            'my-transactions-menu'    => 'my-transactions',
            'backup-database-menu'    => 'backup-database',
            'empty-database-menu'     => 'empty-database',
            'general-setting-menu'    => 'general-setting',
            'activity-logs-menu'      => 'activity-logs',
            'env-setting-menu'        => 'env-setting',
            'mail-setting-menu'       => 'mail-setting',
            'reward-point-setting-menu' => 'reward-point-setting',
            'pos-setting-menu'        => 'pos-setting',
        ];
    }
}
