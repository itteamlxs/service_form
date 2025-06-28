<?php
$modulo = $_GET['modulo'] ?? '';
$ruta = __DIR__ . "/modules/$modulo/index.php";

if (is_file($ruta)) {
    require $ruta;
} else {
    echo "Módulo no encontrado.";
}
