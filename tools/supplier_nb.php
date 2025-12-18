<?php
define('GLPI_ROOT', realpath(__DIR__ . '/../../..'));
require_once GLPI_ROOT . '/inc/includes.php';

$id = (int)($argv[1] ?? 0);
if ($id <= 0) {
   fwrite(STDERR, "Usage: php supplier_nb.php <supplier_id>\n");
   exit(1);
}

global $DB;
$it = $DB->request([
   'SELECT' => ['id', 'name', 'nbparttotal'],
   'FROM'   => 'glpi_suppliers',
   'WHERE'  => ['id' => $id],
   'LIMIT'  => 1
]);
if ($row = $it->current()) {
   echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
   exit(0);
}

echo json_encode(['error' => 'not found', 'id' => $id]), "\n";
exit(2);
