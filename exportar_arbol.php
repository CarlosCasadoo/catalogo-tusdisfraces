<?php
// exportar_arbol.php - CLI: regenera 'arbol_categorias.txt' en la carpeta del proyecto.
// Uso:  php exportar_arbol.php
// NOTA: el archivo también se regenera solo desde api.php tras cada cambio de categorías.
require_once __DIR__ . '/arbol_exporter.php';

$pdo = new PDO('sqlite:' . __DIR__ . '/catalogo_tusdisfraces.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$ruta = __DIR__ . '/arbol_categorias.txt';
if (exportarArbolAArchivo($pdo, $ruta)) {
    $lineas = count(file($ruta));
    echo "✅ Árbol actualizado: $ruta ($lineas líneas)\n";
} else {
    echo "❌ Error al escribir el árbol.\n";
    exit(1);
}