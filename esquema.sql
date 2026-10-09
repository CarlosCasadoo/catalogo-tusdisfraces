PRAGMA foreign_keys = ON;

-- 1. Metadata
CREATE TABLE IF NOT EXISTS metadata (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- 2. Categorías
CREATE TABLE IF NOT EXISTS categories (
    id_category INTEGER PRIMARY KEY,
    parent_id INTEGER NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    depth INTEGER NOT NULL DEFAULT 0,
    direct_product_count INTEGER NOT NULL DEFAULT 0,
    is_new INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);

-- 3. Productos (Sin FOREIGN KEY en default_category_id para permitir huérfanos)
CREATE TABLE IF NOT EXISTS products (
    id_product INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    reference TEXT NULL,
    ean13 TEXT NULL,
    default_category_id INTEGER NULL,
    -- Copias de name/reference ya normalizadas (minúsculas, sin tildes ni Ñ,
    -- sin espacios múltiples). Las rellena importar.php al importar y se
    -- comparan con "LIKE ? ESCAPE '\'" en api.php, para que la búsqueda
    -- funcione con o sin acentos, con o sin Ñ y en cualquier combinación de
    -- mayúsculas. Ver normalizador.php.
    -- NULL (no NOT NULL) a propósito: permite insertar sin nombrarlas.
    name_norm TEXT NULL,
    ref_norm  TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_products_default_category ON products(default_category_id);

-- 4. Relación N:M Productos - Categorías
CREATE TABLE IF NOT EXISTS product_categories (
    id_product INTEGER NOT NULL,
    id_category INTEGER NOT NULL,
    PRIMARY KEY (id_product, id_category),
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_category) REFERENCES categories(id_category) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pc_product ON product_categories(id_product);
CREATE INDEX IF NOT EXISTS idx_pc_category ON product_categories(id_category);

-- 5. Características
CREATE TABLE IF NOT EXISTS features (
    id_feature INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    -- Nombre normalizado para feature_search (ver normalizador.php).
    name_norm TEXT NULL
);

CREATE TABLE IF NOT EXISTS feature_values (
    id_feature_value INTEGER PRIMARY KEY,
    id_feature INTEGER NOT NULL,
    value TEXT NOT NULL,
    -- Valor normalizado para feature_search (ver normalizador.php).
    value_norm TEXT NULL,
    FOREIGN KEY (id_feature) REFERENCES features(id_feature) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_feature_values_feature ON feature_values(id_feature);

CREATE TABLE IF NOT EXISTS product_feature_values (
    id_product INTEGER NOT NULL,
    id_feature_value INTEGER NOT NULL,
    PRIMARY KEY (id_product, id_feature_value),
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_feature_value) REFERENCES feature_values(id_feature_value) ON DELETE CASCADE
);

-- 6. Grupos de Atributos y Atributos
CREATE TABLE IF NOT EXISTS attribute_groups (
    id_attribute_group INTEGER PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS attributes (
    id_attribute INTEGER PRIMARY KEY,
    id_attribute_group INTEGER NOT NULL,
    value TEXT NOT NULL,
    FOREIGN KEY (id_attribute_group) REFERENCES attribute_groups(id_attribute_group) ON DELETE CASCADE
);

-- 7. Combinaciones
CREATE TABLE IF NOT EXISTS combinations (
    id_product_attribute INTEGER PRIMARY KEY,
    id_product INTEGER NOT NULL,
    reference TEXT NULL,
    ean13 TEXT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS combination_attributes (
    id_product_attribute INTEGER NOT NULL,
    id_attribute INTEGER NOT NULL,
    PRIMARY KEY (id_product_attribute, id_attribute),
    FOREIGN KEY (id_product_attribute) REFERENCES combinations(id_product_attribute) ON DELETE CASCADE,
    FOREIGN KEY (id_attribute) REFERENCES attributes(id_attribute) ON DELETE CASCADE
);

-- 8. Referencia auxiliar: categorias de El Informal (Tienda 3) para el aviso en la interfaz
CREATE TABLE IF NOT EXISTS informal_reference (
    id_product INTEGER PRIMARY KEY,
    reference TEXT NULL,
    familia TEXT NULL,
    subcategoria TEXT NULL,
    detalle TEXT NULL,
    hoja_por_defecto TEXT NULL,
    todas_categorias TEXT NULL,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_informal_reference_product ON informal_reference(id_product);

-- 9. Casos dudosos / Auditoría
CREATE TABLE IF NOT EXISTS uncertain_cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_product INTEGER NULL,
    id_category_proposed INTEGER NULL,
    reason TEXT NOT NULL,
    alternatives TEXT NULL,
    raw_data TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_category_proposed) REFERENCES categories(id_category) ON DELETE SET NULL
);
