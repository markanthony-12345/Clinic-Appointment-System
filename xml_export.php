<?php
require_once 'config.php';
requireAdmin();
require_once 'classes/Patient.php';
$patient = new Patient();
$xml = $patient->exportToXML();
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="patients_export.xml"');
echo $xml;
?>