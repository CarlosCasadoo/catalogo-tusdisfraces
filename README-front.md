# Catálogo local — Tienda 1 · Parte 2: Interfaz Web

Complementa a `README.md` (parte 1), que describe los datos y el esquema. Esta página documenta la
interfaz web: puesta en marcha, arquitectura y API. El historial detallado de lo ya hecho está en
`HISTORIAL.md`.

## Puesta en marcha

```text
php importar.php                 # resetea y carga los 4 JSON en SQLite
php importar_informal.php        # carga El Informal en informal_reference (opcional, re-importable)
php -S 127.0.0.1:8409            # servidor desde la carpeta del proyecto
```

Abrir `http://127.0.0.1:8409/index.html`.

Requiere **PHP ≥ 8.0** con `pdo_sqlite`; sin dependencias externas. `api.php` abre la BD con ruta
absoluta (`__DIR__`), no con el directorio de trabajo.

## Arquitectura y flujo

```
JSON de Tienda 1 ──► importar.php ──► catalogo_tusdisfraces.db
consulta informal ─► importar_informal.php ─► informal_reference
Browser (index.html + app.js) ── fetch() ──► api.php ◄── BD
```

- El navegador consulta `api.php?action=...` y recibe JSON `{ success, data, ... }`.
- La búsqueda usa las columnas `*_norm` (normalizadas en `normalizador.php`) con `LIKE … ESCAPE '\'`,
  de modo que es insensible a tildes, ñ y mayúsculas, y los comodines `%`/`_` no fugan.
- `getCategories` añade la categoría virtual `-1` «Sin Categoría Activa (Huérfanos)» con un contador
  calculado en tiempo real.

## API REST

Respuestas JSON (`success: true/false`; los errores llevan `error`). Las peticiones POST envían el
cuerpo en JSON (`Content-Type: application/json`).

| Acción | Método | Parámetros | Descripción |
|---|---|---|---|
| `getCategories` | GET | — | Árbol ordenado + nodo virtual de huérfanos. |
| `getProducts` | GET | `category_id` (`0`=todos, `-1`=huérfanos), `tipo` (tipo físico), `subtree` (`1`), `page`, `search`, `feature_search`, `sort` | Productos paginados (50/pág.) con `informal_*` y `secondary_categories`. |
| `moveProduct` | POST | `product_ids[]`, `new_category_id` | Mueve en lote: actualiza primaria, inserta secundarias y recalcula contadores. |
| `createCategory` | POST | `name`, `parent_id` (def. `2`) | Crea categoría provisional (ID negativo). |
| `deleteCategory` | POST | `category_id` (< 0) | Pasa sus productos a huérfanos, limpia referencias y elimina. |
| `moveCategory` | POST | `category_id`, `new_parent_id` | Mueve una categoría **hoja** y recalcula `depth`. |
| `renameCategory` | POST | `category_id`, `name` | Renombra una provisional (nombre + slug). |
| `addUncertainCase` | POST | `reason`, `id_product?`, `id_category_proposed?`, `alternatives?`, `raw_data?` | Registra un caso dudoso. |
| `getProductExtraCategories` | GET | `product_id` | Categorías secundarias de un producto (excluye la primaria). |
| `addProductCategories` | POST | `product_id`, `category_ids[]` | Asigna secundarias a un producto (`INSERT OR IGNORE`). |
| `addSecondaryCategory` | POST | `product_ids[]`, `category_id` | Añade secundaria en lote **sin tocar** `default_category_id`. |
| `removeSecondaryCategory` | POST | `product_ids[]`, `category_id` | Quita secundaria (nunca la primaria). |

### Filtro por tipo físico (`tipo`) y `subtree`

Los productos tienen una **primaria temática** y una **secundaria de tipo físico** (globos, platos,
pelucas…). `tipo=X` lista todos los productos de ese tipo (por secundaria o por primaria); con
`subtree=1` el filtro usa un CTE recursivo y cuenta **toda la rama** en vez del ID exacto (p. ej.
`tipo=3&subtree=1` devuelve los **8.471** productos de la rama Decoración frente a **3.920** del ID
exacto, verificado en la BD actual). La interfaz usa `subtree=1` por defecto y ofrece «Solo directos»
para el modo exacto. `category_id=0` = todo.

## Funcionalidades de la interfaz

- **Panel izquierdo:** filtro de origen (rama con descendientes y selector de tipo), reasignar
  selección (mover productos / añadir secundaria), crear categoría provisional, mover categoría,
  eliminar provisional, renombrar provisional y registro de casos dudosos.
- **Panel derecho:** búsquedas en tiempo real (nombre/referencia/EAN y por característica), ordenación
  por nombre, selección múltiple, paginación, columna «Cat. Informal» con modal de ruta completa y
  chips de categorías secundarias (con ✕ para quitarlas).
- **Modal árbol de categorías** (indentado, iconos 📁/📄/📦, `[Provisional]`, `(sistema)`, `(n)` directos).
  `arbol_categorias.txt` reproduce el mismo formato y se regenera desde `api.php` tras cada cambio.

## Pendientes

- Atender los 32 casos de `uncertain_cases`.
- Revisar los 12 productos «sin tipo» en las ramas `3/9/10` (7 disfraces con primaria `[10]` + 5
  legítimos) y los casos menores `-130` / `-8` / `-7`.
- Mejora 6: re-evaluar hojas con más de 6 hijos tras el Punto 4.