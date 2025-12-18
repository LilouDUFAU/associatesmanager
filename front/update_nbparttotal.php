<?php
require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
   http_response_code(405);
   echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
   exit;
}

$supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
$nbparttotal = $_POST['nbparttotal'] ?? ($_POST['am_nbparttotal'] ?? null);
// Normalize missing/undefined/empty to 0 so we don’t reject the request
if ($nbparttotal === null || $nbparttotal === '' || $nbparttotal === 'undefined') {
   $nbparttotal = '0';
}

// DEBUG: Log incoming request (GLPI 11 CSRF is handled by CheckCsrfListener)
$hdr_token = $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'] ?? '';
error_log('[AM] nbparttotal endpoint: supplier_id=' . $supplier_id . ', nbparttotal=' . $nbparttotal . ', token_present=' . (isset($_POST['_glpi_csrf_token']) ? 'yes' : 'no') . ', header_token=' . ($hdr_token !== '' ? 'yes' : 'no'));

// Coerce value to float; if invalid, default to 0 to avoid blocking the request
if (!is_numeric($nbparttotal)) {
   error_log('[AM] nbparttotal coerced: supplier_id=' . $supplier_id . ', raw=' . var_export($nbparttotal, true));
   $nbparttotal = '0';
}
$nb = (float)$nbparttotal;
if ($nb < 0) {
   $nb = 0.0;
}

// Mise à jour via ORM
global $DB;
try {
   $DB->update('glpi_suppliers', ['nbparttotal' => $nb], ['id' => $supplier_id]);
   // Log success with user and rows count
   $rows = $DB->affectedRows();
   $uid = method_exists('Session', 'getLoginUserID') ? (Session::getLoginUserID() ?? 0) : 0;
   error_log('[AM] nbparttotal UPDATE OK: supplier_id=' . $supplier_id . ', nbparttotal=' . $nb . ', rows=' . $rows . ', user=' . $uid);
   // If a redirect is provided and request is not AJAX, redirect instead of JSON
   $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
   $redirect = $_POST['redirect'] ?? '';
   if (!$is_ajax && $redirect) {
      header('Location: ' . $redirect);
      exit;
   }
   echo json_encode(['ok' => true, 'supplier_id' => $supplier_id, 'nbparttotal' => $nb]);
} catch (Throwable $e) {
   http_response_code(500);
   echo json_encode(['ok' => false, 'error' => 'Erreur serveur']);
}
