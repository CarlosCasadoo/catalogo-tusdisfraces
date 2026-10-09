# Catálogo local — Tienda 1

Copia de trabajo de los productos activos de **Tienda 1** de PrestaShop (español, `shop_id = 1`).
Se usa como entorno local para reorganizar el catálogo antes de replicarlo. **Modificarla no cambia
PrestaShop**; la BD SQLite no tiene conexión con producción.

## Estado actual (2026-10-09)

| Métrica | Valor |
|---|---|
| Productos activos | **19.033** |
| Categorías | **722** (30 reales + 692 provisionales con ID negativo) |
| Filas producto↔categoría (`product_categories`) | **46.539** |
| Casos documentados en `uncertain_cases` | 32 |
| Claves en `metadata` | 9 |
| Profundidad máxima del árbol | 6 |

---

## Archivos y qué hace cada uno

| Archivo | Qué hace |
|---|---|
| `catalogo_tusdisfraces.db` | Base SQLite de trabajo (los `.bak-*` son respaldos puntuales previos a cada cambio). |
| `esquema.sql` | DDL de todas las tablas y sus claves. |
| `importar.php` | Resetea la BD y carga los 4 JSON de Tienda 1. |
| `importar_informal.php` | Carga la consulta de El Informal (Tienda 3) en `informal_reference` (re-importable). |
| `api.php` | API REST consumida por la interfaz (ver `README-front.md`). |
| `normalizador.php` | Normalización de texto compartida (tildes, ñ, mayúsculas, comodines). |
| `index.html` · `app.js` · `styles.css` | Interfaz web de reorganización. |
| `exportar_arbol.php` | Regenera `arbol_categorias.txt` desde la BD. |
| `arbol_categorias.txt` | Vista legible del árbol (mismo formato que el modal de la interfaz). |
| `exportar_prestashop.php` | Genera el paquete `export-prestashop/` a partir de la BD (solo lectura). |
| `export-prestashop/` | Paquete de réplica: `categorias.csv`, `productos.csv`, `productos_categorias.csv` + `guia_replicacion.md`. |
| `products.json` · `categories.json` · `features.json` · `attributes.json` | Exportación original de Tienda 1 (entrada de la tarea). |
| `movimientos_*.csv` · `verificacion_*.txt` · `propuesta_*.md` · `informe_cambios-*.md` | Evidencia de las rondas de reorganización pasadas (ver `HISTORIAL.md`). |
| `consulta_informal.csv` | Consulta de El Informal (Tienda 3) usada como referencia. |

---

## Base de datos — qué hace cada tabla

| Tabla | Papel |
|---|---|
| `categories` | Árbol de categorías. `id_category` negativo + `is_new = 1` = categoría provisional; `parent_id` reconstruye el árbol. |
| `products` | Ficha base: `id_product`, `name`, `reference`, `ean13`, `default_category_id` (la **primaria**) y las copias `*_norm`. |
| `product_categories` | Relación N:M producto↔categoría. Guarda **solo secundarias** (tipo físico + ancestros hasta la rama `3/9/10`); la primaria **no** se replica aquí (`metadata.replica_rule`). |
| `metadata` | Claves del proyecto: `schema_version`, `shop_id`, `language_id`, `exported_at`, `replica_rule`, `modelo_dos_ejes`, `punto2_resultado`, `agrupadores_navegacionales`, `mejora6_tierA_resultado`. |
| `uncertain_cases` | Casos dudosos y decisiones de categorización documentadas (por producto o generales). |
| `features` · `feature_values` · `product_feature_values` | Características que describen el producto (no crean variantes). |
| `attribute_groups` · `attributes` · `combination_attributes` | Grupos (Talla, Color…) y valores que forman combinaciones. |
| `combinations` | Variantes vendibles (`id_product_attribute`) con referencia/EAN propios. |
| `informal_reference` | **Solo lectura**: categoría por defecto en El Informal por `id_product`. |

---

## Puntos importantes

1. **Modelo de dos ejes.** La categoría **primaria** es temática (`products.default_category_id`); las
   **secundarias** son el tipo físico (velas, globos, platos, pelucas…) y sus ancestros hasta la raíz
   `3/9/10`, en `product_categories`. La primaria vive solo en `default_category_id`.
2. **IDs provisionales negativos.** Las categorías nuevas llevan IDs negativos (`is_new = 1`) y **nunca
   se envían a PrestaShop**: quien replica crea la categoría, recibe el ID real y construye el mapa
   `id_provisional → id_real` para traducir las relaciones.
3. **Trabajo realizado** (detalle en `HISTORIAL.md`): reorganización completa por temáticas y tipos
   (Punto 2), menús con >30 hijos aplanados con agrupadores intermedios (Mejora 3: `[18]→5`, `[3]→6`,
   `[-211]→5`, `[9]→4`), slugs únicos y limpios (Mejora 2), categorías vacías/duplicadas eliminadas
   (Mejora 1), trazabilidad en `uncertain_cases`/`metadata` (Mejoras 4+5) y hojas de >100 productos con
   mezcla de tipos subdivididas en 34 subcategorías (Mejora 6 Tier A: `-84`, `-607`, `-105`, `-624`,
   `-74`, `249`).
4. **Exportación a PrestaShop (Punto 4).** `exportar_prestashop.php` genera `export-prestashop/` con los
   **IDs originales** (negativos incluidos). La traducción de IDs la hace la persona que importa; la BD
   no se modifica al exportar.

---

## Documentación relacionada

- `README-front.md` — interfaz web: puesta en marcha, API y funcionalidades.
- `HISTORIAL.md` — historial íntegro del proyecto (README originales, hitos 1-33 y pendientes).