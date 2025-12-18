<?php
/**
 * Gestion des doublons et archivage des parts
 */

class PluginAssociatesmanagerDataQuality {
   
   /**
    * Vérifie les doublons dans les parts
    * @return array Tableau des doublons trouvés
    */
   public static function findDuplicates() {
      global $DB;
      
      $duplicates = [];
      
      // Chercher les associations multiples avec date_fin NULL pour le même associé-fournisseur
      $result = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => ['date_fin' => null],
         'GROUPBY' => ['supplier_id', 'associates_id']
      ]);
      
      foreach ($result as $row) {
         // Récupérer tous les doublons
         $result_dups = $DB->request([
            'FROM' => 'glpi_plugin_associatesmanager_parts',
            'WHERE' => [
               'supplier_id' => $row['supplier_id'],
               'associates_id' => $row['associates_id'],
               'date_fin' => null
            ],
            'ORDER' => ['date_attribution' => 'DESC']
         ]);
         $dups = [];
         
         foreach ($result_dups as $dup) {
            $dups[] = $dup;
         }
         
         if (count($dups) > 1) {
            $duplicates[] = [
               'supplier_id' => $row['supplier_id'],
               'associates_id' => $row['associates_id'],
               'parts' => $dups,
               'count' => $row['cnt']
            ];
         }
      }
      
      return $duplicates;
   }
   
   /**
    * Fusionne deux parts (conserve celle avec la date d'attribution la plus ancienne)
    * @param integer $part_id_keep ID de la part à conserver
    * @param integer $part_id_remove ID de la part à supprimer
    * @return boolean
    */
   public static function mergeParts($part_id_keep, $part_id_remove) {
      global $DB;
      
      // Récupérer les deux parts
      $part_keep = new PluginAssociatesmanagerPart();
      $part_remove = new PluginAssociatesmanagerPart();
      
      if (!$part_keep->getFromDB($part_id_keep) || !$part_remove->getFromDB($part_id_remove)) {
         return false;
      }
      
      // Si les dates d'attribution sont différentes, supprimer le plus récent et garder l'ancien
      $date_keep = strtotime($part_keep->fields['date_attribution']);
      $date_remove = strtotime($part_remove->fields['date_attribution']);
      
      if ($date_remove < $date_keep) {
         // Inverser: garder le plus ancien
         $temp = $part_id_keep;
         $part_id_keep = $part_id_remove;
         $part_id_remove = $temp;
      }
      
      // Additionner les parts si possible
      $total_parts = $part_keep->fields['nbparts'] + $part_remove->fields['nbparts'];
      
      $DB->update(
         'glpi_plugin_associatesmanager_parts',
         ['nbparts' => $total_parts],
         ['id' => $part_id_keep]
      );
      
      // Supprimer l'autre part
      $part_remove->delete(['id' => $part_id_remove], true);
      
      Toolbox::logInFile('php-errors', "Parts fusionnées: $part_id_keep conservée (total: $total_parts), $part_id_remove supprimée\n");
      
      return true;
   }
   
   /**
    * Archive les parts inactives (date_fin passée) de plus de X jours
    * @param integer $days Nombre de jours depuis la date de fin
    * @return integer Nombre de parts archivées
    */
   public static function archiveOldParts($days = 180) {
      global $DB;
      
      $date_limit = date('Y-m-d', strtotime("-$days days"));
      
      $result = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => [
            'date_fin' => ['!=', null],
            'date_fin' => ['<', $date_limit]
         ]
      ]);
      $count = $result->count();
      
      if ($count > 0) {
         // Marquer comme archivée (on pourrait ajouter un champ 'archived' en future évolution)
         // Pour l'instant, on les met juste dans un log
         Toolbox::logInFile('php-errors', 
            "Archivage de $count parts inactives depuis plus de $days jours (avant $date_limit)\n");
      }
      
      return $count;
   }
   
   /**
    * Vérifie l'intégrité des données
    * @return array Tableau des problèmes trouvés
    */
   public static function checkDataIntegrity() {
      global $DB;
      
      $issues = [];
      
      // 1. Parts sans associé ou fournisseur
      $result = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => [
            ['associates_id' => null],
            ['OR', ['supplier_id' => null]]
         ]
      ]);
      $orphan_count = $result->count();
      
      if ($orphan_count > 0) {
         $issues[] = [
            'type' => 'orphan_parts',
            'message' => "$orphan_count parts sans associé ou fournisseur",
            'severity' => 'high'
         ];
      }
      
      // 2. Dates incohérentes (date_fin < date_attribution)
      $result = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => [
            'date_fin' => ['!=', null],
            'date_fin' => ['<', new QueryExpression('date_attribution')]
         ]
      ]);
      $bad_dates = $result->count();
      
      if ($bad_dates > 0) {
         $issues[] = [
            'type' => 'bad_dates',
            'message' => "$bad_dates parts avec dates incohérentes",
            'severity' => 'critical'
         ];
      }
      
      // 3. Parts avec valeur négative ou > 100
      $result = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => [
            ['nbparts' => ['<', 0]],
            ['OR', ['nbparts' => ['>', 100]]]
         ]
      ]);
      $bad_nbparts = $result->count();
      
      if ($bad_nbparts > 0) {
         $issues[] = [
            'type' => 'bad_nbparts',
            'message' => "$bad_nbparts parts avec nombre de parts invalide",
            'severity' => 'high'
         ];
      }
      
      return $issues;
   }
   
   /**
    * Corrige les données intégrité
    * @param string $type Type de problème à corriger
    * @return boolean
    */
   public static function fixDataIssue($type) {
      global $DB;
      
      switch ($type) {
         case 'bad_dates':
            // Supprimer les parts avec dates incohérentes
            $DB->delete(
               'glpi_plugin_associatesmanager_parts',
               [
                  'date_fin' => ['!=', null],
                  'date_fin' => ['<', new QueryExpression('date_attribution')]
               ]
            );
            Toolbox::logInFile('php-errors', "Correction: parts avec dates incohérentes supprimées\n");
            return true;
            
         case 'bad_nbparts':
            // Remettre les valeurs invalides à NULL
            $DB->update(
               'glpi_plugin_associatesmanager_parts',
               ['nbparts' => null],
               [
                  ['nbparts' => ['<', 0]],
                  ['OR', ['nbparts' => ['>', 100]]]
               ]
            );
            Toolbox::logInFile('php-errors', "Correction: parts avec nbparts invalide remises à NULL\n");
            return true;
            
         default:
            return false;
      }
   }
}
