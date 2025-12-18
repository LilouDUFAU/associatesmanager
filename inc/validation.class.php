<?php
/**
 * Validation des données pour les associés et parts
 */

class PluginAssociatesmanagerValidation {
   
   /**
    * Valide le nombre de parts
    * @param integer $nbparts Nombre de parts
    * @param string &$error Message d'erreur (en cas d'erreur)
    * @return boolean
    */
   public static function validateNbParts($nbparts, &$error = '') {
      if (!is_numeric($nbparts)) {
         $error = "Le nombre de parts doit être numérique";
         return false;
      }
      
      $nbparts = floatval($nbparts);
      // Le nombre de parts représente une quantité absolue (compte), pas un pourcentage.
      // Il doit être supérieur ou égal à 0. Les pourcentages sont calculés à partir
      // du total déclaré du fournisseur (nbparttotal) et ne doivent pas être saisis ici.
      if ($nbparts < 0) {
         $error = "Le nombre de parts doit être supérieur ou égal à 0";
         return false;
      }
      
      return true;
   }
   
   /**
    * Valide une date
    * @param string $date Date au format Y-m-d
    * @param string &$error Message d'erreur
    * @return boolean
    */
   public static function validateDate($date, &$error = '') {
      if (empty($date)) {
         return true; // Date optionnelle
      }
      
      $d = DateTime::createFromFormat('Y-m-d', $date);
      if (!$d || $d->format('Y-m-d') !== $date) {
         $error = "Format de date invalide (Y-m-d attendu)";
         return false;
      }
      
      return true;
   }
   
   /**
    * Valide la cohérence des dates d'attribution et fin
    * @param string $date_attribution Date d'attribution
    * @param string $date_fin Date de fin
    * @param string &$error Message d'erreur
    * @return boolean
    */
   public static function validateDateCoherence($date_attribution, $date_fin, &$error = '') {
      // La date de fin est optionnelle
      if (empty($date_fin)) {
         return true;
      }
      
      if (empty($date_attribution)) {
         $error = "La date d'attribution est requise si une date de fin est définie";
         return false;
      }
      
      $d_debut = DateTime::createFromFormat('Y-m-d', $date_attribution);
      $d_fin = DateTime::createFromFormat('Y-m-d', $date_fin);
      
      if (!$d_debut || !$d_fin) {
         $error = "Formats de date invalides";
         return false;
      }
      
      if ($d_fin < $d_debut) {
         $error = "La date de fin ne peut pas être antérieure à la date d'attribution";
         return false;
      }
      
      return true;
   }
   
   /**
    * Vérifie les doublons (même supplier, associate, avec date_fin NULL)
    * @param integer $suppliers_id ID du fournisseur
    * @param integer $associates_id ID de l'associé
    * @param integer $parts_id ID de la part (NULL pour création)
    * @param string &$error Message d'erreur
    * @return boolean
    */
   public static function checkDuplicate($suppliers_id, $associates_id, $parts_id = null, &$error = '') {
      global $DB;
      
      $result = $DB->request([
         'FROM' => 'glpi_plugin_associatesmanager_parts',
         'WHERE' => [
            'supplier_id' => $suppliers_id,
            'associates_id' => $associates_id,
            'date_fin' => null,
            'id' => ($parts_id ? ['!=', $parts_id] : ['!=', 0])
         ]
      ]);
      
      if ($result->count() > 0) {
         $error = "Cette association existe déjà pour ce fournisseur";
         return false;
      }
      
      return true;
   }
   
   /**
    * Valide un email
    * @param string $email Email à valider
    * @param string &$error Message d'erreur
    * @return boolean
    */
   public static function validateEmail($email, &$error = '') {
      if (empty($email)) {
         return true; // Email optionnel
      }
      
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error = "Adresse email invalide";
         return false;
      }
      
      return true;
   }
   
   /**
    * Valide un numéro de téléphone
    * @param string $phone Numéro de téléphone
    * @param string &$error Message d'erreur
    * @return boolean
    */
   public static function validatePhone($phone, &$error = '') {
      if (empty($phone)) {
         return true; // Téléphone optionnel
      }
      
      // Format très permissif: au moins 8 chiffres
      if (!preg_match('/[0-9]{8,}/', str_replace([' ', '.', '-', '(', ')', '+'], '', $phone))) {
         $error = "Numéro de téléphone invalide (au moins 8 chiffres)";
         return false;
      }
      
      return true;
   }
   
   /**
    * Valide un ensemble de données d'associé
    * @param array $data Données de l'associé
    * @return array Tableau des erreurs [champ => message]
    */
   public static function validateAssociate($data) {
      $errors = [];
      
      // Nom requis
      if (empty($data['name'])) {
         $errors['name'] = "Le nom est requis";
      }
      
      // Email optionnel mais valide
      if (!empty($data['email'])) {
         if (!self::validateEmail($data['email'], $error)) {
            $errors['email'] = $error;
         }
      }
      
      // Téléphone optionnel mais valide
      if (!empty($data['phonenumber'])) {
         if (!self::validatePhone($data['phonenumber'], $error)) {
            $errors['phonenumber'] = $error;
         }
      }
      
      // Date de naissance optionnelle
      if (!empty($data['date_naissance'])) {
         if (!self::validateDate($data['date_naissance'], $error)) {
            $errors['date_naissance'] = $error;
         }
      }
      
      return $errors;
   }
   
   /**
    * Valide un ensemble de données de part
    * @param array $data Données de la part
    * @return array Tableau des erreurs [champ => message]
    */
   public static function validatePart($data) {
      $errors = [];
      
      // Nombre de parts
      if (!self::validateNbParts($data['nbparts'], $error)) {
         $errors['nbparts'] = $error;
      }
      
      // Cohérence des dates
      if (!self::validateDateCoherence($data['date_attribution'], $data['date_fin'], $error)) {
         $errors['dates'] = $error;
      }
      
      // Dates individuelles
      if (!empty($data['date_attribution'])) {
         if (!self::validateDate($data['date_attribution'], $error)) {
            $errors['date_attribution'] = $error;
         }
      }
      
      if (!empty($data['date_fin'])) {
         if (!self::validateDate($data['date_fin'], $error)) {
            $errors['date_fin'] = $error;
         }
      }
      
      return $errors;
   }
}
