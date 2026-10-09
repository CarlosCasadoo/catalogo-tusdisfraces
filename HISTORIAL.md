# Historial del proyecto

Archivo de antecedentes generado el 2026-10-08 al condensar la documentación.
Conserva **íntegros** los dos documentos originales (antes de la revisión) para no perder ningún detalle:
el trabajo realizado, los hitos 1-33, los esquemas de datos y los pendientes históricos.

- Sección 1 — `README.md` original (Parte 1: datos de exportación, esquema SQLite y tarea de reorganización).
- Sección 2 — `README-front.md` original (Parte 2: interfaz web, API, hitos de cambios y pendientes).

---

# 1. README.md (original)

# Catálogo local — Tienda 1

Copia de trabajo de los productos activos de **Tienda 1** (`id_shop = 1`), en español (`id_lang = 1`).

- Exportado: `2026-09-17T23:24:48+02:00`
- Productos activos: **19033**
- Productos simples: **16333**
- Combinaciones: **9063**
- Categorías activas: **37**

## Archivos

- `products.json`: productos, relaciones y combinaciones. Los productos simples llevan `combinations: []`.
- `categories.json`: categorías activas y relación padre-hijo.
- `features.json`: características de producto y sus valores utilizados.
- `attributes.json`: grupos y valores de atributos utilizados por las combinaciones.
- `categories-tree.md`: vista legible generada a partir de `categories.json`.

## Metadatos comunes

Los cuatro JSON empiezan con estos campos:

- `schema_version`: versión del contrato de datos. Esta exportación utiliza la versión `1`.
- `shop_id`: tienda de PrestaShop de la que proceden los datos. Siempre es `1` en este paquete.
- `language_id`: idioma utilizado para nombres, slugs y valores. `1` corresponde al español.
- `exported_at`: fecha y hora de la extracción, incluyendo la zona horaria.

## `products.json`

Contiene únicamente productos activos en Tienda 1. La propiedad `products` es una lista cuyos elementos incluyen:

- `id_product`: identificador interno del producto en PrestaShop.
- `name`: nombre del producto en español para Tienda 1.
- `slug`: segmento legible utilizado en la URL del producto.
- `reference`: referencia o SKU de la ficha base. Puede ser `null`.
- `ean13`: EAN de la ficha base. Puede ser `null`.
- `default_category_id`: categoría predeterminada configurada en PrestaShop.
- `category_ids`: IDs de todas las categorías activas de Tienda 1 asociadas al producto.
- `feature_value_ids`: IDs de los valores de características que describen el producto.
- `combinations`: variantes vendibles del producto. En productos simples siempre es `[]`.

Cada elemento de `combinations` contiene:

- `id_product_attribute`: identificador interno de la combinación en PrestaShop.
- `reference`: referencia o SKU propio de la combinación. Puede ser `null` y no debe confundirse con la referencia base.
- `ean13`: EAN propio de la combinación. Puede ser `null`.
- `is_default`: `true` cuando es la combinación seleccionada inicialmente.
- `attribute_ids`: IDs de los valores que forman la combinación, por ejemplo `Talla: M` y `Color: Rojo`.

## `categories.json`

La propiedad `categories` contiene las categorías activas asociadas a Tienda 1:

- `id_category`: identificador interno de la categoría.
- `parent_id`: ID de su categoría padre. Permite reconstruir el árbol.
- `name`: nombre visible en español.
- `slug`: segmento legible utilizado en la URL de la categoría.
- `position`: orden de la categoría respecto a sus hermanas.
- `depth`: profundidad registrada en el árbol de PrestaShop.
- `direct_product_count`: cantidad de productos activos asociados directamente a esa categoría. No incluye automáticamente los productos de sus descendientes.

`categories-tree.md` es una visualización de estos mismos datos. No constituye una fuente adicional ni debe editarse por separado.

## `features.json`

Las **características** describen el producto completo y no crean variantes vendibles. Ejemplos habituales son composición, temática o fabricante.

La propiedad `features` contiene:

- `id_feature`: identificador del tipo de característica.
- `name`: nombre del tipo de característica.
- `values`: valores utilizados por los productos exportados.

Cada elemento de `values` contiene:

- `id_feature_value`: identificador del valor.
- `value`: texto visible del valor.

Los productos se relacionan con estos valores mediante `products[].feature_value_ids`.

## `attributes.json`

Los **atributos** forman combinaciones vendibles. Por ejemplo, los grupos `Talla` y `Color` pueden formar la combinación `M + Rojo`.

La propiedad `attribute_groups` contiene:

- `id_attribute_group`: identificador del grupo de atributos.
- `name`: nombre del grupo, por ejemplo `Talla`.
- `attributes`: valores utilizados dentro del grupo.

Cada elemento de `attributes` contiene:

- `id_attribute`: identificador del valor.
- `value`: nombre visible, por ejemplo `M`, `L`, `Rojo` o `Azul`.

Las combinaciones se relacionan con estos valores mediante `products[].combinations[].attribute_ids`.

## Relaciones

- `products[].category_ids` apunta a `categories[].id_category`.
- `products[].default_category_id` conserva la categoría predeterminada de PrestaShop.
- `products[].feature_value_ids` apunta a `features[].values[].id_feature_value`.
- `products[].combinations[].attribute_ids` apunta a `attribute_groups[].attributes[].id_attribute`.

Las **características** describen el producto completo. Los **atributos** (por ejemplo, talla o color) forman combinaciones vendibles.

Al importar en SQLite, estas listas de IDs normalmente se convierten en tablas de relación como `product_categories` y `product_feature_values`. Las combinaciones pueden almacenarse en una tabla propia vinculada mediante `id_product`.

EAN y referencias se almacenan como texto o `null`, nunca como números, para preservar ceros iniciales.

## Observaciones de integridad

- Valores de características sin texto español: **0**. Se conservan como `[Valor ID sin traducción]` para no romper relaciones.
- Definiciones de característica ausentes o sin traducción: **1**. Se conservan por ID.
- Categorías activas cuyo padre no está en el conjunto activo de Tienda 1: **5**. En `categories-tree.md` aparecen como raíces separadas.
- Productos cuya categoría predeterminada no pertenece al árbol activo de Tienda 1: **5600**, repartidos entre **199** IDs de categoría. El ID original se conserva para reflejar el catálogo real.

## Alcance

Esta carpeta es una copia de trabajo. Modificarla no cambia PrestaShop y no debe importarse automáticamente en producción. Los JSON contienen datos de catálogo reales; si se publica el repositorio, debe ser privado salvo revisión previa del contenido.

## Tarea: reorganización de categorías

### Objetivo

El objetivo es importar los cuatro JSON a una base de datos SQLite local y utilizarla como entorno de trabajo para reorganizar el catálogo. Desde SQLite se podrán:

- Mover productos entre categorías.
- Cambiar la categoría predeterminada de cada producto.
- Crear categorías nuevas.
- Reordenar y editar libremente el árbol de categorías de trabajo.
- Preparar una propuesta de cambios que, después de ser revisada, pueda replicarse en la tienda real.

La persona responsable del trabajo tendrá libertad para proponer la organización que considere más clara y útil para el cliente. Tomará como referencia principal el árbol de categorías de **Tienda 3 (El Informal)**, especialmente su forma de agrupar familias, temáticas y subcategorías, pero no deberá copiarlo mecánicamente: Tienda 1 puede tener productos, asociaciones y necesidades diferentes.

Como criterio general deberá:

- Favorecer una navegación comprensible para una persona que busca productos, no una organización basada únicamente en códigos internos.
- Reutilizar categorías existentes cuando sean adecuadas y crear categorías nuevas cuando el catálogo no quede bien representado.
- Mantener relaciones padre-hijo coherentes y evitar categorías duplicadas o con diferencias meramente ortográficas.
- Evitar categorías excesivamente genéricas cuando exista un conjunto claro de productos que justifique una agrupación más específica.
- No crear categorías innecesarias para casos aislados sin explicar el motivo.
- Documentar en `uncertain_cases` cualquier decisión dudosa o cualquier caso en el que existan varias ubicaciones razonables.
- Explicar el motivo de las categorías nuevas y de las reasignaciones realizadas.

La referencia de Tienda 3 es una guía de criterio y estructura, no una fuente que prevalezca automáticamente sobre los datos de Tienda 1.

Todo el trabajo se realiza primero en local. La base SQLite no tendrá conexión con PrestaShop ni podrá modificar producción.

### Esquema SQLite obligatorio

La importación debe crear las siguientes tablas. Los identificadores, EAN y referencias deben conservarse sin transformaciones. En particular, `ean13` y `reference` son `TEXT`, nunca valores numéricos.

#### Cómo se organiza el modelo

El modelo separa cada tipo de información para evitar duplicados. Un producto se guarda una sola vez en `products`, una categoría se guarda una sola vez en `categories` y las relaciones entre ambos se guardan en `product_categories`.

```text
categories ──< product_categories >── products ──< combinations
                                           │             │
                                           │             └──< combination_attributes >── attributes
                                           │                                            │
                                           │                                            └── attribute_groups
                                           │
                                           └──< product_feature_values >── feature_values
                                                                                 │
                                                                                 └── features
```

Los símbolos `──<` indican que una fila puede estar relacionada con varias filas de la tabla siguiente. Por ejemplo:

- Un producto puede pertenecer a varias categorías.
- Una categoría puede contener muchos productos.
- Un producto puede tener varias combinaciones.
- Una combinación puede estar formada por varios atributos, como talla y color.
- Un producto puede tener varios valores de características.

Las tablas de relación (`product_categories`, `product_feature_values` y `combination_attributes`) existen porque sus relaciones son de muchos a muchos. No deben sustituirse por listas de IDs guardadas como texto dentro de una columna.

El orden recomendado de importación es:

1. `metadata`.
2. `categories`, `products`, `features` y `attribute_groups`.
3. `feature_values` y `attributes`.
4. `product_categories` y `product_feature_values`.
5. `combinations`.
6. `combination_attributes`.

La aplicación puede utilizar claves foráneas en todas las relaciones normales. La única excepción inicial es `products.default_category_id`, porque el export contiene categorías predeterminadas huérfanas que deben poder cargarse para ser corregidas.

#### `metadata`

Guarda información sobre el propio export, no sobre productos. Cada fila contiene una pareja clave-valor; por ejemplo, una fila con `key = 'shop_id'` y `value = '1'`. Sirve para comprobar posteriormente de qué tienda, idioma y versión del formato procede la base.

| Columna | Tipo | Uso |
|---|---|---|
| `key` | `TEXT PRIMARY KEY` | Nombre del metadato. |
| `value` | `TEXT NOT NULL` | Valor importado. |

Debe guardar como mínimo `schema_version`, `shop_id`, `language_id` y `exported_at`.

#### `products`

