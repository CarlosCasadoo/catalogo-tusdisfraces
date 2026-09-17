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
