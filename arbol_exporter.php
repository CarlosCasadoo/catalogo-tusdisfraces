<?php
// arbol_exporter.php - Genera 'arbol_categorias.txt' en la carpeta del proyecto
// con el árbol de categorías en el MISMO formato que muestra el modal de la
// interfaz (️📁/📄/📦, [Provisional], (sistema), (n) productos directos).
// Se regenera automáticamente desde api.php tras cada cambio de categorías
// (crear / renombrar / mover / eliminar / mover productos) y se puede lanzar
// a mano con:  php exportar_arbol.php

function generarArbolTexto($pdo) {
    // Mismo orden y campos que getCategories en api.php
    $stmt = $pdo->query("SELECT id_category, name, parent_id, depth, position, direct_product_count, is_new FROM categories ORDER BY position ASC, name ASC");
    $allCategories = $stmt->fetchAll();

    $catsById = [];
    $childrenMap = [];
    foreach ($allCategories as $cat) {
        $catsById[$cat['id_category']] = $cat;
        $pId = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : null;
        $childrenMap[$pId][] = $cat['id_category'];
    }

    $orderedList = [];
    $visited = [];

    $traverse = function ($catId, $level = 0) use (&$traverse, &$orderedList, &$visited, &$catsById, &$childrenMap) {
        if (!isset($catsById[$catId]) || isset($visited[$catId])) return;
        $visited[$catId] = true;
        $cat = $catsById[$catId];
        $cat['tree_level'] = $level;
        $orderedList[] = $cat;
        if (isset($childrenMap[$catId])) {
            foreach ($childrenMap[$catId] as $childId) {
                $traverse($childId, $level + 1);
            }
        }
    };

    // 1. Nodo huérfanos (igual que getCategories)
    $orphanCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE default_category_id NOT IN (SELECT id_category FROM categories) OR default_category_id IS NULL")->fetchColumn();
    $orderedList[] = [
        'id_category' => -1,
        'name' => 'Sin Categoría Activa (Huérfanos)',
        'parent_id' => null,
        'direct_product_count' => $orphanCount,
        'is_new' => 1,
        'tree_level' => 0,
    ];
    $visited[-1] = true;

    // 2. Árbol principal desde Raíz (1) o Inicio (2)
    if (isset($catsById[1])) {
        $traverse(1, 0);
    } elseif (isset($catsById[2])) {
        $traverse(2, 0);
    }

    // 3. Ramas separadas del árbol principal
    foreach ($allCategories as $cat) {
        $cId = $cat['id_category'];
        if (!isset($visited[$cId])) {
            $traverse($cId, 0);
        }
    }

    // Resumen (igual que el encabezado del modal)
    $totalCats = count($allCategories);
    $provisionales = 0;
    foreach ($allCategories as $cat) {
        if ((int)$cat['is_new'] === 1 && (int)$cat['id_category'] < 0 && (int)$cat['id_category'] !== -1) {
            $provisionales++;
        }
    }
    $totalProductos = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    $lineas = [];
    $lineas[] = '══════════════════════════════════════════════════════════════════════';
    $lineas[] = 'ÁRBOL DE CATEGORÍAS DEL CATÁLOGO (Tienda 1)';
    $lineas[] = 'Formato: 📁 = tiene subcategorías · 📄 = sin subcategorías · 📦 = huérfanos · (n) = productos directos';
    $lineas[] = 'Generado: ' . date('Y-m-d H:i:s');
    $lineas[] = sprintf('Total: %d categorías · %d provisionales · %d productos · %d productos huérfanos',
        $totalCats, $provisionales, $totalProductos, $orphanCount);
    $lineas[] = '══════════════════════════════════════════════════════════════════════';
    $lineas[] = '';

    foreach ($orderedList as $cat) {
        $nivel = (int)$cat['tree_level'];
        $esHuerfanos = (int)$cat['id_category'] === -1;
        $tieneHijos = isset($childrenMap[$cat['id_category']]) && count($childrenMap[$cat['id_category']]) > 0;
        $icono = $esHuerfanos ? '📦' : ($tieneHijos ? '📁' : '📄');
        $esProvisional = (int)$cat['is_new'] === 1 && (int)$cat['id_category'] < 0 && !$esHuerfanos;
        $esSistema = ((int)$cat['id_category'] === 1 || (int)$cat['id_category'] === 2);

        $sufijos = '';
        if ($esProvisional) $sufijos .= ' [Provisional]';
        if ($esSistema) $sufijos .= ' (sistema)';

        $lineas[] = str_repeat('   ', $nivel) . $icono . ' ' . $cat['name'] . $sufijos . ' (' . (int)$cat['direct_product_count'] . ')';
    }

    return implode("\n", $lineas) . "\n";
}

/**
 * Escribe el árbol en 'arbol_categorias.txt' en la carpeta del proyecto.
 * Devuelve true si se escribió correctamente.
 */
function exportarArbolAArchivo($pdo, $ruta = null) {
    try {
        $ruta = $ruta !== null ? $ruta : __DIR__ . DIRECTORY_SEPARATOR . 'arbol_categorias.txt';
        $texto = generarArbolTexto($pdo);
        $ok = file_put_contents($ruta, $texto, LOCK_EX);
        return $ok !== false;
    } catch (Throwable $e) {
        return false;
    }
}