Contiene una fila por producto activo. Guarda los datos propios de la ficha base y su categoría predeterminada de trabajo. No guarda directamente todas sus categorías, características ni combinaciones; esas relaciones viven en tablas separadas.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product` | `INTEGER PRIMARY KEY` | ID real del producto. |
| `name` | `TEXT NOT NULL` | Nombre del producto. |
| `slug` | `TEXT NOT NULL` | Slug original. |
| `reference` | `TEXT NULL` | Referencia de la ficha base. |
| `ean13` | `TEXT NULL` | EAN de la ficha base. |
| `default_category_id` | `INTEGER NULL` | Categoría predeterminada de trabajo. |

Al importar, `default_category_id` recibe el valor original aunque todavía no exista en la tabla `categories`. Precisamente esos valores huérfanos deberán resolverse durante la tarea. Por este motivo, esta columna no tendrá inicialmente una restricción de clave foránea.

#### `categories`

Contiene una fila por categoría real o provisional. El árbol se construye haciendo que `parent_id` apunte al `id_category` de otra fila de esta misma tabla. Una categoría con varios hijos aparecerá una sola vez como categoría y será referenciada como padre por varias filas.

| Columna | Tipo | Uso |
|---|---|---|
| `id_category` | `INTEGER PRIMARY KEY` | ID real o provisional. |
| `parent_id` | `INTEGER NULL` | Categoría padre. |
| `name` | `TEXT NOT NULL` | Nombre de trabajo. |
| `slug` | `TEXT NOT NULL` | Slug de trabajo. |
| `position` | `INTEGER NOT NULL` | Orden entre categorías hermanas. |
| `depth` | `INTEGER NOT NULL` | Profundidad del export original o recalculada para categorías nuevas. |
| `direct_product_count` | `INTEGER NOT NULL` | Conteo informativo procedente del export. |
| `is_new` | `INTEGER NOT NULL DEFAULT 0` | `1` para categorías provisionales; `0` para categorías reales. |

`parent_id` puede apuntar a una categoría real o a otra categoría provisional. La aplicación debe impedir ciclos en el árbol.

#### `product_categories`

Representa la pertenencia de productos a categorías. Cada fila significa «este producto pertenece a esta categoría». Si un producto pertenece a tres categorías, tendrá tres filas en esta tabla.

Esta tabla permite mover o asociar productos sin duplicar la ficha del producto ni guardar arrays dentro de SQLite.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product` | `INTEGER NOT NULL` | Producto relacionado. |
| `id_category` | `INTEGER NOT NULL` | Categoría relacionada. |

La clave primaria es compuesta: `PRIMARY KEY (id_product, id_category)`. Esta tabla representa el estado de trabajo y se inicializa desde `products[].category_ids`.

La categoría indicada en `products.default_category_id` deberá aparecer también en `product_categories` cuando la reorganización de ese producto se dé por terminada.

#### `features`

Contiene los tipos de características aplicables al producto completo, como composición o temática. No contiene todavía sus valores concretos.

| Columna | Tipo | Uso |
|---|---|---|
| `id_feature` | `INTEGER PRIMARY KEY` | Tipo de característica. |
| `name` | `TEXT NOT NULL` | Nombre de la característica. |

#### `feature_values`

Contiene los valores posibles o utilizados de cada característica. `id_feature` indica a qué fila de `features` pertenece cada valor. Por ejemplo, un valor «100% poliéster» podría pertenecer a la característica «Composición».

| Columna | Tipo | Uso |
|---|---|---|
| `id_feature_value` | `INTEGER PRIMARY KEY` | ID del valor. |
| `id_feature` | `INTEGER NOT NULL` | Característica a la que pertenece. |
| `value` | `TEXT NOT NULL` | Texto del valor. |

#### `product_feature_values`

Relaciona productos con valores de características. Cada fila significa «este producto tiene este valor». Un mismo valor puede utilizarse en muchos productos y un producto puede tener varios valores.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product` | `INTEGER NOT NULL` | Producto relacionado. |
| `id_feature_value` | `INTEGER NOT NULL` | Valor relacionado. |

La clave primaria es compuesta: `PRIMARY KEY (id_product, id_feature_value)`.

#### `attribute_groups`

Contiene los grupos que se utilizan para construir variantes, como Talla o Color. Un grupo no es una opción seleccionable por sí mismo; sus opciones se almacenan en `attributes`.

| Columna | Tipo | Uso |
|---|---|---|
| `id_attribute_group` | `INTEGER PRIMARY KEY` | Grupo, por ejemplo Talla. |
| `name` | `TEXT NOT NULL` | Nombre del grupo. |

#### `attributes`

Contiene los valores seleccionables de cada grupo. Por ejemplo, M y L pueden pertenecer al grupo Talla, mientras que Rojo y Azul pueden pertenecer al grupo Color.

| Columna | Tipo | Uso |
|---|---|---|
| `id_attribute` | `INTEGER PRIMARY KEY` | Valor del atributo. |
| `id_attribute_group` | `INTEGER NOT NULL` | Grupo al que pertenece. |
| `value` | `TEXT NOT NULL` | Nombre visible del valor. |

#### `combinations`

Las combinaciones se importan en una tabla separada; no se convierten en productos independientes.

Cada fila representa una variante vendible de un producto. Una combinación pertenece siempre a un único producto, pero puede tener su propia referencia y su propio EAN. Un producto simple no tendrá filas en esta tabla.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product_attribute` | `INTEGER PRIMARY KEY` | ID real de la combinación. |
| `id_product` | `INTEGER NOT NULL` | Producto al que pertenece. |
| `reference` | `TEXT NULL` | Referencia propia. |
| `ean13` | `TEXT NULL` | EAN propio. |
| `is_default` | `INTEGER NOT NULL` | `1` si es la combinación predeterminada; `0` en caso contrario. |

#### `combination_attributes`

Indica qué valores forman cada combinación. Por ejemplo, una combinación puede tener dos filas: una que la relacione con el atributo M y otra con el atributo Rojo. Juntas representan la variante «Talla M + Color Rojo».

| Columna | Tipo | Uso |
|---|---|---|
| `id_product_attribute` | `INTEGER NOT NULL` | Combinación relacionada. |
| `id_attribute` | `INTEGER NOT NULL` | Valor que forma la combinación. |

La clave primaria es compuesta: `PRIMARY KEY (id_product_attribute, id_attribute)`.

Las características, atributos y combinaciones deben importarse para conservar el contexto completo del catálogo, aunque la tarea principal se centre en categorías.

### Categorías nuevas

Las categorías que todavía no existan en PrestaShop utilizarán IDs provisionales negativos:

- Primera categoría nueva: `-1`.
- Siguientes categorías: `-2`, `-3`, `-4`, etc.
- No se reutiliza un ID provisional, aunque se descarte una categoría durante el trabajo.

Estas categorías llevarán `is_new = 1`. Sus `parent_id` podrán ser positivos si dependen de una categoría real o negativos si dependen de otra categoría nueva.

Los IDs negativos nunca se enviarán directamente a PrestaShop. Durante la futura réplica, cada categoría nueva recibirá un ID real y se construirá un mapa `id_provisional → id_real` para traducir las relaciones.

### Prioridad: productos con categoría predeterminada huérfana

La sección **Observaciones de integridad** identifica **5600 productos** cuya `default_category_id` no pertenece al árbol activo de Tienda 1, repartidos entre **199 IDs de categoría**.

Estos productos son la prioridad número uno. Al finalizar la reorganización, cada uno deberá cumplir las dos condiciones siguientes:

1. `products.default_category_id` apunta a una categoría presente en `categories`, ya sea real o provisional.
2. Existe la misma relación en `product_categories`.

Después deben revisarse también los demás productos para detectar asignaciones demasiado genéricas o incorrectas conforme al criterio de categorización descrito en este documento.


---

# 2. README-front.md (original)

# Catálogo local — Tienda 1 · Parte 2: Interfaz Web (front)

Segunda parte del README del proyecto. Esta página documenta **la parte web**: cómo ponerla en marcha paso a paso, cómo funciona por dentro y qué queda pendiente. Complementa a `README.md` (parte 1), que describe los datos de exportación y la tarea de reorganización de categorías.

Archivos de la interfaz:

- `importar.php` — carga los 4 JSON en SQLite (punto de partida de los datos).
- `importar_informal.php` — carga la consulta de El Informal (Tienda 3) como referencia auxiliar.
- `api.php` — API REST en JSON que la interfaz consume.
- `normalizador.php` — normalización de texto compartida por `api.php` e `importar.php` (acentos, Ñ, mayúsculas y comodines de `LIKE`).
- `index.html`, `app.js`, `styles.css` — interfaz de usuario.
- `esquema.sql` — esquema de la base de datos.
- `catalogo_tusdisfraces.db` — base SQLite local de trabajo.

---

## 1. Estado actual

La interfaz web ya funciona de extremo a extremo y permite reorganizar el catálogo:

