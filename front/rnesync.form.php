<?php

require_once __DIR__ . '/../../../inc/includes.php';

$supplier_id = (int)($_GET['supplier_id'] ?? 0);

if (!$supplier_id) {
    Html::header('Synchronisation RNE', '', 'management', 'PluginAssociatesmanagerMenu', 'rnesync');
    echo "<div class='spaced'>";
    echo "<h2>Synchronisation RNE</h2>";
    echo "<p>Sélectionnez un fournisseur et saisissez le SIREN pour lancer la synchronisation depuis le RNE.</p>";
    echo "<form method='get' action='rnesync.php'>";
    echo "<div class='form-group'>";
    echo "<label>Fournisseur:</label> ";
    Dropdown::show('Supplier', ['name' => 'supplier_id']);
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label>SIREN (9 chiffres):</label>";
    echo "<input type='text' name='siren' class='form-control' placeholder='123456789' pattern='[0-9]{9}' maxlength='9' required style='max-width:200px' />";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'>Synchroniser</button>";
    echo "</form>";
    echo "</div>";
    Html::footer();
    exit;
}

$supplier = new Supplier();
if (!$supplier->getFromDB($supplier_id)) {
    Session::addMessageAfterRedirect("Fournisseur non trouvé", false, ERROR);
    Html::back();
    exit;
}

Html::header('Synchronisation RNE', '', 'management', 'Supplier', $supplier_id);

echo "<div class='spaced'>";
echo "<h2>Synchronisation RNE - " . $supplier->fields['name'] . "</h2>";
echo "<p>Entrez le SIREN de l'entreprise pour synchroniser les bénéficiaires effectifs depuis le Registre National des Entreprises.</p>";

echo "<form method='get' action='rnesync.php'>";
echo Html::hidden('supplier_id', ['value' => $supplier_id]);
echo "<div class='form-group'>";
echo "<label>SIREN (9 chiffres):</label>";
echo "<input type='text' name='siren' class='form-control' placeholder='123456789' pattern='[0-9]{9}' maxlength='9' required style='max-width:200px' />";
echo "</div>";
echo "<button type='submit' class='btn btn-primary'>Synchroniser</button>";
echo "</form>";

echo "</div>";

Html::footer();
?>
