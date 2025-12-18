<?php
/**
 * Page de gestion des profils et droits granulaires
 */

require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$title = 'Gestion des droits - Associates Manager';

Html::header($title, $_SERVER['PHP_SELF']);

echo "<div class='container-fluid'>";
echo "<div class='row mb-3'>";
echo "<div class='col-md-12'>";
echo "<h1>Gestion des droits du plugin Associates Manager</h1>";
echo "</div>";
echo "</div>";

// Afficher les droits granulaires disponibles
echo "<div class='card mb-3'>";
echo "<div class='card-header'>";
echo "<h5>Droits granulaires disponibles</h5>";
echo "</div>";
echo "<div class='card-body'>";

$rights_info = [
   [
      'name' => 'plugin_associatesmanager',
      'label' => 'Accès global au plugin',
      'description' => 'Permet l\'accès de base au plugin. Doit être combiné avec les autres droits.'
   ],
   [
      'name' => 'plugin_associatesmanager_read',
      'label' => 'Voir les associés',
      'description' => 'Permet de consulter la liste des associés, parts et historiques.'
   ],
   [
      'name' => 'plugin_associatesmanager_create',
      'label' => 'Créer/ajouter des associés',
      'description' => 'Permet de créer de nouveaux associés et nouvelles parts.'
   ],
   [
      'name' => 'plugin_associatesmanager_update',
      'label' => 'Modifier les associés',
      'description' => 'Permet de modifier les informations des associés et des parts existantes.'
   ],
   [
      'name' => 'plugin_associatesmanager_delete',
      'label' => 'Supprimer les associés',
      'description' => 'Permet de supprimer des associés et des parts.'
   ],
   [
      'name' => 'plugin_associatesmanager_sync_rne',
      'label' => 'Synchroniser depuis RNE INPI',
      'description' => 'Permet de synchroniser les données depuis l\'API RNE INPI.'
   ]
];

echo "<table class='table table-hover'>";
echo "<thead class='table-dark'>";
echo "<tr><th>Droit</th><th>Description</th></tr>";
echo "</thead>";
echo "<tbody>";

foreach ($rights_info as $right) {
   echo "<tr>";
   echo "<td>";
   echo "<strong>" . htmlspecialchars($right['label']) . "</strong>";
   echo "<br><small class='text-muted'><code>" . htmlspecialchars($right['name']) . "</code></small>";
   echo "</td>";
   echo "<td>" . htmlspecialchars($right['description']) . "</td>";
   echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "</div>";
echo "</div>";

// Configuration recommandée des profils
echo "<div class='card mb-3'>";
echo "<div class='card-header'>";
echo "<h5>Configuration recommandée des profils</h5>";
echo "</div>";
echo "<div class='card-body'>";

$profiles_config = [
   [
      'name' => 'Administrateur',
      'rights' => 'Tous les droits',
      'description' => 'Accès complet au plugin'
   ],
   [
      'name' => 'Gestionnaire RH / Juridique',
      'rights' => 'READ, CREATE, UPDATE, SYNC_RNE',
      'description' => 'Peut consulter, créer et modifier les associés, synchroniser depuis RNE'
   ],
   [
      'name' => 'Auditeur',
      'rights' => 'READ',
      'description' => 'Accès lecture seule pour consultation et rapports'
   ],
   [
      'name' => 'Opérateur',
      'rights' => 'READ, CREATE, UPDATE',
      'description' => 'Peut consulter et saisir les données des associés'
   ]
];

echo "<table class='table'>";
echo "<thead class='table-light'>";
echo "<tr><th>Profil</th><th>Droits recommandés</th><th>Description</th></tr>";
echo "</thead>";
echo "<tbody>";

foreach ($profiles_config as $profile) {
   echo "<tr>";
   echo "<td><strong>" . htmlspecialchars($profile['name']) . "</strong></td>";
   echo "<td><span class='badge badge-info'>" . htmlspecialchars($profile['rights']) . "</span></td>";
   echo "<td>" . htmlspecialchars($profile['description']) . "</td>";
   echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "</div>";
echo "</div>";

// État des droits par profil
echo "<div class='card'>";
echo "<div class='card-header'>";
echo "<h5>État des droits par profil</h5>";
echo "</div>";
echo "<div class='card-body'>";

global $DB;

echo "<table class='table table-hover'>";
echo "<thead>";
echo "<tr>";
echo "<th>Profil</th>";
echo "<th>READ</th>";
echo "<th>CREATE</th>";
echo "<th>UPDATE</th>";
echo "<th>DELETE</th>";
echo "<th>SYNC_RNE</th>";
echo "<th>Action</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$result = $DB->request([
   'FROM' => 'glpi_profiles',
   'ORDER' => ['name' => 'ASC']
]);

foreach ($profile_iter = $result as $profile) {
   $profile_id = $profile['id'];
   $profile_name = $profile['name'];
   
   // Récupérer les droits pour ce profil
   $rights_data = [];
   
   $right_names = [
      'read' => 'plugin_associatesmanager_read',
      'create' => 'plugin_associatesmanager_create',
      'update' => 'plugin_associatesmanager_update',
      'delete' => 'plugin_associatesmanager_delete',
      'sync' => 'plugin_associatesmanager_sync_rne'
   ];
   
   foreach ($right_names as $key => $right_name) {
      $rights_data[$key] = false;
      
      $check = $DB->request([
         'FROM' => 'glpi_profilerights',
         'WHERE' => [
            'profiles_id' => $profile_id,
            'name' => $right_name
         ]
      ]);
      
      if ($check->count() > 0) {
         foreach ($check as $row) {
            $rights_data[$key] = ($row['rights'] & READ) > 0;
            break;
         }
      }
   }
   
   echo "<tr>";
   echo "<td><strong>" . htmlspecialchars($profile_name) . "</strong></td>";
   echo "<td>" . (isset($rights_data['read']) && $rights_data['read'] ? "✓" : "✗") . "</td>";
   echo "<td>" . (isset($rights_data['create']) && $rights_data['create'] ? "✓" : "✗") . "</td>";
   echo "<td>" . (isset($rights_data['update']) && $rights_data['update'] ? "✓" : "✗") . "</td>";
   echo "<td>" . (isset($rights_data['delete']) && $rights_data['delete'] ? "✓" : "✗") . "</td>";
   echo "<td>" . (isset($rights_data['sync']) && $rights_data['sync'] ? "✓" : "✗") . "</td>";
   echo "<td>";
   echo "<a href='" . $GLOBALS['CFG_GLPI']['root_doc'] . "/front/profile.form.php?id=" . $profile_id . "' class='btn btn-sm btn-primary'>";
   echo "<i class='fas fa-edit'></i> Éditer";
   echo "</a>";
   echo "</td>";
   echo "</tr>";
}

echo "</tbody>";
echo "</table>";

echo "<p class='text-muted mt-2'>";
echo "<small>✓ = Droit activé | ✗ = Droit désactivé</small>";
echo "</p>";

echo "</div>";
echo "</div>";

echo "</div>";

Html::footer();
