# Guía de replicación a PrestaShop (Punto 4)

> **Qué es este paquete:** exportación **solo lectura** generada desde `catalogo_tusdisfraces.db`
> (`exportar_prestashop.php`). No modifica la base de datos ni contiene una traducción de IDs:
> esa traducción la construye la persona que importa en PrestaShop (ver §2).

---

## 1. Contenido del paquete

| Archivo | Contenido | Filas |
|---|---|---|
| `categorias.csv` | Árbol completo de categorías con **IDs originales** (los provisionales son negativos) | 688 |
| `productos.csv` | Productos con su `default_category_id` original | 19.033 |
| `productos_categorias.csv` | **Todas** las asociaciones producto↔categoría (predeterminada + secundarias `product_categories`) | 65.572 |

**Columnas de `categorias.csv`:**

| Columna | Significado |
|---|---|
| `id_original` | ID en nuestra BD. **Negativo = categoría nueva no existente aún en PrestaShop.** |
| `parent_id_original` | ID original del padre (también puede ser negativo o vacío en la raíz `1`). |
| `nombre` / `slug` | Nombre comercial y su slug `slugify()` (único). |
| `position` / `depth` | Orden entre hermanos y profundidad en el árbol actual. |
| `productos_directos` | Productos con esta categoría como predeterminada. |
| `is_new` | `1` = categoría provisional (ID negativo), `0` = categoría ya existente (ID positivo). |
| `ruta` | Camino completo de nombres desde la raíz (clave estable legible para verificación humana). |

**`productos_categorias.csv`** tiene `es_default` (`1` para la predeterminada, `0` para el resto)
y `origen` (`default` = `products.default_category_id`, `pc` = fila de `product_categories`).
Ningún `id_category_original` del paquete queda sin su categoría en `categorias.csv` (verificado).

---

## 2. Método de traducción (lo hace quien importa)

Los IDs negativos **nunca viajan a PrestaShop** (convención del README). El proceso recomendado:

1. **Crear las categorías por profundidad creciente** (`depth` de 0 hacia arriba): la raíz `1`
   y su hijo `2` son nodos de sistema del catálogo fuente; en PrestaShop se mapean a la raíz
   y a la categoría raíz de la tienda (o se ignoran y se parte de los 6 hijos de `2`).
2. **Al crear cada categoría nueva**, anotar el ID real que PrestaShop asigna y guardar el mapa:
   `id_original → id_real`.
3. **Traducir `parent_id_original`** de cada categoría usando el mapa (los padres ya existen porque
   se crearon antes por profundidad).
4. **Traducir `default_category_id`** de `productos.csv` y el `id_category_original` de
   `productos_categorias.csv` con el mismo mapa.
5. Importar las asociaciones respetando `es_default` (PrestaShop usa la "categoría por defecto"
   del producto y el resto son categorías adicionales).

> Las 30 categorías con `is_new = 0` ya existen en PrestaShop con su ID positivo actual:
> **no se recrean**, solo se usan como destino de las relaciones y como padres donde proceda.

---

## 3. Advertencias

- **No usar `slug` como clave primaria formal**: es único hoy, pero PrestaShop no lo trata como
  identificador canónico; úsalo solo como verificación legible junto a `ruta`.
- **`productos_categorias.csv` incluye la cadena de secundarias** (tipo físico + ancestros hasta la
  rama `3`/`9`/`10`). En PrestaShop, asociar a un ancestro es *adicional*: no es obligatorio replicar
  la cadena completa si la navegación del front la resuelve por el árbol; basta con el tipo físico
  (la hoja) + la primaria. Mantener la cadena intacta es válido pero multiplica filas sin cambiar
  el resultado visible.
- **Este paquete es una instantánea** de la BD viva: si la BD cambia después, regenera con
  `php exportar_prestashop.php` (sobrescribe los CSVs).
- La BD viva conserva **8 claves de `metadata`** y **32 casos en `uncertain_cases`** que documentan
  decisiones de categorización (modelo de dos ejes, regla de réplica primaria, mejoras 1-3):
  consultarlas antes de decidir sobre categorías ambiguas.