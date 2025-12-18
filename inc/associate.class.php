<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginAssociatesmanagerAssociate extends CommonDBTM {

   static $rightname = 'plugin_associatesmanager';

   static function getTypeName($nb = 0) {
   return ($nb > 1) ? 'Associés' : 'Associé';
   }

   static function getIcon() {
      return 'fas fa-users';
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Supplier') {
         if ($_SESSION['glpishow_count_on_tabs']) {
            global $DB;
            // Count distinct associates linked to this supplier via parts pivot
            // Use SELECT DISTINCT and count in PHP to avoid SQL expression parsing issues
            $supplier_id = (int)$item->getID();
            $it = $DB->request([
               'DISTINCT' => true,
               'SELECT' => ['associates_id'],
               'FROM'   => 'glpi_plugin_associatesmanager_parts',
               'WHERE'  => ['supplier_id' => $supplier_id]
            ]);
            $nb = 0;
            foreach ($it as $r) {
               $nb++;
            }
            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb);
         }
         return self::getTypeName(Session::getPluralNumber());
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == 'Supplier') {
         self::showForSupplier($item);
      }
      return true;
   }

   static function showForSupplier(Supplier $supplier) {
      global $DB, $CFG_GLPI;

      // Charger le CSS du plugin
      echo '<style>';
      echo file_get_contents(__DIR__ . '/../css/associates.css');
      echo '</style>';

      $supplier_id = $supplier->getID();
      $canedit = Session::haveRight('plugin_associatesmanager', UPDATE);

      // Debugging removed: logging disabled in production.
      // (Editing nbparttotal from the supplier tab removed — value is read-only for now)

      // Récupère le nombre de parts actif (date_fin IS NULL) par associé pour ce fournisseur
      // Récupère les parts actives pour ce fournisseur et calcule les totaux en PHP
      $assocParts = [];
      $totalParts = 0;
      $assocDates = []; // associate_id => ['date_attribution'=>..., 'date_fin'=>...]
   // Declared supplier total (may be provided on supplier record). Initialize to avoid warnings.
   $supplierDeclaredTotal = 0.0;
   $supplierTotalSource = 'none';
         // Proactively fetch the supplier row so we always have access to its fields
         // (some DB wrappers or permissions may prevent the JOIN from returning the column)
         $supplierRow = null;
         $itSup0 = $DB->request([
            'SELECT' => ['*'],
            'FROM'   => 'glpi_suppliers',
            'WHERE'  => ['id' => $supplier_id]
         ]);
         $supplierRow = $itSup0->next();
         // supplierRow debug removed
         if ($supplierRow) {
            // If the supplier row already contains the declared total, use it.
            if (isset($supplierRow['nbparttotal']) && is_numeric($supplierRow['nbparttotal'])) {
               $supplierDeclaredTotal = (float)$supplierRow['nbparttotal'];
               $supplierTotalSource = 'supplier_row_initial';
            } else {
               // scan row for any numeric field containing 'nbpart'
               foreach ($supplierRow as $k => $v) {
                  if ((stripos($k, 'nbpart') !== false || stripos($k, 'nb_part') !== false) && is_numeric($v)) {
                     $supplierDeclaredTotal = (float)$v;
                     $supplierTotalSource = 'scanned_field_initial('.$k.')';
                     break;
                  }
               }
            }
         }
      // Fetch parts for this supplier. We intentionally fetch all rows for the supplier
      // and filter for active assignments in PHP because some imports or SQL modes
      // may store "open" rows with date_fin = NULL, empty string or '0000-00-00'.
      // Include supplier.nbparttotal via LEFT JOIN so we can detect a declared total
      // if the DB wrapper returns it on the joined row.
      $it = $DB->request([
         'SELECT' => ['p.*', 's.nbparttotal AS supplier_nbparttotal', 's.name AS supplier_name'],
         'FROM'   => 'glpi_plugin_associatesmanager_parts AS p',
         'LEFT JOIN' => [ 'glpi_suppliers AS s' => ['s.id' => 'p.supplier_id'] ],
         'WHERE'  => [
            'p.supplier_id' => $supplier_id,
         ]
      ]);
      $partsFetched = [];
      foreach ($it as $r) {
         $partsFetched[] = $r;
         // Consider a part active when date_attribution is set and date_fin is either
         // NULL, empty string or '0000-00-00' or strictly > current date attributes
         $da = $r['date_attribution'] ?? null;
         $df = $r['date_fin'] ?? null;
         if (empty($da)) {
            // skip rows without an attribution date
            continue;
         }
         // Treat rows as active when date_fin is not set/empty/'0000-00-00'
         if (!($df === null || trim((string)$df) === '' || $df === '0000-00-00')) {
            // not active
            continue;
         }

         $aid = $r['associates_id'];
         // Keep decimal precision for parts (use float)
         $nb  = isset($r['nbparts']) ? (float)$r['nbparts'] : 0.0;
         // If the JOIN returned a supplier total, prefer it
         if (isset($r['supplier_nbparttotal']) && is_numeric($r['supplier_nbparttotal'])) {
            $supplierDeclaredTotal = (float)$r['supplier_nbparttotal'];
            $supplierTotalSource = 'join';
         }
         if (!isset($assocParts[$aid])) {
            $assocParts[$aid] = 0;
         }
         $assocParts[$aid] += $nb;
         $totalParts += $nb;
         // Track dates for the active assignment for this associate on this supplier.
         // If multiple rows exist (unlikely for active rows) keep the most recent attribution.
         if (!isset($assocDates[$aid]) || empty($assocDates[$aid]['date_attribution']) ||
             strtotime($r['date_attribution']) > strtotime($assocDates[$aid]['date_attribution'])) {
            $assocDates[$aid] = [
               'date_attribution' => $r['date_attribution'] ?? null,
               'date_fin' => $r['date_fin'] ?? null
            ];
         }
      }

      // parts fetch debug removed

      // If supplierDeclaredTotal wasn't found via JOIN above, try a full supplier read
      if ($supplierDeclaredTotal <= 0.0) {
         $itSup = $DB->request([
            'SELECT' => ['*'],
            'FROM'   => 'glpi_suppliers',
            'WHERE'  => ['id' => $supplier_id]
         ]);
         $sr = $itSup->next();
         // supplier full row debug removed
         if ($sr) {
            if (isset($sr['nbparttotal']) && is_numeric($sr['nbparttotal'])) {
               $supplierDeclaredTotal = (float)$sr['nbparttotal'];
               $supplierTotalSource = 'supplier_row';
            } else {
               foreach ($sr as $k => $v) {
                  if ((stripos($k, 'nbpart') !== false || stripos($k, 'nb_part') !== false) && is_numeric($v)) {
                     $supplierDeclaredTotal = (float)$v; 
                     $supplierTotalSource = 'scanned_field('.$k.')';
                     break;
                  }
               }
            }
         }
      }

      // final debug removed

      $assocIds = array_keys($assocParts);
      $iterator = [];
      $assocType = []; // Stocker le type/libelle de chaque associé
      if (count($assocIds)) {
         // Faire un JOIN avec parts pour récupérer le libelle
         $parts_it = $DB->request([
            'FROM'  => 'glpi_plugin_associatesmanager_parts',
            'WHERE' => [
               'supplier_id' => $supplier_id,
               'associates_id' => $assocIds
            ]
         ]);
         foreach ($parts_it as $part) {
            $aid = $part['associates_id'];
            // Garder le libelle du premier associé trouvé
            if (!isset($assocType[$aid])) {
               $assocType[$aid] = $part['libelle'] ?? '';
            }
         }
         
         $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_associatesmanager_associates',
            'WHERE' => ['id' => $assocIds]
         ]);
      }

      // Action buttons: add part + view history + sync RNE (shown side-by-side)
         echo "<div class='am-page'><div class='am-stack'>";
         echo "<div class='am-toolbar'>";
         if ($canedit) {
            echo "<a class='btn btn-primary' href='" . Plugin::getWebDir('associatesmanager') . "/front/part.form.php?supplier_id=$supplier_id'>";
            echo "<i class='fas fa-plus'></i>";
            echo '<span>Ajouter une part</span>';
            echo "</a>";
         }
         // Editing nbparttotal from this tab has been disabled (read-only)
         if (Session::haveRight('plugin_associatesmanager', READ)) {
            echo "<a class='btn btn-secondary' href='" . Plugin::getWebDir('associatesmanager') . "/front/part.php?supplier_id=$supplier_id'>";
            echo "<i class='fas fa-history'></i>";
            echo '<span>Voir l\'historique</span>';
            echo "</a>";
         }
         // Bouton de synchronisation RNE + Voir Kbis
         if ($canedit) {
            // Récupérer le SIREN depuis la table glpi_plugin_fields_supplierdonnesisagis
            // Le SIRET est stocké là, on extrait les 9 premiers chiffres pour obtenir le SIREN
            $siren = '';
            $it = $DB->request([
               'SELECT' => ['siretfield'],
               'FROM'   => 'glpi_plugin_fields_supplierdonnesisagis',
               'WHERE'  => ['items_id' => $supplier_id, 'itemtype' => 'Supplier']
            ]);
            if ($it->count() > 0) {
               $row = $it->current();
               $siret = trim($row['siretfield'] ?? '');
               // Extraire les 9 premiers chiffres du SIRET pour obtenir le SIREN
               if (!empty($siret) && preg_match('/^[0-9]{9}/', $siret, $matches)) {
                  $siren = $matches[0];
               }
            }
            // If we could auto-detect a SIREN, provide a one-click direct sync
            if (!empty($siren)) {
               $redirect_url = Supplier::getFormURL(true) . '?id=' . $supplier_id . '&forcetab=PluginAssociatesmanagerAssociate$1';
               $sync_url = Plugin::getWebDir('associatesmanager') . "/front/rnesync.php?supplier_id=$supplier_id&siren=" . urlencode($siren) . '&silent=1&redirect=' . urlencode($redirect_url);
               echo "<a class='btn btn-info' href='" . $sync_url . "'>";
               echo "<i class='fas fa-cloud-download-alt'></i>";
               echo "<span>Synchroniser avec RNE</span>";
               echo "</a>";
            } else {
               // Otherwise, open the modal to input SIREN
               echo "<a class='btn btn-secondary' href='#' onclick='showRneSyncModal($supplier_id, \"\"); return false;'>";
               echo "<i class='fas fa-cloud-download-alt'></i>";
               echo '<span>Synchroniser depuis API RNE</span>';
               echo "</a>";
            }
            
         }
         echo "</div>";

      // Supplier total parts summary (outside the table)
      // Inline edit handled via dedicated endpoint to avoid core Supplier form validations

      $supplierTotalDisplay = '0';
      $supplierTotalRaw = '0';
      // Utiliser le paramètre $supplier (qui est un objet Supplier complet)
      if (isset($supplier) && isset($supplier->fields['nbparttotal'])) {
         $val = $supplier->fields['nbparttotal'];
         if (is_numeric($val) && (int)$val > 0) {
            $supplierTotalRaw = (string)(int)$val;
            $supplierTotalDisplay = number_format((int)$val, 0, ',', ' ');
         }
      }
      echo "<div class='am-card' style='width: fit-content; display: inline-block;'>";
      echo "<div class='am-filters' style='align-items:center; gap:8px;'>";
      echo "<strong>Nb parts total (fournisseur):</strong> ";
      echo $supplierTotalDisplay;
      if ($canedit) {
         $action = Plugin::getWebDir('associatesmanager') . "/front/update_nbparttotal.php";
         $formId = 'am-nbparttotal-form-' . (int)$supplier_id;
         $standaloneToken = Session::getNewCSRFToken(true);
         echo "<form id='" . htmlspecialchars($formId) . "' class='am-nbparttotal-form' data-action='" . htmlspecialchars($action) . "' action='" . htmlspecialchars($action) . "' method='post' style='display:flex; gap:8px; align-items:center; margin-left:12px;'>";
         echo Html::hidden('_glpi_csrf_token', ['value' => $standaloneToken]);
         echo Html::hidden('supplier_id', ['value' => $supplier_id]);
         echo Html::hidden('redirect', ['value' => Supplier::getFormURL(true) . '?id=' . $supplier_id . '&forcetab=PluginAssociatesmanagerAssociate$1']);
         echo "<input type='number' step='0.0001' min='0' name='am_nbparttotal' value='" . htmlspecialchars($supplierTotalRaw) . "' style='width:140px;'>";
         echo "<button class='btn btn-secondary btn-sm' type='submit' id='" . htmlspecialchars($formId) . "-submit'>Sauvegarder</button>";
         echo "</form>";
      }
      echo "</div>";
      echo "</div>";
      
      // Modal de synchronisation RNE (kept for the case where SIREN is missing)
      if ($canedit) {
         self::showRneSyncModal($supplier_id, $siren ?? '');
      }

      // Filters card: Associate (for this supplier), Type, Search + Sort buttons
      echo "<div class='am-card'>";
      echo "<div class='am-filters'>";

      // Build Associate dropdown (limited to associates linked to this supplier)
      echo "<label>Associé";
      echo "<select id='filter-assoc'><option value=''>Tous</option>";
      foreach ($iterator as $rowopt) {
         echo "<option value='" . (int)$rowopt['id'] . "'>" . htmlspecialchars($rowopt['name']) . "</option>";
      }
      echo "</select>";
      echo "</label>";

      // Type dropdown (Personne / Société)
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
      echo "</div>";

      // Don't apply server-side search filter
      echo "<div class='am-card'>";
      if (count($iterator)) {
         echo "<table class='tab_cadre_fixehov am-table' id='associates-table'>";
         echo "<tr class='noHover'><th colspan='11'>" . self::getTypeName(count($iterator)) . "</th></tr>";
            echo "<tr>";
         echo "<th>Nom</th>";
         echo "<th>Type</th>";
         echo "<th>Statut</th>";
         echo "<th>Email</th>";
         echo "<th>Nombre de parts</th>";
            echo "<th>Part (%)</th>";
         echo "<th>Date d'attribution</th>";
         echo "<th>Date de fin</th>";
         echo "<th>Actions</th>";
         echo "</tr>";
            // tbody for data rows
            echo "<tbody id='associates-table-body'>";

            // Compute supplier declared total (nbparttotal) if available for percent
            $supplierDeclaredTotal = 0.0;
            if (isset($supplier) && isset($supplier->fields['nbparttotal']) && is_numeric($supplier->fields['nbparttotal'])) {
            $supplierDeclaredTotal = (float)$supplier->fields['nbparttotal'];
            }

            foreach ($iterator as $data) {
            $typeLabel = isset($assocType[$data['id']]) ? $assocType[$data['id']] : 'Associé';
            $isDirector = stripos($typeLabel, 'dirigeant') !== false || stripos($typeLabel, 'exploitant') !== false || stripos($typeLabel, 'bénéficiaire') !== false || stripos($typeLabel, 'beneficiaire') !== false;
            
            // Ajouter une classe CSS si c'est un bénéficiaire/dirigeant
            $rowClass = $isDirector ? 'tab_bg_1 associate-highlight' : 'tab_bg_1';
            
            // Prepare data attributes for filtering/sorting
            $is_person_val = !empty($data['is_person']) ? 1 : 0;
            $nb = isset($assocParts[$data['id']]) ? $assocParts[$data['id']] : 0.0;
            // Base de calcul du pourcentage:
            // 1) si le fournisseur a déclaré un nbparttotal (>0), utiliser cette valeur
            // 2) si la somme des parts actives dépasse le nbparttotal déclaré, utiliser la somme pour éviter >100% cumulé
            // 3) sinon, utiliser la somme des parts actives (benef sync + associées saisies)
            if ($supplierDeclaredTotal > 0.0) {
               $baseTotal = ($totalParts > $supplierDeclaredTotal) ? $totalParts : $supplierDeclaredTotal;
            } else {
               $baseTotal = $totalParts;
            }
            $pct = ($baseTotal > 0.0) ? (($nb / $baseTotal) * 100.0) : 0.0;
            echo "<tr class='$rowClass' data-assoc='" . (int)$data['id'] . "' data-type='" . $is_person_val . "' data-nb='" . (float)$nb . "' data-pct='" . (float)$pct . "'>";
            echo "<td>";
            echo "<a href='" . Plugin::getWebDir('associatesmanager') . "/front/associate.form.php?id=" . $data['id'] . "'>";
            echo $data['name'];
            if ($isDirector) {
                  echo " <span class='am-badge info'>";
                  echo "(" . $typeLabel . ")";
                  echo "</span>";
            }
            echo "</a>";
            echo "</td>";
            echo "<td>" . ($data['is_person'] ? 'Personne' : 'Société') . "</td>";
            echo "<td>";
            if ($isDirector) {
                  echo "<span class='am-badge success'>Bénéficiaire effectif</span>";
            } else {
                  echo "<span class='am-badge neutral'>Associé</span>";
            }
            echo "</td>";
            echo "<td>" . $data['email'] . "</td>";
            echo "<td class='left'>" . number_format($nb, 2, ',', ' ') . "</td>";
            echo "<td class='left'>" . sprintf('%.1f', $pct) . "%</td>";
            // Dates
            $da = $assocDates[$data['id']]['date_attribution'] ?? '';
            $df = $assocDates[$data['id']]['date_fin'] ?? '';
            echo "<td>" . Html::convDate($da) . "</td>";
            echo "<td>" . Html::convDate($df) . "</td>";
            echo "<td>";
            if ($canedit) {
               echo "<a href='" . Plugin::getWebDir('associatesmanager') . "/front/associate.form.php?id=" . $data['id'] . "'>";
               echo "<i class='fas fa-edit'></i>";
               echo "</a>";
            }
            echo "</td>";
            echo "</tr>";
         }
            echo "</tbody>";
            echo "</table>";
      } else {
         echo "<table class='tab_cadre_fixe am-table'>";
         echo "<tr><th>Aucun associé trouvé</th></tr>";
         echo "</table>";
      }
      echo "</div>"; // am-card
      echo "</div></div>"; // am-stack + am-page
      
      // Client-side filtering + sorting script (align with associate/part pages)
      ?>
