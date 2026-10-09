<?php
// importar_informal.php - Importa "consulta informal.csv" (categorías de El Informal / Tienda 3)
// a la tabla auxiliar informal_reference. Es re-importable: vacía la tabla antes de cargar.
ini_set('memory_limit', '1024M');
set_time_limit(600);

$dbPath = __DIR__ . '/catalogo_tusdisfraces.db';
$csvPath = __DIR__ . '/consulta informal.csv';

echo "=== IMPORTANDO REFERENCIA DE CATEGORÍAS (El Informal / Tienda 3) ===\n";

if (!file_exists($csvPath)) {
    fwrite(STDERR, "[ERROR] No se encontró el archivo CSV: $csvPath\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Asegurar que la tabla auxiliar exista aunque no se haya ejecutado esquema.sql
    $pdo->exec("CREATE TABLE IF NOT EXISTS informal_reference (
        id_product INTEGER PRIMARY KEY,
        reference TEXT NULL,
        familia TEXT NULL,
        subcategoria TEXT NULL,
        detalle TEXT NULL,
        hoja_por_defecto TEXT NULL,
        todas_categorias TEXT NULL,
        FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
    )");

    // Vaciar la tabla para permitir re-importaciones
    $pdo->exec('DELETE FROM informal_reference');

    // ------- Lectura del CSV -------
    $raw = file_get_contents($csvPath);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3); // quitar BOM si existe
    }

    // Normalizar a UTF-8 por seguridad (el export actual ya llega en UTF-8)
    $check = @iconv('UTF-8', 'UTF-8//IGNORE', $raw);
    if ($check !== $raw) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $raw);
        if ($converted !== false) {
            $raw = $converted;
            echo "[-] CSV convertido de Windows-1252 a UTF-8.\n";
        }
    }

    $h = fopen('php://memory', 'r+');
    fwrite($h, $raw);
    rewind($h);

    $porProducto = [];
    $filaIdx = 0;
    while (($f = fgetcsv($h, 0, ';', '"')) !== false) {
        $filaIdx++;
        if ($filaIdx === 1) continue; // cabecera
        if (count($f) < 11) continue;

        $pid = (int)($f[0] ?? 0);
        if ($pid <= 0) continue;

        // Preferir la fila de la ficha base (ID Combinacion = 0) sobre las de combinaciones
        $esBase = (int)($f[1] ?? 0) === 0;
        if (!isset($porProducto[$pid]) || $esBase) {
            $porProducto[$pid] = [
                'reference'   => trim($f[2] ?? ''),
                'familia'     => trim($f[6] ?? ''),
                'subcategoria'=> trim($f[7] ?? ''),
                'detalle'     => trim($f[8] ?? ''),
                'hoja'        => trim($f[9] ?? ''),
                'todas'       => trim($f[10] ?? ''),
            ];
        }
    }
    fclose($h);

    echo "[-] CSV leído: $filaIdx filas, " . count($porProducto) . " productos distintos.\n";

    // ------- Cruce con Tienda 1 por id_product -------
    $idsT1 = array_flip($pdo->query('SELECT id_product FROM products')->fetchAll(PDO::FETCH_COLUMN));

    $stmtIns = $pdo->prepare(
        "INSERT INTO informal_reference (id_product, reference, familia, subcategoria, detalle, hoja_por_defecto, todas_categorias)
         VALUES (:id, :ref, :fam, :sub, :det, :hoja, :todas)"
    );

    $pdo->beginTransaction();
    $insertados = 0;
    $omitidos = 0;
    foreach ($porProducto as $pid => $d) {
        if (!isset($idsT1[$pid])) {
            $omitidos++;
            continue;
        }
        $stmtIns->execute([
            ':id'   => $pid,
            ':ref'  => $d['reference'] !== '' ? $d['reference'] : null,
            ':fam'  => $d['familia'] !== '' ? $d['familia'] : null,
            ':sub'  => $d['subcategoria'] !== '' ? $d['subcategoria'] : null,
            ':det'  => $d['detalle'] !== '' ? $d['detalle'] : null,
            ':hoja' => $d['hoja'] !== '' ? $d['hoja'] : null,
            ':todas'=> $d['todas'] !== '' ? $d['todas'] : null,
        ]);
        $insertados++;
    }
    $pdo->commit();

    echo "[+] Importación finalizada: $insertados productos con referencia (Tienda 3).\n";
    echo "[i] Omitidos por no existir en Tienda 1: $omitidos.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR CRÍTICO]: " . $e->getMessage() . "\n";
    exit(1);
}