<?php

require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight('plugin_associatesmanager', READ);

Html::header(
   PluginAssociatesmanagerAssociate::getTypeName(Session::getPluralNumber()),
   $_SERVER['PHP_SELF'],
   'admin',
   'PluginAssociatesmanagerMenu',
   'associate'
);

// Inline stylesheet to avoid web-path issues (plugin webdir may vary)
$amCss = __DIR__ . '/../css/associates.css';
if (is_readable($amCss)) {
   echo '<style>' . file_get_contents($amCss) . '</style>';
}

// Shell page container
echo "<div class='am-page'><div class='am-stack'>";

// Add "New" button in header if user has CREATE right
if (Session::haveRight('plugin_associatesmanager', CREATE)) {
   echo "<div class='am-toolbar'>";
   echo "<a href='" . PluginAssociatesmanagerAssociate::getFormURL() . "' class='btn btn-primary'>";
   echo "<i class='fas fa-plus'></i>";
   echo "<span>Nouvel associé</span>";
   echo "</a>";
   // Also add a button to view the full parts history
   if (Session::haveRight('plugin_associatesmanager', READ)) {
      // Link to the Parts page which now exposes the full history view
      echo "<a href='" . Plugin::getWebDir('associatesmanager') . "/front/part.php' class='btn btn-secondary'>";
      echo "<i class='fas fa-history'></i>";
      echo "<span>Voir l'historique des parts</span>";
      echo "</a>";
   }
   echo "</div>";
}

// Custom table: one row per (supplier, associate) duo showing parts and percentage
global $DB;

// Récupère toutes les parts actives (date_fin IS NULL) en joignant la table fournisseurs
$it = $DB->request([
   'SELECT' => ['p.*','s.name AS supplier_name','s.nbparttotal AS supplier_nbparttotal'],
   'FROM'   => 'glpi_plugin_associatesmanager_parts AS p',
   'LEFT JOIN' => [ 'glpi_suppliers AS s' => ['s.id' => 'p.supplier_id'] ],
   'WHERE'  => ['p.date_fin' => null]
]);

$pairLibelles = []; // key supplier|associate => libelle

$pairs = []; // key supplier|associate => nbparts sum
$supplierTotals = []; // supplier_id => total nbparts
$assocIds = [];
$supplierIds = [];
$pairDates = []; // key supplier|associate => ['date_attribution'=>..., 'date_fin'=>...]

   foreach ($it as $r) {
   $sid = $r['supplier_id'];
   $aid = $r['associates_id'];
   $nb  = isset($r['nbparts']) ? (float)$r['nbparts'] : 0.0;
   // If the join returned a supplier total, prefer it for later use
   if (isset($r['supplier_nbparttotal']) && is_numeric($r['supplier_nbparttotal'])) {
      // stash supplier total into suppliers map so we don't need a separate query later
      $supplierRow = $r['supplier_name'] ?? null;
      $suppliers[$sid] = [ 'name' => $supplierRow ?? ('ID ' . $sid), 'nbparttotal' => (float)$r['supplier_nbparttotal'] ];
   }
   $key = $sid . '|' . $aid;
   if (!isset($pairs[$key])) {
      $pairs[$key] = 0.0;
   }
   $pairs[$key] += $nb;
   
   // Store libelle for highlighting
   if (!isset($pairLibelles[$key])) {
      $pairLibelles[$key] = $r['libelle'] ?? 'Associé';
   }

   // Track the attribution/fin dates for the duo. For safety keep the most recent
   // date_attribution if multiple rows exist (shouldn't for active rows but be robust).
   if (!isset($pairDates[$key]) || empty($pairDates[$key]['date_attribution']) ||
       strtotime($r['date_attribution']) > strtotime($pairDates[$key]['date_attribution'])) {
      $pairDates[$key] = [
         'date_attribution' => $r['date_attribution'] ?? null,
         'date_fin' => $r['date_fin'] ?? null
      ];
   }

   if (!isset($supplierTotals[$sid])) {
      $supplierTotals[$sid] = 0.0;
   }
   $supplierTotals[$sid] += $nb;

   $assocIds[$aid] = $aid;
   $supplierIds[$sid] = $sid;
}

