<?php
/**
 * Page d'historique des parts et associés
 */

require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

// Vérifier les droits
if (!PluginAssociatesmanagerRight::canRead()) {
   Html::displayRightError();
}

$title = 'Historique - Associates Manager';

Html::header($title, $_SERVER['PHP_SELF']);

// Inline stylesheet to avoid web-path issues (plugin webdir may vary)
$amCss = __DIR__ . '/../css/associates.css';
if (is_readable($amCss)) {
   echo '<style>' . file_get_contents($amCss) . '</style>';
}

echo "<div class='am-page'><div class='am-stack'>";

// Récupérer l'historique des modifications
global $DB;

$supplier_filter = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$associate_filter = isset($_GET['associates_id']) ? intval($_GET['associates_id']) : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : ''; // create, update, delete
$days_filter = isset($_GET['days']) ? intval($_GET['days']) : 30;

$query = "SELECT l.*, 
                 IF(l.linktype_field = 'glpi_plugin_associatesmanager_parts', 'Part', 
                    IF(l.linktype_field = 'glpi_plugin_associatesmanager_associates', 'Associé', 'Inconnu')) as item_type
          FROM glpi_log as l
          WHERE l.class IN ('PluginAssociatesmanagerPart', 'PluginAssociatesmanagerAssociate')";

// Filtres
if ($supplier_filter > 0) {
   // Cette partie nécessiterait une jointure complexe, donc on garde les filtres simples
   $query .= " AND l.id_search_option > 0";
}

if ($days_filter > 0) {
   $date_from = date('Y-m-d H:i:s', strtotime("-$days_filter days"));
   $query .= " AND l.date_mod >= '$date_from'";
}

if ($action_filter !== '') {
   switch ($action_filter) {
      case 'create':
         $query .= " AND l.id_search_option = 0";
         break;
      case 'update':
         $query .= " AND l.id_search_option > 0";
         break;
      case 'delete':
         $query .= " AND l.linked_action = 'delete'";
         break;
   }
}

$query .= " ORDER BY l.date_mod DESC LIMIT 500";

$result = $DB->request([
   'FROM' => 'glpi_log',
   'WHERE' => ['class' => ['IN', ['PluginAssociatesmanagerPart', 'PluginAssociatesmanagerAssociate']]]
]);

// Convert iteration to standard DB result approach
$rows = [];
foreach ($result as $row) {
   if ($supplier_filter > 0) {
      continue;
   }
   if ($days_filter > 0) {
      $date_from = date('Y-m-d H:i:s', strtotime("-$days_filter days"));
      if ($row['date_mod'] < $date_from) {
         continue;
      }
   }
   if ($action_filter !== '') {
      $match = false;
      switch ($action_filter) {
         case 'create':
            $match = ($row['linked_action'] === 'add');
            break;
         case 'update':
            $match = ($row['linked_action'] === 'update');
            break;
         case 'delete':
            $match = ($row['linked_action'] === 'delete');
            break;
      }
      if (!$match) continue;
   }
   $rows[] = $row;
   if (count($rows) >= 500) break;
}
echo "<div class='am-card'>";
echo "<h2 class='am-title'>Historique des modifications</h2>";
echo "<form method='GET' class='am-filters'>";

echo "<label>Période";
echo "<select name='days'>";
echo "<option value='7' " . ($days_filter === 7 ? 'selected' : '') . ">7 derniers jours</option>";
echo "<option value='30' " . ($days_filter === 30 ? 'selected' : '') . ">30 derniers jours</option>";
echo "<option value='90' " . ($days_filter === 90 ? 'selected' : '') . ">90 derniers jours</option>";
echo "<option value='0' " . ($days_filter === 0 ? 'selected' : '') . ">Tous</option>";
echo "</select>";
echo "</label>";

echo "<label>Action";
echo "<select name='action'>";
echo "<option value=''>Toutes les actions</option>";
echo "<option value='create' " . ($action_filter === 'create' ? 'selected' : '') . ">Créations</option>";
echo "<option value='update' " . ($action_filter === 'update' ? 'selected' : '') . ">Modifications</option>";
echo "<option value='delete' " . ($action_filter === 'delete' ? 'selected' : '') . ">Suppressions</option>";
echo "</select>";
echo "</label>";

echo "<button type='submit' class='btn btn-primary'>Filtrer</button>";
echo "</form>";
echo "</div>";

// Tableau d'historique
echo "<div class='am-card'>";
echo "<table class='tab_cadre_fixehov am-table'>";
echo "<thead>";
echo "<tr>";
echo "<th>Date/Heure</th>";
echo "<th>Type</th>";
echo "<th>Utilisateur</th>";
echo "<th>Action</th>";
echo "<th>Champ modifié</th>";
echo "<th>Ancienne valeur</th>";
echo "<th>Nouvelle valeur</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

if (count($rows) > 0) {
   foreach ($rows as $row) {
      $user_name = $row['user_name'] ?? __('Unknown');
      $action_label = '';
      
      if ($row['linked_action'] === 'delete') {
         $action_label = '<span class="am-badge danger">Suppression</span>';
      } elseif (empty($row['id_search_option'])) {
         $action_label = '<span class="am-badge success">Création</span>';
      } else {
         $action_label = '<span class="am-badge warning">Modification</span>';
      }
      
      echo "<tr>";
      echo "<td>" . $row['date_mod'] . "</td>";
      echo "<td>";
      
      if (strpos($row['class'], 'Part') !== false) {
         echo "Part";
      } elseif (strpos($row['class'], 'Associate') !== false) {
         echo "Associé";
      }
      
      echo "</td>";
      echo "<td>" . htmlspecialchars($user_name) . "</td>";
      echo "<td>$action_label</td>";
      echo "<td>" . htmlspecialchars($row['field_name'] ?? '-') . "</td>";
      echo "<td><code>" . htmlspecialchars(substr($row['old_value'] ?? '', 0, 50)) . "</code></td>";
      echo "<td><code>" . htmlspecialchars(substr($row['new_value'] ?? '', 0, 50)) . "</code></td>";
      echo "</tr>";
   }
} else {
   echo "<tr><td colspan='7' class='cell-muted'>Aucun historique trouvé</td></tr>";
}

echo "</tbody>";
echo "</table>";

echo "</div>"; // table card

echo "</div></div>"; // am-stack + am-page

Html::footer();
