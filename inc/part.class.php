<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginAssociatesmanagerPart extends CommonDBTM {

   static $rightname = 'plugin_associatesmanager';

   static function getTypeName($nb = 0) {
   return ($nb > 1) ? 'Parts' : 'Part';
   }

   static function getMenuName() {
      return self::getTypeName(Session::getPluralNumber());
   }

   static function getMenuContent() {
      $menu = [];
      $menu['title'] = self::getMenuName();
      $menu['page']  = Plugin::getWebDir('associatesmanager') . '/front/part.php';
      $menu['icon']  = 'fas fa-percentage';

      return $menu;
   }

   function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('Log', $ong, $options);
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
         echo "<td>Associé *</td>";
         echo "<td>";
         $assoc_val = $this->fields['associates_id'] ?? ($_GET['associates_id'] ?? 0);
         PluginAssociatesmanagerAssociate::dropdown(['name' => 'associates_id', 'value' => $assoc_val]);
         echo "</td>";

         echo "<td>Fournisseur *</td>";
         echo "<td>";
         $supp_val = $this->fields['supplier_id'] ?? ($_GET['supplier_id'] ?? 0);
         Supplier::dropdown(['name' => 'supplier_id', 'value' => $supp_val]);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>Nombre de parts *</td>";
         echo "<td>";
         echo Html::input('nbparts', ['value' => $this->fields['nbparts'], 'type' => 'number', 'step' => '0.0001']);
         echo "</td>";

         echo "<td>Date d'attribution</td>";
         echo "<td>";
         echo Html::input('date_attribution', ['value' => $this->fields['date_attribution'], 'type' => 'date']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>Date de fin</td>";
         echo "<td>";
         echo Html::input('date_fin', ['value' => $this->fields['date_fin'], 'type' => 'date']);
         echo "</td>";
         echo "<td colspan='2'></td>";
         echo "</tr>";

         $this->showFormButtons($options);

         return true;
      }

   function prepareInputForAdd($input) {
      if (empty($input['associates_id']) || empty($input['supplier_id'])) {
         Session::addMessageAfterRedirect('L\'associé et le fournisseur sont obligatoires', false, ERROR);
         return false;
      }

      if (!isset($input['nbparts']) || $input['nbparts'] === '') {
         Session::addMessageAfterRedirect('Le nombre de parts est obligatoire', false, ERROR);
         return false;
      }

      // Valider le nombre de parts
      $error = '';
      if (!PluginAssociatesmanagerValidation::validateNbParts($input['nbparts'], $error)) {
         Session::addMessageAfterRedirect($error, false, ERROR);
         return false;
      }

      // Valider les dates
      // Si une date de fin est fournie sans date d'attribution, utilise la date de fin comme date d'attribution par défaut
      if (empty($input['date_attribution']) && !empty($input['date_fin'])) {
         $input['date_attribution'] = $input['date_fin'];
      }

      if (!empty($input['date_attribution'])) {
         if (!PluginAssociatesmanagerValidation::validateDate($input['date_attribution'], $error)) {
            Session::addMessageAfterRedirect($error, false, ERROR);
            return false;
         }
      }

      if (!empty($input['date_fin'])) {
         if (!PluginAssociatesmanagerValidation::validateDate($input['date_fin'], $error)) {
            Session::addMessageAfterRedirect($error, false, ERROR);
            return false;
         }
      }

      // Valider la cohérence des dates
      $date_attr = $input['date_attribution'] ?? '';
      $date_fin = $input['date_fin'] ?? '';
      if (!PluginAssociatesmanagerValidation::validateDateCoherence($date_attr, $date_fin, $error)) {
         Session::addMessageAfterRedirect($error, false, ERROR);
         return false;
      }

      // Vérifier les doublons
      $dup_error = '';
      if (!PluginAssociatesmanagerValidation::checkDuplicate($input['supplier_id'], $input['associates_id'], null, $dup_error)) {
         Session::addMessageAfterRedirect($dup_error, false, WARNING);
         // Ne pas retourner false - permettre la création d'un doublonmais avec avertissement
      }

      // Default attribution date to today if not provided
      if (empty($input['date_attribution'])) {
         $input['date_attribution'] = date('Y-m-d');
      }

      return $input;
   }

   function prepareInputForUpdate($input) {
      // Valider le nombre de parts s'il est fourni
      if (isset($input['nbparts']) && $input['nbparts'] !== '') {
         $error = '';
         if (!PluginAssociatesmanagerValidation::validateNbParts($input['nbparts'], $error)) {
            Session::addMessageAfterRedirect($error, false, ERROR);
            return false;
         }
      }

      // Valider les dates
      if (empty($input['date_attribution']) && !empty($input['date_fin'])) {
         $input['date_attribution'] = $input['date_fin'];
      }

      if (!empty($input['date_attribution'])) {
         $error = '';
         if (!PluginAssociatesmanagerValidation::validateDate($input['date_attribution'], $error)) {
            Session::addMessageAfterRedirect($error, false, ERROR);
            return false;
         }
      }

      if (!empty($input['date_fin'])) {
         $error = '';
         if (!PluginAssociatesmanagerValidation::validateDate($input['date_fin'], $error)) {
            Session::addMessageAfterRedirect($error, false, ERROR);
            return false;
         }
      }

      // Valider la cohérence des dates
      $date_attr = $input['date_attribution'] ?? '';
      $date_fin = $input['date_fin'] ?? '';
      if (!PluginAssociatesmanagerValidation::validateDateCoherence($date_attr, $date_fin, $error)) {
         Session::addMessageAfterRedirect($error, false, ERROR);
         return false;
      }

      return $input;
   }

   function getValueToDisplay($field, $values, $options = []) {
      if ($field == 'nbparts') {
         return (string)$values[$field];
      }
      return parent::getValueToDisplay($field, $values, $options);
   }

   static function dropdown($options = []) {
      global $DB, $CFG_GLPI;

      $p = [
         'name'     => 'parts_id',
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

      // Show parts as "Associate - nbparts (date_attribution)"
      $iterator = $DB->request([
         'SELECT' => ['p.id', 'p.nbparts', 'p.date_attribution', 'a.name'],
         'FROM'   => 'glpi_plugin_associatesmanager_parts AS p',
         'JOIN'   => ['glpi_plugin_associatesmanager_associates AS a' => ['a.id' => 'p.associates_id']],
         'ORDER'  => 'a.name'
      ]);

      $values = [0 => Dropdown::EMPTY_VALUE];
      foreach ($iterator as $data) {
         $label = $data['name'] . ' - ' . $data['nbparts'];
         if (!empty($data['date_attribution'])) {
            $label .= ' (' . $data['date_attribution'] . ')';
         }
         $values[$data['id']] = $label;
      }

      return Dropdown::showFromArray($p['name'], $values, $p);
   }

   function rawSearchOptions() {
      $tab = parent::rawSearchOptions();

      // Show associate name and id (associate stored in pivot)
      $tab[] = [
         'id'                 => '21011',
         'table'              => 'glpi_plugin_associatesmanager_associates',
         'field'              => 'name',
         'name'               => 'Associé',
         'datatype'           => 'itemlink',
         // Explicitly tell GLPI which field on the parts table links to the associates table
         'linkfield'          => 'associates_id',
         'jointype'           => 'LEFT',
         'massiveaction'      => false,
      ];

      $tab[] = [
         'id'                 => '21014',
         'table'              => 'glpi_plugin_associatesmanager_associates',
         'field'              => 'id',
         'name'               => "ID de l'associé",
         'datatype'           => 'number',
         'linkfield'          => 'associates_id',
         'jointype'           => 'LEFT',
      ];

      // Show supplier name and id
      $tab[] = [
         'id'                 => '21012',
         'table'              => 'glpi_suppliers',
         'field'              => 'name',
         'name'               => 'Fournisseur',
         'datatype'           => 'itemlink',
         // parts table uses 'supplier_id' for the supplier foreign key
         'linkfield'          => 'supplier_id',
         'jointype'           => 'LEFT',
      ];

      $tab[] = [
         'id'                 => '21015',
         'table'              => 'glpi_suppliers',
         'field'              => 'id',
         'name'               => "ID du fournisseur",
         'datatype'           => 'number',
         'linkfield'          => 'supplier_id',
         'jointype'           => 'LEFT',
      ];

      $tab[] = [
         'id'                 => '21013',
         'table'              => $this->getTable(),
         'field'              => 'nbparts',
         'name'               => 'Nombre de parts',
         'datatype'           => 'decimal',
      ];

      return $tab;
   }

   /**
    * Add a part assignment. If a current open part exists for the same associate+supplier
    * (date_fin IS NULL), set its date_fin to the new date_attribution.
    */
   public function addPart(array $data, $options = [], $history = true) {
      global $DB;
      if (empty($data['associates_id']) || empty($data['supplier_id']) || !isset($data['nbparts'])) {
         return false;
      }

      if (empty($data['date_attribution'])) {
         $data['date_attribution'] = date('Y-m-d');
      }
      // Begin transaction: we'll move existing active part to history, then insert new part
      $DB->beginTransaction();

   // Debug logging removed

      // Find the current active part for this associate+supplier.
      // Some rows may have date_fin = NULL, empty string or '0000-00-00' depending on imports/SQL mode.
      // Also select nbparts because we'll use it when validating available shares.
      $existing = null;
      $itExisting = $DB->request([
         'SELECT' => ['id','nbparts','date_attribution','date_fin'],
         'FROM'   => 'glpi_plugin_associatesmanager_parts',
         'WHERE'  => [
            'associates_id' => $data['associates_id'],
            'supplier_id'   => $data['supplier_id']
         ],
         'ORDER' => 'date_attribution DESC'
      ]);
      foreach ($itExisting as $er) {
         $df = $er['date_fin'] ?? null;
         if ($df === null || trim((string)$df) === '' || $df === '0000-00-00') {
            $existing = $er;
            break;
         }
      }

   // If an active existing part is found, we'll close it by setting date_fin = new date_attribution
   // but validate availability first (we consider that the existing part will be removed at that date).
   if (!empty($existing)) {
         // Validate dates: new attribution must be >= existing attribution
         if (!empty($existing['date_attribution']) && strtotime($data['date_attribution']) < strtotime($existing['date_attribution'])) {
            Session::addMessageAfterRedirect('La date d\'attribution doit être postérieure ou égale à la date d\'attribution existante', false, ERROR);
            $DB->rollback();
            return false;
         }

         // Update existing row. Use $this->update (CommonDBTM) which will handle history logging; if it fails, rollback.
         if (!$this->update(['id' => $existing['id'], 'date_fin' => $data['date_attribution'], 'date_mod' => date('Y-m-d H:i:s')])) {
            $DB->rollback();
            return false;
         }
      }

      // --- Validate available parts against supplier.nbparttotal (if set) ---
      // Fetch supplier declared total
      $supplierTotal = 0.0;
      // Fetch full supplier row to be robust if nbparttotal doesn't exist
      $itSup = $DB->request([
         'SELECT' => ['*'],
         'FROM'   => 'glpi_suppliers',
         'WHERE'  => ['id' => $data['supplier_id']]
      ]);
      $supRow = $itSup->next();
      if ($supRow && isset($supRow['nbparttotal'])) {
         $supplierTotal = (float)$supRow['nbparttotal'];
      }

      // If supplierTotal > 0, ensure we don't exceed it on the date of attribution
      if ($supplierTotal > 0.0) {
         $date_check = $data['date_attribution'];
         // Sum active parts for that supplier on that date
         $assigned = 0.0;
         $itParts = $DB->request([
            'SELECT' => ['nbparts','date_attribution','date_fin'],
            'FROM'   => 'glpi_plugin_associatesmanager_parts',
            'WHERE'  => ['supplier_id' => $data['supplier_id']]
         ]);
         foreach ($itParts as $pr) {
            $da = $pr['date_attribution'] ?? null;
            $df = $pr['date_fin'] ?? null;
            if (empty($da)) {
               continue;
            }
            // active at date: date_attribution <= date_check and (date_fin is null/empty/'0000-00-00' or date_fin > date_check)
            if (strtotime($da) <= strtotime($date_check) && (empty($df) || $df === '0000-00-00' || strtotime($df) > strtotime($date_check))) {
               $assigned += isset($pr['nbparts']) ? (float)$pr['nbparts'] : 0.0;
            }
         }

         // If there is an existing active part for this associate, it will be closed at the same date; subtract its nbparts from the already assigned total
         if (!empty($existing) && isset($existing['nbparts'])) {
            $assigned -= (float)$existing['nbparts'];
            if ($assigned < 0) { $assigned = 0.0; }
         }

         $available = $supplierTotal - $assigned;
         if ($available < 0) { $available = 0.0; }
         if ((float)$data['nbparts'] > $available) {
            Session::addMessageAfterRedirect("Le nombre de parts demandé ({$data['nbparts']}) dépasse le nombre disponible ({$available}) pour le fournisseur.", false, ERROR);
            $DB->rollback();
            return false;
         }
      }

      // Insert new part row
      // Normalize date_fin: if an empty string is provided from the form, store NULL
      // in the DB to avoid "Incorrect date value: ''" errors on strict SQL modes.
      $date_fin = null;
      if (isset($data['date_fin']) && trim((string)$data['date_fin']) !== '') {
         $date_fin = $data['date_fin'];
      }

      $insert = [
         // For compatibility with existing schema where 'libelle' is NOT NULL,
         // provide an empty libelle when creating pivot rows during migration or new assigns.
         'libelle' => isset($data['libelle']) ? $data['libelle'] : '',
         'nbparts' => $data['nbparts'],
         'associates_id' => $data['associates_id'],
         'supplier_id' => $data['supplier_id'],
         'date_attribution' => $data['date_attribution'],
         'date_fin' => $date_fin,
         'date_creation' => date('Y-m-d H:i:s')
      ];

      try {
         $newId = parent::add($insert, $options, $history);
         if (!$newId) {
            $DB->rollback();
            return false;
         }
         $DB->commit();
      } catch (Exception $e) {
         $DB->rollback();
         Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
         return false;
      }

      return $newId;
   }
   /** Override add to route through addPart when pivot data provided
    * Must match CommonDBTM::add signature
    */
   public function add(array $input, $options = [], $history = true) {
      if (isset($input['associates_id']) && isset($input['supplier_id'])) {
         return $this->addPart($input, $options, $history);
      }
      return parent::add($input, $options, $history);
   }

   /**
    * End a part by setting its date_fin
    */
   public function endPart($id, $date_fin) {
      global $DB;
      if (empty($id)) {
         return false;
      }

      // Load the existing part
      $it = $DB->request([
         'SELECT' => ['*'],
         'FROM'   => 'glpi_plugin_associatesmanager_parts',
         'WHERE'  => ['id' => $id]
      ]);
      $existing = $it->next();
      if (empty($existing)) {
         return false;
      }

      // If date_fin is earlier than date_attribution, error
      if (!empty($existing['date_attribution']) && strtotime($date_fin) < strtotime($existing['date_attribution'])) {
         Session::addMessageAfterRedirect('La date de fin doit être postérieure ou égale à la date d\'attribution', false, ERROR);
         return false;
      }

      // Begin transaction: set date_fin on the existing row and keep it in the
      // parts table as part of the historical records. We no longer copy rows
      // to a separate history table.
      $DB->beginTransaction();

      if (!$this->update(['id' => $existing['id'], 'date_fin' => $date_fin, 'date_mod' => date('Y-m-d H:i:s')])) {
         $DB->rollback();
         return false;
      }

      try {
         $DB->commit();
      } catch (Exception $e) {
         // ignore commit exceptions
      }

      return true;
   }

   /**
    * Get parts for an associate; optional supplier filter and date range
    */
   public function getPartsForAssociate($associate_id, $supplier_id = null, $date = null) {
      global $DB;
      $where = ['associates_id' => $associate_id];
      if (!is_null($supplier_id)) {
         $where['supplier_id'] = $supplier_id;
      }
      $iterator = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => $where,
         'ORDER' => 'date_attribution DESC'
      ]);
      $rows = [];
      foreach ($iterator as $r) {
         $rows[] = $r;
      }
      return $rows;
   }

   /**
    * Compute percent share of an associate for a supplier on a given date.
    * percent = associate.nbparts / total_supplier_nbparts * 100
    */
   public function computeSharePercent($associate_id, $supplier_id, $date = null) {
      global $DB;
      if (empty($date)) {
         $date = date('Y-m-d');
      }

      // Sum parts for the associate on that supplier that are active on $date
      $a = 0.0;
      $it = $DB->request([
         'SELECT' => ['nbparts','date_attribution','date_fin'],
         'FROM'  => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => [
            'associates_id' => $associate_id,
            'supplier_id'   => $supplier_id
         ]
      ]);
      foreach ($it as $r) {
         $da = $r['date_attribution'] ?? null;
         $df = $r['date_fin'] ?? null;
         if (empty($da)) { continue; }
         if (strtotime($da) <= strtotime($date) && (empty($df) || $df === '0000-00-00' || strtotime($df) > strtotime($date))) {
            $a += isset($r['nbparts']) ? (float)$r['nbparts'] : 0.0;
         }
      }

      // Check if supplier has declared a fixed total
      $supplierTotal = 0.0;
      // Fetch full supplier row to be robust if nbparttotal doesn't exist
      $itSup = $DB->request([
         'SELECT' => ['*'],
         'FROM'   => 'glpi_suppliers',
         'WHERE'  => ['id' => $supplier_id]
      ]);
      $supRow = $itSup->next();
      if ($supRow) {
         if (isset($supRow['nbparttotal']) && is_numeric($supRow['nbparttotal'])) {
            $supplierTotal = (float)$supRow['nbparttotal'];
         } else {
            foreach ($supRow as $k => $v) {
               if ((stripos($k, 'nbpart') !== false || stripos($k, 'nb_part') !== false) && is_numeric($v)) {
                  $supplierTotal = (float)$v; break;
               }
            }
         }
      }

      // If supplierTotal > 0, use it as denominator, otherwise sum active parts for the supplier on that date
      $t = 0.0;
      if ($supplierTotal > 0.0) {
         $t = $supplierTotal;
      } else {
         $it2 = $DB->request([
            'SELECT' => ['nbparts','date_attribution','date_fin'],
            'FROM'  => 'glpi_plugin_associatesmanager_parts',
            'WHERE' => ['supplier_id' => $supplier_id]
         ]);
         foreach ($it2 as $r) {
            $da = $r['date_attribution'] ?? null;
            $df = $r['date_fin'] ?? null;
            if (empty($da)) { continue; }
            if (strtotime($da) <= strtotime($date) && (empty($df) || $df === '0000-00-00' || strtotime($df) > strtotime($date))) {
               $t += isset($r['nbparts']) ? (float)$r['nbparts'] : 0.0;
            }
         }
      }

      if ($t == 0.0) {
         return 0.0;
      }
      return ($a / $t) * 100.0;
   }

   // history is kept in the parts table under Option 1; no move helper required
   
}
