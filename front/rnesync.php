<?php

require_once __DIR__ . '/../../../inc/includes.php';

$supplier_id = (int)(($_GET['supplier_id'] ?? null) ?? ($_POST['supplier_id'] ?? 0));
if (!$supplier_id) {
    Html::redirect(Plugin::getWebDir('associatesmanager') . '/front/rnesync.form.php');
}

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
$silent   = isset($_GET['silent']) || isset($_POST['silent']) || !empty($redirect);

$supplier = new Supplier();
if (!$supplier->getFromDB($supplier_id)) {
    if ($silent) {
        Session::addMessageAfterRedirect("Fournisseur non trouvé", false, ERROR);
        Html::redirect(Supplier::getFormURL(true) . '?id=' . $supplier_id . '&forcetab=PluginAssociatesmanagerAssociate$1');
    }
    echo "Fournisseur non trouvé";
    exit;
}

$siren = (int)(($_POST['siren'] ?? null) ?? ($_GET['siren'] ?? 0));

if ($siren) {
    $result = PluginAssociatesmanagerRneapi::syncBeneficiairesForSupplier($supplier_id, $siren);
    if ($silent) {
        if (!empty($result['success'])) {
            Session::addMessageAfterRedirect($result['message'], true, INFO);
        } else {
            Session::addMessageAfterRedirect('Erreur de synchronisation RNE: ' . ($result['message'] ?? 'Inconnue'), false, ERROR);
        }
        $target = $redirect ?: Supplier::getFormURL(true) . '?id=' . $supplier_id . '&forcetab=PluginAssociatesmanagerAssociate$1';
        Html::redirect($target);
    }
}

// Non-silent flow: show a simple page (rare path)
Html::header('RNE Synchronization', '', 'management', 'Supplier', $supplier_id);
echo "<div class='spaced'>";
echo "<h2>Synchronisation RNE - " . $supplier->fields['name'] . "</h2>";
if ($siren && isset($result)) {
   if (!empty($result['success'])) {
      echo "<div class='alert alert-success'>✓ " . htmlspecialchars($result['message']) . "</div>";
   } else {
      echo "<div class='alert alert-danger'>✗ Erreur: " . htmlspecialchars($result['message'] ?? 'Inconnue') . "</div>";
   }
}
echo "<div class='center' style='margin-top:20px'>";
echo "<form method='get'>";
echo Html::hidden('supplier_id', ['value' => $supplier_id]);
echo "<label>SIREN (9 chiffres):</label> ";
echo "<input type='text' name='siren' id='siren' class='form-control' placeholder='123456789' pattern='[0-9]{9}' maxlength='9' required style='width:150px; display:inline-block' />";
echo Html::hidden('silent', ['value' => 1]);
echo Html::hidden('redirect', ['value' => Supplier::getFormURL(true) . '?id=' . $supplier_id . '&forcetab=PluginAssociatesmanagerAssociate$1']);
echo " <button type='submit' class='btn btn-primary'>Synchroniser</button>";
echo "</form>";
echo "</div>";
echo "</div>";
Html::footer();
?>
