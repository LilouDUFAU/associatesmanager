<?php

require_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

// Vérifier les droits de lecture
if (!PluginAssociatesmanagerRight::canRead()) {
   Html::displayRightError();
}

// Récupérer les paramètres
$format = isset($_GET['format']) ? $_GET['format'] : 'csv'; // csv ou html
$filter_supplier = isset($_GET['filter_supplier']) ? intval($_GET['filter_supplier']) : 0;
$filter_associate = isset($_GET['filter_associate']) ? intval($_GET['filter_associate']) : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : ''; // active, inactive, all

// Préparer la requête
global $DB;

$where = [];

// Appliquer les filtres
if ($filter_supplier > 0) {
    $where['p.supplier_id'] = $filter_supplier;
}

if ($filter_associate > 0) {
    $where['p.associates_id'] = $filter_associate;
}

if ($filter_status === 'active') {
    $where[] = ['OR', ['p.date_fin' => null], ['p.date_fin' => ['>', new QueryExpression('NOW()')]]];
} elseif ($filter_status === 'inactive') {
    $where[] = ['AND', ['p.date_fin' => ['!=', null]], ['p.date_fin' => ['<=', new QueryExpression('NOW()')]]];
}

$result = $DB->request([
    'SELECT' => ['p.id', 'p.libelle', 'p.nbparts', 'p.date_attribution', 'p.date_fin',
                 'a.id AS associate_id', 'a.name AS associate_name', 'a.date_naissance', 'a.nationalite', 'a.adresse',
                 's.id AS supplier_id', 's.name AS supplier_name'],
    'FROM' => 'glpi_plugin_associatesmanager_parts AS p',
    'LEFT JOIN' => [
        'glpi_plugin_associatesmanager_associates AS a' => [
            'ON' => ['p.associates_id' => 'a.id']
        ],
        'glpi_suppliers AS s' => [
            'ON' => ['p.supplier_id' => 's.id']
        ]
    ],
    'WHERE' => $where,
    'ORDER' => ['s.name', 'a.name']
]);

$rows = [];
foreach ($result as $row) {
    $rows[] = $row;
}

if ($format === 'csv') {
    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="parts_associates_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM pour UTF-8 (Excel compatibilité)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // En-têtes
    $headers = ['Associé', 'Date de naissance', 'Nationalité', 'Adresse', 'Nombre de parts', 'Date d\'attribution', 'Date de fin', 'Fournisseur', 'Statut'];
    fputcsv($output, $headers, ';');

    // Données
    foreach ($rows as $row) {
        $status = (empty($row['date_fin']) || strtotime($row['date_fin']) > time()) ? 'Actif' : 'Inactif';
        $data = [
            $row['associate_name'],
            $row['date_naissance'],
            $row['nationalite'],
            $row['adresse'],
            $row['nbparts'],
            $row['date_attribution'],
            $row['date_fin'],
            $row['supplier_name'],
            $status
        ];
        fputcsv($output, $data, ';');
    }

    fclose($output);
    exit;

} else {
    // Export HTML / Visualisation
    Html::header('Export - Associates Manager', $_SERVER['PHP_SELF']);

    echo "<div class='container-fluid'>";
    echo "<div class='row mb-3'>";
    echo "<div class='col-md-12'>";
    echo "<h1>Export des Associés et Parts</h1>";
    echo "</div>";
    echo "</div>";

    // Formulaire d'export
    echo "<div class='row mb-3'>";
    echo "<div class='col-md-12'>";
    echo "<form method='GET' class='form-inline' style='gap: 10px; flex-wrap: wrap;'>";
    echo "<input type='hidden' name='format' value='csv'>";

    // Filtre fournisseur
    echo "<select name='filter_supplier' class='form-control' style='width: 200px;'>";
    echo "<option value='0'>Tous les fournisseurs</option>";
    $supplier = new Supplier();
    foreach ($supplier->find() as $s) {
        $selected = ($filter_supplier == $s['id']) ? 'selected' : '';
        echo "<option value='{$s['id']}' $selected>{$s['name']}</option>";
    }
    echo "</select>";

    // Filtre associé
    echo "<select name='filter_associate' class='form-control' style='width: 200px;'>";
    echo "<option value='0'>Tous les associés</option>";
    $result_assoc = $DB->request([
        'FROM' => 'glpi_plugin_associatesmanager_associates',
        'ORDER' => ['name' => 'ASC']
    ]);
    foreach ($result_assoc as $row_assoc) {
        $selected = ($filter_associate == $row_assoc['id']) ? 'selected' : '';
        echo "<option value='{$row_assoc['id']}' $selected>{$row_assoc['name']}</option>";
    }
    echo "</select>";

    // Filtre statut
    echo "<select name='filter_status' class='form-control' style='width: 150px;'>";
    echo "<option value=''>Tous les statuts</option>";
    echo "<option value='active' " . ($filter_status === 'active' ? 'selected' : '') . ">Actifs</option>";
    echo "<option value='inactive' " . ($filter_status === 'inactive' ? 'selected' : '') . ">Inactifs</option>";
    echo "</select>";

    echo "<button type='submit' class='btn btn-primary'>Exporter en CSV</button>";
    echo "</form>";
    echo "</div>";
    echo "</div>";

    // Tableau de prévisualisation
    echo "<div class='row'>";
    echo "<div class='col-md-12'>";
    echo "<h3>Aperçu des données (" . count($rows) . " lignes)</h3>";
    echo "<table class='table table-striped table-hover'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Associé</th>";
    echo "<th>Date de naissance</th>";
    echo "<th>Nationalité</th>";
    echo "<th>Adresse</th>";
    echo "<th>Parts</th>";
    echo "<th>Attribution</th>";
    echo "<th>Fin</th>";
    echo "<th>Fournisseur</th>";
    echo "<th>Statut</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    foreach (array_slice($rows, 0, 100) as $row) {
        $status = (empty($row['date_fin']) || strtotime($row['date_fin']) > time()) ? 
            "<span class='badge badge-success'>Actif</span>" :
            "<span class='badge badge-secondary'>Inactif</span>";

        echo "<tr>";
        echo "<td>{$row['associate_name']}</td>";
        echo "<td>{$row['date_naissance']}</td>";
        echo "<td>{$row['nationalite']}</td>";
        echo "<td>{$row['adresse']}</td>";
        echo "<td>{$row['nbparts']}</td>";
        echo "<td>{$row['date_attribution']}</td>";
        echo "<td>" . ($row['date_fin'] ? $row['date_fin'] : '-') . "</td>";
        echo "<td>{$row['supplier_name']}</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";

    if (count($rows) > 100) {
        echo "<p class='text-muted'>Affichage des 100 premières lignes sur " . count($rows) . " lignes totales.</p>";
    }

    echo "</div>";
    echo "</div>";

    echo "</div>";

    Html::footer();
}
