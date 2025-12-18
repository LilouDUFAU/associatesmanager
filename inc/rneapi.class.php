<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Classe de gestion de l'API RNE INPI pour récupérer les bénéficiaires effectifs
 */
class PluginAssociatesmanagerRneapi extends CommonDBTM {

   static $rightname = 'plugin_associatesmanager';

   // Base URL de l'API RNE INPI
   const API_BASE_URL = 'https://registre-national-entreprises.inpi.fr/api/';
   const API_COMPANIES_URL = 'https://registre-national-entreprises.inpi.fr/api/companies/';

   /**
    * Normalise un nom pour comparaison tolérante
    */
   private static function normalizeName($name) {
      $n = trim(mb_strtolower($name ?? '', 'UTF-8'));
      // Remplacer les multiples espaces par un seul
      $n = preg_replace('/\s+/u', ' ', $n);
      // Supprimer accents si possible
      if (function_exists('iconv')) {
         $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n);
         if ($trans !== false) {
            $n = $trans;
         }
      }
      // Supprimer tout caractère non alphanumérique ou espace
      $n = preg_replace('/[^a-z0-9 ]/u', '', $n);
      // Trim final
      $n = trim(preg_replace('/\s{2,}/', ' ', $n));
      return $n;
   }

   /**
    * Retrouve un associé existant par nom normalisé
    */
   private static function findAssociateIdByNormalizedName($normalized_name) {
      global $DB;
      if ($normalized_name === '') {
         return null;
      }
      // Chercher des candidats par préfixe pour limiter
      $prefix = substr($normalized_name, 0, 8);
      $iterator = $DB->request([
         'SELECT' => ['id', 'name'],
         'FROM'   => 'glpi_plugin_associatesmanager_associates',
         'WHERE'  => [
            // comparaison large (LIKE) pour réduire le volume, vérif stricte ensuite
            'name' => ['LIKE', $prefix . '%']
         ]
      ]);
      foreach ($iterator as $row) {
         if (self::normalizeName($row['name']) === $normalized_name) {
            return (int)$row['id'];
         }
      }
      // Si rien, élargir avec une seconde passe sur quelques autres candidats par mots clés
      $words = explode(' ', $normalized_name);
      if (count($words) > 1) {
         $like = $words[0] . '% ' . $words[1] . '%';
         $iterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_plugin_associatesmanager_associates',
            'WHERE'  => [
               'name' => ['LIKE', $like]
            ],
            'LIMIT'  => 50
         ]);
         foreach ($iterator as $row) {
            if (self::normalizeName($row['name']) === $normalized_name) {
               return (int)$row['id'];
            }
         }
      }
      return null;
   }
   
   static function getTypeName($nb = 0) {
      return ($nb > 1) ? 'API RNE' : 'API RNE';
   }

   /**
    * Récupère les identifiants API depuis la configuration
    */
   public static function getCredentials() {
      $config = Config::getConfigurationValues('plugin:associatesmanager');
      return [
         'email' => $config['rne_api_email'] ?? '',
         'password' => $config['rne_api_password'] ?? ''
      ];
   }

   /**
    * Authentification à l'API RNE INPI
    * @return string|null Token d'authentification ou null en cas d'erreur
    */
   public static function authenticate() {
      $credentials = self::getCredentials();
      
      if (empty($credentials['email']) || empty($credentials['password'])) {
         Toolbox::logInFile('php-errors', "RNE API: Identifiants manquants dans la configuration\n");
         return null;
      }

      $auth_url = self::API_BASE_URL . 'sso/login';
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $auth_url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
         'username' => $credentials['email'],
         'password' => $credentials['password']
      ]));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
         'Content-Type: application/json',
         'Accept: application/json'
      ]);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);

      $response = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curl_error = curl_error($ch);
      curl_close($ch);

      if ($curl_error) {
         Toolbox::logInFile('php-errors', "RNE API: Erreur cURL - " . $curl_error . "\n");
         return null;
      }

      if ($http_code !== 200) {
         Toolbox::logInFile('php-errors', "RNE API: Erreur d'authentification (HTTP $http_code) - Response: " . substr($response, 0, 500) . "\n");
         return null;
      }

      $data = json_decode($response, true);
      if (isset($data['token'])) {
         return $data['token'];
      }

      Toolbox::logInFile('php-errors', "RNE API: Token non trouvé dans la réponse\n");
      return null;
   }

   /**
    * Recherche une entreprise par SIREN
    * @param string $siren Numéro SIREN (9 chiffres)
    * @param string $token Token d'authentification
    * @return array|null Données de l'entreprise ou null
    */
   public static function getCompanyBySiren($siren, $token = null) {
      if (empty($token)) {
         $token = self::authenticate();
         if (!$token) {
            return null;
         }
      }

      // Nettoyage du SIREN
      $siren = preg_replace('/[^0-9]/', '', $siren);
      if (strlen($siren) !== 9) {
         Toolbox::logInFile('php-errors', "RNE API: SIREN invalide - $siren\n");
         return null;
      }

      $url = self::API_COMPANIES_URL . $siren;

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
         'Authorization: Bearer ' . $token,
         'Accept: application/json'
      ]);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);

      $response = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curl_error = curl_error($ch);
      curl_close($ch);

      if ($curl_error) {
         Toolbox::logInFile('php-errors', "RNE API: Erreur cURL lors de la récupération du SIREN $siren - " . $curl_error . "\n");
         return null;
      }

      if ($http_code !== 200) {
         $error_msg = "RNE API: Erreur HTTP $http_code pour SIREN $siren";
         if ($http_code == 404) {
            $error_msg .= " - Entreprise non trouvée dans le registre RNE (SIREN inexistant, radié ou non immatriculé)";
            Session::addMessageAfterRedirect("SIREN $siren introuvable dans le Registre National des Entreprises. Vérifiez le numéro ou consultez infogreffe.fr", false, ERROR);
         } elseif ($http_code == 401 || $http_code == 403) {
            $error_msg .= " - Problème d'authentification ou d'autorisation";
            Session::addMessageAfterRedirect("Erreur d'accès à l'API RNE (HTTP $http_code). Vérifiez les identifiants dans la configuration.", false, ERROR);
         } else {
            Session::addMessageAfterRedirect("Impossible de récupérer les données pour le SIREN $siren (HTTP $http_code)", false, ERROR);
         }
         Toolbox::logInFile('php-errors', $error_msg . "\n");
         return null;
      }

      return json_decode($response, true);
   }

   /**
    * Récupère les bénéficiaires effectifs d'une entreprise
    * @param string $siren Numéro SIREN (9 chiffres)
    * @param string $token Token d'authentification
    * @return array Liste des bénéficiaires effectifs
    */
   public static function getBeneficiaires($siren, $token = null) {
      if (empty($token)) {
         $token = self::authenticate();
         if (!$token) {
            return [];
         }
      }

      $company_data = self::getCompanyBySiren($siren, $token);
      if (!$company_data) {
         return [];
      }

      // Vérifier si l'entreprise est cessée
      $nb_beneficiaires_actifs = $company_data['nombreBeneficiairesEffectifsActifs'] ?? null;
      $nature_cessation = $company_data['formality']['content']['natureCessation'] ?? null;
      
      // Uniquement bloquer si l'entreprise est cessée (natureCessation présent)
      // Ne pas bloquer si simplement nombreBeneficiairesEffectifsActifs = 0 (peut être incomplet)
      if ($nature_cessation) {
         Session::addMessageAfterRedirect("L'entreprise SIREN $siren est cessée et n'a plus de bénéficiaires effectifs actifs.", false, WARNING);
         error_log("[RNE] Entreprise cessée: SIREN $siren (natureCessation: $nature_cessation)");
         return [];
      }
      
      if ($nb_beneficiaires_actifs === 0) {
         error_log("[RNE] Attention: nombreBeneficiairesEffectifsActifs = 0 pour SIREN $siren, mais on continue la recherche");
      }

      // Debug: afficher la structure complète retournée par l'API
      error_log("[RNE] === Structure API RNE pour SIREN $siren ===");
      error_log("[RNE] Clés racine: " . implode(', ', array_keys($company_data)));
      error_log("[RNE] Nombre de bénéficiaires actifs: " . ($nb_beneficiaires_actifs ?? 'N/A'));
      
      if (isset($company_data['formality'])) {
         error_log("[RNE] Clés formality: " . implode(', ', array_keys($company_data['formality'])));
         if (isset($company_data['formality']['content'])) {
            error_log("[RNE] Clés content: " . implode(', ', array_keys($company_data['formality']['content'])));
         }
      }
      
      // Log complet dans un fichier séparé
      file_put_contents('/tmp/rne_response_' . $siren . '.json', json_encode($company_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      error_log("[RNE] Données complètes sauvegardées dans /tmp/rne_response_$siren.json");

      $beneficiaires = [];

      // Essayer plusieurs chemins possibles pour les bénéficiaires effectifs
      $be_list = null;
      
      // Chemin 1: formality.content.beneficiairesEffectifs (standard)
      if (isset($company_data['formality']['content']['beneficiairesEffectifs'])) {
         $be_list = $company_data['formality']['content']['beneficiairesEffectifs'];
         error_log("[RNE] Trouvé via formality.content.beneficiairesEffectifs: " . count($be_list) . " bénéficiaire(s)");
      }
      // Chemin 2: formality.beneficiairesEffectifs
      elseif (isset($company_data['formality']['beneficiairesEffectifs'])) {
         $be_list = $company_data['formality']['beneficiairesEffectifs'];
         error_log("[RNE] Trouvé via formality.beneficiairesEffectifs: " . count($be_list) . " bénéficiaire(s)");
      }
      // Chemin 3: beneficiairesEffectifs direct
      elseif (isset($company_data['beneficiairesEffectifs'])) {
         $be_list = $company_data['beneficiairesEffectifs'];
         error_log("[RNE] Trouvé via beneficiairesEffectifs direct: " . count($be_list) . " bénéficiaire(s)");
      }
      // Chemin 4: formality.content.personnePhysique.beneficiairesEffectifs
      elseif (isset($company_data['formality']['content']['personnePhysique']['beneficiairesEffectifs'])) {
         $be_list = $company_data['formality']['content']['personnePhysique']['beneficiairesEffectifs'];
         error_log("[RNE] Trouvé via personnePhysique.beneficiairesEffectifs: " . count($be_list) . " bénéficiaire(s)");
      }
      // Chemin 5: formality.content.personneMorale.beneficiairesEffectifs
      elseif (isset($company_data['formality']['content']['personneMorale']['beneficiairesEffectifs'])) {
         $be_list = $company_data['formality']['content']['personneMorale']['beneficiairesEffectifs'];
         error_log("[RNE] Trouvé via personneMorale.beneficiairesEffectifs: " . count($be_list) . " bénéficiaire(s)");
      }
      
      if ($be_list !== null && is_array($be_list)) {
         
         foreach ($be_list as $be) {
            // L'API peut retourner les données dans deux formats différents:
            // Format 1: structure directe (ancien format ou certains types d'entreprises)
            // Format 2: structure imbriquée avec beneficiaire.descriptionPersonne (personneMorale)
            
            $personne = $be;
            $modalites_data = $be;
            
            // Si la structure imbriquée existe, l'utiliser
            if (isset($be['beneficiaire']['descriptionPersonne'])) {
               $personne = $be['beneficiaire']['descriptionPersonne'];
               $modalites_data = $be['modalite'] ?? $be;
            }
            
            // Extraire nom et prénom
            $nom = $personne['nom'] ?? '';
            $prenoms = $personne['prenoms'] ?? ($personne['prenom'] ?? '');
            
            // Si prenoms est un tableau, le joindre
            if (is_array($prenoms)) {
               $prenoms = implode(' ', $prenoms);
            }
            
            $nom_complet = trim($prenoms . ' ' . $nom);
            
            // Log pour debug
            error_log("[RNE] Bénéficiaire trouvé: nom='$nom', prenoms='$prenoms', nom_complet='$nom_complet'");
            
            if (empty($nom_complet)) {
               error_log("[RNE] ERREUR: nom_complet vide pour ce bénéficiaire, skip");
               continue;
            }
            
            // Extraire l'adresse (peut être dans adresseDomicile)
            $adresse = '';
            if (isset($be['beneficiaire']['adresseDomicile'])) {
               $addr = $be['beneficiaire']['adresseDomicile'];
               $parts = [];
               if (!empty($addr['numVoie'])) $parts[] = $addr['numVoie'];
               if (!empty($addr['typeVoie'])) $parts[] = $addr['typeVoie'];
               if (!empty($addr['voie'])) $parts[] = $addr['voie'];
               if (!empty($addr['codePostal'])) $parts[] = $addr['codePostal'];
               if (!empty($addr['commune'])) $parts[] = $addr['commune'];
               $adresse = implode(' ', $parts);
            } elseif (isset($personne['adresse'])) {
               $adresse = $personne['adresse'];
            }
            
            $beneficiaire = [
               'type' => 'beneficiaire_effectif',
               'nom' => $nom,
               'prenom' => $prenoms,
               'nom_complet' => $nom_complet,
               'date_naissance' => $personne['dateDeNaissance'] ?? ($personne['dateNaissance'] ?? null),
               'nationalite' => $personne['codeNationalite'] ?? ($personne['nationalite'] ?? ''),
               'adresse' => $adresse,
               'pourcentage_parts' => 0,
               'pourcentage_droits_vote' => 0,
               'modalites_controle' => '',
               'parts_directes' => 0,
               'parts_indirectes' => 0,
               'nbparts_absolu' => 0
            ];

            // Récupérer le nombre de parts détenues (pour calcul du total)
            $parts_total = 0;
            $keys_candidates = [
               'partsDirectesPleinePropriete',
               'partsDirectesNuePropriete',
               'partsDirectesUsufruit',
               'partsDirectes',
               'partsIndirectesPersonnesMorales',
               'partsIndirectesPersonnesPhysiques',
               'partsIndirectes',
               'nombreParts',
               'nbParts',
            ];
            foreach ($keys_candidates as $k) {
               if (isset($modalites_data[$k]) && is_numeric($modalites_data[$k])) {
                  $parts_total += (float)$modalites_data[$k];
               }
            }
            if ($parts_total <= 0) {
               foreach ($modalites_data as $k => $v) {
                  if (!is_array($v) && is_numeric($v)) {
                     $lk = strtolower($k);
                     if (strpos($lk, 'part') !== false && strpos($lk, 'pour') === false && strpos($lk, 'percent') === false) {
                        $parts_total += (float)$v;
                     }
                  }
               }
            }
            if ($parts_total > 0) {
               $beneficiaire['nbparts_absolu'] = $parts_total;
               $beneficiaire['parts_directes'] = $parts_total;
               $beneficiaire['parts_indirectes'] = 0;
            }

            // Calculer le pourcentage de parts depuis modalite ou direct
            if (isset($modalites_data['detentionPartTotale'])) {
               $beneficiaire['pourcentage_parts'] = (float)$modalites_data['detentionPartTotale'];
            } elseif (isset($modalites_data['pourcentageCapital'])) {
               $beneficiaire['pourcentage_parts'] = (float)$modalites_data['pourcentageCapital'];
            } elseif (isset($modalites_data['partCapital'])) {
               $beneficiaire['pourcentage_parts'] = (float)$modalites_data['partCapital'];
            }

            // Calculer le pourcentage de droits de vote
            if (isset($modalites_data['detentionVoteTotal'])) {
               $beneficiaire['pourcentage_droits_vote'] = (float)$modalites_data['detentionVoteTotal'];
            } elseif (isset($modalites_data['pourcentageDroitsVote'])) {
               $beneficiaire['pourcentage_droits_vote'] = (float)$modalites_data['pourcentageDroitsVote'];
            } elseif (isset($modalites_data['droitsVote'])) {
               $beneficiaire['pourcentage_droits_vote'] = (float)$modalites_data['droitsVote'];
            }
            
            // Modalités de contrôle
            if (isset($modalites_data['modalitesDeControle']) && is_array($modalites_data['modalitesDeControle'])) {
               $beneficiaire['modalites_controle'] = implode(', ', $modalites_data['modalitesDeControle']);
            } elseif (isset($modalites_data['modalitesControle'])) {
               $beneficiaire['modalites_controle'] = $modalites_data['modalitesControle'];
            }

            error_log("[RNE] Bénéficiaire ajouté: " . $nom_complet . " (" . $beneficiaire['pourcentage_parts'] . "%)");
            
            // Ajouter le bénéficiaire
            $beneficiaires[] = $beneficiaire;
         }
      } else {
         error_log("[RNE] Aucune liste de bénéficiaires trouvée dans aucun chemin connu");
      }

      // Si aucun bénéficiaire trouvé, récupérer le PDG/Dirigeant
      if (empty($beneficiaires)) {
         error_log("[RNE] Aucun bénéficiaire effectif, tentative de récupération des dirigeants");
         $dirigeants = self::getDirigeants($company_data);
         error_log("[RNE] Dirigeants trouvés: " . count($dirigeants));
         if (!empty($dirigeants)) {
            foreach ($dirigeants as $dirigeant) {
               $beneficiaires[] = [
                  'type' => 'dirigeant',
                  'nom' => $dirigeant['nom'] ?? '',
                  'prenom' => $dirigeant['prenom'] ?? '',
                  'nom_complet' => $dirigeant['nom_complet'] ?? '',
                  'date_naissance' => $dirigeant['date_naissance'] ?? null,
                  'nationalite' => $dirigeant['nationalite'] ?? '',
                  'adresse' => $dirigeant['adresse'] ?? '',
                  'fonction' => $dirigeant['fonction'] ?? 'Dirigeant',
                  'pourcentage_parts' => 0,
                  'pourcentage_droits_vote' => 0,
                  'modalites_controle' => 'Dirigeant principal (aucun bénéficiaire effectif déclaré)'
               ];
            }
         }
      }

      return $beneficiaires;
   }

   /**
    * Extrait les dirigeants d'une entreprise
    * @param array $company_data Données complètes de l'entreprise
    * @return array Liste des dirigeants
    */
   private static function getDirigeants($company_data) {
      $dirigeants = [];

      // Cas 1: Représentants légaux pour les personnes morales
      if (isset($company_data['formality']['content']['personneMorale']['representants'])) {
         foreach ($company_data['formality']['content']['personneMorale']['representants'] as $rep) {
            if (isset($rep['descriptionPersonne'])) {
               $personne = $rep['descriptionPersonne'];
               $dirigeants[] = [
                  'nom' => $personne['nom'] ?? '',
                  'prenom' => $personne['prenom'] ?? '',
                  'nom_complet' => trim(($personne['prenom'] ?? '') . ' ' . ($personne['nom'] ?? '')),
                  'date_naissance' => $personne['dateNaissance'] ?? null,
                  'nationalite' => $personne['nationalite'] ?? '',
                  'adresse' => isset($personne['adresse']) ? self::formatAdresse($personne['adresse']) : '',
                  'fonction' => $rep['qualite'] ?? 'Représentant légal'
               ];
            }
         }
      }

      // Cas 2: Établissements (gérant, président, etc.)
      if (isset($company_data['formality']['content']['etablissement'])) {
         foreach ($company_data['formality']['content']['etablissement'] as $etab) {
            if (isset($etab['representant'])) {
               $rep = $etab['representant'];
               $dirigeants[] = [
                  'nom' => $rep['nom'] ?? '',
                  'prenom' => $rep['prenom'] ?? '',
                  'nom_complet' => trim(($rep['prenom'] ?? '') . ' ' . ($rep['nom'] ?? '')),
                  'date_naissance' => $rep['dateNaissance'] ?? null,
                  'nationalite' => $rep['nationalite'] ?? '',
                  'adresse' => isset($rep['adresse']) ? self::formatAdresse($rep['adresse']) : '',
                  'fonction' => $rep['qualite'] ?? 'Gérant'
               ];
            }
         }
      }

      // Cas 3: Entrepreneur pour les personnes physiques (EIRL, auto-entrepreneur, etc.)
      if (isset($company_data['formality']['content']['personnePhysique']['identite']['entrepreneur'])) {
         $entrepreneur = $company_data['formality']['content']['personnePhysique']['identite']['entrepreneur'];
         if (isset($entrepreneur['descriptionPersonne'])) {
            $personne = $entrepreneur['descriptionPersonne'];
            $prenom_list = $personne['prenoms'] ?? [];
            $prenom_str = is_array($prenom_list) ? implode(' ', $prenom_list) : $prenom_list;
            
            $dirigeants[] = [
               'nom' => $personne['nom'] ?? '',
               'prenom' => $prenom_str,
               'nom_complet' => trim($prenom_str . ' ' . ($personne['nom'] ?? '')),
               'date_naissance' => $personne['dateNaissance'] ?? null,
               'nationalite' => $personne['nationalite'] ?? '',
               'adresse' => isset($entrepreneur['adresseEntreprise']) ? self::formatAdresse($entrepreneur['adresseEntreprise']) : '',
               'fonction' => 'Exploitant'
            ];
         }
      }

      return $dirigeants;
   }

   /**
    * Formate une adresse depuis les données API
    * @param array $adresse Données d'adresse
    * @return string Adresse formatée
    */
   private static function formatAdresse($adresse) {
      if (is_string($adresse)) {
         return $adresse;
      }

      if (!is_array($adresse)) {
         return '';
      }

      // Cas 1: Structure plate
      $parts = [];
      if (isset($adresse['numeroVoie'])) $parts[] = $adresse['numeroVoie'];
      if (isset($adresse['typeVoie'])) $parts[] = $adresse['typeVoie'];
      if (isset($adresse['nomVoie'])) $parts[] = $adresse['nomVoie'];
      if (isset($adresse['codePostal'])) $parts[] = $adresse['codePostal'];
      if (isset($adresse['ville'])) $parts[] = $adresse['ville'];
      if (isset($adresse['pays'])) $parts[] = $adresse['pays'];

      // Cas 2: Structure imbriquée avec clé 'adresse'
      if (empty($parts) && isset($adresse['adresse']) && is_array($adresse['adresse'])) {
         $sub_adresse = $adresse['adresse'];
         if (isset($sub_adresse['voie'])) $parts[] = $sub_adresse['voie'];
         if (isset($sub_adresse['codePostal'])) $parts[] = $sub_adresse['codePostal'];
         if (isset($sub_adresse['commune'])) $parts[] = $sub_adresse['commune'];
         if (isset($sub_adresse['pays'])) $parts[] = $sub_adresse['pays'];
      }

      return implode(' ', array_filter($parts));
   }

   /**
    * Synchronise les bénéficiaires effectifs d'un fournisseur
    * @param int $supplier_id ID du fournisseur GLPI
    * @param string $siren Numéro SIREN
    * @return array Résultat de la synchronisation
    */
   public static function syncBeneficiairesForSupplier($supplier_id, $siren) {
      global $DB;

      $result = [
         'success' => false,
         'message' => '',
         'added' => 0,
         'updated' => 0,
         'errors' => []
      ];

      // Authentification
      $token = self::authenticate();
      if (!$token) {
         $result['message'] = "Impossible de s'authentifier à l'API RNE";
         return $result;
      }

      // Récupération des données brutes pour vérifier si c'est une personne physique
      $company_data = self::getCompanyBySiren($siren, $token);
      if (!$company_data) {
         $result['message'] = "Impossible de récupérer les données de l'entreprise";
         return $result;
      }

      // Vérifier si c'est une personne physique avec un exploitant
      $isPersonnePhysique = isset($company_data['formality']['content']['personnePhysique']);
      $exploitant = null;
      
      if ($isPersonnePhysique && isset($company_data['formality']['content']['personnePhysique']['identite']['entrepreneur'])) {
         $entrepreneur = $company_data['formality']['content']['personnePhysique']['identite']['entrepreneur'];
         if (isset($entrepreneur['descriptionPersonne'])) {
            $personne = $entrepreneur['descriptionPersonne'];
            $prenom_list = $personne['prenoms'] ?? [];
            $prenom_str = is_array($prenom_list) ? implode(' ', $prenom_list) : $prenom_list;
            
            $exploitant = [
               'nom' => $personne['nom'] ?? '',
               'prenom' => $prenom_str,
               'nom_complet' => trim($prenom_str . ' ' . ($personne['nom'] ?? '')),
               'email' => $personne['email'] ?? '',
               'adresse' => isset($entrepreneur['adresseEntreprise']) ? self::formatAdresse($entrepreneur['adresseEntreprise']) : '',
               'telephone' => $personne['telephone'] ?? ''
            ];
         }
      }

      // Si c'est une personne physique avec exploitant seul, mettre à jour le fournisseur directement
      if ($isPersonnePhysique && !empty($exploitant)) {
         $supplier = new Supplier();
         if ($supplier->getFromDB($supplier_id)) {
            $update_data = [
               'id' => $supplier_id,
               'address' => $exploitant['adresse'],
               'phonenumber' => $exploitant['telephone'],
               'email' => $exploitant['email']
            ];
            if ($supplier->update($update_data)) {
               // Mettre à jour le fournisseur
               $result['message'] = "Fournisseur mis à jour avec les informations de l'exploitant: " . $exploitant['nom_complet'];
               
               // AUSSI créer/lier l'associé exploitant pour qu'il apparaisse dans le tableau
               $associate = new PluginAssociatesmanagerAssociate();
               $part = new PluginAssociatesmanagerPart();
               
               // Chercher si l'associé existe déjà (par nom complet)
               $iterator = $DB->request([
                  'FROM' => 'glpi_plugin_associatesmanager_associates',
                  'WHERE' => [
                     'name' => $exploitant['nom_complet']
                  ]
               ]);

               $associate_id = null;
               $associate_existed = false;
               
               if (count($iterator) > 0) {
                  // Associé existe déjà
                  $data = $iterator->current();
                  $associate_id = $data['id'];
                  $associate_existed = true;
                  
                  // Mettre à jour ses informations
                  $associate->update([
                     'id' => $associate_id,
                     'email' => $exploitant['email'],
                     'phonenumber' => $exploitant['telephone'],
                     'adresse' => $exploitant['adresse']
                  ]);
                  
                  $result['message'] .= " | Associé existant lié au fournisseur.";
               } else {
                  // Créer un nouvel associé
                  $associate_data = [
                     'name' => $exploitant['nom_complet'],
                     'type' => 1, // dirigeant
                     'matricule' => '',
                     'email' => $exploitant['email'],
                     'phonenumber' => $exploitant['telephone'],
                     'adresse' => $exploitant['adresse']
                  ];
                  $associate_id = $associate->add($associate_data);
                  if ($associate_id) {
                     $result['added']++;
                     $result['message'] .= " | Nouvel associé créé.";
                  }
               }
               
               // Créer/Vérifier la liaison (part) avec le fournisseur
               if ($associate_id) {
                  // Idempotence forte: si une liaison (active ou historisée) existe déjà,
                  // ne pas en créer une nouvelle.
                  $any_link_it = $DB->request([
                     'FROM' => 'glpi_plugin_associatesmanager_parts',
                     'WHERE' => [
                        'associates_id' => $associate_id,
                        'supplier_id'   => $supplier_id
                     ],
                     'LIMIT' => 1
                  ]);

                  if ($any_link_it->count() === 0) {
                     // Créer une seule liaison initiale (nbparts=0, libellé Exploitant)
                     $part_data = [
                        'associates_id' => $associate_id,
                        'supplier_id' => $supplier_id,
                        'nbparts' => 0,
                        'date_attribution' => date('Y-m-d'),
                        'date_fin' => null,
                        'libelle' => 'Exploitant'
                     ];
                     $part->add($part_data);
                  } else {
                     if ($associate_existed) {
                        $result['message'] .= " | Liaison déjà existante.";
                     }
                  }
               }
               
               $result['success'] = true;
            }
         }
         return $result;
      }

      // Sinon, traiter comme avant: récupérer les bénéficiaires effectifs
      $beneficiaires = self::getBeneficiaires($siren, $token);
      
      if (empty($beneficiaires)) {
         $result['success'] = true;
         $result['message'] = "Aucun bénéficiaire effectif trouvé pour le SIREN $siren. L'entreprise n'a peut-être pas de détenteur >25% du capital ou droits de vote (structure légale sans bénéficiaire effectif obligatoire).";
         return $result;
      }

      // Calculer le nombre total de parts à partir du premier bénéficiaire (si possible)
      $total_parts_calculated = null;
      // Ne pas calculer ni modifier nbparttotal automatiquement.
      // Le nombre total de parts du fournisseur est saisi manuellement depuis l'interface.

      $associate = new PluginAssociatesmanagerAssociate();
      $part = new PluginAssociatesmanagerPart();

      // Précharger les associations existantes (quel que soit l'état de la part) pour éviter doublons et préserver les dates
      $existingByName = [];
      $existing_iter = $DB->request([
         'SELECT' => ['a.id AS associate_id', 'a.name'],
         'FROM'   => 'glpi_plugin_associatesmanager_parts AS p',
         'INNER JOIN' => ['glpi_plugin_associatesmanager_associates AS a' => ['ON' => new \Glpi\DBAL\QueryExpression('a.id = p.associates_id')]],
         'WHERE'  => ['p.supplier_id' => $supplier_id]
      ]);
      foreach ($existing_iter as $row) {
         $key = self::normalizeName($row['name']);
         $existingByName[$key] = (int)$row['associate_id'];
      }

      // Récupérer les bénéficiaires effectifs actuels (avec date_fin NULL et libelle contenant "Bénéficiaire")
      // pour fermer ceux qui ne sont plus dans l'API
      $current_beneficiaires_parts = $DB->request([
         'SELECT' => ['p.id AS part_id', 'p.associates_id', 'a.name'],
         'FROM'   => 'glpi_plugin_associatesmanager_parts AS p',
         'INNER JOIN' => ['glpi_plugin_associatesmanager_associates AS a' => ['ON' => new \Glpi\DBAL\QueryExpression('a.id = p.associates_id')]],
         'WHERE'  => [
            'p.supplier_id' => $supplier_id,
            ['OR' => [
               ['p.libelle' => ['LIKE', '%Bénéficiaire%']],
               ['p.libelle' => ['LIKE', '%beneficiaire%']]
            ]],
            'p.date_fin' => null
         ]
      ]);
      
      $current_benef_names = [];
      $current_benef_parts = [];
      foreach ($current_beneficiaires_parts as $row) {
         $normalized = self::normalizeName($row['name']);
         $current_benef_names[$normalized] = $row['part_id'];
         $current_benef_parts[] = $row['part_id'];
      }
      
      // Créer une liste des noms normalisés des nouveaux bénéficiaires de l'API
      $api_benef_names = [];
      foreach ($beneficiaires as $ben) {
         $api_benef_names[] = self::normalizeName($ben['nom_complet']);
      }

      foreach ($beneficiaires as $beneficiaire) {
         // Ne pas créer de ligne de part pour des pourcentages nuls
         // (dirigeants sans parts, bénéficiaires 0%).
         if (isset($beneficiaire['pourcentage_parts']) && (float)$beneficiaire['pourcentage_parts'] <= 0) {
            // On peut tout de même créer/mise à jour de l'associé si besoin, mais
            // on n'ajoutera pas de part pour éviter les doublons à 0.
            // La suite du flux gère l'associé, et plus bas on sautera la création de part.
         }
         $bname = self::normalizeName($beneficiaire['nom_complet']);
         $associate_id = null;
         if (!empty($bname) && isset($existingByName[$bname])) {
            // Déjà lié à ce fournisseur : vérifier si le pourcentage a changé
            $associate_id = $existingByName[$bname];
            // Récupérer la part active
            $existing_part = $DB->request([
               'FROM' => 'glpi_plugin_associatesmanager_parts',
               'WHERE' => [
                  'associates_id' => $associate_id,
                  'supplier_id'   => $supplier_id,
                  'date_fin'     => null,
                  ['OR' => [
                     ['libelle' => ['LIKE', '%Bénéficiaire%']],
                     ['libelle' => ['LIKE', '%beneficiaire%']]
                  ]]
               ],
               'LIMIT' => 1
            ]);
            $new_pct = (isset($beneficiaire['pourcentage_parts']) && is_numeric($beneficiaire['pourcentage_parts'])) ? (float)$beneficiaire['pourcentage_parts'] : null;
            if ($existing_part->count() > 0 && $new_pct !== null) {
               $part_row = $existing_part->current();
               $part_id = $part_row['id'];
               $old_nbparts = (float)($part_row['nbparts'] ?? 0);
               $supplier_total = 0.0;
               $supplier_obj = new Supplier();
               if ($supplier_obj->getFromDB($supplier_id) && isset($supplier_obj->fields['nbparttotal']) && is_numeric($supplier_obj->fields['nbparttotal'])) {
                  $supplier_total = (float)$supplier_obj->fields['nbparttotal'];
               }
               // Pour les bénéficiaires effectifs uniquement, recalcule toujours nbparts à partir du pourcentage et du total
               if ($beneficiaire['type'] === 'beneficiaire_effectif' && $supplier_total > 0.0) {
                  $new_nbparts = round(($new_pct * $supplier_total) / 100.0, 4);
                  if (abs($old_nbparts - $new_nbparts) > 0.0001) {
                     $part->update([
                        'id' => $part_id,
                        'nbparts' => $new_nbparts
                     ]);
                     $result['updated']++;
                  }
                  // Le pourcentage affiché pour le bénéficiaire effectif doit TOUJOURS rester celui de l'API (ne jamais recalculer ni afficher nbparts/total)
                  // Si tu affiches le pourcentage dans l'UI, utilises la valeur stockée dans la colonne pourcentage_parts (ou équivalent), pas un calcul dynamique.
               }
               // Pour les autres types, ne rien faire
               continue;
            }
            // Si pas de part active, on continue le flux normal pour créer la part
         }
         try {
            // Chercher si l'associé existe déjà
            $iterator = $DB->request([
               'FROM' => 'glpi_plugin_associatesmanager_associates',
               'WHERE' => [
                  'name' => $beneficiaire['nom_complet']
               ]
            ]);

            $associate_id = null;
            $associate_exists = false;
            if (count($iterator) > 0) {
               // Associé existe déjà (match exact)
               $data = $iterator->current();
               $associate_id = (int)$data['id'];
               $associate_exists = true;

               // Si une part (active ou historisée) existe déjà pour ce fournisseur, ne rien changer
               $existing_part = $DB->request([
                  'FROM' => 'glpi_plugin_associatesmanager_parts',
                  'WHERE' => [
                     'associates_id' => $associate_id,
                     'supplier_id'   => $supplier_id
                  ],
                  'LIMIT' => 1
               ]);
               if ($existing_part->count() > 0) {
                  continue; // pas de nouvelle part, pas de mise à jour
               }
            } else {
               // Essayer de retrouver par nom normalisé un associé existant (pour éviter doublons proches)
               $norm = self::normalizeName($beneficiaire['nom_complet']);
               $found_id = self::findAssociateIdByNormalizedName($norm);
               if ($found_id) {
                  $associate_id = $found_id;
                  $associate_exists = true;
                  // Si déjà lié pour ce fournisseur, ne rien changer
                  $existing_part = $DB->request([
                     'FROM' => 'glpi_plugin_associatesmanager_parts',
                     'WHERE' => [
                        'associates_id' => $associate_id,
                        'supplier_id'   => $supplier_id
                     ],
                     'LIMIT' => 1
                  ]);
                  if ($existing_part->count() > 0) {
                     continue;
                  }
               }
               // Créer un nouvel associé
               $associate_data = [
                  'name' => $beneficiaire['nom_complet'],

                  'type' => ($beneficiaire['type'] === 'dirigeant') ? 1 : 0, // 0 = personne physique, 1 = dirigeant
                  'matricule' => '',
                  'date_naissance' => $beneficiaire['date_naissance'],
                  'nationalite' => $beneficiaire['nationalite'],
                  'adresse' => $beneficiaire['adresse']
               ];

               $associate_id = $associate->add($associate_data);
               if (!$associate_id) {
                  $result['errors'][] = "Impossible de créer l'associé: " . $beneficiaire['nom_complet'];
                  continue;
               }
            }

            // Ajouter/Mettre à jour les parts (idempotent)
            if ($associate_id) {
               // Idempotence: si une part (quelle qu'elle soit) existe déjà pour cet associé + fournisseur,
               // ne pas créer de nouvelle entrée.
               $any_part = $DB->request([
                  'FROM' => 'glpi_plugin_associatesmanager_parts',
                  'WHERE' => [
                     'associates_id' => $associate_id,
                     'supplier_id'   => $supplier_id
                  ],
                  'LIMIT' => 1
               ]);

               if ($any_part->count() > 0) {
                  // Déjà lié: ne rien créer/modifier
                  continue;
               }

               // Utiliser le nombre de parts absolu si fourni par l'API
               $nbparts_abs = 0.0;
               if (isset($beneficiaire['nbparts_absolu']) && is_numeric($beneficiaire['nbparts_absolu'])) {
                  $nbparts_abs = (float)$beneficiaire['nbparts_absolu'];
               } else {
                  // Fallback: directes + indirectes
                  if (isset($beneficiaire['parts_directes'])) {
                     $nbparts_abs += (float)$beneficiaire['parts_directes'];
                  }
                  if (isset($beneficiaire['parts_indirectes'])) {
                     $nbparts_abs += (float)$beneficiaire['parts_indirectes'];
                  }
               }
               // Si non disponible mais pourcentage présent et nb total de parts fournisseur saisi, estimer
               if ($nbparts_abs <= 0.0 && isset($beneficiaire['pourcentage_parts']) && (float)$beneficiaire['pourcentage_parts'] > 0) {
                  $supplier_total = 0.0;
                  $supplier_obj = new Supplier();
                  if ($supplier_obj->getFromDB($supplier_id) && isset($supplier_obj->fields['nbparttotal']) && is_numeric($supplier_obj->fields['nbparttotal'])) {
                     $supplier_total = (float)$supplier_obj->fields['nbparttotal'];
                  }
                  if ($supplier_total > 0.0) {
                     $nbparts_abs = round(((float)$beneficiaire['pourcentage_parts'] * $supplier_total) / 100.0, 4);
                  }
               }
               if ($nbparts_abs < 0) {
                  $nbparts_abs = 0;
               }

               // Créer une nouvelle part avec le nombre de parts réel
               $part_data = [
                  'associates_id' => $associate_id,
                  'supplier_id' => $supplier_id,
                  'nbparts' => $nbparts_abs,
                  'date_attribution' => date('Y-m-d'),
                  'date_fin' => null,
                  'libelle' => 'Bénéficiaire effectif'
               ];
               $part->add($part_data);
               if ($associate_exists) {
                  $result['updated']++;
               } else {
                  $result['added']++;
               }
            }

         } catch (Exception $e) {
            $result['errors'][] = "Erreur pour " . $beneficiaire['nom_complet'] . ": " . $e->getMessage();
         }
      }

      // Fermer les bénéficiaires effectifs qui ne sont plus dans l'API (date_fin = aujourd'hui)
      $closed_count = 0;
      foreach ($current_benef_names as $normalized_name => $part_id) {
         if (!in_array($normalized_name, $api_benef_names)) {
            // Ce bénéficiaire n'est plus dans l'API, fermer sa part
            $part->update([
               'id' => $part_id,
               'date_fin' => date('Y-m-d')
            ]);
            $closed_count++;
         }
      }

      $result['success'] = true;
      $msg = "Synchronisation terminée: {$result['added']} ajouté(s), {$result['updated']} mis à jour";
      if ($closed_count > 0) {
         $msg .= ", $closed_count fermé(s)";
      }
      $result['message'] = $msg;

      return $result;
   }

   /**
    * Teste la connexion à l'API RNE
    * @return array Résultat du test
    */
   public static function testConnection() {
      $token = self::authenticate();
      
      if ($token) {
         return [
            'success' => true,
            'message' => 'Connexion réussie à l\'API RNE INPI'
         ];
      } else {
         return [
            'success' => false,
            'message' => 'Échec de la connexion à l\'API RNE INPI. Vérifiez vos identifiants.'
         ];
      }
   }
}

// Backward-compatibility alias: some code may reference PluginAssociatesmanagerRneApi
if (!class_exists('PluginAssociatesmanagerRneApi')) {
   class PluginAssociatesmanagerRneApi extends PluginAssociatesmanagerRneapi {}
}

// Backward-compatibility alias: some code may reference PluginAssociatesmanagerRneApi
if (!class_exists('PluginAssociatesmanagerRneApi')) {
   class PluginAssociatesmanagerRneApi extends PluginAssociatesmanagerRneapi {}
}
