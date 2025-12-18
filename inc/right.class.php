<?php

/**
 * Gestion des droits d'accès au plugin
 */

class PluginAssociatesmanagerRight extends CommonDBTM {

    public static $rightname = 'plugin_associatesmanager';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return 'Associates';
    }

    public static function canRead(): bool {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canCreate(): bool {
        return Session::haveRight(self::$rightname, CREATE);
    }

    public static function canView(): bool {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canUpdate(): bool {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function canDelete(): bool {
        return Session::haveRight(self::$rightname, DELETE);
    }

    public static function canPurge(): bool {
        return Session::haveRight(self::$rightname, PURGE);
    }

    public static function canAdmin(): bool {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function getAllRights($all = false) {
        $rights = [
            ['itemtype' => 'PluginAssociatesmanagerAssociate',
             'right'    => CREATE,
             'label'    => __('Create')],

            ['itemtype' => 'PluginAssociatesmanagerAssociate',
             'right'    => READ,
             'label'    => __('Read')],

            ['itemtype' => 'PluginAssociatesmanagerAssociate',
             'right'    => UPDATE,
             'label'    => __('Update')],

            ['itemtype' => 'PluginAssociatesmanagerAssociate',
             'right'    => DELETE,
             'label'    => __('Delete')],

            ['itemtype' => 'PluginAssociatesmanagerAssociate',
             'right'    => PURGE,
             'label'    => __('Purge')]
        ];

        if ($all) {
            return $rights;
        }

        foreach ($rights as $right) {
            if (Session::haveRight($right['itemtype'], $right['right'])) {
                return $rights;
            }
        }

        return [];
    }
}