// Fetch associate details
$associates = [];
if (count($assocIds)) {
   $it2 = $DB->request([
      'SELECT' => ['id','name','is_person','email','phonenumber','town'],
      'FROM'   => 'glpi_plugin_associatesmanager_associates',
      'WHERE'  => ['id' => array_values($assocIds)]
   ]);
   foreach ($it2 as $a) {
      $associates[$a['id']] = $a;
   }
}

// Fetch supplier names and declared total parts (nbparttotal)
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
      // Robust detection: try common column names and fall back to scanning row for numeric field containing 'nbpart'
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

if (count($pairs)) {
   echo "<div class='am-card'>";
   // Filters toolbar
   echo "<div class='am-filters'>";
   echo "<label>Fournisseur";
   echo "<select id='filter-supplier'><option value=''>Tous</option>";
   foreach ($suppliers as $sid => $sentry) {
      $displayName = is_array($sentry) ? ($sentry['name'] ?? 'ID ' . $sid) : $sentry;
      echo "<option value='" . $sid . "'>" . htmlspecialchars($displayName) . "</option>";
   }
   echo "</select></label>";

   echo "<label>Associé";
   echo "<select id='filter-assoc'><option value=''>Tous</option>";
   foreach ($associates as $aid => $a) {
      echo "<option value='" . $aid . "'>" . htmlspecialchars($a['name']) . "</option>";
   }
   echo "</select></label>";

   echo "<label>Type";
   echo "<select id='filter-type'><option value=''>Tous</option><option value='1'>Personne</option><option value='0'>Société</option></select>";
   echo "</label>";

   echo "<label>Recherche";
   echo "<input type='search' id='filter-text' placeholder='Nom, email, ville...'>";
   echo "</label>";

   echo "<button id='sort-nb' class='btn btn-outline-secondary btn-sm' data-order='desc'>Tri parts ↓</button>";
   echo "<button id='sort-pct' class='btn btn-outline-secondary btn-sm' data-order='desc'>Tri % ↓</button>";
   echo "</div>";

   echo "<table class='tab_cadre_fixehov am-table'>";
   echo "<tr class='noHover'><th colspan='12'>Associés - parts par fournisseur</th></tr>";
   echo "<tr>";
   echo "<th>Nom</th>";
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

   // tbody for data rows (use an explicit id so JS targets the correct body)
   echo "<tbody id='associatesmanager-table-body'>";

   foreach ($pairs as $key => $nb) {
      list($sid, $aid) = explode('|', $key);
   $assoc = $associates[$aid] ?? null;
   $sentry = $suppliers[$sid] ?? null;
   $sname = $sentry['name'] ?? ('ID ' . $sid);
   $supplierDeclaredTotal = isset($sentry['nbparttotal']) ? (float)$sentry['nbparttotal'] : 0.0;
   // use declared total as denominator if provided, otherwise fall back to computed supplierTotals
   $denom = ($supplierDeclaredTotal > 0.0) ? $supplierDeclaredTotal : ($supplierTotals[$sid] ?? 0.0);
   $pct = ($denom > 0) ? ($nb / $denom * 100.0) : 0.0;

   // add data attributes for client-side filtering/sorting
   $da = $pairDates[$key]['date_attribution'] ?? '';
   $df = $pairDates[$key]['date_fin'] ?? '';
   $libelle = $pairLibelles[$key] ?? 'Associé';
   $isBeneficiaire = stripos($libelle, 'bénéficiaire') !== false || stripos($libelle, 'beneficiaire') !== false || stripos($libelle, 'dirigeant') !== false || stripos($libelle, 'exploitant') !== false;
   $rowClass = $isBeneficiaire ? 'tab_bg_1 associate-highlight' : 'tab_bg_1';
   echo "<tr class='" . $rowClass . "' data-supplier='" . $sid . "' data-assoc='" . $aid . "' data-type='" . ($assoc ? $assoc['is_person'] : '') . "' data-nb='" . $nb . "' data-pct='" . $pct . "' data-supplier-total='" . $supplierDeclaredTotal . "' data-date_attribution='" . ($da ?: '') . "' data-date_fin='" . ($df ?: '') . "'>";
      if ($assoc) {
         echo "<td><a href='" . Plugin::getWebDir('associatesmanager') . "/front/associate.form.php?id=" . $assoc['id'] . "'>" . htmlspecialchars($assoc['name']) . "</a></td>";
            echo "<td>" . ($assoc['is_person'] ? 'Personne' : 'Société') . "</td>";
            echo "<td>";
            if ($isBeneficiaire) {
               echo "<span class='am-badge success'>Bénéficiaire effectif</span>";
            } else {
               echo "<span class='am-badge neutral'>Associé</span>";
            }
            echo "</td>";
         } else {
            echo "<td>Assoc ID " . $aid . "</td>";
            echo "<td>-</td>";
         }

         echo "<td class='left'>" . number_format($nb, 2, ',', ' ') . "</td>";
            echo "<td class='left'>" . sprintf('%.1f', $pct) . "%</td>";
            // Supplier declared total: show formatted number when set, otherwise an em-dash for clarity
            $supplierDisplay = ($supplierDeclaredTotal > 0.0) ? number_format($supplierDeclaredTotal, 2, ',', ' ') : '&mdash;';
            echo "<td class='left'>" . $supplierDisplay . "</td>";
            echo "<td>" . Html::convDate($da) . "</td>";
            echo "<td>" . Html::convDate($df) . "</td>";
         // Link to GLPI supplier page (core Supplier) instead of plugin supplier form
         $supplier_url = '/front/supplier.form.php?id=' . $sid;
         if (class_exists('Supplier') && method_exists('Supplier', 'getFormURL')) {
            // getFormURL(true) should return the full path to the core form; append id param
            $supplier_url = Supplier::getFormURL(true) . '?id=' . $sid;
         }
         echo "<td><a href='" . $supplier_url . "'>" . htmlspecialchars($sname) . "</a></td>";
         // Actions: link to associate form where user can edit or delete
         echo "<td>";
            echo "<a class='' href='" . Plugin::getWebDir('associatesmanager') . "/front/associate.form.php?id=" . $aid . "' title='Ouvrir la fiche de l\'associé'>";
               echo "<i class='fas fa-edit'></i>";
            echo "</a>";
         echo "</td>";
      echo "</tr>";
   }

      echo "</tbody>";

      echo "</table>";
   echo "</div>"; // am-card
} else {
   echo "<div class='am-card'>";
   echo "<table class='tab_cadre_fixe am-table'>";
      echo "<tr><th>Aucun couple fournisseur/associé avec parts actives trouvé</th></tr>";
   echo "</table>";
   echo "</div>";
}
echo "</div></div>"; // am-stack + am-page