<script>
(function($){
   if (!$) return;

   function applyFilters(){
     var fa = $('#filter-assoc').val();
     var ft = $('#filter-type').val();
     var txt = $('#filter-text').val().toLowerCase();
     $('#associates-table-body tr').each(function(){
       var $tr = $(this);
       var ok = true;
       if (fa && String($tr.data('assoc')) !== fa) ok = false;
       if (ft !== '' && String($tr.data('type')) !== ft) ok = false;
       if (txt){
         var line = ($tr.find('td').map(function(){ return $(this).text(); }).get().join(' ')).toLowerCase();
         if (line.indexOf(txt) === -1) ok = false;
       }
       $tr.toggle(ok);
     });
   }

   $('#filter-assoc,#filter-type').on('change', applyFilters);
   $('#filter-text').on('input', function(){ setTimeout(applyFilters, 100); });

   function sortTableByData(attr, order){
         var tbody = $('#associates-table-body');
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
     $b.data('order', order).text('Tri parts ' + (order==='asc'?'\u2191':'\u2193'));
     sortTableByData('nb', order);
   });
   $('#sort-pct').on('click', function(){
     var $b = $(this); var order = $b.data('order') === 'asc' ? 'desc' : 'asc';
     $b.data('order', order).text('Tri % ' + (order==='asc'?'\u2191':'\u2193'));
     sortTableByData('pct', order);
   });

   // Submit nbparttotal without nesting a form inside the Supplier form
   var nbFormId = '#am-nbparttotal-form-<?php echo (int)$supplier_id; ?>';
   var nbBtnId = nbFormId + '-submit';
   // Inline fetch handler disabled; rely on native form submission + GLPI CSRF
   // $(document).on('click', nbBtnId, function(){ ... });

})(window.jQuery);
</script>
<?php
   }

   function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);
   // Historical tab removed (history is available via Parts list)
      return $ong;
   }

   function showForm($ID, array $options = []) {
      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         $this->check(-1, CREATE);
      }

      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
   echo "<td>Nom *</td>";
      echo "<td>";
      echo Html::input('name', ['value' => $this->fields['name'], 'size' => 50]);
      echo "</td>";

   echo "<td>Type *</td>";
      echo "<td>";
      // Show a dropdown with explicit labels 'Person' / 'Company' instead of Yes/No
      $type_values = [
         1 => 'Personne',
         0 => 'Société'
      ];
   // Ensure default for new associate is 'Société' (is_person = 0)
   $default_is_person = ($ID > 0) ? $this->fields['is_person'] : 0;
   $p = ['name' => 'is_person', 'value' => $default_is_person];
   Dropdown::showFromArray($p['name'], $type_values, $p);
      echo "</td>";
      echo "</tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td>Matricule (N° Insee)</td>";
   echo "<td>";
   echo Html::input('matricule', ['value' => $this->fields['matricule'], 'size' => 30]);
   echo "</td>";

   echo "<td>Contact</td>";
   echo "<td>";
   Contact::dropdown(['name' => 'contacts_id', 'value' => $this->fields['contacts_id']]);
   echo "</td>";
   echo "</tr>";

      echo "<tr class='tab_bg_1'>";
   echo "<td>Email</td>";
      echo "<td>";
      echo Html::input('email', ['value' => $this->fields['email'], 'size' => 50]);
      echo "</td>";

   echo "<td>Téléphone</td>";
      echo "<td>";
      echo Html::input('phonenumber', ['value' => $this->fields['phonenumber'], 'size' => 30]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
   echo "<td>Adresse</td>";
      echo "<td>";
      echo "<textarea name='address' rows='3' cols='50'>" . $this->fields['address'] . "</textarea>";
      echo "</td>";

   echo "<td>Code postal</td>";
      echo "<td>";
      echo Html::input('postcode', ['value' => $this->fields['postcode'], 'size' => 10]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
   echo "<td>Ville</td>";
      echo "<td>";
      echo Html::input('town', ['value' => $this->fields['town'], 'size' => 50]);
      echo "</td>";

   echo "<td></td>";
   echo "<td></td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
   echo "<td>Pays</td>";
      echo "<td>";
      echo Html::input('country', ['value' => $this->fields['country'], 'size' => 50]);
      echo "</td>";
      echo "<td colspan='2'></td>";
      echo "</tr>";

      $this->showFormButtons($options);

      return true;
   }

   function prepareInputForAdd($input) {
      if (empty($input['name'])) {
         Session::addMessageAfterRedirect('Le nom est obligatoire', false, ERROR);
         return false;
      }

      // suppliers_id is no longer stored on associates; supplier linkage is managed via parts pivot.

      return $input;
   }

   function prepareInputForUpdate($input) {
      return $input;
   }

   /**
    * Display value override to show explicit labels for is_person field
    */
   function getValueToDisplay($field, $values, $options = []) {
      if ($field === 'is_person') {
         return ($values[$field]) ? 'Personne' : 'Société';
      }
      return parent::getValueToDisplay($field, $values, $options);
   }

   function post_addItem() {
      // If an associate is a person and no contact is linked, create a Contact in GLPI.
      // Do not create Contact_Supplier mapping here because suppliers are now managed
      // via the parts pivot table.
      if ($this->fields['is_person'] == 1 && $this->fields['contacts_id'] == 0) {
         $contact = new Contact();
         $contact_data = [
            'name'     => $this->fields['name'],
            'email'    => $this->fields['email'],
            'phone'    => $this->fields['phonenumber'],
            'address'  => $this->fields['address'],
            'postcode' => $this->fields['postcode'],
            'town'     => $this->fields['town'],
            'country'  => $this->fields['country']
         ];

         $contact_id = $contact->add($contact_data);
         if ($contact_id) {
            $this->update([
               'id'          => $this->fields['id'],
               'contacts_id' => $contact_id
            ]);
         }
      }
   }

   static function dropdown($options = []) {
      global $DB, $CFG_GLPI;

      $p = [
         'name'     => 'associates_id',
         'value'    => 0,
         'comments' => true,
         'entity'   => -1,
         'entity_sons' => false,
         'on_change' => '',
         'width'    => '',
      ];

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $iterator = $DB->request([
         'SELECT' => ['id', 'name'],
         'FROM'   => 'glpi_plugin_associatesmanager_associates',
         'ORDER'  => 'name'
      ]);

      $values = [0 => Dropdown::EMPTY_VALUE];
      foreach ($iterator as $data) {
         $values[$data['id']] = $data['name'];
      }

      return Dropdown::showFromArray($p['name'], $values, $p);
   }

   function rawSearchOptions() {
      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'                 => '21001',
         'table'              => $this->getTable(),
         'field'              => 'name',
         'name'               => 'Nom',
         'datatype'           => 'itemlink',
         'massiveaction'      => false,
      ];

      $tab[] = [
         'id'                 => '21002',
         'table'              => $this->getTable(),
         'field'              => 'is_person',
         'name'               => 'Type',
         'datatype'           => 'dropdown',
         'values'             => [
            1 => 'Personne',
            0 => 'Société'
         ],
      ];

      $tab[] = [
         'id'                 => '21003',
         'table'              => $this->getTable(),
         'field'              => 'email',
         'name'               => 'Email',
         'datatype'           => 'email',
      ];

      $tab[] = [
         'id'                 => '21004',
         'table'              => $this->getTable(),
         'field'              => 'phonenumber',
         'name'               => 'Téléphone',
         'datatype'           => 'string',
      ];

      // Supplier search option removed: supplier linkage is now via the parts pivot table
      // and no longer stored on the associates table (suppliers_id was dropped).

      return $tab;
   }

   /**
    * Affiche le modal de synchronisation RNE
    * @param int $supplier_id ID du fournisseur
    * @param string $siren SIREN pré-rempli si disponible
    */
   static function showRneSyncModal($supplier_id, $siren = '') {
      echo "<div id='rneModal' class='modal fade' tabindex='-1' role='dialog' style='display:none;'>";
      echo "<div class='modal-dialog modal-lg' role='document'>";
      echo "<div class='modal-content'>";
      
      echo "<div class='modal-header'>";
      echo "<h5 class='modal-title'><i class='fas fa-cloud-download-alt'></i> Synchronisation API RNE INPI</h5>";
      echo "<button type='button' class='close' data-dismiss='modal' aria-label='Close' onclick='hideRneSyncModal()'>";
      echo "<span aria-hidden='true'>&times;</span>";
      echo "</button>";
      echo "</div>";
      
      echo "<form method='get' action='" . Plugin::getWebDir('associatesmanager') . "/front/rnesync.php'>";
      echo "<div class='modal-body'>";
      
      echo "<p>Cette fonction va récupérer automatiquement les bénéficiaires effectifs depuis le Registre National des Entreprises (INPI).</p>";
      
      echo "<div class='form-group'>";
      echo "<label for='modal_siren'>Numéro SIREN (9 chiffres) :</label>";
      echo "<input type='text' name='siren' id='modal_siren' class='form-control' value='" . htmlspecialchars($siren) . "' placeholder='123456789' pattern='[0-9]{9}' maxlength='9' required />";
      echo "<small class='form-text text-muted'>Le SIREN de l'entreprise à synchroniser</small>";
      echo "</div>";
      
      echo "<input type='hidden' name='supplier_id' value='$supplier_id' />";
      echo "<input type='hidden' name='silent' value='1' />";
      echo "<input type='hidden' name='redirect' value='" . Supplier::getFormURL(true) . '?id=' . $supplier_id . '&forcetab=PluginAssociatesmanagerAssociate$1' . "' />";
      
      echo "<div class='alert alert-info'>";
      echo "<strong>Règles de synchronisation :</strong>";
      echo "<ul>";
      echo "<li>Les associés ayant ≤ 15% des parts seront importés</li>";
      echo "<li>Si aucun bénéficiaire effectif n'est déclaré, le PDG/dirigeant sera importé</li>";
      echo "<li>Les parts sociales seront créées automatiquement</li>";
      echo "</ul>";
      echo "</div>";
      
      echo "</div>";
      
      echo "<div class='modal-footer'>";
      echo "<button type='button' class='btn btn-secondary' onclick='hideRneSyncModal()'>Annuler</button>";
      echo "<button type='submit' name='sync_beneficiaires' class='btn btn-primary'>";
      echo "<i class='fas fa-sync-alt'></i> Synchroniser";
      echo "</button>";
      echo "</div>";
      
      Html::closeForm();
      echo "</form>";
      
      echo "</div>";
      echo "</div>";
      echo "</div>";
      
      // JavaScript pour gérer le modal
      echo "<script type='text/javascript'>";
      echo "function showRneSyncModal(supplierId, siren) {";
      echo "  document.getElementById('modal_siren').value = siren || '';";
      echo "  var modal = document.getElementById('rneModal');";
      echo "  modal.style.display = 'block';";
      echo "  modal.classList.add('show');";
      echo "}";
      echo "function hideRneSyncModal() {";
      echo "  var modal = document.getElementById('rneModal');";
      echo "  modal.style.display = 'none';";
      echo "  modal.classList.remove('show');";
      echo "}";
      echo "</script>";
      
      // JavaScript pour fermer modal au clic sur le fond
      echo "<script>";
      echo "window.onclick = function(event) {";
      echo "  var rneModal = document.getElementById('rneModal');";
      echo "  if (event.target === rneModal) rneModal.style.display = 'none';";
      echo "}";
      echo "</script>";
      
      // Style pour le modal
      echo "<style>";
      echo ".modal { position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }";
      echo ".modal-dialog { position: relative; margin: 1.75rem auto; max-width: 700px; }";
      echo ".modal-content { position: relative; background-color: #fefefe; border: 1px solid #888; border-radius: 0.3rem; padding: 0; }";
      echo ".modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #dee2e6; }";
      echo ".modal-body { position: relative; padding: 1rem; }";
      echo ".modal-footer { display: flex; align-items: center; justify-content: flex-end; padding: 1rem; border-top: 1px solid #dee2e6; }";
      echo ".modal-footer button { margin-left: 0.5rem; }";
      echo ".close { font-size: 1.5rem; font-weight: 700; line-height: 1; color: #000; opacity: .5; background: none; border: 0; cursor: pointer; }";
      echo "</style>";
   }
}
