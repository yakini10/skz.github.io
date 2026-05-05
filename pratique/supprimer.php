<?php
require_once 'config.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM animaux WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['message'] = "Animal supprimé !";
header('Location: index.php');
exit();
?>
