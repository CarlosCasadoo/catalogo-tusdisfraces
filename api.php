<?php
// api.php - API REST para la reorganización del catálogo e incertidumbres
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/arbol_exporter.php';
require_once __DIR__ . '/normalizador.php';

// Slug legible: translitera acentos y ñ en vez de sustituirlos por '-' (antes
// "Piñatas cumpleaños" acababa en "pi-atas-cumplea-os") y quita guiones sobrantes
// de los extremos (antes "Payaso (freak)" daba "-payaso-freak-").
function slugify($name) {
    $map = ['Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'];
    return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtr($name, $map)), '-'));
}

try {
    // Ruta ABSOLUTA: con ruta relativa SQLite la resuelve contra el directorio de trabajo
    // del servidor, no contra __DIR__, y la API devuelve "unable to open database" si el
    // proyecto se sirve desde otra carpeta.
    $pdo = new PDO('sqlite:' . __DIR__ . '/catalogo_tusdisfraces.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $action = $_GET['action'] ?? '';

    switch ($action) {

        // 1. OBTENER TODAS LAS CATEGORÍAS EN ESTRUCTURA DE ÁRBOL
        case 'getCategories':
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

            $traverse = function ($catId, $level = 0, $isLast = false, $prefix = '') use (&$traverse, &$orderedList, &$visited, &$catsById, &$childrenMap) {
                if (!isset($catsById[$catId]) || isset($visited[$catId])) return;
                $visited[$catId] = true;

                $cat = $catsById[$catId];
                $cat['tree_level'] = $level;
                $branchSymbol = $level === 0 ? '' : ($isLast ? '└── ' : '├── ');
                $cat['tree_prefix'] = $prefix . $branchSymbol;
                $orderedList[] = $cat;

                if (isset($childrenMap[$catId])) {
                    $children = $childrenMap[$catId];
                    $total = count($children);
                    $newPrefix = $prefix . ($level === 0 ? '' : ($isLast ? '    ' : '│   '));
                    foreach ($children as $idx => $childId) {
                        $traverse($childId, $level + 1, $idx === $total - 1, $newPrefix);
                    }
                }
            };

            // 1. Huérfanos (-1) calculado dinámicamente según productos no asignados a categorías activas
            $stmtOrphans = $pdo->query("SELECT COUNT(*) FROM products WHERE default_category_id NOT IN (SELECT id_category FROM categories) OR default_category_id IS NULL");
            $orphanCount = (int)$stmtOrphans->fetchColumn();

            $orphan = [
                'id_category' => -1,
                'name' => 'Sin Categoría Activa (Huérfanos)',
                'parent_id' => null,
                'depth' => 0,
                'position' => 0,
                'direct_product_count' => $orphanCount,
                'is_new' => 1,
                'tree_level' => 0,
                'tree_prefix' => '⚠️ '
            ];
            $orderedList[] = $orphan;
            $visited[-1] = true;

            // 2. Árbol principal desde Raíz (1) o Inicio (2)
            if (isset($catsById[1])) {
                $traverse(1, 0, true, '');
            } elseif (isset($catsById[2])) {
                $traverse(2, 0, true, '');
            }

            // 3. Ramas separadas del árbol principal
            foreach ($allCategories as $cat) {
                $cId = $cat['id_category'];
                if (!isset($visited[$cId])) {
                    $traverse($cId, 0, true, '');
                }
            }

            echo json_encode(['success' => true, 'data' => $orderedList]);
            break;

        // 2. OBTENER PRODUCTOS PAGINADOS POR CATEGORÍA, FILTRO POR NOMBRE Y FILTRO POR FEATURES
        case 'getProducts':
            // category_id omitido = "todos" (0). Antes el valor por defecto era -1
            // (huérfanos), lo que hacía que un 'tipo' sin categoría se interpretara
            // como "huérfanos ∩ tipo" en vez de "todos los productos de ese tipo".
            $catId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $search = trim($_GET['search'] ?? '');
            $featureSearch = trim($_GET['feature_search'] ?? '');
            $sort = trim($_GET['sort'] ?? '');
            $tipo = isset($_GET['tipo']) && $_GET['tipo'] !== '' ? (int)$_GET['tipo'] : null;
            // category_id=0 significa "todos los productos" (sin filtro de categoría).
            // category_id=-1 conserva su significado histórico: productos huérfanos.
            // subtree=1 amplía el filtro al subárbol completo de la categoría en vez de
            // comparar el ID exacto. Es opt-in para no romper la navegación por niveles.
            $subtree = isset($_GET['subtree']) && $_GET['subtree'] === '1';
            $limit = 50;
            $offset = ($page - 1) * $limit;

            // Ordenación por nombre (ascendente/descendente, sin distinguir mayúsculas)
            $orderBy = '';
            if ($sort === 'name_asc') {
                $orderBy = ' ORDER BY p.name COLLATE NOCASE ASC';
            } elseif ($sort === 'name_desc') {
                $orderBy = ' ORDER BY p.name COLLATE NOCASE DESC';
            }

            // Filtro por tipo físico (categoría secundaria, param 'tipo'):
            //   - Con 'tipo' aislado devuelve TODOS los productos de ese tipo (por secundaria O por
            //     primaria), incluidos los que viven en temáticas. 'category_id' es opcional en este
            //     modo: si se pasa un valor distinto de -1 (huérfanos) y 0 (todos) se intersecta, es
            //     decir, productos de ese tipo DENTRO de esa categoría.
            //   - Sin 'tipo' se mantiene el comportamiento original (filtrar por primaria).
            $cte = '';
            if ($tipo !== null) {
                $stmtCheckTipo = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id_category = :tipo');
                $stmtCheckTipo->execute([':tipo' => $tipo]);
                if ((int)$stmtCheckTipo->fetchColumn() === 0) {
                    throw new Exception('La categoría de tipo indicada no existe.');
                }
                $params = [':tipo' => $tipo];
                if ($subtree) {
                    // Cualquier categoría del subárbol de $tipo cuenta como el mismo tipo.
                    // OJO: :tipo lo usa el CTE, así que debe seguir en $params.
                    $cte = "WITH RECURSIVE arb_tipo(id_category) AS (
                                SELECT :tipo
                                UNION
                                SELECT c.id_category FROM categories c
                                JOIN arb_tipo a ON c.parent_id = a.id_category
                             ) ";
                    $whereSql = "WHERE (p.default_category_id IN (SELECT id_category FROM arb_tipo)
                                        OR EXISTS (SELECT 1 FROM product_categories pc
                                                   WHERE pc.id_product = p.id_product
                                                     AND pc.id_category IN (SELECT id_category FROM arb_tipo)))";
                } else {
                    $whereSql = "WHERE (p.default_category_id = :tipo
                                        OR EXISTS (SELECT 1 FROM product_categories pc
                                                   WHERE pc.id_product = p.id_product AND pc.id_category = :tipoExists))";
                    $params[':tipoExists'] = $tipo;
                }
                if ($catId === -1) {
                    // Huérfanos + un tipo: se intersecta igual que cualquier otra
                    // categoría. Antes esta rama se saltaba y elegir «Huérfanos»
                    // con un tipo devolvía TODOS los productos del tipo.
                    $whereSql .= " AND (p.default_category_id NOT IN (SELECT id_category FROM categories)
                                        OR p.default_category_id IS NULL)";
                } elseif ($catId !== 0) {
                    if ($subtree) {
                        // Coherente con el filtro normal: con subtree=1 la categoría
                        // de origen se interpreta como rama también al cruzar con un
                        // tipo. Antes se comparaba la primaria exacta y se perdían
                        // los productos de las subcategorías (la fuga B reaparecía).
                        $cte .= ", arb_cat(id_category) AS (
                                    SELECT :catId
                                    UNION
                                    SELECT c.id_category FROM categories c
                                    JOIN arb_cat a ON c.parent_id = a.id_category
                                 ) ";
                        $whereSql .= " AND p.default_category_id IN (SELECT id_category FROM arb_cat)";
                    } else {
                        $whereSql .= " AND p.default_category_id = :catId";
                    }
                    $params[':catId'] = $catId;
                }
            } elseif ($catId === 0) {
                // "Todos los productos": sin filtro de categoría.
                $whereSql = '';
                $params = [];
            } elseif ($catId === -1) {
                $whereSql = "WHERE (p.default_category_id NOT IN (SELECT id_category FROM categories) OR p.default_category_id IS NULL)";
                $params = [];
            } elseif ($subtree) {
                $cte = "WITH RECURSIVE arb_cat(id_category) AS (
                            SELECT :catId
                            UNION
                            SELECT c.id_category FROM categories c
                            JOIN arb_cat a ON c.parent_id = a.id_category
                         ) ";
                $whereSql = "WHERE p.default_category_id IN (SELECT id_category FROM arb_cat)";
                $params = [':catId' => $catId];
            } else {
                $whereSql = "WHERE p.default_category_id = :catId";
                $params = [':catId' => $catId];
            }

            // $whereSql puede venir vacía (category_id=0 = todos los productos): en ese caso
            // el primer AND tiene que abrir su propio WHERE o la consulta sería inválida.
            $addCond = function ($cond) use (&$whereSql) {
                $whereSql .= ($whereSql === '' ? 'WHERE ' : ' AND ') . $cond;
            };

            // Búsqueda por texto libre. El término se normaliza (minúsculas, sin
            // tildes ni Ñ, sin espacios múltiples) y se compara contra las
            // columnas *_norm, de modo que da igual cómo se escriba. También se
            // busca por EAN, que antes no era rastreable. Los comodines % y _
            // se escapan con ESCAPE para que «50%» no traiga todo el catálogo.
            if ($search !== '') {
                $patron = patron_like(normalizar_texto($search));
                $campos = [];
                if (columna_normalizada_disponible($pdo, 'products', 'name_norm')) {
                    $campos[] = "p.name_norm LIKE :search ESCAPE '\\'";
                    $campos[] = "p.ref_norm  LIKE :search ESCAPE '\\'";
                } else {
                    // Base de datos sin migrar: se cae a la comparación cruda.
                    $campos[] = "p.name LIKE :search ESCAPE '\\'";
                    $campos[] = "p.reference LIKE :search ESCAPE '\\'";
                }
                // ean13 es numérico: no necesita normalizar, pero sí el ESCAPE.
                $campos[] = "COALESCE(p.ean13, '') LIKE :search ESCAPE '\\'";
                $addCond('(' . implode(' OR ', $campos) . ')');
                $params[':search'] = $patron;
            }

            if ($featureSearch !== '') {
                $patronF = patron_like(normalizar_texto($featureSearch));
                if (columna_normalizada_disponible($pdo, 'feature_values', 'value_norm')) {
                    $colValor = "fv.value_norm LIKE :featSearch ESCAPE '\\'";
                    $colFeature = "f.name_norm  LIKE :featSearch ESCAPE '\\'";
                } else {
                    $colValor  = "fv.value LIKE :featSearch ESCAPE '\\'";
                    $colFeature = "f.name  LIKE :featSearch ESCAPE '\\'";
                }
                $addCond("p.id_product IN (
                    SELECT pfv.id_product 
                    FROM product_feature_values pfv 
                    JOIN feature_values fv ON pfv.id_feature_value = fv.id_feature_value 
                    JOIN features f ON fv.id_feature = f.id_feature 
                    WHERE $colValor OR $colFeature
                )");
                $params[':featSearch'] = $patronF;
            }

            $stmtCount = $pdo->prepare("$cte SELECT COUNT(*) FROM products p $whereSql");
            foreach ($params as $key => $val) {
                $stmtCount->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $totalRecords = (int)$stmtCount->fetchColumn();

            $stmt = $pdo->prepare("$cte
                SELECT p.id_product, p.name, p.reference, p.ean13,
                       ir.familia AS informal_familia,
                       ir.subcategoria AS informal_subcategoria,
                       ir.detalle AS informal_detalle,
                       ir.hoja_por_defecto AS informal_hoja,
                       ir.todas_categorias AS informal_todas
                FROM products p
                LEFT JOIN informal_reference ir ON ir.id_product = p.id_product
                $whereSql$orderBy LIMIT :limit OFFSET :offset");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();

            // Añadir a cada producto su lista de categorías secundarias
            // (filas de product_categories que NO son su categoría principal)
            if ($products) {
                $idsPage = array_column($products, 'id_product');
                $in = implode(',', array_fill(0, count($idsPage), '?'));

                $stmtDef = $pdo->prepare("SELECT id_product, default_category_id FROM products WHERE id_product IN ($in)");
                $stmtDef->execute($idsPage);
                $defMap = [];
                foreach ($stmtDef->fetchAll() as $d) {
                    $defMap[$d['id_product']] = $d['default_category_id'];
                }

                $stmtSec = $pdo->prepare("SELECT pc.id_product, pc.id_category, c.name
                                          FROM product_categories pc
                                          JOIN categories c ON c.id_category = pc.id_category
                                          WHERE pc.id_product IN ($in)
                                          ORDER BY c.name COLLATE NOCASE");
                $stmtSec->execute($idsPage);
                $secPorProducto = [];
                foreach ($stmtSec->fetchAll() as $r) {
                    $esPrincipal = isset($defMap[$r['id_product']]) && $defMap[$r['id_product']] !== null
                        && (int)$defMap[$r['id_product']] === (int)$r['id_category'];
                    if ($esPrincipal) continue;
                    $secPorProducto[$r['id_product']][] = ['id' => (int)$r['id_category'], 'name' => $r['name']];
                }
                foreach ($products as &$p) {
                    $p['secondary_categories'] = $secPorProducto[$p['id_product']] ?? [];
                }
                unset($p);
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $products, 
                'page' => $page,
                'limit' => $limit,
                'total_records' => $totalRecords,
                'total_pages' => max(1, ceil($totalRecords / $limit))
            ]);
            break;

        // 3. MOVER PRODUCTOS EN LOTE
        case 'moveProduct':
            $input = json_decode(file_get_contents('php://input'), true);
            $productIds = $input['product_ids'] ?? [];
            $newCatId = $input['new_category_id'] ?? null;

            if (empty($productIds) || $newCatId === null) {
                throw new Exception("Parámetros incompletos, se requiere product_ids (array) y new_category_id.");
            }

            // Validar que la categoría destino exista (evita filas huérfanas en product_categories)
            $stmtCheckCat = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id_category = :cid');
            $stmtCheckCat->execute([':cid' => $newCatId]);
            if ((int)$stmtCheckCat->fetchColumn() === 0) {
                throw new Exception('La categoría destino no existe.');
            }

            $pdo->beginTransaction();

            $stmtUpdateProd = $pdo->prepare("UPDATE products SET default_category_id = :newCatId WHERE id_product = :productId");

            foreach ($productIds as $productId) {
                $stmtUpdateProd->execute([':newCatId' => $newCatId, ':productId' => $productId]);
                // NO se inserta una fila en product_categories para la nueva primaria: en este
                // modelo la primaria vive sólo en products.default_category_id y las filas de
                // product_categories son siempre secundarias (ver getProductExtraCategories).
                // Insertarla aquí creaba una auto-fila que ningún otro endpoint genera y que
                // luego se devolvía como si fuera una categoría secundaria del producto.
            }

            // Recalculate direct product counts for all categories
            $pdo->exec("UPDATE categories SET direct_product_count = (SELECT COUNT(*) FROM products WHERE products.default_category_id = categories.id_category)");

            $pdo->commit();

            // Helper to fetch products for a given category (including orphans when -1)
            $fetchProducts = function (int $catId) use ($pdo) {
                if ($catId === -1) {
                    $where = "WHERE (p.default_category_id NOT IN (SELECT id_category FROM categories) OR p.default_category_id IS NULL)";
                    $stmt = $pdo->prepare("SELECT p.id_product, p.name, p.reference, p.ean13,
                                           ir.familia AS informal_familia,
                                           ir.subcategoria AS informal_subcategoria,
                                           ir.detalle AS informal_detalle,
                                           ir.hoja_por_defecto AS informal_hoja,
                                           ir.todas_categorias AS informal_todas
                                    FROM products p
                                    LEFT JOIN informal_reference ir ON ir.id_product = p.id_product
                                    $where");
                    $stmt->execute();
                } else {
                    $stmt = $pdo->prepare("SELECT p.id_product, p.name, p.reference, p.ean13,
                                           ir.familia AS informal_familia,
                                           ir.subcategoria AS informal_subcategoria,
                                           ir.detalle AS informal_detalle,
                                           ir.hoja_por_defecto AS informal_hoja,
                                           ir.todas_categorias AS informal_todas
                                    FROM products p
                                    LEFT JOIN informal_reference ir ON ir.id_product = p.id_product
                                    WHERE p.default_category_id = :catId");
                    $stmt->bindValue(':catId', $catId, PDO::PARAM_INT);
                    $stmt->execute();
                }
                return $stmt->fetchAll();
            };

            $updatedNewCategory = $fetchProducts((int)$newCatId);
            $updatedOrphans = $fetchProducts(-1);

            exportarArbolAArchivo($pdo); // el árbol cambia: conteos de categorías

            echo json_encode([
                'success' => true,
                'message' => 'Lote de productos reorganizado correctamente.',
                'data' => [
                    'new_category_id' => $newCatId,
                    'updated_products' => $updatedNewCategory,
                    'orphan_products' => $updatedOrphans
                ]
            ]);
            break;

        // 4. CREAR CATEGORÍA PROVISIONAL (IDs NEGATIVOS DESDE -2)
        case 'createCategory':
            $input = json_decode(file_get_contents('php://input'), true);
            $name = trim($input['name'] ?? '');
            $parentId = isset($input['parent_id']) && $input['parent_id'] !== '' && $input['parent_id'] !== null ? (int)$input['parent_id'] : 2; // Por defecto Inicio (2) para raíz

            if (empty($name)) {
                throw new Exception("El nombre de la categoría es obligatorio.");
            }
            if (mb_strlen($name) > 90) {
                throw new Exception('El nombre no puede superar 90 caracteres.');
            }
            // Evita nombres que se interpretarían como HTML en el front (defensa
            // en profundidad: el front además escapa con escapeHtml()).
            if (preg_match('/[<>]/', $name)) {
                throw new Exception('El nombre no puede contener los caracteres < ni >.');
            }

            $pdo->beginTransaction();

            // Calcular profundidad (depth) según el padre. El padre debe existir:
            // antes, un parent_id inexistente creaba una categoría "colgando".
            $stmtParent = $pdo->prepare("SELECT depth FROM categories WHERE id_category = :pid");
            $stmtParent->execute([':pid' => $parentId]);
            $parentDepth = $stmtParent->fetchColumn();
            if ($parentDepth === false) {
                throw new Exception('La categoría padre no existe.');
            }
            $depth = (int)$parentDepth + 1;

            // Buscar el menor ID negativo para asignar el siguiente (-2, -3, ...)
            $stmtMin = $pdo->query("SELECT MIN(id_category) FROM categories WHERE id_category < 0");
            $minId = $stmtMin->fetchColumn();
            
            $newCategoryId = ($minId !== null && $minId <= -1) ? ($minId - 1) : -2;
            $slug = slugify($name);

            $stmt = $pdo->prepare("INSERT INTO categories (id_category, parent_id, name, slug, position, depth, direct_product_count, is_new) VALUES (:idCat, :parentId, :name, :slug, 0, :depth, 0, 1)");
            $stmt->execute([
                ':idCat' => $newCategoryId,
                ':parentId' => $parentId,
                ':name' => $name,
                ':slug' => $slug,
                ':depth' => $depth
            ]);

            $pdo->commit();

            exportarArbolAArchivo($pdo); // se añadió una categoría
            echo json_encode([
                'success' => true,
                'message' => 'Categoría provisional creada con éxito.',
                'data' => [
                    'id_category' => $newCategoryId,
                    'name' => $name,
                    'parent_id' => $parentId,
                    'depth' => $depth,
                    'direct_product_count' => 0,
                    'is_new' => 1
                ]
            ]);
            break;

        // 5. ELIMINAR CATEGORÍA PROVISIONAL
        case 'deleteCategory':
            $input = json_decode(file_get_contents('php://input'), true);
            $catId = isset($input['category_id']) ? (int)$input['category_id'] : null;

            if ($catId === null) {
                throw new Exception('Parámetro category_id es obligatorio.');
            }

            // Sólo se pueden eliminar categorías provisionales (id < 0)
            if ($catId >= 0) {
                throw new Exception('Solo se pueden eliminar categorías provisionales (ID negativo).');
            }

            // Verificar que la categoría exista
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id_category = :catId');
            $stmtCheck->execute([':catId' => $catId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new Exception('La categoría a eliminar no existe.');
            }

            // No se puede eliminar una categoría con subcategorías: las hijas se
            // quedarían con parent_id apuntando a una categoría que ya no existe
            // (categorías "colgando"). Igual que moveCategory, exigimos que sea hoja.
            $stmtCheckChild = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :catId');
            $stmtCheckChild->execute([':catId' => $catId]);
            $numHijos = (int)$stmtCheckChild->fetchColumn();
            if ($numHijos > 0) {
                throw new Exception("No se puede eliminar una categoría que tiene subcategorías ($numHijos). Elimina o mueve primero las hijas.");
            }

            $pdo->beginTransaction();

            // Reasignar productos a categoría huérfana (default_category_id = NULL)
            $stmtReasign = $pdo->prepare('UPDATE products SET default_category_id = NULL WHERE default_category_id = :catId');
            $stmtReasign->execute([':catId' => $catId]);

            // Eliminar las asignaciones de productos a esa categoría (evita filas huérfanas)
            $stmtDelAssign = $pdo->prepare('DELETE FROM product_categories WHERE id_category = :catId');
            $stmtDelAssign->execute([':catId' => $catId]);

            // Desvincular los casos dudosos que proponían esta categoría.
            // El esquema declara ON DELETE SET NULL sobre uncertain_cases.id_category_proposed,
            // pero SQLite no la aplica porque las claves foráneas están desactivadas por
            // defecto en cada conexión. Sin esto quedan referencias a una categoría inexistente.
            $stmtDelCaso = $pdo->prepare('UPDATE uncertain_cases SET id_category_proposed = NULL WHERE id_category_proposed = :catId');
            $stmtDelCaso->execute([':catId' => $catId]);

            // Eliminar la categoría provisional
            $stmtDel = $pdo->prepare('DELETE FROM categories WHERE id_category = :catId');
            $stmtDel->execute([':catId' => $catId]);

            // Recalcular contador de productos directos de todas las categorías
            $pdo->exec('UPDATE categories SET direct_product_count = (SELECT COUNT(*) FROM products WHERE products.default_category_id = categories.id_category)');

            $pdo->commit();

            exportarArbolAArchivo($pdo); // se eliminó una categoría
            echo json_encode([
                'success' => true,
                'message' => 'Categoría provisional eliminada y sus productos movidos a huérfanos.'
            ]);
            break;

        // 6. MOVER CATEGORÍA HOJA A OTRA RAMA PADRE
        case 'moveCategory':
            $input = json_decode(file_get_contents('php://input'), true);
            $catId = isset($input['category_id']) ? (int)$input['category_id'] : null;
            $newParentId = isset($input['new_parent_id']) ? (int)$input['new_parent_id'] : null;

            if ($catId === null || $newParentId === null) {
                throw new Exception("Parámetros incompletos. Se requiere category_id y new_parent_id.");
            }

            if ($catId === $newParentId) {
                throw new Exception("Una categoría no puede ser padre de sí misma.");
            }

            $categoriasSistema = [1, 2, -1]; // Raíz, Inicio y Huérfanos
            if (in_array($catId, $categoriasSistema, true)) {
                throw new Exception("No está permitido mover categorías del sistema (Raíz 1 / Inicio 2 / Huérfanos -1).");
            }

            if (in_array($newParentId, $categoriasSistema, true)) {
                throw new Exception("No está permitido seleccionar una categoría del sistema como nuevo padre.");
            }

            // Verificar que la categoría a mover exista
            $stmtExists = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id_category = :catId");
            $stmtExists->execute([':catId' => $catId]);
            if ((int)$stmtExists->fetchColumn() === 0) {
                throw new Exception("La categoría a mover no existe.");
            }

            // Validar que la categoría a mover NO tenga hijos (solo hojas)
            $stmtCheckChild = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = :catId");
            $stmtCheckChild->execute([':catId' => $catId]);
            $numHijos = (int)$stmtCheckChild->fetchColumn();

            if ($numHijos > 0) {
                throw new Exception("Solo se pueden mover categorías que no tengan ramas hijas (hojas). Esta categoría tiene $numHijos subcategoría(s).");
            }

            // El nuevo padre debe existir en categories
            $stmtParent = $pdo->prepare("SELECT depth FROM categories WHERE id_category = :pid");
            $stmtParent->execute([':pid' => $newParentId]);
            $parentDepth = $stmtParent->fetchColumn();
            if ($parentDepth === false) {
                throw new Exception("El nuevo padre no existe.");
            }

            $newDepth = (int)$parentDepth + 1;

            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("UPDATE categories SET parent_id = :newParentId, depth = :newDepth WHERE id_category = :catId");
            $stmtUpdate->execute([
                ':newParentId' => $newParentId,
                ':newDepth' => $newDepth,
                ':catId' => $catId
            ]);

            $pdo->commit();

            exportarArbolAArchivo($pdo); // el árbol cambia de estructura
            echo json_encode([
                'success' => true,
                'message' => 'Categoría movida con éxito.',
                'data' => [
                    'id_category' => $catId,
                    'new_parent_id' => $newParentId,
                    'depth' => $newDepth
                ]
            ]);
            break;

        // 7. RENOMBRAR CATEGORÍA PROVISIONAL
        case 'renameCategory':
            $input = json_decode(file_get_contents('php://input'), true);
            $catId = isset($input['category_id']) ? (int)$input['category_id'] : null;
            $name = trim($input['name'] ?? '');

            if ($catId === null) {
                throw new Exception('Parámetro category_id es obligatorio.');
            }
            if (empty($name)) {
                throw new Exception('El nuevo nombre es obligatorio.');
            }
            if (mb_strlen($name) > 90) {
                throw new Exception('El nombre no puede superar 90 caracteres.');
            }
            // Evita nombres que se interpretarían como HTML en el front.
            if (preg_match('/[<>]/', $name)) {
                throw new Exception('El nombre no puede contener los caracteres < ni >.');
            }
            if ($catId >= 0) {
                throw new Exception('Solo se pueden renombrar categorías provisionales (ID negativo).');
            }

            // Verificar que la categoría exista
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id_category = :catId');
            $stmtCheck->execute([':catId' => $catId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new Exception('La categoría a renombrar no existe.');
            }

            $slug = slugify($name);

            $stmt = $pdo->prepare('UPDATE categories SET name = :name, slug = :slug WHERE id_category = :catId');
            $stmt->execute([':name' => $name, ':slug' => $slug, ':catId' => $catId]);

            exportarArbolAArchivo($pdo); // cambia el nombre en el árbol
            echo json_encode([
                'success' => true,
                'message' => 'Categoría provisional renombrada correctamente.',
                'data' => [
                    'id_category' => $catId,
                    'name' => $name
                ]
            ]);
            break;

        // 8. REGISTRAR CASO DUDOSO EN uncertain_cases
        case 'addUncertainCase':
            $input = json_decode(file_get_contents('php://input'), true);
            $idProduct = isset($input['id_product']) ? (int)$input['id_product'] : null;
            $idCatProposed = isset($input['id_category_proposed']) ? (int)$input['id_category_proposed'] : null;
            $reason = trim($input['reason'] ?? '');
            $alternatives = isset($input['alternatives']) ? trim($input['alternatives']) : null;
            $rawData = isset($input['raw_data']) ? json_encode($input['raw_data']) : null;

            if (empty($reason)) {
                throw new Exception("Es obligatorio especificar el motivo de la duda.");
            }

            $stmt = $pdo->prepare("INSERT INTO uncertain_cases (id_product, id_category_proposed, reason, alternatives, raw_data) VALUES (:idProduct, :idCatProposed, :reason, :alternatives, :rawData)");
            $stmt->execute([
                ':idProduct' => $idProduct,
                ':idCatProposed' => $idCatProposed,
                ':reason' => $reason,
                ':alternatives' => $alternatives,
                ':rawData' => $rawData
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Caso dudoso registrado correctamente.'
            ]);
            break;

            // 9. OBTENER CATEGORÍAS SECUNDARIAS DE UN PRODUCTO
            case 'getProductExtraCategories':
                $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
                if ($productId === null) {
                    throw new Exception('Falta el parámetro product_id.');
                }
                // Se excluye la primaria por si quedara alguna auto-fila antigua: en este modelo
            // las filas de product_categories son siempre secundarias.
            $stmt = $pdo->prepare("SELECT pc.id_category FROM product_categories pc JOIN products p ON p.id_product = pc.id_product WHERE pc.id_product = :pid AND pc.id_category <> p.default_category_id");
                $stmt->execute([':pid' => $productId]);
                $extra = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode(['success' => true, 'data' => $extra]);
                break;

            // 10. ASIGNAR CATEGORÍAS SECUNDARIAS A UN PRODUCTO
            case 'addProductCategories':
                $input = json_decode(file_get_contents('php://input'), true);
                $productId = $input['product_id'] ?? null;
                $categoryIds = $input['category_ids'] ?? [];
                if ($productId === null) {
                    throw new Exception('product_id es obligatorio.');
                }
                if (!is_array($categoryIds)) {
                    throw new Exception('category_ids debe ser un array.');
                }
                $pdo->beginTransaction();
                $stmtInsert = $pdo->prepare("INSERT OR IGNORE INTO product_categories (id_product, id_category) VALUES (:pid, :cid)");
                // Ignora la categoría que ya es la principal del producto (evita
                // auto-filas) y las categorías inexistentes (evita filas huérfanas).
                $stmtCatOk = $pdo->prepare('SELECT 1 FROM categories WHERE id_category = :cid');
                $stmtEsPrincipal = $pdo->prepare('SELECT 1 FROM products WHERE id_product = :pid AND default_category_id = :cid');
                foreach ($categoryIds as $cid) {
                    $cid = (int)$cid;
                    $stmtCatOk->execute([':cid' => $cid]);
                    if ($stmtCatOk->fetchColumn() === false) {
                        continue;
                    }
                    $stmtEsPrincipal->execute([':pid' => (int)$productId, ':cid' => $cid]);
                    if ($stmtEsPrincipal->fetchColumn() !== false) {
                        continue;
                    }
                    $stmtInsert->execute([':pid' => (int)$productId, ':cid' => $cid]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Categorías secundarias asignadas.']);
                break;

        // 11. AÑADIR CATEGORÍA SECUNDARIA EN LOTE (sin cambiar default_category_id)
        case 'addSecondaryCategory':
            $input = json_decode(file_get_contents('php://input'), true);
            $productIds = $input['product_ids'] ?? [];
            $categoryId = isset($input['category_id']) ? (int)$input['category_id'] : null;

            if (empty($productIds) || $categoryId === null) {
                throw new Exception("Parámetros incompletos. Se requiere product_ids (array) y category_id.");
            }

            // La categoría debe existir
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id_category = :cid');
            $stmtCheck->execute([':cid' => $categoryId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new Exception('La categoría secundaria no existe.');
            }

            $pdo->beginTransaction();
            $stmtInsert = $pdo->prepare('INSERT OR IGNORE INTO product_categories (id_product, id_category) VALUES (:pid, :cid)');
            // No se añade como secundaria la categoría que ya es la principal del
            // producto: sería una auto-fila que getProducts oculta, pero que
            // reaparecería como secundaria real si el producto se moviera después.
            $stmtEsPrincipal = $pdo->prepare('SELECT 1 FROM products WHERE id_product = :pid AND default_category_id = :cid');
            $asignadas = 0;
            foreach ($productIds as $productId) {
                $stmtEsPrincipal->execute([':pid' => (int)$productId, ':cid' => $categoryId]);
                if ($stmtEsPrincipal->fetchColumn() !== false) {
                    continue;
                }
                $stmtInsert->execute([':pid' => (int)$productId, ':cid' => $categoryId]);
                $asignadas += (int)$stmtInsert->rowCount();
            }
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Categoría secundaria asignada a $asignadas producto(s).",
                'data' => ['asignadas' => $asignadas]
            ]);
            break;

        // 12. QUITAR CATEGORÍA SECUNDARIA EN LOTE (nunca toca la categoría principal)
        case 'removeSecondaryCategory':
            $input = json_decode(file_get_contents('php://input'), true);
            $productIds = $input['product_ids'] ?? [];
            $categoryId = isset($input['category_id']) ? (int)$input['category_id'] : null;

            if (empty($productIds) || $categoryId === null) {
                throw new Exception("Parámetros incompletos. Se requiere product_ids (array) y category_id.");
            }

            // La categoría debe existir
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id_category = :cid');
            $stmtCheck->execute([':cid' => $categoryId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                throw new Exception('La categoría no existe.');
            }

            $pdo->beginTransaction();
            // Si la categoría es la principal del producto, se ignora (no se permite quitar la principal)
            $stmtEsPrincipal = $pdo->prepare('SELECT id_product FROM products WHERE id_product = :pid AND default_category_id = :cid');
            $stmtDel = $pdo->prepare('DELETE FROM product_categories WHERE id_product = :pid AND id_category = :cid');
            $eliminadas = 0;
            foreach ($productIds as $productId) {
                $stmtEsPrincipal->execute([':pid' => (int)$productId, ':cid' => $categoryId]);
                if ($stmtEsPrincipal->fetchColumn() !== false) {
                    continue; // es su categoría principal: no se toca
                }
                $stmtDel->execute([':pid' => (int)$productId, ':cid' => $categoryId]);
                $eliminadas += (int)$stmtDel->rowCount();
            }
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Categoría secundaria quitada de $eliminadas producto(s).",
                'data' => ['removed' => $eliminadas]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida o no especificada.']);
            break;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}