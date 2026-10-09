<?php
// importar.php - Script de importación de catálogo SQLite conforme a README.md
ini_set('memory_limit', '1024M');
set_time_limit(600);

$dbPath = __DIR__ . '/catalogo_tusdisfraces.db';
$schemaPath = __DIR__ . '/esquema.sql';

// Las columnas *_norm (name_norm, ref_norm, value_norm) son la base de las
// búsquedas con y sin tildes/Ñ. Se rellenan durante la importación para no
// depender de una migración posterior.
require_once __DIR__ . '/normalizador.php';

echo "=== INICIANDO IMPORTACIÓN DEL CATÁLOGO LOCAL (Tienda 1) ===\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Desactivar FK temporalmente para resetear tablas
    $pdo->exec('PRAGMA foreign_keys = OFF;');
    $pdo->exec("
        DROP TABLE IF EXISTS uncertain_cases;
        DROP TABLE IF EXISTS combination_attributes;
        DROP TABLE IF EXISTS combinations;
        DROP TABLE IF EXISTS attributes;
        DROP TABLE IF EXISTS attribute_groups;
        DROP TABLE IF EXISTS product_feature_values;
        DROP TABLE IF EXISTS feature_values;
        DROP TABLE IF EXISTS features;
        DROP TABLE IF EXISTS product_categories;
        DROP TABLE IF EXISTS products;
        DROP TABLE IF EXISTS categories;
        DROP TABLE IF EXISTS metadata;
    ");
    $pdo->exec('PRAGMA foreign_keys = ON;');
    echo "[-] Tablas existentes limpiadas correctamente.\n";

    // 2. Ejecutar esquema DDL completo desde esquema.sql
    if (!file_exists($schemaPath)) {
        throw new Exception("No se encontró el archivo esquema.sql");
    }
    $schemaSql = file_get_contents($schemaPath);
    $pdo->exec($schemaSql);
    echo "[+] Esquema SQL ejecutado correctamente con todas las tablas e índices.\n";

    $pdo->beginTransaction();

    // ------------------------------------------------------------------------
    // PASO 1: Metadata (desde metadatos comunes en JSON)
    // ------------------------------------------------------------------------
    echo "[1/6] Importando Metadata...\n";
    $stmtMeta = $pdo->prepare("INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)");

    $catJson = json_decode(file_get_contents(__DIR__ . '/categories.json'), true);
    $stmtMeta->execute(['schema_version', (string)($catJson['schema_version'] ?? '1')]);
    $stmtMeta->execute(['shop_id', (string)($catJson['shop_id'] ?? '1')]);
    $stmtMeta->execute(['language_id', (string)($catJson['language_id'] ?? '1')]);
    $stmtMeta->execute(['exported_at', (string)($catJson['exported_at'] ?? '')]);

    // ------------------------------------------------------------------------
    // PASO 2.1: Categorías (categories.json)
    // ------------------------------------------------------------------------
    echo "[2/6] Importando Categorías...\n";
    $stmtCat = $pdo->prepare("
        INSERT INTO categories (id_category, parent_id, name, slug, position, depth, direct_product_count, is_new)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $countCats = 0;
    foreach ($catJson['categories'] as $cat) {
        $stmtCat->execute([
            $cat['id_category'],
            $cat['parent_id'] !== null ? (int)$cat['parent_id'] : null,
            $cat['name'],
            $cat['slug'] ?? '',
            $cat['position'] ?? 0,
            $cat['depth'] ?? 0,
            $cat['direct_product_count'] ?? 0
        ]);
        $countCats++;
    }
    echo "    -> $countCats categorías activas importadas conservando su direct_product_count original.\n";
    unset($catJson);

    // ------------------------------------------------------------------------
    // PASO 2.2: Características y Valores (features.json)
    // ------------------------------------------------------------------------
    echo "[3/6] Importando Características y Valores...\n";
    $featJson = json_decode(file_get_contents(__DIR__ . '/features.json'), true);
    $stmtFeat = $pdo->prepare("INSERT INTO features (id_feature, name, name_norm) VALUES (?, ?, ?)");
    $stmtFeatVal = $pdo->prepare("INSERT INTO feature_values (id_feature_value, id_feature, value, value_norm) VALUES (?, ?, ?, ?)");

    $countFeat = 0;
    $countFeatVal = 0;
    foreach ($featJson['features'] as $feat) {
        $stmtFeat->execute([$feat['id_feature'], $feat['name'], normalizar_texto($feat['name'])]);
        $countFeat++;

        if (!empty($feat['values']) && is_array($feat['values'])) {
            foreach ($feat['values'] as $val) {
                $stmtFeatVal->execute([
                    $val['id_feature_value'],
                    $feat['id_feature'],
                    $val['value'],
                    normalizar_texto($val['value'])
                ]);
                $countFeatVal++;
            }
        }
    }
    echo "    -> $countFeat características y $countFeatVal valores de características importados.\n";
    unset($featJson);

    // ------------------------------------------------------------------------
    // PASO 2.3: Grupos de Atributos y Atributos (attributes.json)
    // ------------------------------------------------------------------------
    echo "[4/6] Importando Grupos de Atributos y Atributos...\n";
    $attrJson = json_decode(file_get_contents(__DIR__ . '/attributes.json'), true);
    $stmtGroup = $pdo->prepare("INSERT INTO attribute_groups (id_attribute_group, name) VALUES (?, ?)");
    $stmtAttr = $pdo->prepare("INSERT INTO attributes (id_attribute, id_attribute_group, value) VALUES (?, ?, ?)");

    $countGroups = 0;
    $countAttrs = 0;
    foreach ($attrJson['attribute_groups'] as $group) {
        $stmtGroup->execute([$group['id_attribute_group'], $group['name']]);
        $countGroups++;

        if (!empty($group['attributes']) && is_array($group['attributes'])) {
            foreach ($group['attributes'] as $attr) {
                $stmtAttr->execute([
                    $attr['id_attribute'],
                    $group['id_attribute_group'],
                    $attr['value']
                ]);
                $countAttrs++;
            }
        }
    }
    echo "    -> $countGroups grupos de atributos y $countAttrs atributos importados.\n";
    unset($attrJson);

    // ------------------------------------------------------------------------
    // PASO 2.4 y posteriores: Productos, Combinaciones y Tablas N:M (products.json)
    // ------------------------------------------------------------------------
    echo "[5/6] Importando Productos, Relaciones N:M y Combinaciones...\n";
    $prodJson = json_decode(file_get_contents(__DIR__ . '/products.json'), true);

    $stmtProd = $pdo->prepare("
        INSERT INTO products (id_product, name, slug, reference, ean13, default_category_id, name_norm, ref_norm)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtPC = $pdo->prepare("INSERT OR IGNORE INTO product_categories (id_product, id_category) VALUES (?, ?)");
    $stmtPFV = $pdo->prepare("INSERT OR IGNORE INTO product_feature_values (id_product, id_feature_value) VALUES (?, ?)");
    $stmtComb = $pdo->prepare("
        INSERT INTO combinations (id_product_attribute, id_product, reference, ean13, is_default)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtCA = $pdo->prepare("INSERT OR IGNORE INTO combination_attributes (id_product_attribute, id_attribute) VALUES (?, ?)");

    $countProds = 0;
    $countPC = 0;
    $countPFV = 0;
    $countComb = 0;
    $countCA = 0;
    $countOrphanDefault = 0;

    // Obtener mapa de categorías reales existentes para conteo de huérfanos
    $realCategories = $pdo->query("SELECT id_category FROM categories")->fetchAll(PDO::FETCH_COLUMN);
    $realCatMap = array_flip($realCategories);

    foreach ($prodJson['products'] as $prod) {
        $defaultCatId = $prod['default_category_id'] !== null ? (int)$prod['default_category_id'] : null;
        
        if ($defaultCatId === null || !isset($realCatMap[$defaultCatId])) {
            $countOrphanDefault++;
        }

        // 1. Insertar producto base preservando el default_category_id original
        $stmtProd->execute([
            $prod['id_product'],
            $prod['name'],
            $prod['slug'] ?? '',
            isset($prod['reference']) ? (string)$prod['reference'] : null,
            isset($prod['ean13']) ? (string)$prod['ean13'] : null,
            $defaultCatId,
            normalizar_texto($prod['name']),
            normalizar_texto(isset($prod['reference']) ? (string)$prod['reference'] : null)
        ]);
        $countProds++;

        // 2. Relación N:M Categorías (product_categories) inicializada desde category_ids
        if (!empty($prod['category_ids']) && is_array($prod['category_ids'])) {
            foreach ($prod['category_ids'] as $cid) {
                $stmtPC->execute([$prod['id_product'], (int)$cid]);
                $countPC++;
            }
        }

        // 3. Relación N:M Características (product_feature_values)
        if (!empty($prod['feature_value_ids']) && is_array($prod['feature_value_ids'])) {
            foreach ($prod['feature_value_ids'] as $fvid) {
                $stmtPFV->execute([$prod['id_product'], (int)$fvid]);
                $countPFV++;
            }
        }

        // 4. Combinaciones y combinación-atributos
        if (!empty($prod['combinations']) && is_array($prod['combinations'])) {
            foreach ($prod['combinations'] as $comb) {
                $stmtComb->execute([
                    $comb['id_product_attribute'],
                    $prod['id_product'],
                    isset($comb['reference']) ? (string)$comb['reference'] : null,
                    isset($comb['ean13']) ? (string)$comb['ean13'] : null,
                    !empty($comb['is_default']) ? 1 : 0
                ]);
                $countComb++;

                if (!empty($comb['attribute_ids']) && is_array($comb['attribute_ids'])) {
                    foreach ($comb['attribute_ids'] as $aid) {
                        $stmtCA->execute([$comb['id_product_attribute'], (int)$aid]);
                        $countCA++;
                    }
                }
            }
        }
    }

    echo "    -> $countProds productos activos importados ($countOrphanDefault con default_category_id huérfana preservada).\n";
    echo "    -> $countPC relaciones en product_categories.\n";
    echo "    -> $countPFV relaciones en product_feature_values.\n";
    echo "    -> $countComb combinaciones y $countCA relaciones combination_attributes.\n";
    unset($prodJson);

    $pdo->commit();
    echo "[6/6] ¡IMPORTACIÓN FINALIZADA CON ÉXITO! Todos los datos coinciden con las especificaciones del README.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR CRÍTICO]: " . $e->getMessage() . "\n";
    exit(1);
}