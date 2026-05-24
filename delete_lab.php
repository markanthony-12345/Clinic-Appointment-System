<?php
require_once 'config.php';
requireLogin();
$id = $_GET['id'];
$pdo->prepare("DELETE FROM laboratory WHERE lab_id = ?")->execute([$id]);
header("Location: laboratory.php");
?>