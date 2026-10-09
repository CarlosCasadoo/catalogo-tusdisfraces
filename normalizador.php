<?php
// ════════════════════════════════════════════════════════════════════════════
//  normalizador.php - Normalización de texto para que las búsquedas no fallen
//  por acentos, mayúsculas, Ñ, espacios raremos ni comodines de LIKE.
// ════════════════════════════════════════════════════════════════════════════
//
//  POR QUÉ EXISTE
//  El LIKE de SQLite solo pliega mayúsculas ASCII, así que 'CUMPLEAÑOS' no
//  encontraba nada de los 1.727 productos que se llaman 'Cumpleaños...' y
//  escribir 'cumpleanos' devolvía 8 resultados en vez de 1.727.
//  Además, los comodines % y _ se interpolaban sin escapar en el patrón, de
//  modo que buscar '50%' devolvía los 19.033 productos del catálogo.
//
//  CÓMO FUNCIONA
//  Se guarda una copia ya normalizada de cada texto en una columna *Norm
//  (products.name_norm, products.ref_norm, feature_values.value_norm,
//  features.name_norm) y el término que escribe el usuario se normaliza con la
//  MISMA función antes de compararlo. Así el usuario puede escribir con o sin
//  tildes, con o sin Ñ, en mayúsculas o minúsculas, y con espacios de más.
//
//  POR QUÉ COLUMNAS Y NO COLUMNAS GENERATED
//  SQLite no admite el mapa completo dentro de una expresión GENERATED: el
//  parser aborta con 'parser stack overflow' a partir de unas 30 llamadas a
//  replace() anidadas. Y registrar una función de PHP con sqliteCreateFunction
//  obliga a invocarla una vez por cada fila (≈110 ms por consulta de catálogo).
//  Una columna real es lo más rápido y además se puede indexar en el futuro.
//
//  MANTENIMIENTO
//  Solo importar.php inserta nombres/references/valores (api.php únicamente
//  actualiza products.default_category_id), así que basta con que importar.php
//  rellene estas columnas. Si algún día se añade un endpoint que renombre un
//  producto, hay que volver a normalizar esa fila: lo hace indexar_normas().
//
//  Si las columnas *_norm no existen, api.php cae automáticamente en la
//  comparación sin normalizar (see columna_normalizada_disponible en api.php).
// ════════════════════════════════════════════════════════════════════════════

