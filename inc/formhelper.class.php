<?php
/**
 * Amélioration des formulaires et ergonomie de saisie
 */

class PluginAssociatesmanagerFormHelper {
   
   /**
    * Affiche un champ de saisie avec autocomplétion pour les noms d'associés
    */
   public static function showAssociateAutocomplete($value = '') {
      global $DB;
      
      // Récupérer les noms existants pour l'autocomplétion
      $associates = [];
      $result = $DB->request([
         'SELECT' => ['DISTINCT' => 'name'],
         'FROM' => 'glpi_plugin_associatesmanager_associates',
         'ORDER' => ['name' => 'ASC']
      ]);
      foreach ($result as $row) {
         $associates[] = $row['name'];
      }
      
      $json_associates = json_encode($associates);
      
      echo "<input type='text' name='associate_name' class='form-control' 
             value='" . htmlspecialchars($value) . "'
             list='associate_list' autocomplete='off'>";
      echo "<datalist id='associate_list'>";
      foreach ($associates as $name) {
         echo "<option value='" . htmlspecialchars($name) . "'>";
      }
      echo "</datalist>";
   }
   
   /**
    * Étiquettes prédéfinies pour les types de parts
    */
   public static $predefinedLabels = [
      'Fondateur' => 'Fondateur',
      'Associé' => 'Associé',
      'Actionnaire' => 'Actionnaire',
      'Commanditaire' => 'Commanditaire',
      'Commandité' => 'Commandité',
      'Associé commanditaire' => 'Associé commanditaire',
      'Gérant' => 'Gérant',
      'Administrateur' => 'Administrateur',
      'Président' => 'Président',
      'Directeur' => 'Directeur',
      'Associé passif' => 'Associé passif',
   ];
   
   /**
    * Affiche un dropdown avec les labels prédéfinis
    */
   public static function showLabelDropdown($value = '', $allow_custom = true) {
      echo "<select name='libelle' class='form-control' id='libelle_select'>";
      echo "<option value=''>" . __('Select...') . "</option>";
      
      foreach (self::$predefinedLabels as $key => $label) {
         $selected = ($value === $key) ? 'selected' : '';
         echo "<option value='" . htmlspecialchars($key) . "' $selected>" . htmlspecialchars($label) . "</option>";
      }
      
      echo "<option value='_custom'>--- Personnalisé ---</option>";
      echo "</select>";
      
      if ($allow_custom) {
         echo "<input type='text' name='libelle_custom' id='libelle_custom' class='form-control' 
                style='display: none; margin-top: 5px;' placeholder='Entrer un label personnalisé'
                value='" . htmlspecialchars($value) . "'>";
         
         echo "<script>
            document.getElementById('libelle_select').addEventListener('change', function() {
               if (this.value === '_custom') {
                  document.getElementById('libelle_custom').style.display = 'block';
               } else {
                  document.getElementById('libelle_custom').style.display = 'none';
               }
            });
         </script>";
      }
   }
   
   /**
    * Affiche un champ de pourcentage avec contrôle de validité
    */
   public static function showPercentageField($value = '', $name = 'nbparts') {
      echo "<div class='input-group'>";
      echo "<input type='number' name='" . htmlspecialchars($name) . "' class='form-control' 
             value='" . htmlspecialchars($value) . "'
             min='0' max='100' step='0.01' 
             placeholder='0.00'
             title='Entrez une valeur entre 0 et 100'>";
      echo "<div class='input-group-append'>";
      echo "<span class='input-group-text'>%</span>";
      echo "</div>";
      echo "</div>";
   }
   
   /**
    * Affiche un champ de date avec helper
    */
   public static function showDateField($value = '', $name = 'date_attribution', $required = false) {
      $required_attr = $required ? 'required' : '';
      
      echo "<div class='input-group'>";
      echo "<input type='date' name='" . htmlspecialchars($name) . "' class='form-control' 
             value='" . htmlspecialchars($value) . "' $required_attr>";
      echo "<div class='input-group-append'>";
      echo "<button class='btn btn-outline-secondary' type='button' onclick='setToday(this)' title='Utiliser aujourd\'hui'>";
      echo "<i class='fas fa-calendar-today'></i>";
      echo "</button>";
      echo "</div>";
      echo "</div>";
   }
   
   /**
    * Affiche un formulaire de saisie rapide avec champs prédéfinis
    */
   public static function showQuickEntryForm() {
      echo "<div class='card'>";
      echo "<div class='card-header'>";
      echo "<h5>Saisie rapide d'une part</h5>";
      echo "</div>";
      echo "<div class='card-body'>";
      echo "<form method='POST' action='part.form.php'>";
      
      echo "<div class='form-group'>";
      echo "<label>Fournisseur *</label>";
      Supplier::dropdown(['name' => 'supplier_id', 'required' => true]);
      echo "</div>";
      
      echo "<div class='form-group'>";
      echo "<label>Associé *</label>";
      PluginAssociatesmanagerAssociate::dropdown(['name' => 'associates_id', 'required' => true]);
      echo "</div>";
      
      echo "<div class='form-group'>";
      echo "<label>Type de part</label>";
      self::showLabelDropdown();
      echo "</div>";
      
      echo "<div class='form-group'>";
      echo "<label>Nombre de parts (%)*</label>";
      self::showPercentageField();
      echo "</div>";
      
      echo "<div class='form-group'>";
      echo "<label>Date d'attribution</label>";
      self::showDateField();
      echo "</div>";
      
      echo "<div class='form-group'>";
      echo "<label>Date de fin</label>";
      self::showDateField('', 'date_fin');
      echo "</div>";
      
      echo "<button type='submit' class='btn btn-primary'>Ajouter</button>";
      echo "</form>";
      echo "</div>";
      echo "</div>";
   }
}

/**
 * Fonction utilitaire JavaScript pour définir la date d'aujourd'hui
 */
function plugin_associatesmanager_addDateHelperScript() {
   echo "<script>
   function setToday(btn) {
      var dateInput = btn.closest('.input-group').querySelector('input[type=\"date\"]');
      if (dateInput) {
         var today = new Date().toISOString().split('T')[0];
         dateInput.value = today;
      }
   }
   </script>";
}