// Client-side filtering + sorting script
echo <<<'JS'
<script>
(function($){
   if (!$) return;
   function applyFilters(){
     var fs = $('#filter-supplier').val();
     var fa = $('#filter-assoc').val();
     var ft = $('#filter-type').val();
     var txt = $('#filter-text').val().toLowerCase();
     $('table.tab_cadre_fixehov tbody tr').each(function(){
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
         // Target the specific tbody by id to avoid selecting multiple/implicit tbodies
         var tbody = $('#associatesmanager-table-body');
         if (!tbody.length) return;

         // Ensure visibility state matches current filters
         applyFilters();

         // Get all child rows and record which positions are currently visible
         var all = tbody.children('tr').get();
         var visiblePositions = [];
         for (var i=0;i<all.length;i++){
            if ($(all[i]).is(':visible')) visiblePositions.push(i);
         }
         if (!visiblePositions.length) return;

         // Detach visible rows (preserves handlers/data)
         var visibleNodes = [];
         for (var j=0;j<visiblePositions.length;j++){
            var node = all[visiblePositions[j]];
            var detached = $(node).detach().get(0);
            visibleNodes.push(detached);
         }

         // Sort visible nodes by data attribute
         visibleNodes.sort(function(a,b){
             var va = parseFloat($(a).data(attr)) || 0;
             var vb = parseFloat($(b).data(attr)) || 0;
             return (order === 'asc') ? va - vb : vb - va;
         });

         // Rebuild the tbody content preserving hidden rows positions
         var newOrder = [];
         var si = 0;
         for (var k=0;k<all.length;k++){
            if (visiblePositions.indexOf(k) !== -1){
               newOrder.push(visibleNodes[si++]);
            } else {
               newOrder.push(all[k]);
            }
         }

         // Empty and append in the new order, restoring visibility state
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
