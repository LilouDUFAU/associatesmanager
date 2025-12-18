<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginAssociatesmanagerMenu extends CommonGLPI {

   static $rightname = 'plugin_associatesmanager';

   static function getMenuName() {
   return 'Gestion des associés';
   }

   static function getMenuContent() {
      global $CFG_GLPI;

      $menu = [];

      $menu['title'] = self::getMenuName();
      $menu['page']  = Plugin::getWebDir('associatesmanager') . '/front/associate.php';
      $menu['icon']  = 'fas fa-users';

      $menu['options']['associate'] = [
         'title' => PluginAssociatesmanagerAssociate::getTypeName(Session::getPluralNumber()),
         'page'  => Plugin::getWebDir('associatesmanager') . '/front/associate.php',
         'icon'  => 'fas fa-user-tie'
      ];

      $menu['options']['part'] = [
         'title' => PluginAssociatesmanagerPart::getTypeName(Session::getPluralNumber()),
         'page'  => Plugin::getWebDir('associatesmanager') . '/front/part.php',
         'icon'  => 'fas fa-percentage'
      ];

      // Ajouter l'option de synchronisation RNE si l'utilisateur a les droits de modification
      if (Session::haveRight('plugin_associatesmanager', UPDATE)) {
         $menu['options']['rnesync'] = [
            'title' => 'Synchronisation API RNE',
            'page'  => Plugin::getWebDir('associatesmanager') . '/front/rnesync.php',
            'icon'  => 'fas fa-cloud-download-alt'
         ];
      }

      // The dedicated partshistory page/class was removed; history is available
      // from the Parts list and associate views.

      return $menu;
   }
}
