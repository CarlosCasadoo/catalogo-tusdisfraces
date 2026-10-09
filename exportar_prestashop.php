<?php
// Punto 4 - Paquete de exportacion a PrestaShop.
// Genera archivos SOLO-LECTURA desde la BD viva (no la modifica):
//   export-prestashop/categorias.csv            (arbol completo, id ORIGINAL -incluye negativos-)
//   export-prestashop/productos.csv             (productos con default_category_id ORIGINAL)
//   export-prestashop/productos_categorias.csv  (todas las asociaciones: default + filas pc)
// La traduccion id_original -> id_real la hace la persona que importa en PrestaShop.
error_reporting(E_ALL & ~E_DEPRECATED);

$DB  = 'C:/Users/Usuario/Desktop/proyecto/catalogo-tusdisfraces/catalogo_tusdisfraces.db';
$OUT = 'C:/Users/Usuario/Desktop/proyecto/catalogo-tusdisfraces/export-prestashop';
if (!is_dir($OUT)) mkdir($OUT, 0777, true);

$d = new PDO('sqlite:' . $DB);
$d->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function abrir_csv($path) {
    $fh = fopen($path, 'wb');
    fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM para Excel
    return $fh;
}
function fila_csv($fh, array $row) { fputcsv($fh, $row, ';', '"', "\n"); }

// ---- rutas (camino de nombres desde la raiz) para clave estable consultable ----
$padres = [];
foreach ($d->query('SELECT id_category, parent_id FROM categories') as $r)
    $padres[$r['id_category']] = $r['parent_id'];
$nombres = [];
foreach ($d->query('SELECT id_category, name FROM categories') as $r)
    $nombres[$r['id_category']] = $r['name'];
// BFS desde raices para construir rutas
$hijos = [];
foreach ($padres as $hijo => $padre) $hijos[(int)$padre][] = $hijo;
$ruta = [];
$cola = [];
foreach ($d->query('SELECT id_category FROM categories WHERE parent_id IS NULL') as $r) $cola[] = [$r['id_category'], ''];
while ($cola) {
    [$id, $acc] = array_shift($cola);
    $ruta[$id] = $acc . $nombres[$id];
    foreach ($hijos[$id] ?? [] as $h) $cola[] = [$h, $ruta[$id] . ' > '];
}
$nRutas = count($ruta);

// ---- categorias.csv ----
$fh = abrir_csv("$OUT/categorias.csv");
fila_csv($fh, ['id_original','parent_id_original','nombre','slug','position','depth','productos_directos','is_new','ruta']);
$n = 0;
foreach ($d->query('SELECT id_category, parent_id, name, slug, position, depth, direct_product_count, is_new FROM categories ORDER BY depth, position, id_category') as $c) {
    fila_csv($fh, [
        $c['id_category'],
        $c['parent_id'] === null ? '' : $c['parent_id'],
        $c['name'],
        $c['slug'],
        $c['position'],
        $c['depth'],
        $c['direct_product_count'],
        $c['is_new'],
        isset($ruta[$c['id_category']]) ? $ruta[$c['id_category']] : ''
    ]);
    $n++;
}
fclose($fh);

// ---- productos.csv ----
$fh = abrir_csv("$OUT/productos.csv");
fila_csv($fh, ['id_product','name','slug','reference','ean13','default_category_id']);
$nP = 0; $nSinDefault = 0;
foreach ($d->query('SELECT id_product, name, slug, reference, ean13, default_category_id FROM products ORDER BY id_product') as $p) {
    fila_csv($fh, [
        $p['id_product'], $p['name'], $p['slug'],
        $p['reference'] === null ? '' : $p['reference'],
        $p['ean13'] === null ? '' : $p['ean13'],
        $p['default_category_id'] === null ? '' : $p['default_category_id']
    ]);
    $nP++;
    if ($p['default_category_id'] === null) $nSinDefault++;
}
fclose($fh);

// ---- productos_categorias.csv (default + todas las filas pc, dedupe a favor de default) ----
$fh = abrir_csv("$OUT/productos_categorias.csv");
fila_csv($fh, ['id_product','id_category_original','es_default','origen']);
$rows = $d->query(
    "SELECT p.id_product, p.default_category_id AS id_category, 1 AS es_default, 'default' AS origen
       FROM products p WHERE p.default_category_id IS NOT NULL
     UNION ALL
     SELECT id_product, id_category, 0, 'pc' FROM product_categories"
)->fetchAll();
usort($rows, function ($a, $b) {
    return [$a['id_product'], -$a['es_default'], $a['id_category']]
        <=> [$b['id_product'], -$b['es_default'], $b['id_category']];
});
$nA = 0; $nDedup = 0; $prev = null;
foreach ($rows as $r) {
    $k = $r['id_product'] . '|' . $r['id_category'];
    if ($prev === $k) { $nDedup++; continue; }
    fila_csv($fh, [$r['id_product'], $r['id_category'], $r['es_default'], $r['origen']]);
    $nA++;
    $prev = $k;
}
fclose($fh);

// ---- verificacion ----
$idsCsv = [];
$h = fopen("$OUT/categorias.csv", 'r'); fgetcsv($h, 0, ';');
while (($row = fgetcsv($h, 0, ';')) !== false) $idsCsv[$row[0]] = true;
fclose($h);

$h = fopen("$OUT/productos_categorias.csv", 'r'); fgetcsv($h, 0, ';');
$refsForaneas = $idsCsv;
while (($row = fgetcsv($h, 0, ';')) !== false) {
    $cat = $row[1];
    if (!isset($refsForaneas[$cat])) $refsForaneas[$cat] = false;
}
fclose($h);
$foraneasRojas = 0;
foreach ($refsForaneas as $cat => $ok) if ($ok === false) $foraneasRojas++;
$negEnCategorias = 0; $posEnCategorias = 0;
foreach (array_keys($idsCsv) as $id) { if ((int)$id < 0) $negEnCategorias++; else $posEnCategorias++; }

printf("== GENERADO ==\n");
printf("categorias.csv:            %d filas (rutas %d/%d)\n", $n, $nRutas, $n);
printf("productos.csv:             %d filas (sin default: %d)\n", $nP, $nSinDefault);
printf("productos_categorias.csv:  %d filas (dedup: %d)\n", $nA, $nDedup);
printf("ids en categorias: neg=%d pos=%d\n", $negEnCategorias, $posEnCategorias);
printf("ids foraneos en productos_categorias sin categoria en el CSV: %d\n", $foraneasRojas);
printf("tamano archivos:\n");
foreach (glob("$OUT/*.csv") as $f) printf("   %-38s %s bytes\n", basename($f), number_format(filesize($f)));
echo "\nMUESTRA categorias.csv (primeras 3 + ultimas 2):\n";
$lines = file("$OUT/categorias.csv");
echo "   " . trim($lines[0]) . "\n" . "   " . trim($lines[1]) . "\n" . "   " . trim($lines[2]) . "\n" . "   " . trim($lines[3]) . "\n";
$ll = count($lines);
echo "   ...\n   " . trim($lines[$ll-2]) . "\n   " . trim($lines[$ll-1]) . "\n";