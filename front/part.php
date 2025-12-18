<?php

require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

// Vérifier les droits granulaires
if (!PluginAssociatesmanagerRight::canRead()) {
   Html::displayRightError();
}

Html::header(
   PluginAssociatesmanagerPart::getTypeName(Session::getPluralNumber()),
   $_SERVER['PHP_SELF'],
   'admin',
   'PluginAssociatesmanagerMenu',
   'part'
);

// Inline stylesheet to avoid web-path issues (plugin webdir may vary)
$amCss = __DIR__ . '/../css/associates.css';
if (is_readable($amCss)) {
   echo '<style>' . file_get_contents($amCss) . '</style>';
}

// Shell page container
echo "<div class='am-page'><div class='am-stack'>";

// Add "New" button in header if user has CREATE right
if (PluginAssociatesmanagerRight::canCreate()) {
   echo "<div class='am-toolbar'>";
   echo "<a href='" . PluginAssociatesmanagerPart::getFormURL() . "' class='btn btn-primary'>";
   echo "<i class='fas fa-plus'></i>";
   echo "<span>Nouvelle part</span>";
   echo "</a>";
   echo "</div>";
}

// Filters: supplier, associate, part type (libelle), search, date range
global $DB;

$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$associate_id = (int)($_GET['associates_id'] ?? 0);
$part_label  = trim($_GET['libelle'] ?? '');
$q_search    = '';
$date_from   = '';
$date_to     = '';
$sort_by     = ($_GET['sort_by'] ?? 'date_attribution');
$sort_dir    = (strtoupper($_GET['sort_dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

echo "<div class='am-card'>";
echo "<div class='am-filters'>";

// Supplier filter - build options
echo "<label>Fournisseur";
echo "<select id='filter-supplier'><option value=''>Tous</option>";
$supplier_it = $DB->request([
   'SELECT' => ['id', 'name'],
   'FROM'   => 'glpi_suppliers',
   'WHERE'  => ['is_deleted' => 0],
   'ORDER'  => 'name'
]);
foreach ($supplier_it as $s) {
   $selected = ($supplier_id == $s['id']) ? 'selected' : '';
   echo "<option value='" . $s['id'] . "' " . $selected . ">" . htmlspecialchars($s['name']) . "</option>";
}
echo "</select>";
echo "</label>";

// Associate filter - build options
echo "<label>Associé";
echo "<select id='filter-assoc'><option value=''>Tous</option>";
$assoc_it = $DB->request([
   'SELECT' => ['id', 'name'],
   'FROM'   => 'glpi_plugin_associatesmanager_associates',
   'ORDER'  => 'name'
]);
foreach ($assoc_it as $a) {
   $selected = ($associate_id == $a['id']) ? 'selected' : '';
   echo "<option value='" . $a['id'] . "' " . $selected . ">" . htmlspecialchars($a['name']) . "</option>";
}
echo "</select>";
echo "</label>";

// Type filter (Person/Company)
echo "<label>Type";
echo "<select id='filter-type'><option value=''>Tous</option><option value='1'>Personne</option><option value='0'>Société</option></select>";
echo "</label>";

// Search box
echo "<label>Recherche";
echo "<input type='search' id='filter-text' placeholder='Nom, email, ville...'>";
echo "</label>";

// Sort buttons
echo "<button id='sort-nb' class='btn btn-outline-secondary btn-sm' type='button' data-order='desc'>Tri parts ↓</button>";
echo "<button id='sort-pct' class='btn btn-outline-secondary btn-sm' type='button' data-order='desc'>Tri % ↓</button>";

echo "</div>";
echo "</div>"; // am-card

// JavaScript: restore base filter values and submit the form
// Base/default values are taken from PHP variables above
$defaults = [
   // Reset targets: clear filters to base state
   'supplier_id' => 0,
   'associates_id' => 0,
   'libelle' => '',
   // search removed
   // date_from/date_to removed
   'sort_by' => 'date_attribution',
   'sort_dir' => 'DESC'
];
// reset JS removed

// Build WHERE conditions
$where = [];
$params = [];
if ($supplier_id > 0) { $where[] = "p.supplier_id = :supplier_id"; $params[':supplier_id'] = $supplier_id; }
if ($associate_id > 0) { $where[] = "p.associates_id = :associate_id"; $params[':associate_id'] = $associate_id; }
if ($part_label !== '') { $where[] = "p.libelle = :libelle"; $params[':libelle'] = $part_label; }
// Search-based raw WHERE removed

// Date range overlap: we want assignments that intersect [date_from, date_to]
// Date filtering removed per user request.

// Build structured query arguments for DB wrapper
// Simpler flat WHERE map: keys are fields and values are either scalars or
// operator arrays. Use 'OR' for text search across multiple fields.
$where_criteria = [];
if ($supplier_id > 0) { $where_criteria['p.supplier_id'] = $supplier_id; }
if ($associate_id > 0) { $where_criteria['p.associates_id'] = $associate_id; }
if ($part_label !== '') { $where_criteria['p.libelle'] = $part_label; }
// Search-based structured WHERE removed
// Date filters: show parts whose attribution date is within the requested range.
// If both bounds provided, add both operators on the same field.
// Date filters removed per user request.

// Sorting
if ($sort_by === 'percent') {
   // percent is computed per row; we will fetch rows and sort in PHP
   $order_sql = 'p.date_attribution DESC';
} else {
   $allowed = ['date_attribution','nbparts'];
   if (!in_array($sort_by, $allowed)) { $sort_by = 'date_attribution'; }
   $order_sql = 'p.' . $sort_by . ' ' . $sort_dir;
}

// Use DB wrapper structured request and include supplier.nbparttotal directly for reliability
$request_args = [
   'SELECT' => [ 'p.*', 's.name AS supplier_name', 's.nbparttotal AS supplier_nbparttotal' ],
   'FROM' => 'glpi_plugin_associatesmanager_parts AS p',
   'LEFT JOIN' => [
      'glpi_plugin_associatesmanager_associates AS a' => ['a.id' => 'p.associates_id'],
      'glpi_suppliers AS s' => ['s.id' => 'p.supplier_id']
   ],
   'WHERE' => $where_criteria,
   'ORDER' => $order_sql
];

$rows = [];
$it = $DB->request($request_args);
foreach ($it as $r) {
   $rows[] = $r;
}

// Debug helper: show constructed WHERE criteria and fetched rows when requested
// When ?debug_filters=1 is present we both echo detailed info and append a
// human-readable dump into a log file in the plugin root for offline
// inspection. This helps diagnose issues that don't appear with a simple
// on-screen dump (formatting, missing fields, etc.).
if (!empty($_GET['debug_filters'])) {
   $debug = [];
   $debug['timestamp'] = date('c');
   $debug['request_uri'] = ($_SERVER['REQUEST_URI'] ?? '');
   $debug['get'] = $_GET;
   $debug['date_from'] = $date_from;
   $debug['date_to'] = $date_to;
   $debug['supplier_id'] = $supplier_id;
   $debug['associates_id'] = $associate_id;
   $debug['part_label'] = $part_label;
   $debug['q_search'] = $q_search;
   $debug['raw_where_clauses'] = $where; // older raw SQL-fragments for reference
   $debug['where_criteria'] = $where_criteria; // structured criteria sent to DB wrapper
   $debug['request_args'] = $request_args;
   $debug['rows_count'] = count($rows);
   $debug['rows_sample'] = array_slice($rows, 0, 10);

   // Compose readable output for browser
   echo "<div class='spaced'><pre style='text-align:left;'>";
   echo htmlspecialchars("Debug dump (frontend) -- " . $debug['timestamp'] . "\n\n");
   echo htmlspecialchars("REQUEST_URI: " . $debug['request_uri'] . "\n\n");
   echo htmlspecialchars("GET:\n" . var_export($debug['get'], true) . "\n\n");
   echo htmlspecialchars("where_criteria:\n" . var_export($debug['where_criteria'], true) . "\n\n");
   echo htmlspecialchars("rows_count: " . $debug['rows_count'] . "\n\n");
   if ($debug['rows_count']) {
      echo htmlspecialchars("rows_sample:\n" . var_export($debug['rows_sample'], true) . "\n\n");
   }
   echo htmlspecialchars("request_args:\n" . var_export($debug['request_args'], true) . "\n");
   echo "</pre></div>";

   // Debug dump displayed in browser; file logging disabled
}

// Build maps for associates and suppliers to show names/phone/town
$assocIds = [];
$supplierIds = [];
foreach ($rows as $r) {
   if (!empty($r['associates_id'])) { $assocIds[$r['associates_id']] = (int)$r['associates_id']; }
   if (!empty($r['supplier_id']))   { $supplierIds[$r['supplier_id']] = (int)$r['supplier_id']; }
}

$associates = [];
if (count($assocIds)) {
   $it2 = $DB->request([
      'SELECT' => ['id','name','is_person','phonenumber','town'],
      'FROM'   => 'glpi_plugin_associatesmanager_associates',
      'WHERE'  => ['id' => array_values($assocIds)]
   ]);
   foreach ($it2 as $a) {
      $associates[$a['id']] = $a;
   }
}

$suppliers = [];
if (count($supplierIds)) {
   // Fetch full supplier rows to be robust if nbparttotal column is not present
   $it3 = $DB->request([
      'SELECT' => ['*'],
      'FROM'   => 'glpi_suppliers',
      'WHERE'  => ['id' => array_values($supplierIds)]
   ]);
   foreach ($it3 as $s) {
      $name = $s['name'] ?? '';
      $nb = 0.0;
      if (isset($s['nbparttotal']) && is_numeric($s['nbparttotal'])) {
         $nb = (float)$s['nbparttotal'];
      } else {
         foreach ($s as $k => $v) {
            if (stripos($k, 'nbpart') !== false || stripos($k, 'nb_part') !== false) {
               if (is_numeric($v)) { $nb = (float)$v; break; }
            }
         }
      }
      $suppliers[$s['id']] = [ 'name' => $name, 'nbparttotal' => $nb ];
   }
}

// If sorting by percent, compute percent for each row and sort in PHP
if ($sort_by === 'percent' && count($rows)) {
   $part_helper = new PluginAssociatesmanagerPart();
   foreach ($rows as &$r) {
      // Use the row's attribution date for percent calculation
      $supplierDeclaredTotal = 0.0;
      if (!empty($r['supplier_id']) && isset($suppliers[$r['supplier_id']])) {
         $supplierDeclaredTotal = $suppliers[$r['supplier_id']]['nbparttotal'];
      }
      if ($supplierDeclaredTotal > 0.0) {
         $nb = isset($r['nbparts']) ? (float)$r['nbparts'] : 0.0;
         $r['percent'] = ($supplierDeclaredTotal > 0.0) ? ($nb / $supplierDeclaredTotal * 100.0) : 0.0;
      } else {
         $r['percent'] = $part_helper->computeSharePercent($r['associates_id'], $r['supplier_id'], $r['date_attribution']);
      }
   }
   usort($rows, function($a, $b) use ($sort_dir) {
      $pa = $a['percent'] ?? 0.0;
      $pb = $b['percent'] ?? 0.0;
      if ($pa == $pb) return 0;
      if ($sort_dir === 'ASC') return ($pa < $pb) ? -1 : 1;
      return ($pa > $pb) ? -1 : 1;
   });
}

echo "<div class='am-card'>";
if (count($rows)) {
   echo "<table class='tab_cadre_fixehov am-table'>";
   echo "<tr class='noHover'><th colspan='11'>" . PluginAssociatesmanagerPart::getTypeName(count($rows)) . "</th></tr>";
   echo "<tr>";
   echo "<th>Associé</th>";
   echo "<th>Type</th>";
   echo "<th>Statut</th>";
   echo "<th>Nombre de parts</th>";
   echo "<th>Part (%)</th>";
   echo "<th>Nb parts total (fournisseur)</th>";
   echo "<th>Date d'attribution</th>";
   echo "<th>Date de fin</th>";
   echo "<th>Fournisseur associé</th>";
   echo "<th>Actions</th>";
   echo "</tr>";

   // tbody for data rows
   echo "<tbody id='parts-table-body'>";

   foreach ($rows as $data) {
      // Resolve associate data from the pre-fetched map using the id stored on the part
      $assoc = null;
      if (!empty($data['associates_id']) && isset($associates[$data['associates_id']])) {
         $assoc = $associates[$data['associates_id']];
      }
      $assoc_name = $assoc['name'] ?? '';
      $is_person = (!empty($assoc['is_person'])) ? 'Personne' : 'Société';
      $is_person_value = (!empty($assoc['is_person'])) ? 1 : 0;
      $phone = $assoc['phonenumber'] ?? '';
      $town  = $assoc['town'] ?? '';
      $label = $data['libelle'] ?? '';
      $nbparts = isset($data['nbparts']) ? (float)$data['nbparts'] : 0.0;
      $date_attr = $data['date_attribution'] ?? null;
      $date_fin = $data['date_fin'] ?? null;
   // Resolve supplier name from the pre-fetched map using the id stored on the part
   $supplier_name = '';
   $supplierDeclaredTotal = 0.0;
   if (!empty($data['supplier_id']) && isset($suppliers[$data['supplier_id']])) {
      $supplier_name = $suppliers[$data['supplier_id']]['name'];
      $supplierDeclaredTotal = $suppliers[$data['supplier_id']]['nbparttotal'];
   }

   // percent: compute on date_from if provided, else on date_attribution
   // computeSharePercent is an instance method; ensure helper exists
   if (!isset($part_helper)) { $part_helper = new PluginAssociatesmanagerPart(); }
      // Prefer declared supplier total when available for percentage calculation
      if ($supplierDeclaredTotal > 0.0) {
         $pct = ($supplierDeclaredTotal > 0.0) ? ($nbparts / $supplierDeclaredTotal * 100.0) : 0.0;
      } else {
         $pct = $part_helper->computeSharePercent($data['associates_id'], $data['supplier_id'], $date_attr);
      }

      // Gray out rows with an end date (closed parts)
      $hasEndDate = !empty($date_fin) && $date_fin !== '0000-00-00';
      
      // Highlight bénéficiaires effectifs (yellow background)
      $isBeneficiaire = stripos($label, 'bénéficiaire') !== false || stripos($label, 'beneficiaire') !== false || stripos($label, 'dirigeant') !== false || stripos($label, 'exploitant') !== false;
      
      $rowClass = 'tab_bg_1';
      if ($isBeneficiaire && !$hasEndDate) {
         $rowClass .= ' associate-highlight';
      }
      $rowStyle = $hasEndDate ? "style='background-color:#e0e0e0; opacity:0.7;'" : "";
      
      echo "<tr class='$rowClass' $rowStyle data-supplier='" . $data['supplier_id'] . "' data-assoc='" . $data['associates_id'] . "' data-type='" . $is_person_value . "' data-libelle='" . htmlspecialchars($label) . "' data-nb='" . $nbparts . "' data-pct='" . $pct . "'>";
      echo "<td>";
      if ($assoc_name !== '') {
         echo "<a href='" . Plugin::getWebDir('associatesmanager') . "/front/associate.form.php?id=" . $data['associates_id'] . "'>" . htmlspecialchars($assoc_name) . "</a>";
      } else {
         echo "&mdash;";
      }
      echo "</td>";
      echo "<td>" . htmlspecialchars($is_person) . "</td>";
      echo "<td>";
      if ($isBeneficiaire) {
         echo "<span class='am-badge success'>Bénéficiaire effectif</span>";
      } else {
         echo "<span class='am-badge neutral'>Associé</span>";
      }
      echo "</td>";
      echo "<td class='left'>" . number_format($nbparts, 4, ',', ' ') . "</td>";
      echo "<td class='left'>" . sprintf('%.1f', $pct) . "%</td>";
      // Supplier declared total: formatted or em-dash if not set
      $supplierDisplay = ($supplierDeclaredTotal > 0.0) ? number_format($supplierDeclaredTotal, 2, ',', ' ') : '&mdash;';
      echo "<td class='left'>" . $supplierDisplay . "</td>";
      echo "<td>" . Html::convDate($date_attr) . "</td>";
      echo "<td>" . Html::convDate($date_fin) . "</td>";
      echo "<td>";
      if ($supplier_name !== '') {
         echo "<a href='" . Plugin::getWebDir('associatesmanager') . "/front/supplier.form.php?id=" . $data['supplier_id'] . "'>" . htmlspecialchars($supplier_name) . "</a>";
      } else {
         echo "&mdash;";
      }
      echo "</td>";
      echo "<td>";
      // Seule la modification reste accessible; la suppression se gère via le formulaire d'édition
      if (PluginAssociatesmanagerRight::canUpdate()) {
         echo "<a href='" . Plugin::getWebDir('associatesmanager') . "/front/part.form.php?id=" . $data['id'] . "' title='Éditer'><i class='fas fa-edit'></i></a>";
      }
      echo "</td>";
      echo "</tr>";
   }
   
   echo "</tbody>";
   
   echo "</table>";
} else {
   echo "<table class='tab_cadre_fixe am-table'>";
   echo "<tr><th>Aucune part trouvée</th></tr>";
   echo "</table>";
}
echo "</div>"; // am-card

echo "</div></div>"; // am-stack + am-page

// Client-side filtering + sorting script (same as associate.php)
echo <<<'JS'
<script>
(function($){
   if (!$) return;
   
   function applyFilters(){
     var fs = $('#filter-supplier').val();
     var fa = $('#filter-assoc').val();
     var ft = $('#filter-type').val();
     var txt = $('#filter-text').val().toLowerCase();
     $('#parts-table-body tr').each(function(){
       var $tr = $(this);
       var ok = true;
       if (fs && String($tr.data('supplier')) !== fs) ok = false;
       if (fa && String($tr.data('assoc')) !== fa) ok = false;
       if (ft !== '' && String($tr.data('type')) !== ft) ok = false;
       if (txt){
         var line = ($tr.find('td').map(function(){ return $(this).text(); }).get().join(' ')).toLowerCase();
         if (line.indexOf(txt) === -1) ok = false;
       }
       $tr.toggle(ok);
     });
   }
   
   $('#filter-supplier,#filter-assoc,#filter-type').on('change', applyFilters);
   $('#filter-text').on('input', function(){ setTimeout(applyFilters, 100); });

    function sortTableByData(attr, order){
         var tbody = $('#parts-table-body');
         if (!tbody.length) return;

         applyFilters();

         var all = tbody.children('tr').get();
         var visiblePositions = [];
         for (var i=0;i<all.length;i++){
            if ($(all[i]).is(':visible')) visiblePositions.push(i);
         }
         if (!visiblePositions.length) return;

         var visibleNodes = [];
         for (var j=0;j<visiblePositions.length;j++){
            var node = all[visiblePositions[j]];
            var detached = $(node).detach().get(0);
            visibleNodes.push(detached);
         }

         visibleNodes.sort(function(a,b){
             var va = parseFloat($(a).data(attr)) || 0;
             var vb = parseFloat($(b).data(attr)) || 0;
             return (order === 'asc') ? va - vb : vb - va;
         });

         var newOrder = [];
         var si = 0;
         for (var k=0;k<all.length;k++){
            if (visiblePositions.indexOf(k) !== -1){
               newOrder.push(visibleNodes[si++]);
            } else {
               newOrder.push(all[k]);
            }
         }

         tbody.empty();
         for (var m=0;m<newOrder.length;m++){
            var nd = newOrder[m];
            tbody.append(nd);
            if (visiblePositions.indexOf(m) !== -1){
               $(nd).show();
            } else {
               $(nd).hide();
            }
         }
    }

   $('#sort-nb').on('click', function(){
     var $b = $(this); var order = $b.data('order') === 'asc' ? 'desc' : 'asc';
     $b.data('order', order).text('Tri parts ' + (order==='asc'?'↑':'↓'));
     sortTableByData('nb', order);
   });
   $('#sort-pct').on('click', function(){
     var $b = $(this); var order = $b.data('order') === 'asc' ? 'desc' : 'asc';
     $b.data('order', order).text('Tri % ' + (order==='asc'?'↑':'↓'));
     sortTableByData('pct', order);
   });

})(window.jQuery);
</script>
JS;

Html::footer();
