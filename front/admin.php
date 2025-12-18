<?php
/**
 * Page d'administration - Gestion de la qualité des données
 */

require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$title = 'Administration - Associates Manager';

Html::header($title, $_SERVER['PHP_SELF']);

echo "<div class='container-fluid'>";
echo "<div class='row mb-3'>";
echo "<div class='col-md-12'>";
echo "<h1>Administration - Qualité des données</h1>";
echo "</div>";
echo "</div>";

// Vérifier les doublons
$duplicates = PluginAssociatesmanagerDataQuality::findDuplicates();

echo "<div class='card mb-3'>";
echo "<div class='card-header'>";
echo "<h5>Doublons détectés</h5>";
echo "</div>";
echo "<div class='card-body'>";

if (count($duplicates) > 0) {
   echo "<div class='alert alert-warning'>";
   echo "<strong>" . count($duplicates) . " groupe(s) de doublons détecté(s)</strong>";
   echo "</div>";
   
   foreach ($duplicates as $dup_group) {
      $supplier_id = $dup_group['supplier_id'];
      $associate_id = $dup_group['associates_id'];
      
      // Récupérer les noms
      global $DB;
      $it_s = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_suppliers', 'WHERE' => ['id' => $supplier_id]]);
      $supplier_name = ($it_s->count() > 0) ? $it_s->current()['name'] : 'Inconnu';
      
      $it_a = $DB->request(['SELECT' => ['name'], 'FROM' => 'glpi_plugin_associatesmanager_associates', 'WHERE' => ['id' => $associate_id]]);
      $associate_name = ($it_a->count() > 0) ? $it_a->current()['name'] : 'Inconnu';
      
      echo "<div class='card mt-3'>";
      echo "<div class='card-header'>";
      echo "<strong>$associate_name @ $supplier_name</strong> (" . count($dup_group['parts']) . " parts)";
      echo "</div>";
      echo "<div class='card-body'>";
      echo "<table class='table table-sm'>";
      echo "<tr><th>ID</th><th>Parts</th><th>Date attribution</th><th>Date fin</th><th>Action</th></tr>";
      
      foreach ($dup_group['parts'] as $idx => $part) {
         $to_delete = ($idx === 0) ? '' : ' (À supprimer)';
         echo "<tr>";
         echo "<td>{$part['id']}</td>";
         echo "<td>{$part['nbparts']}%</td>";
         echo "<td>{$part['date_attribution']}</td>";
         echo "<td>" . ($part['date_fin'] ? $part['date_fin'] : '-') . "</td>";
         echo "<td>";
         
         if ($idx === 0) {
            echo "<span class='badge badge-success'>À conserver</span>";
         } else {
            echo "<form method='POST' style='display: inline;'>";
            echo "<input type='hidden' name='action' value='merge_parts'>";
            echo "<input type='hidden' name='keep_id' value='" . $dup_group['parts'][0]['id'] . "'>";
            echo "<input type='hidden' name='remove_id' value='{$part['id']}'>";
            echo "<button type='submit' class='btn btn-sm btn-danger' onclick='return confirm(\"Fusionner ces parts?\");'>";
            echo "Fusionner";
            echo "</button>";
            echo "</form>";
         }
         
         echo "</td>";
         echo "</tr>";
      }
      
      echo "</table>";
      echo "</div>";
      echo "</div>";
   }
} else {
   echo "<div class='alert alert-success'>";
   echo "<strong>Aucun doublon détecté</strong> ✓";
   echo "</div>";
}

echo "</div>";
echo "</div>";

// Vérifier l'intégrité des données
$issues = PluginAssociatesmanagerDataQuality::checkDataIntegrity();

echo "<div class='card mb-3'>";
echo "<div class='card-header'>";
echo "<h5>Intégrité des données</h5>";
echo "</div>";
echo "<div class='card-body'>";

if (count($issues) > 0) {
   echo "<div class='alert alert-danger'>";
   echo "<strong>" . count($issues) . " problème(s) détecté(s)</strong>";
   echo "</div>";
   
   foreach ($issues as $issue) {
      $severity_class = 'warning';
      if ($issue['severity'] === 'critical') {
         $severity_class = 'danger';
      } elseif ($issue['severity'] === 'high') {
         $severity_class = 'warning';
      }
      
      echo "<div class='alert alert-$severity_class' role='alert'>";
      echo "<strong>[" . strtoupper($issue['severity']) . "]</strong> " . $issue['message'];
      
      if (in_array($issue['type'], ['bad_dates', 'bad_nbparts'])) {
         echo " <form method='POST' style='display: inline;'>";
         echo "<input type='hidden' name='action' value='fix_issue'>";
         echo "<input type='hidden' name='issue_type' value='" . $issue['type'] . "'>";
         echo "<button type='submit' class='btn btn-sm btn-danger' onclick='return confirm(\"Corriger ce problème?\");'>";
         echo "Corriger";
         echo "</button>";
         echo "</form>";
      }
      
      echo "</div>";
   }
} else {
   echo "<div class='alert alert-success'>";
   echo "<strong>Toutes les données sont intègres</strong> ✓";
   echo "</div>";
}