if (!function_exists('normalizar_texto')) {

    /**
     * Minúsculas, sin tildes, sin Ñ, sin espacios múltiplos y sin espacios
     * "invisibles" (NBSP y companhia). Es idempotente: normalizar(normalizar(x))
     * devuelve lo mismo que normalizar(x).
     *
     * @param  string|null $texto
     * @return string
     */
    function normalizar_texto($texto)
    {
        if ($texto === null) {
            return '';
        }
        $texto = (string)$texto;
        if ($texto === '') {
            return '';
        }

        // 1. Minúsculas Unicode. PHP sí pliega Á->á y Ñ->ñ (SQLite no lo hace),
        //    así que el mapa de abajo solo necesita las claves en minúscula.
        $texto = mb_strtolower($texto, 'UTF-8');

        // 2. Espacios "invisibles" -> espacio normal, para que "cumpleaños\tinfantil"
        //    o "cumpleaños\u{00A0}infantil" se comporten como un espacio normal.
        $texto = strtr($texto, [
            "\u{00A0}" => ' ',  // NBSP: el que Word mete entre «nº» y la cifra
            "\u{2000}" => ' ', "\u{2001}" => ' ', "\u{2002}" => ' ', "\u{2003}" => ' ',
            "\u{2004}" => ' ', "\u{2005}" => ' ', "\u{2006}" => ' ', "\u{2007}" => ' ',
            "\u{2008}" => ' ', "\u{2009}" => ' ', "\u{200A}" => ' ',
            "\u{202F}" => ' ', "\u{205F}" => ' ', "\u{3000}" => ' ',
            "\u{0009}" => ' ', "\u{000A}" => ' ', "\u{000D}" => ' ',
        ]);

        // 3. Tildes y diéresis. El catálogo sólo contiene á é í ó ú ü ñ ç
        //    (16 caracteres no-ASCII en total), pero se cubren también las
        //    variantes que aparecen en nombres importados del extranjero.
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'ý' => 'y', 'ÿ' => 'y',
            'š' => 's', 'ž' => 'z', 'ł' => 'l', 'ą' => 'a', 'ę' => 'e',
            'ć' => 'c', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ř' => 'r',
        ]);

        // 4. Colapsar espacios (390 nombres del catálogo tienen doble espacio).
        $texto = preg_replace('/\s+/u', ' ', $texto);

        return trim($texto);
    }

    /**
     * Envuelve un término ya normalizado en el patrón '%término%' escapando los
     * comodines de LIKE, para poder compararlo con «LIKE ? ESCAPE '\'».
     *
     * @param  string $termino
     * @param  bool   $normalizado true si $termino ya pasó por normalizar_texto().
     * @return string
     */
    function patron_like($termino, $normalizado = true)
    {
        $termino = $normalizado ? (string)$termino : normalizar_texto($termino);
        // Primero la barra invertida: si no, escaparía las propias barras de escape.
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino) . '%';
    }

    /**
     * Comprueba si una columna *_norm está disponible. Permite que api.php
     * funcione (con la búsqueda old-school) contra una base de datos que aún no
     * haya pasado por la migración.
     *
     * @param  PDO    $pdo
     * @param  string $tabla
     * @param  string $columna
     * @return bool
     */
    function columna_normalizada_disponible(PDO $pdo, $tabla, $columna)
    {
        static $cache = [];
        $clave = $tabla . '.' . $columna;
        if (!isset($cache[$clave])) {
            $existe = $pdo->prepare("SELECT 1 FROM pragma_table_info(?) WHERE name = ? LIMIT 1");
            $existe->execute([$tabla, $columna]);
            $cache[$clave] = (bool)$existe->fetchColumn();
        }
        return $cache[$clave];
    }

    /**
     * Rellena (o repara) todas las columnas *_norm de la base de datos.
     * Es idempotente: se puede ejecutar tantas veces como haga falta y también
     * sirve para recuperar una base de datos a la que se le haya cambiado un
     * nombre a mano.
     *
     * @param  PDO    $pdo
     * @param  bool   $verbose
     * @return array  Contadores ['products' => n, 'feature_values' => n, 'features' => n]
     */
    function indexar_normas(PDO $pdo, $verbose = true)
    {
        $resumen = [];

        // OJO: no se usa columna_normalizada_disponible() aquí porque su caché
        // estática podría haberse llenado ANTES del ALTER TABLE ADD COLUMN (la
        // migración añade las columnas y acto seguido las rellena), y entonces
        // se saltaría el relleno sin avisar.
        $tieneColumna = function ($tabla, $col) use ($pdo) {
            $existe = $pdo->prepare("SELECT 1 FROM pragma_table_info(?) WHERE name = ? LIMIT 1");
            $existe->execute([$tabla, $col]);
            return (bool)$existe->fetchColumn();
        };

        // --- products.name_norm y products.ref_norm ---
        if ($tieneColumna('products', 'name_norm') && $tieneColumna('products', 'ref_norm')) {
            $pdo->beginTransaction();
            $filas = $pdo->query('SELECT id_product, name, reference FROM products')->fetchAll();
            $upd = $pdo->prepare('UPDATE products SET name_norm = ?, ref_norm = ? WHERE id_product = ?');
            $n = 0;
            foreach ($filas as $f) {
                $upd->execute([normalizar_texto($f['name']), normalizar_texto($f['reference']), $f['id_product']]);
                $n++;
            }
            $pdo->commit();
            $resumen['products'] = $n;
            if ($verbose) {
                echo "    -> $n productos normalizados.\n";
            }
        } elseif ($verbose) {
            echo "    -> products.name_norm / ref_norm no existen: se omite.\n";
        }

        // --- feature_values.value_norm ---
        if ($tieneColumna('feature_values', 'value_norm')) {
            $pdo->beginTransaction();
            $filas = $pdo->query('SELECT id_feature_value, value FROM feature_values')->fetchAll();
            $upd = $pdo->prepare('UPDATE feature_values SET value_norm = ? WHERE id_feature_value = ?');
            $n = 0;
            foreach ($filas as $f) {
                $upd->execute([normalizar_texto($f['value']), $f['id_feature_value']]);
                $n++;
            }
            $pdo->commit();
            $resumen['feature_values'] = $n;
            if ($verbose) {
                echo "    -> $n valores de característica normalizados.\n";
            }
        } elseif ($verbose) {
            echo "    -> feature_values.value_norm no existe: se omite.\n";
        }

        // --- features.name_norm ---
        if ($tieneColumna('features', 'name_norm')) {
            $pdo->beginTransaction();
            $filas = $pdo->query('SELECT id_feature, name FROM features')->fetchAll();
            $upd = $pdo->prepare('UPDATE features SET name_norm = ? WHERE id_feature = ?');
            $n = 0;
            foreach ($filas as $f) {
                $upd->execute([normalizar_texto($f['name']), $f['id_feature']]);
                $n++;
            }
            $pdo->commit();
            $resumen['features'] = $n;
            if ($verbose) {
                echo "    -> $n características normalizadas.\n";
            }
        } elseif ($verbose) {
            echo "    -> features.name_norm no existe: se omite.\n";
        }

        return $resumen;
    }
}