- Listar el árbol de categorías y los productos de cada categoría (incluidos los huérfanos).
- Buscar productos por nombre/referencia/**EAN** y por características, **sin distinguir tildes, Ñ ni mayúsculas**.
- Mover productos en lote a otra categoría.
- Crear categorías provisionales (IDs negativos) bajo la raíz o como subcategoría.
- Mover categorías hoja a otra rama del árbol.
- Eliminar categorías provisionales.
- Registrar casos dudosos (por producto o generales).
- Mostrar, por cada producto, su categoría por defecto en **El Informal (Tienda 3)** como orientación.

---

## 2. Requisitos

- **PHP ≥ 8.0** en línea de comandos, con la extensión **pdo_sqlite** habilitada. Probado con PHP 8.2.12.
- Un navegador moderno.
- Sin dependencias externas: no se necesita Composer ni base de datos aparte.

> ℹ️ `api.php` abre la base con la ruta **absoluta** `__DIR__ . '/catalogo_tusdisfraces.db'`. Antes usaba la ruta relativa `sqlite:catalogo_tusdisfraces.db`, que SQLite resuelve contra el *directorio de trabajo* del servidor y no contra la carpeta del proyecto: si se lanzaba el servidor desde otro sitio, la API devolvía `unable to open database` y la interfaz se quedaba vacía.

---

## 3. Puesta en marcha (pasos en orden)

### Paso 1 — Importar los datos de Tienda 1

Desde la carpeta del proyecto:

```text
php importar.php
```

Este script **resetea la base de datos** (borra y recrea el esquema) y carga los cuatro JSON: `categories.json`, `features.json`, `attributes.json` y `products.json`. Al terminar quedan listas las tablas `categories`, `products`, `product_categories`, `product_feature_values`, `attribute_groups`, `attributes`, `combinations`, `combination_attributes`, `metadata` y `uncertain_cases`.

> Si lo necesitas, puedes volver a ejecutarlo cuando quieras partir de cero.

### Paso 2 — Cargar la referencia de El Informal (opcional pero recomendado)

```text
php importar_informal.php
```

Lee `consulta informal.csv` (25.203 filas / 18.813 productos de la Tienda 3) y guarda la categoría por defecto y la ruta completa de cada producto en la tabla auxiliar `informal_reference`. El cruce se hace por `id_product` (18.764 de 18.813 productos coinciden con Tienda 1; los 49 restantes se omiten).

Es **re-importable**: el script vacía la tabla antes de cargar. Si ejecutas el Paso 1 después del Paso 2, esta tabla se conserva (no la borra `importar.php`); solo se recrea su contenido si vuelves a lanzar este script.

### Paso 3 — Levantar el servidor

```text
php -S 127.0.0.1:8409
```

Debe ejecutarse dentro de la carpeta del proyecto. También vale cualquier servidor con PHP (XAMPP, Laragon…), siempre que la base esté en la misma carpeta que `api.php`.

### Paso 4 — Abrir la interfaz

En el navegador:

```text
http://127.0.0.1:8409/index.html
```

### Comprobación rápida de la API

```text
# PowerShell
Invoke-RestMethod "http://127.0.0.1:8409/api.php?action=getCategories"
Invoke-RestMethod "http://127.0.0.1:8409/api.php?action=getProducts&category_id=0&page=1"
Invoke-RestMethod "http://127.0.0.1:8409/api.php?action=getProducts&category_id=-1&page=1"
```

`category_id=0` son **todos los productos** (sin filtro de categoría) — es lo que muestra la interfaz al abrir. `category_id=-1` corresponde a los productos **huérfanos** (sin categoría activa o con `default_category_id` fuera del árbol); ahora mismo son 0.

---

## 4. Cómo funciona (arquitectura y flujo de datos)

```
products.json / categories.json / features.json / attributes.json
        │
        └─── importar.php ────────────► catalogo_tusdisfraces.db (esquema.sql)
                                            │
consulta informal.csv                       │
        │                                   │
        └─── importar_informal.php ────► informal_reference
                                            │
Browser (index.html + app.js)               │
        │  fetch()  ◄── JSON ── api.php ◄───┘
        ▼
 DOM (tabla de productos, selectores, modales)
```

1. Los datos llegan a SQLite mediante los scripts de importación (Pasos 1 y 2).
2. El navegador carga `index.html`, que ejecuta `app.js`.
3. `app.js` hace peticiones `fetch()` a `api.php?action=...` y recibe JSON (`{ success, data, ... }`).
4. Con esa respuesta se rellenan los selectores, la tabla de productos, la paginación y los modales.

### Base de datos (vista desde el front)

| Tabla | Papel en la interfaz |
|---|---|
| `categories` | Árbol de categorías (selector de origen, destino, padre y categorías a mover). |
| `products` | Ficha base: `id_product`, `name`, `reference`, `ean13`, `default_category_id`, y las copias normalizadas `name_norm` / `ref_norm` que usa la búsqueda. |
| `product_categories` | Relación N:M producto–categoría (se mantiene al mover productos). |
| `uncertain_cases` | Casos dudosos registrados desde la interfaz. |
| `informal_reference` | **Auxiliar de solo lectura**: categoría de El Informal por `id_product` (`familia`, `subcategoria`, `detalle`, `hoja_por_defecto`, `todas_categorias`). Se usa solo para mostrar información, no participa en la reorganización. |

El resto de tablas del esquema (`features`, `attribute_groups`, `combinations`…) dan contexto y se usan en búsquedas por característica, pero no se manipulan desde la interfaz.

### Búsqueda de texto: cómo se ignoran tildes, Ñ y comodines

`features.name` y `feature_values.value` llevan además `name_norm` y `value_norm`. Las cuatro columnas `*_norm` guardan el texto con las reglas de `normalizador.php`: minúsculas Unicode, sin tildes ni `ñ`, sin espacios múltiples ni NBSP, recortado. El término que escribe el usuario se normaliza con la **misma** función y se compara con `LIKE :param ESCAPE '\'`, de modo que da igual cómo se escriba. Sin esto, el `LIKE` de SQLite (que solo pliega mayúsculas ASCII) devolvía 0 resultados para `CUMPLEAÑOS`, `MUÑECA` o `BEBÉ`, y los comodines `%` y `_` introducidos a mano devolvían el catálogo entero.

### Nodo sintético de huérfanos

`getCategories` añade la categoría virtual `id_category = -1` llamada **«Sin Categoría Activa (Huérfanos)»** con un contador calculado en tiempo real a partir de los productos cuyo `default_category_id` no existe en `categories` (o es `NULL`).

---

## 5. API REST

Todas las respuestas son JSON con `success: true/false`; ante un error se devuelve `{ success: false, error: "mensaje" }`. Las peticiones POST envían el cuerpo en JSON con `Content-Type: application/json`.

| Acción | Método | Parámetros | Descripción |
|---|---|---|---|
| `getCategories` | GET | — | Árbol de categorías ordenado + nodo de huérfanos. |
| `getProducts` | GET | `category_id` (int; `0` = todos, `-1` = huérfanos), `tipo` (int; ID de tipo físico, opcional), `subtree` (`1` = filtrar por la rama completa en vez del ID exacto, opcional), `page` (≥ 1), `search` (texto), `feature_search` (texto), `sort` (`name_asc`/`name_desc`) | Productos paginados (50/página) con los campos `informal_*` de El Informal y `secondary_categories`. Con `tipo` se filtran por categoría secundaria (ver nota abajo). |
| `moveProduct` | POST | `{ "product_ids": [int…], "new_category_id": int }` | Mueve un lote: actualiza `default_category_id`, inserta en `product_categories` y recalcula los contadores. |
| `createCategory` | POST | `{ "name": str, "parent_id": int ·opcional, por defecto 2· }` | Crea categoría provisional con ID negativo (`-2`, `-3`…). |
| `deleteCategory` | POST | `{ "category_id": int < 0 }` | Pasa sus productos a huérfanos y elimina la categoría provisional. |
| `addUncertainCase` | POST | `{ "reason": str, "id_product": int ·opcional·, "id_category_proposed": int ·opcional·, "alternatives": str ·opcional·, "raw_data": obj ·opcional· }` | Registra un caso dudoso. |
| `moveCategory` | POST | `{ "category_id": int, "new_parent_id": int }` | Mueve una categoría **hoja** (sin hijos) a otra rama y recalcula su `depth`. Rechaza las categorías del sistema (`1` Raíz, `2` Inicio y `-1` Huérfanos) como origen o destino, la propia categoría como padre y los padres inexistentes. |

### Filtro por tipo físico (`tipo`) en `getProducts`

Los productos reconstruidos tienen una **categoría secundaria de tipo físico** (velas, globos, platos, disfraces, pelucas…) ortogonal a su temática (primaria). El parámetro `tipo` permite listar **todos los productos de un tipo**, incluidos los que viven en categorías temáticas:

- `tipo=X` aislado → todos los productos cuyo tipo es `X` (por secundaria **o** por primaria, p. ej. `tipo=-5` lista los 2.375 globos aunque su `default_category_id` sea una temática).
- `tipo=X&category_id=Y` → productos de ese tipo **dentro** de la categoría `Y` (intersección; `category_id=-1` y `category_id=0` se ignoran en este modo).
- `tipo=X&subtree=1` → en vez de comparar el ID exacto, cuenta **toda la rama** de `X` (ver nota siguiente).
- `category_id=0` → todos los productos, sin filtro de categoría.
- Combina con `search`, `feature_search` y `sort`.
- Si `X` no existe en `categories`, responde `{ success: false, error: "La categoría de tipo indicada no existe." }`.

Para construir un listado de tipos (p. ej. para un menú de navegación), úsese `getCategories` y quedarse con las raíces de tipo: `id_category = 10` (Disfraces) más los hijos directos de `3` (Decoración) y `9` (Accesorios).

#### `tipo` exacto frente a `tipo&subtree=1`

Por defecto el filtro hace `pc.id_category = :tipo` (o `p.default_category_id = :tipo`), es decir compara el **ID exacto**: `tipo=3` (Decoración, raíz con 35 subcategorías) devuelve 2.563, no 7.123, porque solo cuenta lo etiquetado con el ID `3` exacto y no lo de `Cubiertos`, `Velas`, `Platos`… El valor por defecto se mantiene así para no romper la navegación nivel a nivel del front (el selector de subcategorías hijas).

Añadiendo `subtree=1` el filtro pasa a usar un CTE recursivo y cuenta cualquier categoría del subárbol, tanto por primaria como por secundaria:

| `tipo` | Exacto | `subtree=1` |
|---|---:|---:|
| `3` Decoración | 2.563 | **7.123** |
| `4` HALLOWEEN | 3.201 | 3.201 |
| `5` NAVIDAD | 931 | 931 |
| `6` Cumpleaños y fiestas | 5.491 | 5.491 |
| `9` Accesorios disfraces | 4.117 | **6.234** |
| `10` Disfraces | 3.993 | 3.993 |

`subtree=1` también funciona sin `tipo`: `category_id=4&subtree=1` → 3.201.

#### Las tres capas de secundaria: tipo físico, raíz temática y temática concreta

Además del tipo físico, cada producto lleva **dos** capas de contexto temático como secundaria:

1. **La raíz temática** — HALLOWEEN, NAVIDAD, DECORACIÓN Y ARTÍCULOS DE FIESTA, ACCESORIOS DISFRACES, DISFRACES o CUMPLEAÑOS Y FIESTAS TEMÁTICAS. Regla: toda categoría que cuelgue de `2 Inicio` es raíz temática y se añade como secundaria de los productos de su rama, salvo que ya sea la primaria.
2. **La temática concreta** — el nodo inmediatamente superior a la primaria (`categories.parent_id`), siempre que no sea `1 Raíz` ni `2 Inicio`. Así el disfraz de Iron Man lleva `Disfraz Superhéroes Marvel` y no solo el genérico `DISFRACES`.

`secondary_categories` en la respuesta excluye la primaria, así que el disfraz de Iron Man (primaria `Iron Man`) aparece como:

```json
"secondary_categories": [
  { "id": -238, "name": "Disfraz Superhéroes Marvel" },
  { "id": 10,   "name": "DISFRACES" }
]
```

Quedan 157 productos con cero secundarias: los que tienen como primaria una raíz temática directa y ningún tipo físico (`ACCESORIOS DISFRACES` 93, `CUMPLEAÑOS Y FIESTAS TEMATICAS` 34, `DECORACION` 23, `DISFRACES` 7). Son los que habría que revisar a mano para asignarles tipo. Si se quiere que la regla «todo producto tiene su raíz como subcategoría» se cumpla también para ésos, habría que permitir la fila auto-referencia; hoy está excluida por diseño para no duplicar la primaria.

### Estado de la reconstrucción (a 2026-10-07)

| Métrica | Valor |
|---|---|
| Productos | 19.033 |
| Categorías | 666 (+1 nodo sintético de huérfanos que devuelve `getCategories`) |
| Filas en `product_categories` | 43.009 |
| Productos con ≥1 secundaria | 18.876 (99,2 %) |
| Productos con 2+ secundarias | 15.973 |
| Media de secundarias por producto | 2,26 |
| Filas auto-referencia / duplicadas / inválidas | 0 / 0 / 0 |
| Categorías con `depth` o `direct_product_count` desfasado | 0 |
| Huérfanos / ciclos en el árbol | 0 / 0 |

Nodos añadidos en las últimas rondas (primaria = producto con esa categoría como `default_category_id`; secundaria = fila en `product_categories`):

| ID | Nombre | Padre | Como primaria | Como secundaria | Total |
|---|---|---|---:|---:|---:|
| `-618` | Calaveras y cráneos | `3` Decoración | 0 | 21 | 21 |
| `-619` | Telarañas y telas de araña | `3` Decoración | 0 | 23 | 23 |
| `-620` | Lápidas y cementerios | `3` Decoración | 0 | 24 | 24 |
| `-621` | Manos y miembros de monstruo | `9` Accesorios disfraces | 0 | 17 | 17 |
| `-622` | Corsés y lencería | `-440` Vestuario y prendas | 0 | 13 | 13 |
| `-625` | Decoración colgante (techo, rack y pared) | `3` Decoración | 30 | 110 | 140 |
| `-623` | Freddy Krueger (Pesadilla en Elm Street) | `-135` Disfraz terror cine y series | 26 | 0 | 26 |
| `-624` | Decoración Halloween | `-80` Disfraz Halloween | 153 | 0 | 153 |
| `-626` | Disfraz Cazafantasmas (Ghostbusters) | `-135` Disfraz terror cine y series | 8 | 0 | 8 |
| `-627` | Máscaras de payaso | `-129` Mascaras completas | 45 | 0 | 45 |
| `-628` | Máscaras de terror de cine y series | `-129` Mascaras completas | 79 | 0 | 79 |
| `-629` | Máscaras La Purga | `-129` Mascaras completas | 32 | 0 | 32 |
| `-630` | Máscaras Scream (Ghost Face) | `-129` Mascaras completas | 25 | 0 | 25 |
| `-631` | Máscaras de calaveras y catrinas | `-129` Mascaras completas | 20 | 0 | 20 |
| `-632` | Máscaras de brujas y demonios | `-129` Mascaras completas | 20 | 0 | 20 |
| `-633` | Máscaras de zombis | `-129` Mascaras completas | 15 | 0 | 15 |
| `-634` | Máscaras de animales | `-129` Mascaras completas | 13 | 0 | 13 |
| `-635` | Máscaras de superhéroes y cómic | `-129` Mascaras completas | 20 | 0 | 20 |
| `-636` | Máscaras de monstruos clásicos | `-129` Mascaras completas | 9 | 0 | 9 |
| `-637` | Máscaras de fantasmas | `-129` Mascaras completas | 5 | 0 | 5 |
| `-638` | Globos Patrulla Canina | `-32` Cumpleaños Patrulla canina | 17 | 0 | 17 |
| `-639` | Vajilla y mesa Patrulla Canina | `-32` Cumpleaños Patrulla canina | 42 | 0 | 42 |
| `-640` | Decoración y guirnaldas Patrulla Canina | `-32` Cumpleaños Patrulla canina | 10 | 0 | 10 |
| `-641` | Piñatas y fiesta Patrulla Canina | `-32` Cumpleaños Patrulla canina | 12 | 0 | 12 |
| `-642` | Ropa infantil Patrulla Canina | `-32` Cumpleaños Patrulla canina | 54 | 0 | 54 |
| `-643` | Complementos Patrulla Canina | `-32` Cumpleaños Patrulla canina | 19 | 0 | 19 |
| `-644` | Globos Mickey Mouse | `15` Cumpleaños Mickey Mouse | 31 | 0 | 31 |
| `-645` | Vajilla y mesa Mickey Mouse | `15` Cumpleaños Mickey Mouse | 47 | 0 | 47 |
| `-646` | Decoración y guirnaldas Mickey Mouse | `15` Cumpleaños Mickey Mouse | 19 | 0 | 19 |
| `-647` | Piñatas y fiesta Mickey Mouse | `15` Cumpleaños Mickey Mouse | 24 | 0 | 24 |
| `-648` | Ropa infantil Mickey Mouse | `15` Cumpleaños Mickey Mouse | 35 | 0 | 35 |
| `-649` | Complementos Mickey Mouse | `15` Cumpleaños Mickey Mouse | 17 | 0 | 17 |
| `-650` | Globos Primera Comunión | `-37` Primera Comunión | 57 | 0 | 57 |
| `-651` | Decoración y guirnaldas Primera Comunión | `-37` Primera Comunión | 30 | 0 | 30 |
| `-652` | Letras y letreros Primera Comunión | `-37` Primera Comunión | 57 | 0 | 57 |
| `-653` | Vajilla y mesa Primera Comunión | `-37` Primera Comunión | 22 | 0 | 22 |
| `-654` | Piñatas y toppers Primera Comunión | `-37` Primera Comunión | 18 | 0 | 18 |
| `-655` | Figuras Primera Comunión | `-37` Primera Comunión | 30 | 0 | 30 |
| `-656` | Fotos y papelería Primera Comunión | `-37` Primera Comunión | 61 | 0 | 61 |
| `-657` | Bolsas y cajas Primera Comunión | `-37` Primera Comunión | 65 | 0 | 65 |
| `-658` | Detalles y regalos Primera Comunión | `-37` Primera Comunión | 20 | 0 | 20 |

`-638`…`-649` nacieron pobladas (primaria = tipo de producto dentro del tema); como su padre es temático y no de tipo, sus productos no ganan nodo de tipo y el eje `[3]/[9]/[10]` sigue intacto. `-32` y `15` pasaron a ser **contenedores**: 0 y 2 productos directos, 154 y 173 filas como secundaria y 6 hijas cada uno.

`-650`…`-658` son las 9 hijas con las que se dividió `-37 Primera Comunión` (hito 31): `-37` quedó en **0 directos** con **360 en rama** (los 343 de siempre + 17 que estaban repartidos por otras ramas) y cada hija recibe como secundarias `-37` → `-207 Fiestas especiales` → `6 CUMPLEAÑOS Y FIESTAS TEMATICAS`, además de su nodo de tipo si lo tenía (`-5 Globos`, `-229 Letras y letreros`, `-4 Guirnaldas`…).

`-627`…`-637` son las 11 subcategorías con las que se dividió `-129 Mascaras completas` (344 → 61 genéricas + 283 repartidas); cada una hereda como secundarias `-129` → `-128` → `[4] HALLOWEEN`, y los productos temáticos añaden también la capa de la raíz temática (p. ej. `-71 Disfraz Payaso` en las máscaras IT).

Categorías **retiradas** en esta ronda:

| ID | Nombre | Motivo |
|---|---|---|
| `-11` | Colgantes | Mezclaba bisutería de collar (7) con decoración colgante de techo/rack/pared (110). Se partió en `-625 Decoración colgante` y `-571 Colgantes`, bajo `-488 Collares y cadenas`. |
| `-462`, `-428`, `-411` | Bruja y Halloween (×3) | El mismo nombre repetido en tres ramas de tipo distintas. Los 24 productos se unificaron en `-85 Accesorios Bruja/o`, conservando su tipo físico (panty, máscara, peluca) y la raíz HALLOWEEN como secundarias. |
| `312` | Cubiertos | Duplicaba a `266 Cubiertos`; estaba vacía. |
| `258` | Cumpleaños Mickey | Duplicaba a `15 Cumpleaños Mickey Mouse`; estaba vacía. |
| `270` | Fiesta fubol | Duplicaba a `241 Cumpleaños futbl`; estaba vacía. |
| `239` | Fiesta blanco | Los colores ya viven en `-59 Fiesta colores`; estaba vacía. |
| `259` | Accesorios cumpleaños | Su padre `-211 Material de fiesta` ya es el cajón; estaba vacía. |
| `12` + `227` | TALLER PERSONALIZACION + AM | Padre e hija, ambos vacíos. |

Renombradas:

- `[-10] Collares` → **Collares de fiesta y leis**. Sus 11 productos son collares de papel, plástico y leis de cotillón, **no joyería**, así que no se fusionaron con `[-570] Collares` (que sí es bisutería): era una colisión de nombre, no un error de sitio.
- `[-483] Narices y prótesis` → **Dientes, colmillos y prótesis faciales**. Su contenido real es dientes (46), colmillos (18), nariz (18), orejas (17), dentaduras (6) y aureolas (4). Se descartó «Prótesis y efectos especiales» porque duplicaba `-580` y `-133`.
- `327 Paletas y sets de maquillaje` dejó de ser raíz temática bajo `2 Inicio` y pasó a ser hija de `247 Maquillaje`.
- Los 28 nodos cuyo nombre empezaba en minúscula (`plata`, `confeti`, `pijama`, `fiesta 30 cumpleaños`, `topper`…) se normalizaron a mayúscula inicial, con su `slug` regenerado.

Las 6 temáticas que se decidió **conservar** (son temas reales) están **ya pobladas** desde el 2026-10-07: `-587` Casino (9), `-588` Oeste (25), `-590` Orgullo (30), `-591` Graduación (23), `-592` Baloncesto (7), `-593` Caballos (7). Los productos se tomaron de la referencia de El Informal (asignación temática) y se conservó la primaria anterior (tipo físico) más el contexto de rama como secundarias (ver §8).


### `getProducts` y los campos `informal_*`

Además de los datos del producto, cada fila incluye la categoría de El Informal mediante un `LEFT JOIN` sobre `informal_reference`:

```json
{
  "id_product": 48183,
  "name": "Figura niño de comunion informal 16 cm",
  "reference": "315118DE",
  "ean13": "8435599756892",
  "informal_familia": "CUMPLEAÑOS Y FIESTAS TEMATICAS",
  "informal_subcategoria": "COMUNION",
  "informal_detalle": "Figuras comunion",
  "informal_hoja": "Figuras comunion",
  "informal_todas": "AM | Figuras comunion"
}
```

Los campos vienen `null` cuando el producto no aparece en la consulta de El Informal.

---

## 6. Funcionalidades de la interfaz

### Panel izquierdo

El panel es **fijo (sticky)**: se queda visible mientras te desplazas por la lista de productos de la derecha; si su contenido no cabe en pantalla, gana su propio scroll interno.

1. **Filtrar origen** — Buscador por nombre con sugerencias **y** selector con el árbol de categorías (elegir una sugerencia fija el selector de origen, actualiza la rama hija y recarga los productos). El origen se interpreta como **rama completa** (categoría + descendientes); si la categoría elegida tiene descendientes, aparece un segundo selector que permite elegir un descendiente concreto o el modo **«📄 Solo directos»** (categoría exacta, sin subcategorías). Los contadores de los selectores muestran `(directos · productos en rama)` cuando ambos difieren. Además, el **selector de tipo** (eje secundario: ramas Decoración / Accesorios / Disfraces) filtra por `tipo=`, de modo que también aparecen los productos cuya categoría principal está en otra rama pero tienen ese tipo como categoría secundaria.
2. **Reasignar selección** — Buscador por nombre de categoría destino con sugerencias, un selector de destino y el botón **«Mover Productos»** (aplica a todos los productos marcados).
3. **Nueva categoría** — Permite elegir la ubicación (raíz bajo *Inicio* o como hija de una rama) y crear la categoría provisional.
4. **Mover categoría** — Mover una categoría hoja a otra rama.
5. **Eliminar categoría** — Sección propia: eliminar categorías provisionales (el botón se activa al seleccionar una).
6. **Gestión de incertidumbres** — Botón **«Registrar Caso Dudoso»** para dudas generales o de lote.

### Panel derecho (productos)

- **Búsquedas** en tiempo real: por nombre/referencia y por característica (ej. `Halloween`, `Adulto`, `Látex`), con debounce de 250 ms.
- **Ordenación**: clic en el encabezado «Nombre» de la tabla alterna A → Z / Z → A en cada pulsación (▲/▼ indican el orden activo; `api.php` añade `ORDER BY name COLLATE NOCASE`; cambiar el orden vuelve a la página 1).
- **Tabla** con: checkbox de selección (y «seleccionar todos»), ID, nombre, referencia, **Cat. Informal** y acciones por fila.
- **Acciones por fila:** «Marcar Dudoso» (pide motivo y alternativas) y «Copiar Ref» (copia al portapapeles con aviso _toast_).
- **Paginación**: 50 productos por página.
- **Clic en la fila**: marca/desmarca el checkbox (excepto al pulsar botones o la referencia).

### Columna «Cat. Informal» y aviso al pulsar la referencia

La columna muestra la **hoja por defecto** del producto en El Informal (o `—` si no hay registro).

Al **pulsar la referencia** se abre un modal con:

- nombre y referencia del producto;
- ruta completa: **Nivel 1 (Familia) → Nivel 2 (Subcategoría) → Nivel 3 (Detalle)**;
- **📌 Hoja por defecto** destacada;
- **Todas las categorías asignadas** en El Informal (separadas por `|`).

Se cierra con el botón ×, haciendo clic fuera o con la tecla `Escape`. El texto se escapa (`escapeHtml`) para evitar inyección de HTML.

### Árbol de categorías (modal)

Botón **«🌳 Ver árbol de categorías»** en la parte superior del panel izquierdo. Abre un modal grande con el árbol completo y claro:

- indentación por nivel e iconos 📁 (con subcategorías) / 📄 (sin) / 📦 (huérfanos);
- etiqueta **[Provisional]** y marca **(sistema)** para las categorías del sistema;
- nº de productos directos por categoría `(n)`;
- resumen con totales (categorías, provisionales y productos huérfanos).

Se cierra con el botón ×, clic fuera o `Escape`.

### Avisos (toast)

Las confirmaciones de éxito, los errores y los mensajes de validación se muestran como notificaciones **toast** en la esquina superior derecha que desaparecen solas (éxito ≈ 3 s, error ≈ 5 s, información ≈ 3,5 s), sin botón de aceptar. Solo las acciones que piden una decisión o texto al usuario (`confirm`/`prompt`) siguen usando los diálogos nativos.

Detalle de usabilidad: al enfocar **cualquier buscador** que ya tenga texto (origen, destino o filtros de la tabla de productos), todo el contenido queda seleccionado para poder borrarlo o sobrescribirlo directamente con una sola pulsación.

---

## 7. Lo que se ha hecho hasta ahora

1. **Importación Tienda 1 → SQLite** (`importar.php` + `esquema.sql`): esquema conforme a la parte 1 del README, con datos reales (19.033 productos activos, combinaciones, características, atributos y categorías).
2. **API REST base** (`api.php`): categorías en árbol, productos paginados con búsqueda por nombre/referencia y por características, mover productos en lote, crear/eliminar categorías provisionales y registrar casos dudosos.
3. **Interfaz de dos paneles** (`index.html`, `app.js`, `styles.css`): selector de origen con descendientes, buscador de destino, selección múltiple, paginación y los botones de las secciones 1 a 5 de la parte 1 del README.
4. **Referencia de El Informal**: análisis del cruce por `id_product` (18.764 coincidencias de 18.813), tabla auxiliar `informal_reference` y script re-importable `importar_informal.php`.
5. **Interfaz informal**: columna «Cat. Informal» en la tabla y modal con la ruta completa al pulsar la referencia.
6. **`moveCategory` activo** (`api.php`): el `case` quedó implementado y validado (el código que antes estaba muerto tras `deleteCategory`). Se corrigió además la comprobación de categorías del sistema: antes `id <= 2` bloqueaba también a las provisionales de ID negativo (`-2`, `-3`…); ahora solo se rechazan las reales (`1`, `2`, `-1`), en `api.php` y en `app.js`.
7. **Avisos toast**: los `alert()` de confirmación y error se sustituyeron por notificaciones auto-desaparecientes (verde éxito, rojo error, neutro información) apiladas arriba a la derecha.
8. **Buscador de categoría de origen**: caja de búsqueda por nombre con sugerencias en la sección «Filtrar Origen»; elegir una sugerencia fija el selector, actualiza la rama hija y recarga los productos. (Se corrigió además el bucle del botón «Eliminar Categoría Provisional», que re-enlazaba su manejador en cada refresco.)
9. **Ordenación por nombre**: con un clic en el encabezado «Nombre» de la tabla se alterna A → Z / Z → A (indicador ▲/▼ en el encabezado), implementado en `api.php` (parámetro `sort` con `ORDER BY name COLLATE NOCASE`) y en `app.js`.
10. **Árbol de categorías (modal)**: botón «🌳 Ver árbol de categorías» que abre el árbol completo en un modal grande (indentado, con iconos, provisionales, sistema y nº de productos directos).
11. **Documentación**: este `README-front.md` como segunda parte del README.
12. **Diagnóstico y saneamiento de datos**: revisión integral de la BD y de todos los endpoints (batería de pruebas funcionales, 0 fallos) con las siguientes correcciones:
    - `api.php` (`deleteCategory`): ahora borra también las asignaciones en `product_categories` de la categoría eliminada (antes quedaban filas huérfanas apuntando a categorías borradas);
    - `api.php` (`moveProduct`): valida que la categoría destino exista antes de mover (evita crear filas huérfanas por un destino inválido);
    - datos: eliminadas 54 filas huérfanas de `product_categories` (referencias a categorías borradas, incluida la pseudo-categoría `-1`); corregido el `parent_id` de Raíz (`0` → `NULL`); rellenadas las 895 filas por defecto faltantes en `product_categories` (insertadas donde faltaban); asignado nombre a 2 productos con nombre vacío (usando su referencia);
    - verificación posterior: `PRAGMA integrity_check` OK, 0 violaciones de clave foránea, 0 padres rotos, 0 filas huérfanas, `depth` coherente en las 129 categorías;
    - `styles.css`: añadidas la clase `.btn-copy` (el botón «Copiar Ref» quedaba con el estilo azul por defecto) y el estilo de `.check-producto` (checkbox de la tabla);
    - Respaldado el estado previo en `catalogo_tusdisfraces.db.bak-diag` (13,2 MB) por si se quiere revertir.
13. **Editar nombre de categoría provisional** (sección 7 del panel izquierdo, tras «Gestión de Incertidumbres»): desplegable con las categorías provisionales (ID negativo) y campo de texto que se rellena con el nombre actual (seleccionándolo al enfocar); al guardar se valida (no vacío, máx. 90 caracteres, nombre distinto del actual) y se confirma con `confirm()`. Se llama al nuevo endpoint `renameCategory` de `api.php`, que actualiza `name` y `slug` y devuelve un toast. Añadido el estilo `.btn-edit` para el botón «Guardar Nombre».
14. **Categoría secundaria** (en la sección 2 «Reasignar Selección»): botón «Añadir como Categoría Secundaria» que asigna la categoría elegida como secundaria a los productos seleccionados. Usa el nuevo endpoint en lote `addSecondaryCategory` de `api.php`, que inserta filas en `product_categories` con `INSERT OR IGNORE` **sin tocar `default_category_id`** (por eso no cambian ni la lista de productos ni el contador `direct_product_count`, que solo cuenta las categorías por defecto). El endpoint valida que la categoría exista y devuelve cuántas asignaciones nuevas se han hecho (las repetidas se ignoran). Al eliminar una categoría provisional, sus filas secundarias se borran junto con ella (mismo `DELETE FROM product_categories` ya añadido en el hito 12). Añadido el estilo `.btn-secundaria`.
15. **Visualización y gestión de categorías secundarias**: nueva columna «Cat. Secundarias» en la tabla de productos con las categorías secundarias de cada producto (excluyendo su categoría principal), mostradas apiladas una debajo de otra ocupando el ancho de la celda para que el nombre se lea completo.
16. **Paginación superior**: barra compacta (`.pagination-top`, alineada a la derecha) sobre la tabla de productos con los botones **«← Anterior»** y **«Siguiente →»**, con el mismo comportamiento y estados deshabilitados (primera/última página) que los botones inferiores.
17. **Retoques visuales**: los botones de acción por fila «Marcar Dudoso» y «Copiar Ref» usan la variante compacta `.btn-mini` (padding y fuente menores, `min-width` eliminado) para que la fila respire; y el modal del árbol de categorías se agranda (hasta `1080px` de ancho x `92vh` de alto, contenido hasta `76vh`), con mayor especificidad (`modal-overlay .modal.modal-tree`) porque la clase base `.modal` lo limitaba a 520px en la cascada.
18. **Árbol de categorías en archivo (`arbol_categorias.txt`)**: se guarda en la carpeta del proyecto un fichero de texto plano (UTF-8) con el árbol de categorías **en el mismo formato que muestra el modal de la interfaz** (📁/📄/📦, `[Provisional]`, `(sistema)`, `(n)` productos directos, mismo orden `position ASC, name ASC` y mismo recorrido desde Raíz). Nueva librería `arbol_exporter.php` (función `generarArbolTexto` + `exportarArbolAArchivo`). **Se regenera automáticamente** desde `api.php` tras cada acción que cambie el árbol: `createCategory`, `renameCategory`, `moveCategory`, `deleteCategory` y `moveProduct` (los cambios de productos secundarios no lo alteran). Para regenerarlo a mano: `php exportar_arbol.php`. `getProducts` de `api.php` devuelve ahora `secondary_categories` (array de `{id, name}`) calculado a partir de `product_categories`. Cada chip tiene un botón «✕» para quitar esa asignación mediante el nuevo endpoint `removeSecondaryCategory`, que nunca permite borrar la categoría principal (si el `category_id` coincide con el `default_category_id` del producto, se ignora) y devuelve cuántas asignaciones se han quitado. Estilos `.col-secundarias`, `.chip-secundaria` y `.chip-remove`.
19. **Modelo de dos ejes consolidado**: la **primaria** es temática y la **secundaria** es el tipo físico canónico, más las capas de contexto temático (raíz temática + temática concreta). Cada producto lleva de media 2,19 secundarias. `secondary_categories` excluía la primaria, así que los 164 productos cuya primaria es una raíz temática directa (`ACCESORIOS DISFRACES`, `CUMPLEAÑOS Y FIESTAS TEMATICAS`, `DECORACION`, `DISFRACES`) aparecen sin chips: son los que quedan por revisar a mano para asignarles tipo.
20. **`subtree=1` en `getProducts`**: nuevo parámetro opcional que hace que `category_id` y `tipo` comparen **toda la rama** en vez del ID exacto, mediante un CTE recursivo. Sin él, `tipo=3` devolvía 2.563 (solo lo etiquetado con el ID `3`) en lugar de los 7.123 productos de la rama Decoración. Con `tipo` inexistente responde `{ success: false, error: "La categoría de tipo indicada no existe." }`.
21. **`category_id=0` = todos los productos**: valor nuevo para listar el catálogo completo sin filtro (era imposible: `0` se degradaba a `-1`, los huérfanos). Es lo que muestra la interfaz al abrir.
22. **Arranque de la interfaz arreglado**: `app.js` arrancaba siempre en el nodo sintético `-1` «Sin Categoría Activa (Huérfanos)», que tiene 0 productos, así que la tabla salía vacía aunque la API funcionase. Se añadió una primera opción «📦 Todos los productos (sin filtro)», se eliminó la doble carga inicial (dos peticiones en carrera) y la primera carga la dispara ahora `cargarCategorias()`.
23. **`api.php` abre la BD con ruta absoluta** (`__DIR__ . '/catalogo_tusdisfraces.db'`). Antes usaba `sqlite:catalogo_tusdisfraces.db`, que SQLite resuelve contra el *directorio de trabajo* del servidor: si se lanzaba desde otro sitio, la API devolvía `unable to open database`.
24. **Saneamiento del árbol** (`depth` y `direct_product_count` recalculados en las 626 categorías; 0 ciclos, 0 huérfanos, 0 duplicados, 0 filas con categoría inexistente). Detalle de qué se movió, renombró y borró en las tablas de las secciones anteriores.
25. **`deleteCategory` desvincula los casos dudosos** (`api.php`). El esquema declara `ON DELETE SET NULL` sobre `uncertain_cases.id_category_proposed`, pero **SQLite no aplica ninguna clave foránea porque están desactivadas por defecto en cada conexión** (`PRAGMA foreign_keys` vale 0). Al borrar una categoría, los casos dudosos que la proponían quedaban apuntando a una categoría inexistente. Ahora `deleteCategory` hace el `UPDATE … SET id_category_proposed = NULL` explícito antes del `DELETE`, que es lo que el esquema promete. También se repararon las 4 referencias que ya estaban colgantes en la BD.
26. **`moveProduct` ya no crea una auto-fila** (`api.php`). Insertaba la nueva primaria en `product_categories`, algo que ningún otro endpoint hace: en este modelo **la primaria vive solo en `products.default_category_id` y las filas de `product_categories` son siempre secundarias**. El efecto era que un producto movido desde la interfaz devolvía su propia primaria como «categoría secundaria» en `getProductExtraCategories` (y habría inflado el recuento de filas). Se quitó el `INSERT` y, por si quedaran auto-filas antiguas, `getProductExtraCategories` ahora excluye explícitamente la primaria. Verificado: los contadores de `tipo=3/4/5/6/9/10` no cambian (2.563 / 3.201 / 931 / 5.491 / 4.117 / 3.993).
27. **Búsqueda insensible a acentos, Ñ y mayúsculas + comodines escapados + búsqueda por EAN** (`api.php`, `normalizador.php`, `importar.php`, `esquema.sql`). Los tres fallos de la auditoría de filtros quedan arreglados:
    - **Acentos y Ñ.** El `LIKE` de SQLite solo pliega mayúsculas ASCII, así que escribir `CUMPLEAÑOS` no encontraba **ninguno** de los productos de cumpleaños, y `cumpleanos` (sin ñ) devolvía 8 en lugar de 1.727. Se añaden cuatro columnas con el texto ya normalizado —`products.name_norm`, `products.ref_norm`, `features.name_norm`, `feature_values.value_norm`— y el término del usuario se pasa por la **misma** función (`normalizar_texto()`: minúsculas Unicode, sin tildes ni Ñ, sin espacios múltiples ni NBSP). Ahora las grafías son indistintas: `cumpleanos` / `cumpleaños` / `CUMPLEAÑOS` / `CumpleÁños` → **1.735** en los cuatro casos (antes 1.727, 1.727, 0 y 0). También `MUÑECA` (antes 0 → 29), `BEBÉ` (0 → 379), `NIÑO` (0 → 729), `COMUNIÓN` (0 → 337) y `feature_search=LÁTEX` (antes 37 frente a 1.038 de `latex` → ahora 1.068 en ambas grafías). Afecta a **5.047 productos (26,5 %)** con acentos o Ñ.
    - **Comodines.** `%` y `_` se interpolaban sin escapar, de modo que `search=%` o `search=_` devolvían los **19.033** productos del catálogo. Ahora el patrón se construye con `patron_like()` (`str_replace(['\\','%','_'], ['\\\\','\\%','\\_'])`) y la consulta usa `LIKE :search ESCAPE '\'`. `search=%` → 3 productos (los tres con «100%» en el nombre), `search=50%` → 0, `search=a%b` → 0.
    - **EAN.** `search` ahora incluye `COALESCE(p.ean13,'')`, así que un código de barras o un fragmento de referencia localiza el producto (antes había que teclear la referencia entera y con las mayúsculas exactas).
    - **Cómo se mantienen las columnas.** `importar.php` las rellena durante la importación (y `indexar_normas()` sirve para repararlas). Son columnas normales, no `GENERATED`, porque SQLite aborta con `parser stack overflow` a partir de unas 30 llamadas a `replace()` anidadas y el mapa completo no cabe. Si algún día se añade un endpoint que renombre un producto o edite un valor de característica, hay que volver a normalizar esa fila. Si las columnas no existen (BD sin migrar), `api.php` cae solo a la comparación cruda.
    - **Nada más cambia:** los 23 filtros de categoría y tipo verificados devuelven exactamente las mismas cifras, el coste de las consultas sube un 1–3 % (las pesadas, que son las del CTE recursivo, no se mueven) y la interfaz se comprobó de extremo a extremo (`_banco_busqueda.html`, 0 fallos).
    - Copia del estado previo en `catalogo_tusdisfraces.db.bak-norm-20261005`.
28. **Auditoría de filtros, fugas B, C y D** (`app.js`, `index.html`, `api.php`). Se aplican las tres fugas elegidas; E, F y G quedan descartadas (ver §8).
    - **B. La categoría de origen es una rama.** `app.js` envía `subtree=1` en todas las consultas de productos, así que al elegir `[3] Decoración` se ven los 2.563 productos de su rama (antes 24). El desplegable de hijas conserva la opción **«📄 Solo directos»** (`data-subtree="0"`) para forzar el modo exacto de antes.
    - **C. Selector de tipo (eje secundario).** Nuevo `<select id="selectTipo">` con las **413 categorías** de las ramas `[3]/[9]/[10]` (más «Todos los tipos»), que llama a `tipo=`. Solo esas ramas son tipos físicos reales: las **17.560 filas** de `product_categories` que caen fuera son **ancestros de la categoría principal** (comprobado con `po_fuera.php`: 0 coinciden con la primaria, 0 son ajenas, 17.560 son ancestro), no tipos independientes. Con el selector, `[3]` pasa de 2.563 (primaria) a 7.123 (primaria o secundaria).
    - **D. El contador del selector es el de la rama.** Cada `<option>` muestra `(directos · productos en rama)` cuando difieren. El total de rama se calcula en `app.js` sumando `direct_product_count` de todo el subárbol (verificado idéntico al `COUNT` del CTE recursivo). Ej.: `[18]` pasa de `(0)` a `(0 · 1.533 en rama)`, `[-5] Globos` a `(0 · 1.100 en rama)`.
    - **Coherencia de `api.php`:** con `subtree=1`, el cruce de un `tipo` con la categoría de origen también usa la rama (CTE `arb_cat`) en vez de la primaria exacta. Sin él, `tipo=3&category_id=9` devolvía 0; ahora devuelve 13.
    - **Verificación:** `php -l` limpio; `pd_final.php` (0 fallos) y `k6_final.php` (0 fallos); UI con Edge headless (selector de tipo con 414 opciones, 19.033 productos en la vista global, etiquetas `directo · rama` en origen). Sin cambios en los contadores de los filtros existentes.
29. **Ronda de disfraces: 12 categorías nuevas y 310 productos realineados** (2026-10-07; respaldo `catalogo_tusdisfraces.db.bak-disfraces-20261007`, script `apply_q.php`). Se aplicaron en un solo lote los acuerdos con el usuario sobre la rama `[4] HALLOWEEN`:
    - **Cazafantasmas.** Nueva `-626 Disfraz Cazafantasmas (Ghostbusters)` (bajo `-135`, hermana de Freddy y Familia Addams) con los 8 disfraces que estaban repartidos entre `-140 Disfraz fantasma` (5) y `-246` (3). Secundarias: `-135` → `[4]` → `[10]`.
    - **Freddy Krueger.** A `-623` llegan 6 productos que estaban fuera: `#43200` y `#19307` (máscaras, salían de `-129`/`-130`) más `#5667`, `#5693`, `#23034` y `#18878` → `-623` pasa de 20 a **26**.
    - **IT / Pennywise unificados con payaso.** `#43978`, `#37804`, `#43975` (desde `-135`) y `#52840` (desde `-286`) se mueven a `-71 Disfraz Payaso` (41 → **45**); las 4 máscaras IT (`#43981`, `#39314`, `#45510`, `#24318`) reciben `-71` como secundaria temática, con lo que quedan consultables desde el payaso y desde su subcategoría de máscara.
    - **Monster High.** `#46142`, `#30448`, `#49630`, `#30449` pasan a `-137` (12 → **16**), a la vez que `#46142`/`#49630` salían de `Maquillaje cara y pinturas` (87 → 85) y de `Disfraz monstruos clásicos` (37 → 35).
    - **Sangre falsa.** `#18869`, `#18872`, `#2254`, `#18328` (maquillaje) → `-133 Efectos especiales y sangre` (56 → **60**), y `#53250 Rosa blanca con sangre` → `-79 Complementos Halloween` (550 → **551**). El resto de los 146 productos con «sangre» en el nombre ya estaba bien ubicado (props, cuchillos, tela y mantel se quedan en Halloween).
    - **División de `-129 Mascaras completas`** (344 productos, d4): 11 subcategorías nuevas `-627`…`-637` con reparto por palabra clave sobre `name_norm` + 8 correcciones manuales (Michael Myers capturado por «zombie», máscara «similar payaso» de La Purga, antigás Breaking Bad, catwoman, pico de la peste, conejo Fortnite, «serial killer piel descamada»). Resultado: payaso 45, terror de cine y series 79, La Purga 32, Scream 25, calaveras y catrinas 20, brujas y demonios 20, zombis 15, animales 13, superhéroes y cómic 20, monstruos clásicos 9, fantasmas 5, y **61 genéricas** que siguen en `-129`. Chucky (4) y Rorschach (1) subieron de esas genéricas a cine/súper-héroes.
    - **Convención aplicada** (la de `moveProduct`): la primaria solo vive en `products.default_category_id`, no se crea fila auto en `product_categories`; cada producto movido recibe como secundarias los **ancestros del nuevo primario** sin `[1]` ni `[2]` (308 filas nuevas en 302 productos) y se recalcularon todos los `direct_product_count`.
    - **Verificación:** 19.033 productos, 645 categorías, 42.200 filas, 0 huérfanos, 0 filas colgantes, 0 auto-filas, 0 duplicados, 0 contadores desfasados, `PRAGMA integrity_check = ok`; contadores por API (`getProducts`) comprobados uno a uno (`-129` 60 directos / 343 en rama, `tipo=-628` 79, `-128`+`tipo=-629&subtree=1` 32…); `arbol_categorias.txt` regenerado.
30. **División de los dos cumpleaños de licencia grandes: 12 subcategorías y 327 productos** (2026-10-07; respaldo `catalogo_tusdisfraces.db.bak-cumple-20261007`, script `apply_cumple.php`):
    - **Motivo.** `-32 Cumpleaños Patrulla canina` (154 productos) y `15 Cumpleaños Mickey Mouse` (175) eran las dos ramas planas más grandes de `[18] CUMPLEAÑOS INFANTIL LICENCIA`; las siguientes son `-36 Princesas Disney` (120), `-20 Frozen` (89), `-43 Spiderman` (73) y `-39 Vengadores` (72), y las otras 41 hermanas siguen planas. Cada una de las dos se dividió en **6 hijas de `d5`** con posiciones 0–5, con el nombre del tema al final siguiendo la convención de `-624 Decoración Halloween`: **Globos · Vajilla y mesa · Decoración y guirnaldas · Piñatas y fiesta · Ropa infantil · Complementos** (nuevas `-638`…`-649`).
    - **Reparto** por palabra clave sobre `name_norm` (primera coincidencia en orden) + 4 correcciones manuales: `#12580` «Piñata … globos con antifaz» → piñatas, `#36649` y `#51407` «braga cuello + careta» → ropa, `#20657` Sacapuntas → complementos. **Patrulla:** ropa 54, mesa 42, complementos 19, globos 17, piñatas y fiesta 12, decoración 10 (0 sin clasificar). **Mickey:** mesa 47, ropa 35, globos 31, piñatas y fiesta 24, decoración 19, complementos 17; solo `#36638 EP2026-SU` y `#36651 HO7583-SU` (códigos internos sin nombre descriptivo) siguen en el padre.
    - **Convención de `moveProduct`:** sin fila auto para la primaria; cada producto movido recibe como secundaria el contenedor (`-32`/`15`), que es lo único que faltaba —`6` y `18` ya estaban en los 329—. Sus nodos de tipo existentes (Globos `-5`, Platos `-7`, Servilletas `-615`, Piñata `-8`…) **se conservan**, de modo que siguen filtrables por tipo y ahora además navegables por tema.
    - **Verificación:** 19.033 productos, **657** categorías, **42.527** filas, 0 huérfanos, 0 colgantes, 0 auto-filas, 0 duplicados, 0 contadores/`depth` desfasados, `PRAGMA integrity_check = ok`; API (`getProducts`) con los 12 contadores nuevos exactos, `-32&subtree=1` 154, `15&subtree=1` 175 y `15` exacto 2; UI con Edge headless pinta las 12 hijas con sus recuentos; `arbol_categorias.txt` regenerado (665 líneas).
31. **División de `-37 Primera Comunión`: 9 subcategorías, 360 productos y arreglo de `slugify()`** (2026-10-07, hito 31; respaldo `catalogo_tusdisfraces.db.bak-comunion-20261007`, script `apply_comunion.php`):
    - **Motivo.** `-37 Primera Comunión` (343 productos) era una hoja plana a `d4` dentro de `[-207] Fiestas especiales`, y además **17 productos de comunión de El Informal vivían en otras ramas** (confeti de cruces en `-230 Confeti`, 5 letras de corcho en `-229`, guirnalda y banderín en `-224`/`-6`, 7 globos en `-212`/`-215`, cajita en `-194 Azul`, vela figura en `-53 Unicornio`), así que el catálogo de comunión no estaba ni completo ni navegable.
    - **9 hijas de `d5`** con posiciones 0–8, nombre con el tema al final (convención de `-624 Decoración Halloween`): **Globos (57) · Decoración y guirnaldas (30) · Letras y letreros (57) · Vajilla y mesa (22) · Piñatas y toppers (18) · Figuras (30) · Fotos y papelería (61) · Bolsas y cajas (65) · Detalles y regalos (20)**. Nuevas `-650`…`-658`; 0 productos sin clasificar.
    - **Reparto** por palabra clave sobre `name_norm` (primera coincidencia en orden) + 3 correcciones manuales: `#35723` «Toppers para cupcake … pinchos» y `#21960`/`#21961` «Pinchos … 63 cm» (El Informal los cataloga como *Toppers*) → piñatas y toppers. Los 17 estraviados se asignaron a su grupo y **conservaron su categoría de tipo original como secundaria** (p. ej. `-229`, `-215`, `-230`), además de recibir `-37` → `-207` → `6` como contexto: 409 filas de secundaria nuevas en 360 productos.
    - **Renombrado** `[-37] Primera Comunion` → **`Primera Comunión`** (le faltaba la tilde; el slug `primera-comunion` ya era correcto).
    - **Arreglo de `slugify()`**: `createCategory` y `renameCategory` generaban el slug sustituyendo cada **byte** no ASCII por `-`, así que «Piñatas» daba `pi-atas`, «Cumpleaños» `cumplea-os` y «Jubilación» `jubilaci-n`. Ahora hay una función `slugify()` en `api.php` que translitera acentos y ñ y limpia los guiones sobrantes de los extremos (antes «Payaso (freak)» daba `-payaso-freak-`). Se corrigieron en la misma pasada los 8 slugs rotos que había dejado el hito 30 (`patulla` → `patrulla` y `decoracin-n-y-…`/`pin-atas-y-…`); quedan **288 slugs provisionales** con el viejo malformado, anotados en §8.
    - **Verificación:** 19.033 productos, **666** categorías, **42.936** filas, 0 huérfanos, 0 colgantes, 0 auto-filas, 0 duplicados, 0 contadores/`depth` desfasados, `PRAGMA integrity_check = ok`; API (`getProducts`) con los 9 contadores nuevos exactos, `-37` exacto 0 y `-37&subtree=1` 360, `-207&subtree=1` 809; `renameCategory` probado end-to-end (`Jubilación` → `jubilacion`); UI con Edge headless pinta la rama con `(0 · 360 en rama)` y las 9 hijas con sus recuentos; `arbol_categorias.txt` regenerado (674 líneas).

32. **`-112 Set decoracion y globos cumpleaños` → `Globos cumpleaños`: 26 productos a su casa** (2026-10-07, hito 32; respaldo `catalogo_tusdisfraces.db.bak-globos-20261007`, script `apply_globos.php`):
    - **Motivo.** Era el nodo más grande de `[-211] Material de fiesta` por 5× (510 frente a 107 de Piñatas) y **el nombre mentía**: 494 de 510 llevaban «globo» y el resto era decoración variada (kits de Año Nuevo, una camiseta, finger food…), mientras todos los hermanos de `-211` son de un solo tipo. Los ejes ya estaban bien montados —sus productos llevan `-211,-5,6`, igual que `-116 Guirnalda` lleva `-211,-4,6`—, así que la ronda solo limpió el contenido. Se descartó migrar los globos a la rama `[-5] Globos` (1.085 productos): solo **1** nombre coincide entre ambas ramas y la rama `-5` tiene **0** productos con «cumpleaños» en el nombre frente a **157** aquí; son dos líneas distintas (material de fiesta de cumpleaños vs. decoración profesional) y `-5` ya está como secundaria de contexto.
    - **26 movimientos** (16 sin «globo» + 10 de otro tipo inequívoco): 4 espirales, estrella, hojas y panel de lentejuelas → `-625 Decoración colgante` (23 → 30); 2 sets de decoración, puntos adhesivos y el set «globos y fondo» → **`-617 Kits y conjuntos` (0 → 4)**; 2 kits de Año Nuevo → `-607 Nochevieja y Año Nuevo` (164 → 166); camiseta Soy Luna → `-549 Camisetas` (8 → 9); set finger food y 2 moldes de cup cakes → `-15 Mesa dulce y candy bar` (26 → 29); 2 cuerdas → `-168 Lazos y cintas` (5 → 7); piñata → `-114` (107 → 108); 2 guirnaldas → `-116` (76 → 78); 2 cortinas → `-167` (2 → 4); 2 juguetes → `-180 Juguetes de fiesta` (11 → 13).
    - **Renombrado** `[-112] Set decoracion y globos cumpleaños` → **`Globos cumpleaños`**, con slug `set-decoracion-y-globos-cumplea-os` → `globos-cumpleanos` (regenerado igual que haría `renameCategory`; nada en el front referencia slugs). Quedan **484** productos, todos globos o accesorios de globos, y **todos con la secundaria de contexto `-5 Globos`** (a 8 se le añadió; el resto ya la tenía).
    - **Secundarias**: 17 filas nuevas con la cadena de ancestros del destino (`3`, `5`, `-440` + `9`…) + 8 filas `-5`. Los 5 productos que ya tenían su destino como secundaria (los espirales en `-625` y un set en `-617`) generaron auto-filas al moverse: se borraron, porque la primaria solo vive en `products.default_category_id`.
    - **Verificación:** 666 categorías, 19.033 productos, **42.956** filas (42.936 + 25 − 5), 0 huérfanos, 0 auto-filas, 0 contadores desfasados, `integrity_check = ok`; API `-112` exacto **484** = `-112&subtree=1` **484**, `-617` 4; rama `-211` 1.302 → 1.285 (17 salen de la rama, 9 se mueven entre hermanos); UI con Edge headless pinta `-112 Globos cumpleaños (484)` en los desplegables; `arbol_categorias.txt` regenerado (674 líneas).

33. **`-16 Bromas`: 183 productos ajenos a su casa** (2026-10-07, hito 33; respaldo `catalogo_tusdisfraces.db.bak-bromas-20261007`, script `apply_bromas.php`):
    - **Motivo.** `-16 Bromas` (409 productos, `d3`, sin hijas) era la mayor hermana de `[3] DECORACION` y **la mitad de su contenido no era broma**: 149 piezas de confeti (sueltos, sacos, bolsas, serpentinas y escarcha) cuando ya existía la hermana `[-230] Confeti`, 37 cañones de confeti con su hermana `[257] Cañones de confeti`, 18 disfraces de broma/adulto cuando `[249] Despedidas` ya vive ese estilo (68 disfraces), y ropa, gorros y navidad que tienen casa propia. **No hizo falta renombrar**: «Bromas» sigue describiendo lo que queda (226 productos reales).
    - **183 movimientos**: 37 cañones → `[257] Cañones de confeti` (24 → **61**); 112 de confeti/serpentina/escarcha → `[-230] Confeti` (61 → **173**); 2 globos con confeti → `[-212] Globos látex lisos` (532 → 534); 18 disfraces gag/adultos («3ª pierna», «bolsa de cannabis», «water de goma», «pedorreta»…) → `[249] Despedidas` (129 → **147**); 7 delantales graciosos + tanga Caperucita → `[-440] Vestuario y prendas` (55 → 63); zuecos → `[-572] Zapatos` (15 → 16); gorra fontanero → `[-412] Sombreros, gorros y tocados` (68 → 69); gorro navidad → `[-598] Gorros… navideños` (9 → 10); 2 papeles de WC navideños → `[-601] Accesorios navideños` (33 → 35); diadema Feliz Año Nuevo → `[-607] Nochevieja y Año Nuevo` (166 → 167).
    - **Se quedan** los que son broma aunque mencionen una ocasión o un disfraz: «Te picante broma nochevieja», «Broma picante tanga elefante», «Tanga broma espermatozoides», «Pollo de goma para broma o disfraz», los 5 de amigo invisible y las ~200 bromas clásicas (calambres, dientes, ventosas, sangre, skunk, disfrazes gag…). La otra `[-122] Confeti` (bajo `[-211]`) es de cumpleaños (moldes, cañones), así que la casa por cercanía es `-230`, en la misma rama `[3]`.
    - **Secundarias**: **+53 filas** (43.009 = 42.956 + 53) con la cadena de ancestros del destino (`-207`,`6` para Despedidas; `9`; `5`; `-507`,`9`; `-5`). Los destinos de `[257]` y `-230` comparten la rama con el origen, así que ahí los productos ya tenían su único contexto (`3`) y no se añadió nada; ninguna auto-fila que borrar.
    - **Verificación:** 666 categorías, 19.033 productos, **43.009** filas, 0 huérfanos, 0 auto-filas, 0 contadores desfasados, `integrity_check = ok`; API `-16` exacto **226** = `-16&subtree=1` **226**, `tipo=-16&subtree=1` 227 (los 226 + el externo `#52884 Billetes 500 falsos`, que lleva `-16` como secundaria desde `-587 Fiesta Casino`), contadores de los 10 destinos exactos y `getProducts` global 19.033; UI con Edge headless sin errores (`Fatal error`/`Uncaught`/`PDOException` = 0); árbol sin cambios de estructura (674 líneas).

> ⚠️ Nota para futuros scripts SQL: **no confíes en `ON DELETE CASCADE` / `ON DELETE SET NULL`**. SQLite no las aplica con `PRAGMA foreign_keys = 0`, que es el valor por defecto de PHP. Hay que borrar o anular las referencias a mano, tanto en `product_categories` como en `uncertain_cases`, y comprobar **también** `products.default_category_id`, que no tiene clave foránea declarada.

> ⚠️ Nota para futuros scripts SQL: **`LIKE` no es un buscador de texto en mayúsculas**. `lower()`/`UPPER()` de SQLite solo pliega ASCII, así que `LIKE '%AÑO%'` no encuentra `año` ni `año`. Para comparar textos usa siempre las columnas `*_norm` (mismas reglas que `normalizar_texto()` en `normalizador.php`) y recuerda `ESCAPE '\'` al construir el patrón.

---

## 8. Pasos siguientes (pendientes)

### Estado de la auditoría de filtros (hitos 27 y 28)

Se auditaron los filtros de productos y se encontraron diez fugas. Están resueltas la **A** (hito 27: acentos, comodines y EAN) y las **B, C y D** (hito 28: rama de origen, selector de tipo y contadores de rama); **E, F y G** quedan fuera de esta ronda:

- ✅ **B. `category_id` era exacto sobre `default_category_id`.** Resuelta en el hito 28: la interfaz manda `subtree=1` por defecto y el desplegable de hijas ofrece «Solo directos» para el modo exacto.
- ✅ **C. El eje secundario no tenía vista propia.** Resuelta en el hito 28 con el selector de tipo (`tipo=`).
- ✅ **D. El `(n)` del selector era `direct_product_count`, no el total de la rama.** Resuelta en el hito 28: ahora muestra `(directos · rama)`.
- **E. 14.095 productos (74 %)** tienen la primaria a `depth 4`, lo que hace el árbol muy profundo para llegar al producto. (Descartada en esta ronda.)
- **F. 131 productos sin características** no aparecen nunca en `feature_search`. (Descartada en esta ronda.)
- **G. 436 productos con nombre duplicado** (208 nombres distintos) complican la lectura de la tabla. (Descartada en esta ronda.)

### Pendientes de la reorganización

- **Asignar tipo físico a los 157 productos sin ninguna secundaria** — Tampoco tienen ninguna fila en `product_categories`. Su primaria es casi siempre una raíz temática directa: `ACCESORIOS DISFRACES` (93), `CUMPLEAÑOS Y FIESTAS TEMATICAS` (34), `DECORACION` (23), `DISFRACES` (7). Hasta entonces no aparecen en ningún filtro por tipo. (Son un subconjunto del punto siguiente: les falta **toda** la secundaria; si se les asignara una en una rama `[3] [9] [10]`, quedarían tipificados.)
- **Asignar tipo físico a los 1.822 productos sin nodo de tipo** (9,6 % del catálogo): cajas, decoración, figuras, invitaciones, pijamas, papel, textiles, botellas, llaveros, estuches, puzzles… Del recuento original de 1.829 (los 7 de la ronda de disfraces ya quedaron tipificados), algo más de 700 tienen un patrón reconocible en el nombre y el resto hay que decidirlos a mano. **Definición:** sin ninguna fila en `product_categories` que apunte a las **413 categorías de las ramas `[3] Decoración`, `[9] Accesorios` y `[10] Disfraces`**, que son donde vive el eje secundario. (Cuenta no nulos en `default_category_id`, así que incluye productos cuya primaria es una raíz temática.)
- ✅ **Pobladas las 6 temáticas conservadas** (2026-10-07) — `-587` Casino (9), `-588` Oeste (25), `-590` Orgullo (30), `-591` Graduación (23), `-592` Baloncesto (7) y `-593` Caballos (7): **101 productos** en total. Para cada uno se usó su temática en El Informal como **primaria** y se conservaron como **secundarias** la primaria anterior (el tipo físico) y el contexto de rama (contenedor —`-585` Fiestas temáticas / `-207` Fiestas especiales / `-209` Cumpleaños infantil sin licencia— y la raíz `6` CUMPLEAÑOS Y FIESTAS TEMATICAS). Candidatos extraídos de `consulta_informal.csv` por coincidencia de la categoría de El Informal; evidencia y respaldo en `bak-punto1y3-20261007`.
- **Usar El Informal como guía de asignación** — Al decidir la categoría destino de cada producto, consultar su hoja por defecto del Tienda 3 (columna «Cat. Informal» o el aviso al pulsar la referencia).
- **Revisar `uncertain_cases`** — Atender los casos dudosos registrados durante la reorganización.
- ✅ **Reintroducidas las 7 categorías borradas** (2026-10-07) — `12` TALLER PERSONALIZACION, `227` AM, `239` Fiesta blanco, `258` Cumpleaños Mickey, `259` Accesorios cumpleaños, `270` Fiesta fubol y `312` Cubiertos, con su `id_category`, padre, posición y `depth` originales (restauradas desde `bak-lote-20261005`). Se comprobó que no había ningún `id_category_proposed` ni `default_category_id` apuntando a ellas (0 referencias colgantes), así que solo hubo que insertar las filas; siguen vacías. Total de categorías: 626 → **633**. Respaldo previo: `bak-punto1y3-20261007`.
- ✅ **Ronda de disfraces: `-626`…`-637` y 310 productos realineados** (2026-10-07, hito 29) — Nuevas `-626 Disfraz Cazafantasmas (Ghostbusters)` y las 11 subcategorías `-627`…`-637` con las que se dividió `-129 Mascaras completas` (344 → 61 genéricas + 283 repartidas). Además: 6 productos Freddy → `-623` (20 → 26), 4 disfraces Monster High → `-137` (12 → 16), IT/Pennywise unificado en `-71 Payaso` (41 → 45) con `-71` como secundaria de sus 4 máscaras, y la sangre falsa de maquillaje → `-133` (56 → 60) + el ramo rosa → `-79`. Total: 633 → **645** categorías, 41.892 → **42.200** filas, 0 huérfanos y `integrity_check = ok`. Respaldo previo: `catalogo_tusdisfraces.db.bak-disfraces-20261007`.
- ✅ **Divididos los dos cumpleaños de licencia grandes** (2026-10-07, hito 30) — `-32 Cumpleaños Patrulla canina` (154) y `15 Cumpleaños Mickey Mouse` (175), las dos ramas planas más grandes de `[18] CUMPLEAÑOS INFANTIL LICENCIA`, ahora tienen 6 hijas cada una (nuevas `-638`…`-649`: globos, vajilla y mesa, decoración y guirnaldas, piñatas y fiesta, ropa infantil y complementos). 327 productos movidos + 327 filas de secundaria; `-32`/`15` pasan a ser contenedores. Las otras 44 ramas de `[18]` siguen planas (Princesas Disney 120, Frozen 89, Spiderman 73, Vengadores 72, Cars 70…): candidatas a lo mismo si se quiere. Respaldo previo: `catalogo_tusdisfraces.db.bak-cumple-20261007`.
- ✅ **Primera Comunión dividida en 9 y completada** (2026-10-07, hito 31) — `[-37] Primera Comunión` (renombrada: le faltaba la tilde) pasa de 343 productos planos a **9 hijas** `-650`…`-658` (globos 57, decoración y guirnaldas 30, letras y letreros 57, vajilla y mesa 22, piñatas y toppers 18, figuras 30, fotos y papelería 61, bolsas y cajas 65, detalles y regalos 20) y se lleva dentro a los **17 productos de comunión** que estaban repartidos por `-229`, `-212`, `-215`, `-230`, `-224`, `-6`, `-194` y `-53` (343 → **360 en rama**, 0 directos). 360 movimientos + 409 filas de secundaria; el tipo original de cada estraviado se conserva como secundaria. Además se arregló `slugify()` en `api.php` (los slugs salían `pi-atas`, `cumplea-os`, `jubilaci-n`) y los 8 slugs rotos del hito 30. Respaldo previo: `catalogo_tusdisfraces.db.bak-comunion-20261007`.
- ✅ **`-112` renombrada a `Globos cumpleaños` y limpia de tipos ajenos** (2026-10-07, hito 32) — 510 → **484** productos: los 26 que no eran globos (espirales, estrella, panel, kits de Año Nuevo, camiseta, finger food, moldes, cuerdas, piñata, guirnaldas, cortinas, juguetes…) se repartieron entre `-625`, `-617 Kits y conjuntos` (**0 → 4**), `-607 Nochevieja y Año Nuevo`, `-549 Camisetas`, `-15 Mesa dulce`, `-168 Lazos y cintas`, `-114 Piñatas`, `-116 Guirnalda`, `-167 Cortinas` y `-180 Juguetes de fiesta`; los 484 restantes llevan todos la secundaria de contexto `-5 Globos`. Se descartó moverlos a la rama `[-5] Globos`: no hay duplicado (1 nombre en común) y son líneas distintas. Respaldo previo: `catalogo_tusdisfraces.db.bak-globos-20261007`.
- ✅ **`-16 Bromas` limpia de tipos ajenos** (2026-10-07, hito 33) — 409 → **226** productos: 149 de confeti → `[-230] Confeti` (61 → 173), 37 cañones → `[257] Cañones de confeti` (24 → 61), 18 disfraces gag/adultos → `[249] Despedidas` (129 → 147), 2 globos con confeti → `[-212]`, 8 prendas → `[-440] Vestuario`, y 1 cada uno a `[-572] Zapatos`, `[-412] Sombreros`, `[-598] Gorros navideños`, `[-601] Accesorios navideños` y `[-607] Nochevieja`. El nombre no cambió: «Bromas» sigue siendo correcto. 183 movimientos + 53 filas de secundaria (43.009 en total). Respaldo previo: `catalogo_tusdisfraces.db.bak-bromas-20261007`.
- **Pendientes que dejó esta ronda**: poblar las categorías vacías de decoración/atajo (`-622` Corsés, `-621` Manos, `-620` Lápidas, `-619` Telarañas, `-618` Calaveras, `-616` Manteles, `-615` Servilletas, `-614` Vasos), valorar dividir también `-130 Caretas y antifaces` (117 productos, el mismo problema que tenía `-129`) y revisar los nombres que quedaron en `-129` tras la división.
- **Normalizar los 288 slugs provisionales malformados** — `slugify()` ya genera slugs correctos, pero los creados antes siguen con un `-` en lugar de la letra acentuada (`cumplea-os-frozen`, `pi-ata`, `tem-ticas`, `celebraciones-del-a-o`) o con guiones sobrantes en los extremos (`-payaso-`, `jedi-obi-wan-yoda-rey-leia-`, `gafas-de-broma-`). Es cosmético: nada en el front referencia los slugs y los IDs ≥ 0 conservan los suyos de PrestaShop; se arregla de golpe con `slugify(name)` sobre las categorías con ID negativo.
- **Exportar la propuesta** — Preparar un archivo (SQL/CSV) con los cambios de categoría y las categorías nuevas para revisión y réplica en PrestaShop (los IDs provisionales negativos deben traducirse a IDs reales).
- **Ideas opcionales**: copiar la referencia desde el modal, reordenar categorías hermanas (`position`), editar el nombre/slug de categorías reales (el de provisionales ya está disponible en la sección 7), o un informe/mapa de huérfanos resueltos por lote.

---

# 3. Mejora 6 - Tier A (2026-10-09)

Subdivisión de las **6 hojas con mezcla de tipos físicos** detectadas en `mejora6_hojas_2026-10-09.txt`
(análisis de 22 hojas con ≥100 productos directos = 3.902 productos, 20,5% del catálogo; umbral de trabajo
aceptado = hojas con ≥100 productos directos). Propuesta completa en
`propuesta_mejora6_tierA_2026-10-09.md` (ignorada por `.gitignore`).

## Qué se hizo

Se crearon **34 subcategorías provisionales nuevas** (`is_new=1`, IDs `-688`…`-721`, `depth` y
`position` secuenciales por hoja, `slug` coherente con la regla de la Mejora 2) y se reasignó la
**primaria** (`default_category_id`) de **972 productos**, sin tocar `product_categories`: las nuevas
subcategorías no son secundarias de nadie y los tipos físicos quedaron **idénticos** al backup
(46.539 filas pc, 0 diferencias por categoría). Las 6 hojas pasan a ser **contenedores** (0 directos).

| Hoja (origen) | Subcategorías nuevas (productos) |
|---|---|
| `-84` Accesorios Halloween (261) | Capas y mantos 47 · Diademas/tiaras/coronas 38 · Pelucas 37 · Sombreros 22 · Medias 14 · Alas 13 · Corsés 13 · **Otros 77** |
| `-607` Nochevieja y Año Nuevo (166) | Globos 32 · Gafas 30 · Gorros/sombreros/diademas 43 · Vajilla y mesa 13 · Efectos y bromas 25 · **Otros cotillón 23** |
| `-105` Fiesta Halloween (131) | Globos 29 · Guirnaldas 19 · Platos 19 · Vasos 19 · Velas 15 · Servilletas/manteles 22 · **Otros 8** |
| `-624` Decoración Halloween (152) | Figuras y adornos 66 · Colgante 34 · Textil y telas 14 · Calaveras/lápidas 14 · **Otros 24** |
| `-74` Complementos Monstruo (115) | Figuras y adornos 44 · Colgante 23 · Manos y miembros 8 · **Otros 40** |
| `249` Despedidas (147) | Disfraces 86 · Accesorios soltera 25 · Bromas 32 · **Complementos 4** |

**Criterio de asignación.** Las 5 primeras hojas se subdividen por la **secundaria de tipo físico** ya
registrada (cada producto va a su tipo; el residuo sin ninguno de los tipos del grupo va a "Otros…").
`249 Despedidas` se divide **por nombre** (mezcla disfraces-gag / accesorios-broma sin criterio de tipo
limpio): prefijo `Disfraz…`, accesorios de novia (velo/banda/diadema/ramo/liga/babero), artículos
picantes (biberón/pajita/pene/muñeca/bomba/esposas/chupete/silbato/broche/chapa/cinturón/delantal/set)
y el resto a "Complementos".

**Verificación posterior:** 0 huérfanos · 0 slugs duplicados · 0 violaciones FK · 0 auto-ciclos ·
conteos por tipo idénticos al backup · 688 → **722 categorías** · 19.033 productos · 46.539 filas pc.

**Respaldo previo:** `catalogo_tusdisfraces.db.bak-mejora6-tierA-20261009` (excluido de git vía `.gitignore`).

## Regenerado tras los cambios

- `arbol_categorias.txt` (730 líneas).
- Paquete `export-prestashop/` (Punto 4): `categorias.csv` 722 filas, `productos.csv` 19.033 filas,
  `productos_categorias.csv` 65.572 filas (incluye primaria para PrestaShop), 0 IDs foráneos huérfanos.
- Clave `mejora6_tierA_resultado` en `metadata` con el reparto y la verificación.

## Pendientes de esta ronda

- **Tier B** de Mejora 6 si se quiere ampliar: `-212` Globos látex 534, `-112` Globos cumpleaños 484,
  `-16` Bromas, `-230` Confeti, `-8` Piñata (hojas 50-100 productos).
- Revisión heredada: `-130`/`-8`/`-7`, posiciones hermanas duplicadas (`position=0`), 12 productos
  «sin tipo» en ramas 3/9/10 y los 32 `uncertain_cases`.

> Nota de mantenimiento: este archivo se regeneró el 2026-10-09 para corregir una doble codificación
> (cp1252→UTF-8) que afectaba al contenido copiado desde los README originales; el texto quedó
> íntegro y legible, sin pérdida de caracteres.