echo "</div>";
echo "</div>";

// Archivage automatique
echo "<div class='card mb-3'>";
echo "<div class='card-header'>";
echo "<h5>Archivage</h5>";
echo "</div>";
echo "<div class='card-body'>";

$archived_count = PluginAssociatesmanagerDataQuality::archiveOldParts(180);

echo "<p>Parts inactives depuis plus de 180 jours: <strong>$archived_count</strong></p>";
echo "<form method='POST' style='display: inline;'>";
echo "<input type='hidden' name='action' value='archive_old'>";
echo "<button type='submit' class='btn btn-warning' onclick='return confirm(\"Archiver les parts inactives?\");'>";
echo "Archiver maintenant";
echo "</button>";
echo "</form>";

echo "</div>";
echo "</div>";

// Statistiques
echo "<div class='card'>";
echo "<div class='card-header'>";
echo "<h5>Statistiques</h5>";
echo "</div>";
echo "<div class='card-body'>";

global $DB;

// Total de parts
$it = $DB->request(['FROM' => 'glpi_plugin_associatesmanager_parts']);
$total_parts = $it->count();

// Parts actives
$it = $DB->request(['FROM' => 'glpi_plugin_associatesmanager_parts', 'WHERE' => [['OR' => [['date_fin' => null], ['date_fin' => ''], ['date_fin' => '0000-00-00']]]]]);
$active_parts = $it->count();

// Parts inactives
$it = $DB->request(['FROM' => 'glpi_plugin_associatesmanager_parts', 'WHERE' => [['AND' => [['date_fin' => ['!=', null]], ['date_fin' => ['!=', '']], ['date_fin' => ['!=', '0000-00-00']]]]]]);
$inactive_parts = $it->count();

// Total associés
$it = $DB->request(['FROM' => 'glpi_plugin_associatesmanager_associates']);
$total_associates = $it->count();

// Fournisseurs ayant des parts
$it = $DB->request(['DISTINCT' => true, 'FROM' => 'glpi_plugin_associatesmanager_parts']);
$suppliers = [];
foreach ($it as $row) {
   $suppliers[] = $row['supplier_id'];
}
$total_suppliers = count(array_unique($suppliers));

echo "<table class='table'>";
echo "<tr><th>Métrique</th><th>Valeur</th></tr>";
echo "<tr><td>Total de parts</td><td><strong>$total_parts</strong></td></tr>";
echo "<tr><td>Parts actives</td><td><span class='badge badge-success'>$active_parts</span></td></tr>";
echo "<tr><td>Parts inactives</td><td><span class='badge badge-secondary'>$inactive_parts</span></td></tr>";
echo "<tr><td>Total associés</td><td><strong>$total_associates</strong></td></tr>";
echo "<tr><td>Fournisseurs ayant des parts</td><td><strong>$total_suppliers</strong></td></tr>";
echo "</table>";

echo "</div>";
echo "</div>";

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $action = $_POST['action'] ?? '';
   
   if ($action === 'merge_parts') {
      $keep_id = intval($_POST['keep_id']);
      $remove_id = intval($_POST['remove_id']);
      
      if (PluginAssociatesmanagerDataQuality::mergeParts($keep_id, $remove_id)) {
         Session::addMessageAfterRedirect('Parts fusionnées avec succès', false, INFO);
      } else {
         Session::addMessageAfterRedirect('Erreur lors de la fusion', false, ERROR);
      }
      
      header('Location: admin.php');
      exit;
   }
   
   if ($action === 'fix_issue') {
      $issue_type = $_POST['issue_type'] ?? '';
      
      if (PluginAssociatesmanagerDataQuality::fixDataIssue($issue_type)) {
         Session::addMessageAfterRedirect('Problème corrigé avec succès', false, INFO);
      } else {
         Session::addMessageAfterRedirect('Erreur lors de la correction', false, ERROR);
      }
      
      header('Location: admin.php');
      exit;
   }
}

echo "</div>";

Html::footer();
