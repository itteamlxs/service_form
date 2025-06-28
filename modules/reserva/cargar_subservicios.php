<?php
require_once __DIR__ . '/../../core/db.php';
header('Content-Type: application/json');

$categoria_id = $_GET['categoria_id'] ?? null;

if (!$categoria_id || !is_numeric($categoria_id)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre FROM subservicios WHERE categoria_id = ?");
$stmt->execute([$categoria_id]);
echo json_encode($stmt->fetchAll());
