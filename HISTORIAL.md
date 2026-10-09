# Historial del proyecto

Archivo de antecedentes generado el 2026-10-08 al condensar la documentación.
Conserva **íntegros** los dos documentos originales (antes de la revisión) para no perder ningún detalle:
el trabajo realizado, los hitos 1-33, los esquemas de datos y los pendientes históricos.

- Sección 1 — `README.md` original (Parte 1: datos de exportación, esquema SQLite y tarea de reorganización).
- Sección 2 — `README-front.md` original (Parte 2: interfaz web, API, hitos de cambios y pendientes).

---

# 1. README.md (original)

# CatÃ¡logo local â€” Tienda 1

Copia de trabajo de los productos activos de **Tienda 1** (`id_shop = 1`), en espaÃ±ol (`id_lang = 1`).

- Exportado: `2026-09-17T23:24:48+02:00`
- Productos activos: **19033**
- Productos simples: **16333**
- Combinaciones: **9063**
- CategorÃ­as activas: **37**

## Archivos

- `products.json`: productos, relaciones y combinaciones. Los productos simples llevan `combinations: []`.
- `categories.json`: categorÃ­as activas y relaciÃ³n padre-hijo.
- `features.json`: caracterÃ­sticas de producto y sus valores utilizados.
- `attributes.json`: grupos y valores de atributos utilizados por las combinaciones.
- `categories-tree.md`: vista legible generada a partir de `categories.json`.

## Metadatos comunes

Los cuatro JSON empiezan con estos campos:

- `schema_version`: versiÃ³n del contrato de datos. Esta exportaciÃ³n utiliza la versiÃ³n `1`.
- `shop_id`: tienda de PrestaShop de la que proceden los datos. Siempre es `1` en este paquete.
- `language_id`: idioma utilizado para nombres, slugs y valores. `1` corresponde al espaÃ±ol.
- `exported_at`: fecha y hora de la extracciÃ³n, incluyendo la zona horaria.

## `products.json`

Contiene Ãºnicamente productos activos en Tienda 1. La propiedad `products` es una lista cuyos elementos incluyen:

- `id_product`: identificador interno del producto en PrestaShop.
- `name`: nombre del producto en espaÃ±ol para Tienda 1.
- `slug`: segmento legible utilizado en la URL del producto.
- `reference`: referencia o SKU de la ficha base. Puede ser `null`.
- `ean13`: EAN de la ficha base. Puede ser `null`.
- `default_category_id`: categorÃ­a predeterminada configurada en PrestaShop.
- `category_ids`: IDs de todas las categorÃ­as activas de Tienda 1 asociadas al producto.
- `feature_value_ids`: IDs de los valores de caracterÃ­sticas que describen el producto.
- `combinations`: variantes vendibles del producto. En productos simples siempre es `[]`.

Cada elemento de `combinations` contiene:

- `id_product_attribute`: identificador interno de la combinaciÃ³n en PrestaShop.
- `reference`: referencia o SKU propio de la combinaciÃ³n. Puede ser `null` y no debe confundirse con la referencia base.
- `ean13`: EAN propio de la combinaciÃ³n. Puede ser `null`.
- `is_default`: `true` cuando es la combinaciÃ³n seleccionada inicialmente.
- `attribute_ids`: IDs de los valores que forman la combinaciÃ³n, por ejemplo `Talla: M` y `Color: Rojo`.

## `categories.json`

La propiedad `categories` contiene las categorÃ­as activas asociadas a Tienda 1:

- `id_category`: identificador interno de la categorÃ­a.
- `parent_id`: ID de su categorÃ­a padre. Permite reconstruir el Ã¡rbol.
- `name`: nombre visible en espaÃ±ol.
- `slug`: segmento legible utilizado en la URL de la categorÃ­a.
- `position`: orden de la categorÃ­a respecto a sus hermanas.
- `depth`: profundidad registrada en el Ã¡rbol de PrestaShop.
- `direct_product_count`: cantidad de productos activos asociados directamente a esa categorÃ­a. No incluye automÃ¡ticamente los productos de sus descendientes.

`categories-tree.md` es una visualizaciÃ³n de estos mismos datos. No constituye una fuente adicional ni debe editarse por separado.

## `features.json`

Las **caracterÃ­sticas** describen el producto completo y no crean variantes vendibles. Ejemplos habituales son composiciÃ³n, temÃ¡tica o fabricante.

La propiedad `features` contiene:

- `id_feature`: identificador del tipo de caracterÃ­stica.
- `name`: nombre del tipo de caracterÃ­stica.
- `values`: valores utilizados por los productos exportados.

Cada elemento de `values` contiene:

- `id_feature_value`: identificador del valor.
- `value`: texto visible del valor.

Los productos se relacionan con estos valores mediante `products[].feature_value_ids`.

## `attributes.json`

Los **atributos** forman combinaciones vendibles. Por ejemplo, los grupos `Talla` y `Color` pueden formar la combinaciÃ³n `M + Rojo`.

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
- `products[].default_category_id` conserva la categorÃ­a predeterminada de PrestaShop.
- `products[].feature_value_ids` apunta a `features[].values[].id_feature_value`.
- `products[].combinations[].attribute_ids` apunta a `attribute_groups[].attributes[].id_attribute`.

Las **caracterÃ­sticas** describen el producto completo. Los **atributos** (por ejemplo, talla o color) forman combinaciones vendibles.

Al importar en SQLite, estas listas de IDs normalmente se convierten en tablas de relaciÃ³n como `product_categories` y `product_feature_values`. Las combinaciones pueden almacenarse en una tabla propia vinculada mediante `id_product`.

EAN y referencias se almacenan como texto o `null`, nunca como nÃºmeros, para preservar ceros iniciales.

## Observaciones de integridad

- Valores de caracterÃ­sticas sin texto espaÃ±ol: **0**. Se conservan como `[Valor ID sin traducciÃ³n]` para no romper relaciones.
- Definiciones de caracterÃ­stica ausentes o sin traducciÃ³n: **1**. Se conservan por ID.
- CategorÃ­as activas cuyo padre no estÃ¡ en el conjunto activo de Tienda 1: **5**. En `categories-tree.md` aparecen como raÃ­ces separadas.
- Productos cuya categorÃ­a predeterminada no pertenece al Ã¡rbol activo de Tienda 1: **5600**, repartidos entre **199** IDs de categorÃ­a. El ID original se conserva para reflejar el catÃ¡logo real.

## Alcance

Esta carpeta es una copia de trabajo. Modificarla no cambia PrestaShop y no debe importarse automÃ¡ticamente en producciÃ³n. Los JSON contienen datos de catÃ¡logo reales; si se publica el repositorio, debe ser privado salvo revisiÃ³n previa del contenido.

## Tarea: reorganizaciÃ³n de categorÃ­as

### Objetivo

El objetivo es importar los cuatro JSON a una base de datos SQLite local y utilizarla como entorno de trabajo para reorganizar el catÃ¡logo. Desde SQLite se podrÃ¡n:

- Mover productos entre categorÃ­as.
- Cambiar la categorÃ­a predeterminada de cada producto.
- Crear categorÃ­as nuevas.
- Reordenar y editar libremente el Ã¡rbol de categorÃ­as de trabajo.
- Preparar una propuesta de cambios que, despuÃ©s de ser revisada, pueda replicarse en la tienda real.

La persona responsable del trabajo tendrÃ¡ libertad para proponer la organizaciÃ³n que considere mÃ¡s clara y Ãºtil para el cliente. TomarÃ¡ como referencia principal el Ã¡rbol de categorÃ­as de **Tienda 3 (El Informal)**, especialmente su forma de agrupar familias, temÃ¡ticas y subcategorÃ­as, pero no deberÃ¡ copiarlo mecÃ¡nicamente: Tienda 1 puede tener productos, asociaciones y necesidades diferentes.

Como criterio general deberÃ¡:

- Favorecer una navegaciÃ³n comprensible para una persona que busca productos, no una organizaciÃ³n basada Ãºnicamente en cÃ³digos internos.
- Reutilizar categorÃ­as existentes cuando sean adecuadas y crear categorÃ­as nuevas cuando el catÃ¡logo no quede bien representado.
- Mantener relaciones padre-hijo coherentes y evitar categorÃ­as duplicadas o con diferencias meramente ortogrÃ¡ficas.
- Evitar categorÃ­as excesivamente genÃ©ricas cuando exista un conjunto claro de productos que justifique una agrupaciÃ³n mÃ¡s especÃ­fica.
- No crear categorÃ­as innecesarias para casos aislados sin explicar el motivo.
- Documentar en `uncertain_cases` cualquier decisiÃ³n dudosa o cualquier caso en el que existan varias ubicaciones razonables.
- Explicar el motivo de las categorÃ­as nuevas y de las reasignaciones realizadas.

La referencia de Tienda 3 es una guÃ­a de criterio y estructura, no una fuente que prevalezca automÃ¡ticamente sobre los datos de Tienda 1.

Todo el trabajo se realiza primero en local. La base SQLite no tendrÃ¡ conexiÃ³n con PrestaShop ni podrÃ¡ modificar producciÃ³n.

### Esquema SQLite obligatorio

La importaciÃ³n debe crear las siguientes tablas. Los identificadores, EAN y referencias deben conservarse sin transformaciones. En particular, `ean13` y `reference` son `TEXT`, nunca valores numÃ©ricos.

#### CÃ³mo se organiza el modelo

El modelo separa cada tipo de informaciÃ³n para evitar duplicados. Un producto se guarda una sola vez en `products`, una categorÃ­a se guarda una sola vez en `categories` y las relaciones entre ambos se guardan en `product_categories`.

```text
categories â”€â”€< product_categories >â”€â”€ products â”€â”€< combinations
                                           â”‚             â”‚
                                           â”‚             â””â”€â”€< combination_attributes >â”€â”€ attributes
                                           â”‚                                            â”‚
                                           â”‚                                            â””â”€â”€ attribute_groups
                                           â”‚
                                           â””â”€â”€< product_feature_values >â”€â”€ feature_values
                                                                                 â”‚
                                                                                 â””â”€â”€ features
```

Los sÃ­mbolos `â”€â”€<` indican que una fila puede estar relacionada con varias filas de la tabla siguiente. Por ejemplo:

- Un producto puede pertenecer a varias categorÃ­as.
- Una categorÃ­a puede contener muchos productos.
- Un producto puede tener varias combinaciones.
- Una combinaciÃ³n puede estar formada por varios atributos, como talla y color.
- Un producto puede tener varios valores de caracterÃ­sticas.

Las tablas de relaciÃ³n (`product_categories`, `product_feature_values` y `combination_attributes`) existen porque sus relaciones son de muchos a muchos. No deben sustituirse por listas de IDs guardadas como texto dentro de una columna.

El orden recomendado de importaciÃ³n es:

1. `metadata`.
2. `categories`, `products`, `features` y `attribute_groups`.
3. `feature_values` y `attributes`.
4. `product_categories` y `product_feature_values`.
5. `combinations`.
6. `combination_attributes`.

La aplicaciÃ³n puede utilizar claves forÃ¡neas en todas las relaciones normales. La Ãºnica excepciÃ³n inicial es `products.default_category_id`, porque el export contiene categorÃ­as predeterminadas huÃ©rfanas que deben poder cargarse para ser corregidas.

#### `metadata`

Guarda informaciÃ³n sobre el propio export, no sobre productos. Cada fila contiene una pareja clave-valor; por ejemplo, una fila con `key = 'shop_id'` y `value = '1'`. Sirve para comprobar posteriormente de quÃ© tienda, idioma y versiÃ³n del formato procede la base.

| Columna | Tipo | Uso |
|---|---|---|
| `key` | `TEXT PRIMARY KEY` | Nombre del metadato. |
| `value` | `TEXT NOT NULL` | Valor importado. |

Debe guardar como mÃ­nimo `schema_version`, `shop_id`, `language_id` y `exported_at`.

#### `products`

Contiene una fila por producto activo. Guarda los datos propios de la ficha base y su categorÃ­a predeterminada de trabajo. No guarda directamente todas sus categorÃ­as, caracterÃ­sticas ni combinaciones; esas relaciones viven en tablas separadas.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product` | `INTEGER PRIMARY KEY` | ID real del producto. |
| `name` | `TEXT NOT NULL` | Nombre del producto. |
| `slug` | `TEXT NOT NULL` | Slug original. |
| `reference` | `TEXT NULL` | Referencia de la ficha base. |
| `ean13` | `TEXT NULL` | EAN de la ficha base. |
| `default_category_id` | `INTEGER NULL` | CategorÃ­a predeterminada de trabajo. |

Al importar, `default_category_id` recibe el valor original aunque todavÃ­a no exista en la tabla `categories`. Precisamente esos valores huÃ©rfanos deberÃ¡n resolverse durante la tarea. Por este motivo, esta columna no tendrÃ¡ inicialmente una restricciÃ³n de clave forÃ¡nea.

#### `categories`

Contiene una fila por categorÃ­a real o provisional. El Ã¡rbol se construye haciendo que `parent_id` apunte al `id_category` de otra fila de esta misma tabla. Una categorÃ­a con varios hijos aparecerÃ¡ una sola vez como categorÃ­a y serÃ¡ referenciada como padre por varias filas.

| Columna | Tipo | Uso |
|---|---|---|
| `id_category` | `INTEGER PRIMARY KEY` | ID real o provisional. |
| `parent_id` | `INTEGER NULL` | CategorÃ­a padre. |
| `name` | `TEXT NOT NULL` | Nombre de trabajo. |
| `slug` | `TEXT NOT NULL` | Slug de trabajo. |
| `position` | `INTEGER NOT NULL` | Orden entre categorÃ­as hermanas. |
| `depth` | `INTEGER NOT NULL` | Profundidad del export original o recalculada para categorÃ­as nuevas. |
| `direct_product_count` | `INTEGER NOT NULL` | Conteo informativo procedente del export. |
| `is_new` | `INTEGER NOT NULL DEFAULT 0` | `1` para categorÃ­as provisionales; `0` para categorÃ­as reales. |

`parent_id` puede apuntar a una categorÃ­a real o a otra categorÃ­a provisional. La aplicaciÃ³n debe impedir ciclos en el Ã¡rbol.

#### `product_categories`

Representa la pertenencia de productos a categorÃ­as. Cada fila significa Â«este producto pertenece a esta categorÃ­aÂ». Si un producto pertenece a tres categorÃ­as, tendrÃ¡ tres filas en esta tabla.

Esta tabla permite mover o asociar productos sin duplicar la ficha del producto ni guardar arrays dentro de SQLite.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product` | `INTEGER NOT NULL` | Producto relacionado. |
| `id_category` | `INTEGER NOT NULL` | CategorÃ­a relacionada. |

La clave primaria es compuesta: `PRIMARY KEY (id_product, id_category)`. Esta tabla representa el estado de trabajo y se inicializa desde `products[].category_ids`.

La categorÃ­a indicada en `products.default_category_id` deberÃ¡ aparecer tambiÃ©n en `product_categories` cuando la reorganizaciÃ³n de ese producto se dÃ© por terminada.

#### `features`

Contiene los tipos de caracterÃ­sticas aplicables al producto completo, como composiciÃ³n o temÃ¡tica. No contiene todavÃ­a sus valores concretos.

| Columna | Tipo | Uso |
|---|---|---|
| `id_feature` | `INTEGER PRIMARY KEY` | Tipo de caracterÃ­stica. |
| `name` | `TEXT NOT NULL` | Nombre de la caracterÃ­stica. |

#### `feature_values`

Contiene los valores posibles o utilizados de cada caracterÃ­stica. `id_feature` indica a quÃ© fila de `features` pertenece cada valor. Por ejemplo, un valor Â«100% poliÃ©sterÂ» podrÃ­a pertenecer a la caracterÃ­stica Â«ComposiciÃ³nÂ».

| Columna | Tipo | Uso |
|---|---|---|
| `id_feature_value` | `INTEGER PRIMARY KEY` | ID del valor. |
| `id_feature` | `INTEGER NOT NULL` | CaracterÃ­stica a la que pertenece. |
| `value` | `TEXT NOT NULL` | Texto del valor. |

#### `product_feature_values`

Relaciona productos con valores de caracterÃ­sticas. Cada fila significa Â«este producto tiene este valorÂ». Un mismo valor puede utilizarse en muchos productos y un producto puede tener varios valores.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product` | `INTEGER NOT NULL` | Producto relacionado. |
| `id_feature_value` | `INTEGER NOT NULL` | Valor relacionado. |

La clave primaria es compuesta: `PRIMARY KEY (id_product, id_feature_value)`.

#### `attribute_groups`

Contiene los grupos que se utilizan para construir variantes, como Talla o Color. Un grupo no es una opciÃ³n seleccionable por sÃ­ mismo; sus opciones se almacenan en `attributes`.

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

Cada fila representa una variante vendible de un producto. Una combinaciÃ³n pertenece siempre a un Ãºnico producto, pero puede tener su propia referencia y su propio EAN. Un producto simple no tendrÃ¡ filas en esta tabla.

| Columna | Tipo | Uso |
|---|---|---|
| `id_product_attribute` | `INTEGER PRIMARY KEY` | ID real de la combinaciÃ³n. |
| `id_product` | `INTEGER NOT NULL` | Producto al que pertenece. |
| `reference` | `TEXT NULL` | Referencia propia. |
| `ean13` | `TEXT NULL` | EAN propio. |
| `is_default` | `INTEGER NOT NULL` | `1` si es la combinaciÃ³n predeterminada; `0` en caso contrario. |

#### `combination_attributes`

Indica quÃ© valores forman cada combinaciÃ³n. Por ejemplo, una combinaciÃ³n puede tener dos filas: una que la relacione con el atributo M y otra con el atributo Rojo. Juntas representan la variante Â«Talla M + Color RojoÂ».

| Columna | Tipo | Uso |
|---|---|---|
| `id_product_attribute` | `INTEGER NOT NULL` | CombinaciÃ³n relacionada. |
| `id_attribute` | `INTEGER NOT NULL` | Valor que forma la combinaciÃ³n. |

La clave primaria es compuesta: `PRIMARY KEY (id_product_attribute, id_attribute)`.

Las caracterÃ­sticas, atributos y combinaciones deben importarse para conservar el contexto completo del catÃ¡logo, aunque la tarea principal se centre en categorÃ­as.

### CategorÃ­as nuevas

Las categorÃ­as que todavÃ­a no existan en PrestaShop utilizarÃ¡n IDs provisionales negativos:

- Primera categorÃ­a nueva: `-1`.
- Siguientes categorÃ­as: `-2`, `-3`, `-4`, etc.
- No se reutiliza un ID provisional, aunque se descarte una categorÃ­a durante el trabajo.

Estas categorÃ­as llevarÃ¡n `is_new = 1`. Sus `parent_id` podrÃ¡n ser positivos si dependen de una categorÃ­a real o negativos si dependen de otra categorÃ­a nueva.

Los IDs negativos nunca se enviarÃ¡n directamente a PrestaShop. Durante la futura rÃ©plica, cada categorÃ­a nueva recibirÃ¡ un ID real y se construirÃ¡ un mapa `id_provisional â†’ id_real` para traducir las relaciones.

### Prioridad: productos con categorÃ­a predeterminada huÃ©rfana

La secciÃ³n **Observaciones de integridad** identifica **5600 productos** cuya `default_category_id` no pertenece al Ã¡rbol activo de Tienda 1, repartidos entre **199 IDs de categorÃ­a**.

Estos productos son la prioridad nÃºmero uno. Al finalizar la reorganizaciÃ³n, cada uno deberÃ¡ cumplir las dos condiciones siguientes:

1. `products.default_category_id` apunta a una categorÃ­a presente en `categories`, ya sea real o provisional.
2. Existe la misma relaciÃ³n en `product_categories`.

DespuÃ©s deben revisarse tambiÃ©n los demÃ¡s productos para detectar asignaciones demasiado genÃ©ricas o incorrectas conforme al criterio de categorizaciÃ³n descrito en este documento.


---

# 2. README-front.md (original)

# CatÃ¡logo local â€” Tienda 1 Â· Parte 2: Interfaz Web (front)

Segunda parte del README del proyecto. Esta pÃ¡gina documenta **la parte web**: cÃ³mo ponerla en marcha paso a paso, cÃ³mo funciona por dentro y quÃ© queda pendiente. Complementa a `README.md` (parte 1), que describe los datos de exportaciÃ³n y la tarea de reorganizaciÃ³n de categorÃ­as.

Archivos de la interfaz:

- `importar.php` â€” carga los 4 JSON en SQLite (punto de partida de los datos).
- `importar_informal.php` â€” carga la consulta de El Informal (Tienda 3) como referencia auxiliar.
- `api.php` â€” API REST en JSON que la interfaz consume.
- `normalizador.php` â€” normalizaciÃ³n de texto compartida por `api.php` e `importar.php` (acentos, Ã‘, mayÃºsculas y comodines de `LIKE`).
- `index.html`, `app.js`, `styles.css` â€” interfaz de usuario.
- `esquema.sql` â€” esquema de la base de datos.
- `catalogo_tusdisfraces.db` â€” base SQLite local de trabajo.

---

## 1. Estado actual

La interfaz web ya funciona de extremo a extremo y permite reorganizar el catÃ¡logo:

- Listar el Ã¡rbol de categorÃ­as y los productos de cada categorÃ­a (incluidos los huÃ©rfanos).
- Buscar productos por nombre/referencia/**EAN** y por caracterÃ­sticas, **sin distinguir tildes, Ã‘ ni mayÃºsculas**.
- Mover productos en lote a otra categorÃ­a.
- Crear categorÃ­as provisionales (IDs negativos) bajo la raÃ­z o como subcategorÃ­a.
- Mover categorÃ­as hoja a otra rama del Ã¡rbol.
- Eliminar categorÃ­as provisionales.
- Registrar casos dudosos (por producto o generales).
- Mostrar, por cada producto, su categorÃ­a por defecto en **El Informal (Tienda 3)** como orientaciÃ³n.

---

## 2. Requisitos

- **PHP â‰¥ 8.0** en lÃ­nea de comandos, con la extensiÃ³n **pdo_sqlite** habilitada. Probado con PHP 8.2.12.
- Un navegador moderno.
- Sin dependencias externas: no se necesita Composer ni base de datos aparte.

> â„¹ï¸ `api.php` abre la base con la ruta **absoluta** `__DIR__ . '/catalogo_tusdisfraces.db'`. Antes usaba la ruta relativa `sqlite:catalogo_tusdisfraces.db`, que SQLite resuelve contra el *directorio de trabajo* del servidor y no contra la carpeta del proyecto: si se lanzaba el servidor desde otro sitio, la API devolvÃ­a `unable to open database` y la interfaz se quedaba vacÃ­a.

---

## 3. Puesta en marcha (pasos en orden)

### Paso 1 â€” Importar los datos de Tienda 1

Desde la carpeta del proyecto:

```text
php importar.php
```

Este script **resetea la base de datos** (borra y recrea el esquema) y carga los cuatro JSON: `categories.json`, `features.json`, `attributes.json` y `products.json`. Al terminar quedan listas las tablas `categories`, `products`, `product_categories`, `product_feature_values`, `attribute_groups`, `attributes`, `combinations`, `combination_attributes`, `metadata` y `uncertain_cases`.

> Si lo necesitas, puedes volver a ejecutarlo cuando quieras partir de cero.

### Paso 2 â€” Cargar la referencia de El Informal (opcional pero recomendado)

```text
php importar_informal.php
```

Lee `consulta informal.csv` (25.203 filas / 18.813 productos de la Tienda 3) y guarda la categorÃ­a por defecto y la ruta completa de cada producto en la tabla auxiliar `informal_reference`. El cruce se hace por `id_product` (18.764 de 18.813 productos coinciden con Tienda 1; los 49 restantes se omiten).

Es **re-importable**: el script vacÃ­a la tabla antes de cargar. Si ejecutas el Paso 1 despuÃ©s del Paso 2, esta tabla se conserva (no la borra `importar.php`); solo se recrea su contenido si vuelves a lanzar este script.

### Paso 3 â€” Levantar el servidor

```text
php -S 127.0.0.1:8409
```

Debe ejecutarse dentro de la carpeta del proyecto. TambiÃ©n vale cualquier servidor con PHP (XAMPP, Laragonâ€¦), siempre que la base estÃ© en la misma carpeta que `api.php`.

### Paso 4 â€” Abrir la interfaz

En el navegador:

```text
http://127.0.0.1:8409/index.html
```

### ComprobaciÃ³n rÃ¡pida de la API

```text
# PowerShell
Invoke-RestMethod "http://127.0.0.1:8409/api.php?action=getCategories"
Invoke-RestMethod "http://127.0.0.1:8409/api.php?action=getProducts&category_id=0&page=1"
Invoke-RestMethod "http://127.0.0.1:8409/api.php?action=getProducts&category_id=-1&page=1"
```

`category_id=0` son **todos los productos** (sin filtro de categorÃ­a) â€” es lo que muestra la interfaz al abrir. `category_id=-1` corresponde a los productos **huÃ©rfanos** (sin categorÃ­a activa o con `default_category_id` fuera del Ã¡rbol); ahora mismo son 0.

---

## 4. CÃ³mo funciona (arquitectura y flujo de datos)

```
products.json / categories.json / features.json / attributes.json
        â”‚
        â””â”€â”€â”€ importar.php â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â–º catalogo_tusdisfraces.db (esquema.sql)
                                            â”‚
consulta informal.csv                       â”‚
        â”‚                                   â”‚
        â””â”€â”€â”€ importar_informal.php â”€â”€â”€â”€â–º informal_reference
                                            â”‚
Browser (index.html + app.js)               â”‚
        â”‚  fetch()  â—„â”€â”€ JSON â”€â”€ api.php â—„â”€â”€â”€â”˜
        â–¼
 DOM (tabla de productos, selectores, modales)
```

1. Los datos llegan a SQLite mediante los scripts de importaciÃ³n (Pasos 1 y 2).
2. El navegador carga `index.html`, que ejecuta `app.js`.
3. `app.js` hace peticiones `fetch()` a `api.php?action=...` y recibe JSON (`{ success, data, ... }`).
4. Con esa respuesta se rellenan los selectores, la tabla de productos, la paginaciÃ³n y los modales.

### Base de datos (vista desde el front)

| Tabla | Papel en la interfaz |
|---|---|
| `categories` | Ãrbol de categorÃ­as (selector de origen, destino, padre y categorÃ­as a mover). |
| `products` | Ficha base: `id_product`, `name`, `reference`, `ean13`, `default_category_id`, y las copias normalizadas `name_norm` / `ref_norm` que usa la bÃºsqueda. |
| `product_categories` | RelaciÃ³n N:M productoâ€“categorÃ­a (se mantiene al mover productos). |
| `uncertain_cases` | Casos dudosos registrados desde la interfaz. |
| `informal_reference` | **Auxiliar de solo lectura**: categorÃ­a de El Informal por `id_product` (`familia`, `subcategoria`, `detalle`, `hoja_por_defecto`, `todas_categorias`). Se usa solo para mostrar informaciÃ³n, no participa en la reorganizaciÃ³n. |

El resto de tablas del esquema (`features`, `attribute_groups`, `combinations`â€¦) dan contexto y se usan en bÃºsquedas por caracterÃ­stica, pero no se manipulan desde la interfaz.

### BÃºsqueda de texto: cÃ³mo se ignoran tildes, Ã‘ y comodines

`features.name` y `feature_values.value` llevan ademÃ¡s `name_norm` y `value_norm`. Las cuatro columnas `*_norm` guardan el texto con las reglas de `normalizador.php`: minÃºsculas Unicode, sin tildes ni `Ã±`, sin espacios mÃºltiples ni NBSP, recortado. El tÃ©rmino que escribe el usuario se normaliza con la **misma** funciÃ³n y se compara con `LIKE :param ESCAPE '\'`, de modo que da igual cÃ³mo se escriba. Sin esto, el `LIKE` de SQLite (que solo pliega mayÃºsculas ASCII) devolvÃ­a 0 resultados para `CUMPLEAÃ‘OS`, `MUÃ‘ECA` o `BEBÃ‰`, y los comodines `%` y `_` introducidos a mano devolvÃ­an el catÃ¡logo entero.

### Nodo sintÃ©tico de huÃ©rfanos

`getCategories` aÃ±ade la categorÃ­a virtual `id_category = -1` llamada **Â«Sin CategorÃ­a Activa (HuÃ©rfanos)Â»** con un contador calculado en tiempo real a partir de los productos cuyo `default_category_id` no existe en `categories` (o es `NULL`).

---

## 5. API REST

Todas las respuestas son JSON con `success: true/false`; ante un error se devuelve `{ success: false, error: "mensaje" }`. Las peticiones POST envÃ­an el cuerpo en JSON con `Content-Type: application/json`.

| AcciÃ³n | MÃ©todo | ParÃ¡metros | DescripciÃ³n |
|---|---|---|---|
| `getCategories` | GET | â€” | Ãrbol de categorÃ­as ordenado + nodo de huÃ©rfanos. |
| `getProducts` | GET | `category_id` (int; `0` = todos, `-1` = huÃ©rfanos), `tipo` (int; ID de tipo fÃ­sico, opcional), `subtree` (`1` = filtrar por la rama completa en vez del ID exacto, opcional), `page` (â‰¥ 1), `search` (texto), `feature_search` (texto), `sort` (`name_asc`/`name_desc`) | Productos paginados (50/pÃ¡gina) con los campos `informal_*` de El Informal y `secondary_categories`. Con `tipo` se filtran por categorÃ­a secundaria (ver nota abajo). |
| `moveProduct` | POST | `{ "product_ids": [intâ€¦], "new_category_id": int }` | Mueve un lote: actualiza `default_category_id`, inserta en `product_categories` y recalcula los contadores. |
| `createCategory` | POST | `{ "name": str, "parent_id": int Â·opcional, por defecto 2Â· }` | Crea categorÃ­a provisional con ID negativo (`-2`, `-3`â€¦). |
| `deleteCategory` | POST | `{ "category_id": int < 0 }` | Pasa sus productos a huÃ©rfanos y elimina la categorÃ­a provisional. |
| `addUncertainCase` | POST | `{ "reason": str, "id_product": int Â·opcionalÂ·, "id_category_proposed": int Â·opcionalÂ·, "alternatives": str Â·opcionalÂ·, "raw_data": obj Â·opcionalÂ· }` | Registra un caso dudoso. |
| `moveCategory` | POST | `{ "category_id": int, "new_parent_id": int }` | Mueve una categorÃ­a **hoja** (sin hijos) a otra rama y recalcula su `depth`. Rechaza las categorÃ­as del sistema (`1` RaÃ­z, `2` Inicio y `-1` HuÃ©rfanos) como origen o destino, la propia categorÃ­a como padre y los padres inexistentes. |

### Filtro por tipo fÃ­sico (`tipo`) en `getProducts`

Los productos reconstruidos tienen una **categorÃ­a secundaria de tipo fÃ­sico** (velas, globos, platos, disfraces, pelucasâ€¦) ortogonal a su temÃ¡tica (primaria). El parÃ¡metro `tipo` permite listar **todos los productos de un tipo**, incluidos los que viven en categorÃ­as temÃ¡ticas:

- `tipo=X` aislado â†’ todos los productos cuyo tipo es `X` (por secundaria **o** por primaria, p. ej. `tipo=-5` lista los 2.375 globos aunque su `default_category_id` sea una temÃ¡tica).
- `tipo=X&category_id=Y` â†’ productos de ese tipo **dentro** de la categorÃ­a `Y` (intersecciÃ³n; `category_id=-1` y `category_id=0` se ignoran en este modo).
- `tipo=X&subtree=1` â†’ en vez de comparar el ID exacto, cuenta **toda la rama** de `X` (ver nota siguiente).
- `category_id=0` â†’ todos los productos, sin filtro de categorÃ­a.
- Combina con `search`, `feature_search` y `sort`.
- Si `X` no existe en `categories`, responde `{ success: false, error: "La categorÃ­a de tipo indicada no existe." }`.

Para construir un listado de tipos (p. ej. para un menÃº de navegaciÃ³n), Ãºsese `getCategories` y quedarse con las raÃ­ces de tipo: `id_category = 10` (Disfraces) mÃ¡s los hijos directos de `3` (DecoraciÃ³n) y `9` (Accesorios).

#### `tipo` exacto frente a `tipo&subtree=1`

Por defecto el filtro hace `pc.id_category = :tipo` (o `p.default_category_id = :tipo`), es decir compara el **ID exacto**: `tipo=3` (DecoraciÃ³n, raÃ­z con 35 subcategorÃ­as) devuelve 2.563, no 7.123, porque solo cuenta lo etiquetado con el ID `3` exacto y no lo de `Cubiertos`, `Velas`, `Platos`â€¦ El valor por defecto se mantiene asÃ­ para no romper la navegaciÃ³n nivel a nivel del front (el selector de subcategorÃ­as hijas).

AÃ±adiendo `subtree=1` el filtro pasa a usar un CTE recursivo y cuenta cualquier categorÃ­a del subÃ¡rbol, tanto por primaria como por secundaria:

| `tipo` | Exacto | `subtree=1` |
|---|---:|---:|
| `3` DecoraciÃ³n | 2.563 | **7.123** |
| `4` HALLOWEEN | 3.201 | 3.201 |
| `5` NAVIDAD | 931 | 931 |
| `6` CumpleaÃ±os y fiestas | 5.491 | 5.491 |
| `9` Accesorios disfraces | 4.117 | **6.234** |
| `10` Disfraces | 3.993 | 3.993 |

`subtree=1` tambiÃ©n funciona sin `tipo`: `category_id=4&subtree=1` â†’ 3.201.

#### Las tres capas de secundaria: tipo fÃ­sico, raÃ­z temÃ¡tica y temÃ¡tica concreta

AdemÃ¡s del tipo fÃ­sico, cada producto lleva **dos** capas de contexto temÃ¡tico como secundaria:

1. **La raÃ­z temÃ¡tica** â€” HALLOWEEN, NAVIDAD, DECORACIÃ“N Y ARTÃCULOS DE FIESTA, ACCESORIOS DISFRACES, DISFRACES o CUMPLEAÃ‘OS Y FIESTAS TEMÃTICAS. Regla: toda categorÃ­a que cuelgue de `2 Inicio` es raÃ­z temÃ¡tica y se aÃ±ade como secundaria de los productos de su rama, salvo que ya sea la primaria.
2. **La temÃ¡tica concreta** â€” el nodo inmediatamente superior a la primaria (`categories.parent_id`), siempre que no sea `1 RaÃ­z` ni `2 Inicio`. AsÃ­ el disfraz de Iron Man lleva `Disfraz SuperhÃ©roes Marvel` y no solo el genÃ©rico `DISFRACES`.

`secondary_categories` en la respuesta excluye la primaria, asÃ­ que el disfraz de Iron Man (primaria `Iron Man`) aparece como:

```json
"secondary_categories": [
  { "id": -238, "name": "Disfraz SuperhÃ©roes Marvel" },
  { "id": 10,   "name": "DISFRACES" }
]
```

Quedan 157 productos con cero secundarias: los que tienen como primaria una raÃ­z temÃ¡tica directa y ningÃºn tipo fÃ­sico (`ACCESORIOS DISFRACES` 93, `CUMPLEAÃ‘OS Y FIESTAS TEMATICAS` 34, `DECORACION` 23, `DISFRACES` 7). Son los que habrÃ­a que revisar a mano para asignarles tipo. Si se quiere que la regla Â«todo producto tiene su raÃ­z como subcategorÃ­aÂ» se cumpla tambiÃ©n para Ã©sos, habrÃ­a que permitir la fila auto-referencia; hoy estÃ¡ excluida por diseÃ±o para no duplicar la primaria.

### Estado de la reconstrucciÃ³n (a 2026-10-07)

| MÃ©trica | Valor |
|---|---|
| Productos | 19.033 |
| CategorÃ­as | 666 (+1 nodo sintÃ©tico de huÃ©rfanos que devuelve `getCategories`) |
| Filas en `product_categories` | 43.009 |
| Productos con â‰¥1 secundaria | 18.876 (99,2 %) |
| Productos con 2+ secundarias | 15.973 |
| Media de secundarias por producto | 2,26 |
| Filas auto-referencia / duplicadas / invÃ¡lidas | 0 / 0 / 0 |
| CategorÃ­as con `depth` o `direct_product_count` desfasado | 0 |
| HuÃ©rfanos / ciclos en el Ã¡rbol | 0 / 0 |

Nodos aÃ±adidos en las Ãºltimas rondas (primaria = producto con esa categorÃ­a como `default_category_id`; secundaria = fila en `product_categories`):

| ID | Nombre | Padre | Como primaria | Como secundaria | Total |
|---|---|---|---:|---:|---:|
| `-618` | Calaveras y crÃ¡neos | `3` DecoraciÃ³n | 0 | 21 | 21 |
| `-619` | TelaraÃ±as y telas de araÃ±a | `3` DecoraciÃ³n | 0 | 23 | 23 |
| `-620` | LÃ¡pidas y cementerios | `3` DecoraciÃ³n | 0 | 24 | 24 |
| `-621` | Manos y miembros de monstruo | `9` Accesorios disfraces | 0 | 17 | 17 |
| `-622` | CorsÃ©s y lencerÃ­a | `-440` Vestuario y prendas | 0 | 13 | 13 |
| `-625` | DecoraciÃ³n colgante (techo, rack y pared) | `3` DecoraciÃ³n | 30 | 110 | 140 |
| `-623` | Freddy Krueger (Pesadilla en Elm Street) | `-135` Disfraz terror cine y series | 26 | 0 | 26 |
| `-624` | DecoraciÃ³n Halloween | `-80` Disfraz Halloween | 153 | 0 | 153 |
| `-626` | Disfraz Cazafantasmas (Ghostbusters) | `-135` Disfraz terror cine y series | 8 | 0 | 8 |
| `-627` | MÃ¡scaras de payaso | `-129` Mascaras completas | 45 | 0 | 45 |
| `-628` | MÃ¡scaras de terror de cine y series | `-129` Mascaras completas | 79 | 0 | 79 |
| `-629` | MÃ¡scaras La Purga | `-129` Mascaras completas | 32 | 0 | 32 |
| `-630` | MÃ¡scaras Scream (Ghost Face) | `-129` Mascaras completas | 25 | 0 | 25 |
| `-631` | MÃ¡scaras de calaveras y catrinas | `-129` Mascaras completas | 20 | 0 | 20 |
| `-632` | MÃ¡scaras de brujas y demonios | `-129` Mascaras completas | 20 | 0 | 20 |
| `-633` | MÃ¡scaras de zombis | `-129` Mascaras completas | 15 | 0 | 15 |
| `-634` | MÃ¡scaras de animales | `-129` Mascaras completas | 13 | 0 | 13 |
| `-635` | MÃ¡scaras de superhÃ©roes y cÃ³mic | `-129` Mascaras completas | 20 | 0 | 20 |
| `-636` | MÃ¡scaras de monstruos clÃ¡sicos | `-129` Mascaras completas | 9 | 0 | 9 |
| `-637` | MÃ¡scaras de fantasmas | `-129` Mascaras completas | 5 | 0 | 5 |
| `-638` | Globos Patrulla Canina | `-32` CumpleaÃ±os Patrulla canina | 17 | 0 | 17 |
| `-639` | Vajilla y mesa Patrulla Canina | `-32` CumpleaÃ±os Patrulla canina | 42 | 0 | 42 |
| `-640` | DecoraciÃ³n y guirnaldas Patrulla Canina | `-32` CumpleaÃ±os Patrulla canina | 10 | 0 | 10 |
| `-641` | PiÃ±atas y fiesta Patrulla Canina | `-32` CumpleaÃ±os Patrulla canina | 12 | 0 | 12 |
| `-642` | Ropa infantil Patrulla Canina | `-32` CumpleaÃ±os Patrulla canina | 54 | 0 | 54 |
| `-643` | Complementos Patrulla Canina | `-32` CumpleaÃ±os Patrulla canina | 19 | 0 | 19 |
| `-644` | Globos Mickey Mouse | `15` CumpleaÃ±os Mickey Mouse | 31 | 0 | 31 |
| `-645` | Vajilla y mesa Mickey Mouse | `15` CumpleaÃ±os Mickey Mouse | 47 | 0 | 47 |
| `-646` | DecoraciÃ³n y guirnaldas Mickey Mouse | `15` CumpleaÃ±os Mickey Mouse | 19 | 0 | 19 |
| `-647` | PiÃ±atas y fiesta Mickey Mouse | `15` CumpleaÃ±os Mickey Mouse | 24 | 0 | 24 |
| `-648` | Ropa infantil Mickey Mouse | `15` CumpleaÃ±os Mickey Mouse | 35 | 0 | 35 |
| `-649` | Complementos Mickey Mouse | `15` CumpleaÃ±os Mickey Mouse | 17 | 0 | 17 |
| `-650` | Globos Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 57 | 0 | 57 |
| `-651` | DecoraciÃ³n y guirnaldas Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 30 | 0 | 30 |
| `-652` | Letras y letreros Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 57 | 0 | 57 |
| `-653` | Vajilla y mesa Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 22 | 0 | 22 |
| `-654` | PiÃ±atas y toppers Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 18 | 0 | 18 |
| `-655` | Figuras Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 30 | 0 | 30 |
| `-656` | Fotos y papelerÃ­a Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 61 | 0 | 61 |
| `-657` | Bolsas y cajas Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 65 | 0 | 65 |
| `-658` | Detalles y regalos Primera ComuniÃ³n | `-37` Primera ComuniÃ³n | 20 | 0 | 20 |

`-638`â€¦`-649` nacieron pobladas (primaria = tipo de producto dentro del tema); como su padre es temÃ¡tico y no de tipo, sus productos no ganan nodo de tipo y el eje `[3]/[9]/[10]` sigue intacto. `-32` y `15` pasaron a ser **contenedores**: 0 y 2 productos directos, 154 y 173 filas como secundaria y 6 hijas cada uno.

`-650`â€¦`-658` son las 9 hijas con las que se dividiÃ³ `-37 Primera ComuniÃ³n` (hito 31): `-37` quedÃ³ en **0 directos** con **360 en rama** (los 343 de siempre + 17 que estaban repartidos por otras ramas) y cada hija recibe como secundarias `-37` â†’ `-207 Fiestas especiales` â†’ `6 CUMPLEAÃ‘OS Y FIESTAS TEMATICAS`, ademÃ¡s de su nodo de tipo si lo tenÃ­a (`-5 Globos`, `-229 Letras y letreros`, `-4 Guirnaldas`â€¦).

`-627`â€¦`-637` son las 11 subcategorÃ­as con las que se dividiÃ³ `-129 Mascaras completas` (344 â†’ 61 genÃ©ricas + 283 repartidas); cada una hereda como secundarias `-129` â†’ `-128` â†’ `[4] HALLOWEEN`, y los productos temÃ¡ticos aÃ±aden tambiÃ©n la capa de la raÃ­z temÃ¡tica (p. ej. `-71 Disfraz Payaso` en las mÃ¡scaras IT).

CategorÃ­as **retiradas** en esta ronda:

| ID | Nombre | Motivo |
|---|---|---|
| `-11` | Colgantes | Mezclaba bisuterÃ­a de collar (7) con decoraciÃ³n colgante de techo/rack/pared (110). Se partiÃ³ en `-625 DecoraciÃ³n colgante` y `-571 Colgantes`, bajo `-488 Collares y cadenas`. |
| `-462`, `-428`, `-411` | Bruja y Halloween (Ã—3) | El mismo nombre repetido en tres ramas de tipo distintas. Los 24 productos se unificaron en `-85 Accesorios Bruja/o`, conservando su tipo fÃ­sico (panty, mÃ¡scara, peluca) y la raÃ­z HALLOWEEN como secundarias. |
| `312` | Cubiertos | Duplicaba a `266 Cubiertos`; estaba vacÃ­a. |
| `258` | CumpleaÃ±os Mickey | Duplicaba a `15 CumpleaÃ±os Mickey Mouse`; estaba vacÃ­a. |
| `270` | Fiesta fubol | Duplicaba a `241 CumpleaÃ±os futbl`; estaba vacÃ­a. |
| `239` | Fiesta blanco | Los colores ya viven en `-59 Fiesta colores`; estaba vacÃ­a. |
| `259` | Accesorios cumpleaÃ±os | Su padre `-211 Material de fiesta` ya es el cajÃ³n; estaba vacÃ­a. |
| `12` + `227` | TALLER PERSONALIZACION + AM | Padre e hija, ambos vacÃ­os. |

Renombradas:

- `[-10] Collares` â†’ **Collares de fiesta y leis**. Sus 11 productos son collares de papel, plÃ¡stico y leis de cotillÃ³n, **no joyerÃ­a**, asÃ­ que no se fusionaron con `[-570] Collares` (que sÃ­ es bisuterÃ­a): era una colisiÃ³n de nombre, no un error de sitio.
- `[-483] Narices y prÃ³tesis` â†’ **Dientes, colmillos y prÃ³tesis faciales**. Su contenido real es dientes (46), colmillos (18), nariz (18), orejas (17), dentaduras (6) y aureolas (4). Se descartÃ³ Â«PrÃ³tesis y efectos especialesÂ» porque duplicaba `-580` y `-133`.
- `327 Paletas y sets de maquillaje` dejÃ³ de ser raÃ­z temÃ¡tica bajo `2 Inicio` y pasÃ³ a ser hija de `247 Maquillaje`.
- Los 28 nodos cuyo nombre empezaba en minÃºscula (`plata`, `confeti`, `pijama`, `fiesta 30 cumpleaÃ±os`, `topper`â€¦) se normalizaron a mayÃºscula inicial, con su `slug` regenerado.

Las 6 temÃ¡ticas que se decidiÃ³ **conservar** (son temas reales) estÃ¡n **ya pobladas** desde el 2026-10-07: `-587` Casino (9), `-588` Oeste (25), `-590` Orgullo (30), `-591` GraduaciÃ³n (23), `-592` Baloncesto (7), `-593` Caballos (7). Los productos se tomaron de la referencia de El Informal (asignaciÃ³n temÃ¡tica) y se conservÃ³ la primaria anterior (tipo fÃ­sico) mÃ¡s el contexto de rama como secundarias (ver Â§8).


### `getProducts` y los campos `informal_*`

AdemÃ¡s de los datos del producto, cada fila incluye la categorÃ­a de El Informal mediante un `LEFT JOIN` sobre `informal_reference`:

```json
{
  "id_product": 48183,
  "name": "Figura niÃ±o de comunion informal 16 cm",
  "reference": "315118DE",
  "ean13": "8435599756892",
  "informal_familia": "CUMPLEAÃ‘OS Y FIESTAS TEMATICAS",
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

1. **Filtrar origen** â€” Buscador por nombre con sugerencias **y** selector con el Ã¡rbol de categorÃ­as (elegir una sugerencia fija el selector de origen, actualiza la rama hija y recarga los productos). El origen se interpreta como **rama completa** (categorÃ­a + descendientes); si la categorÃ­a elegida tiene descendientes, aparece un segundo selector que permite elegir un descendiente concreto o el modo **Â«ðŸ“„ Solo directosÂ»** (categorÃ­a exacta, sin subcategorÃ­as). Los contadores de los selectores muestran `(directos Â· productos en rama)` cuando ambos difieren. AdemÃ¡s, el **selector de tipo** (eje secundario: ramas DecoraciÃ³n / Accesorios / Disfraces) filtra por `tipo=`, de modo que tambiÃ©n aparecen los productos cuya categorÃ­a principal estÃ¡ en otra rama pero tienen ese tipo como categorÃ­a secundaria.
2. **Reasignar selecciÃ³n** â€” Buscador por nombre de categorÃ­a destino con sugerencias, un selector de destino y el botÃ³n **Â«Mover ProductosÂ»** (aplica a todos los productos marcados).
3. **Nueva categorÃ­a** â€” Permite elegir la ubicaciÃ³n (raÃ­z bajo *Inicio* o como hija de una rama) y crear la categorÃ­a provisional.
4. **Mover categorÃ­a** â€” Mover una categorÃ­a hoja a otra rama.
5. **Eliminar categorÃ­a** â€” SecciÃ³n propia: eliminar categorÃ­as provisionales (el botÃ³n se activa al seleccionar una).
6. **GestiÃ³n de incertidumbres** â€” BotÃ³n **Â«Registrar Caso DudosoÂ»** para dudas generales o de lote.

### Panel derecho (productos)

- **BÃºsquedas** en tiempo real: por nombre/referencia y por caracterÃ­stica (ej. `Halloween`, `Adulto`, `LÃ¡tex`), con debounce de 250 ms.
- **OrdenaciÃ³n**: clic en el encabezado Â«NombreÂ» de la tabla alterna A â†’ Z / Z â†’ A en cada pulsaciÃ³n (â–²/â–¼ indican el orden activo; `api.php` aÃ±ade `ORDER BY name COLLATE NOCASE`; cambiar el orden vuelve a la pÃ¡gina 1).
- **Tabla** con: checkbox de selecciÃ³n (y Â«seleccionar todosÂ»), ID, nombre, referencia, **Cat. Informal** y acciones por fila.
- **Acciones por fila:** Â«Marcar DudosoÂ» (pide motivo y alternativas) y Â«Copiar RefÂ» (copia al portapapeles con aviso _toast_).
- **PaginaciÃ³n**: 50 productos por pÃ¡gina.
- **Clic en la fila**: marca/desmarca el checkbox (excepto al pulsar botones o la referencia).

### Columna Â«Cat. InformalÂ» y aviso al pulsar la referencia

La columna muestra la **hoja por defecto** del producto en El Informal (o `â€”` si no hay registro).

Al **pulsar la referencia** se abre un modal con:

- nombre y referencia del producto;
- ruta completa: **Nivel 1 (Familia) â†’ Nivel 2 (SubcategorÃ­a) â†’ Nivel 3 (Detalle)**;
- **ðŸ“Œ Hoja por defecto** destacada;
- **Todas las categorÃ­as asignadas** en El Informal (separadas por `|`).

Se cierra con el botÃ³n Ã—, haciendo clic fuera o con la tecla `Escape`. El texto se escapa (`escapeHtml`) para evitar inyecciÃ³n de HTML.

### Ãrbol de categorÃ­as (modal)

BotÃ³n **Â«ðŸŒ³ Ver Ã¡rbol de categorÃ­asÂ»** en la parte superior del panel izquierdo. Abre un modal grande con el Ã¡rbol completo y claro:

- indentaciÃ³n por nivel e iconos ðŸ“ (con subcategorÃ­as) / ðŸ“„ (sin) / ðŸ“¦ (huÃ©rfanos);
- etiqueta **[Provisional]** y marca **(sistema)** para las categorÃ­as del sistema;
- nÂº de productos directos por categorÃ­a `(n)`;
- resumen con totales (categorÃ­as, provisionales y productos huÃ©rfanos).

Se cierra con el botÃ³n Ã—, clic fuera o `Escape`.

### Avisos (toast)

Las confirmaciones de Ã©xito, los errores y los mensajes de validaciÃ³n se muestran como notificaciones **toast** en la esquina superior derecha que desaparecen solas (Ã©xito â‰ˆ 3 s, error â‰ˆ 5 s, informaciÃ³n â‰ˆ 3,5 s), sin botÃ³n de aceptar. Solo las acciones que piden una decisiÃ³n o texto al usuario (`confirm`/`prompt`) siguen usando los diÃ¡logos nativos.

Detalle de usabilidad: al enfocar **cualquier buscador** que ya tenga texto (origen, destino o filtros de la tabla de productos), todo el contenido queda seleccionado para poder borrarlo o sobrescribirlo directamente con una sola pulsaciÃ³n.

---

## 7. Lo que se ha hecho hasta ahora

1. **ImportaciÃ³n Tienda 1 â†’ SQLite** (`importar.php` + `esquema.sql`): esquema conforme a la parte 1 del README, con datos reales (19.033 productos activos, combinaciones, caracterÃ­sticas, atributos y categorÃ­as).
2. **API REST base** (`api.php`): categorÃ­as en Ã¡rbol, productos paginados con bÃºsqueda por nombre/referencia y por caracterÃ­sticas, mover productos en lote, crear/eliminar categorÃ­as provisionales y registrar casos dudosos.
3. **Interfaz de dos paneles** (`index.html`, `app.js`, `styles.css`): selector de origen con descendientes, buscador de destino, selecciÃ³n mÃºltiple, paginaciÃ³n y los botones de las secciones 1 a 5 de la parte 1 del README.
4. **Referencia de El Informal**: anÃ¡lisis del cruce por `id_product` (18.764 coincidencias de 18.813), tabla auxiliar `informal_reference` y script re-importable `importar_informal.php`.
5. **Interfaz informal**: columna Â«Cat. InformalÂ» en la tabla y modal con la ruta completa al pulsar la referencia.
6. **`moveCategory` activo** (`api.php`): el `case` quedÃ³ implementado y validado (el cÃ³digo que antes estaba muerto tras `deleteCategory`). Se corrigiÃ³ ademÃ¡s la comprobaciÃ³n de categorÃ­as del sistema: antes `id <= 2` bloqueaba tambiÃ©n a las provisionales de ID negativo (`-2`, `-3`â€¦); ahora solo se rechazan las reales (`1`, `2`, `-1`), en `api.php` y en `app.js`.
7. **Avisos toast**: los `alert()` de confirmaciÃ³n y error se sustituyeron por notificaciones auto-desaparecientes (verde Ã©xito, rojo error, neutro informaciÃ³n) apiladas arriba a la derecha.
8. **Buscador de categorÃ­a de origen**: caja de bÃºsqueda por nombre con sugerencias en la secciÃ³n Â«Filtrar OrigenÂ»; elegir una sugerencia fija el selector, actualiza la rama hija y recarga los productos. (Se corrigiÃ³ ademÃ¡s el bucle del botÃ³n Â«Eliminar CategorÃ­a ProvisionalÂ», que re-enlazaba su manejador en cada refresco.)
9. **OrdenaciÃ³n por nombre**: con un clic en el encabezado Â«NombreÂ» de la tabla se alterna A â†’ Z / Z â†’ A (indicador â–²/â–¼ en el encabezado), implementado en `api.php` (parÃ¡metro `sort` con `ORDER BY name COLLATE NOCASE`) y en `app.js`.
10. **Ãrbol de categorÃ­as (modal)**: botÃ³n Â«ðŸŒ³ Ver Ã¡rbol de categorÃ­asÂ» que abre el Ã¡rbol completo en un modal grande (indentado, con iconos, provisionales, sistema y nÂº de productos directos).
11. **DocumentaciÃ³n**: este `README-front.md` como segunda parte del README.
12. **DiagnÃ³stico y saneamiento de datos**: revisiÃ³n integral de la BD y de todos los endpoints (baterÃ­a de pruebas funcionales, 0 fallos) con las siguientes correcciones:
    - `api.php` (`deleteCategory`): ahora borra tambiÃ©n las asignaciones en `product_categories` de la categorÃ­a eliminada (antes quedaban filas huÃ©rfanas apuntando a categorÃ­as borradas);
    - `api.php` (`moveProduct`): valida que la categorÃ­a destino exista antes de mover (evita crear filas huÃ©rfanas por un destino invÃ¡lido);
    - datos: eliminadas 54 filas huÃ©rfanas de `product_categories` (referencias a categorÃ­as borradas, incluida la pseudo-categorÃ­a `-1`); corregido el `parent_id` de RaÃ­z (`0` â†’ `NULL`); rellenadas las 895 filas por defecto faltantes en `product_categories` (insertadas donde faltaban); asignado nombre a 2 productos con nombre vacÃ­o (usando su referencia);
    - verificaciÃ³n posterior: `PRAGMA integrity_check` OK, 0 violaciones de clave forÃ¡nea, 0 padres rotos, 0 filas huÃ©rfanas, `depth` coherente en las 129 categorÃ­as;
    - `styles.css`: aÃ±adidas la clase `.btn-copy` (el botÃ³n Â«Copiar RefÂ» quedaba con el estilo azul por defecto) y el estilo de `.check-producto` (checkbox de la tabla);
    - Respaldado el estado previo en `catalogo_tusdisfraces.db.bak-diag` (13,2 MB) por si se quiere revertir.
13. **Editar nombre de categorÃ­a provisional** (secciÃ³n 7 del panel izquierdo, tras Â«GestiÃ³n de IncertidumbresÂ»): desplegable con las categorÃ­as provisionales (ID negativo) y campo de texto que se rellena con el nombre actual (seleccionÃ¡ndolo al enfocar); al guardar se valida (no vacÃ­o, mÃ¡x. 90 caracteres, nombre distinto del actual) y se confirma con `confirm()`. Se llama al nuevo endpoint `renameCategory` de `api.php`, que actualiza `name` y `slug` y devuelve un toast. AÃ±adido el estilo `.btn-edit` para el botÃ³n Â«Guardar NombreÂ».
14. **CategorÃ­a secundaria** (en la secciÃ³n 2 Â«Reasignar SelecciÃ³nÂ»): botÃ³n Â«AÃ±adir como CategorÃ­a SecundariaÂ» que asigna la categorÃ­a elegida como secundaria a los productos seleccionados. Usa el nuevo endpoint en lote `addSecondaryCategory` de `api.php`, que inserta filas en `product_categories` con `INSERT OR IGNORE` **sin tocar `default_category_id`** (por eso no cambian ni la lista de productos ni el contador `direct_product_count`, que solo cuenta las categorÃ­as por defecto). El endpoint valida que la categorÃ­a exista y devuelve cuÃ¡ntas asignaciones nuevas se han hecho (las repetidas se ignoran). Al eliminar una categorÃ­a provisional, sus filas secundarias se borran junto con ella (mismo `DELETE FROM product_categories` ya aÃ±adido en el hito 12). AÃ±adido el estilo `.btn-secundaria`.
15. **VisualizaciÃ³n y gestiÃ³n de categorÃ­as secundarias**: nueva columna Â«Cat. SecundariasÂ» en la tabla de productos con las categorÃ­as secundarias de cada producto (excluyendo su categorÃ­a principal), mostradas apiladas una debajo de otra ocupando el ancho de la celda para que el nombre se lea completo.
16. **PaginaciÃ³n superior**: barra compacta (`.pagination-top`, alineada a la derecha) sobre la tabla de productos con los botones **Â«â† AnteriorÂ»** y **Â«Siguiente â†’Â»**, con el mismo comportamiento y estados deshabilitados (primera/Ãºltima pÃ¡gina) que los botones inferiores.
17. **Retoques visuales**: los botones de acciÃ³n por fila Â«Marcar DudosoÂ» y Â«Copiar RefÂ» usan la variante compacta `.btn-mini` (padding y fuente menores, `min-width` eliminado) para que la fila respire; y el modal del Ã¡rbol de categorÃ­as se agranda (hasta `1080px` de ancho x `92vh` de alto, contenido hasta `76vh`), con mayor especificidad (`modal-overlay .modal.modal-tree`) porque la clase base `.modal` lo limitaba a 520px en la cascada.
18. **Ãrbol de categorÃ­as en archivo (`arbol_categorias.txt`)**: se guarda en la carpeta del proyecto un fichero de texto plano (UTF-8) con el Ã¡rbol de categorÃ­as **en el mismo formato que muestra el modal de la interfaz** (ðŸ“/ðŸ“„/ðŸ“¦, `[Provisional]`, `(sistema)`, `(n)` productos directos, mismo orden `position ASC, name ASC` y mismo recorrido desde RaÃ­z). Nueva librerÃ­a `arbol_exporter.php` (funciÃ³n `generarArbolTexto` + `exportarArbolAArchivo`). **Se regenera automÃ¡ticamente** desde `api.php` tras cada acciÃ³n que cambie el Ã¡rbol: `createCategory`, `renameCategory`, `moveCategory`, `deleteCategory` y `moveProduct` (los cambios de productos secundarios no lo alteran). Para regenerarlo a mano: `php exportar_arbol.php`. `getProducts` de `api.php` devuelve ahora `secondary_categories` (array de `{id, name}`) calculado a partir de `product_categories`. Cada chip tiene un botÃ³n Â«âœ•Â» para quitar esa asignaciÃ³n mediante el nuevo endpoint `removeSecondaryCategory`, que nunca permite borrar la categorÃ­a principal (si el `category_id` coincide con el `default_category_id` del producto, se ignora) y devuelve cuÃ¡ntas asignaciones se han quitado. Estilos `.col-secundarias`, `.chip-secundaria` y `.chip-remove`.
19. **Modelo de dos ejes consolidado**: la **primaria** es temÃ¡tica y la **secundaria** es el tipo fÃ­sico canÃ³nico, mÃ¡s las capas de contexto temÃ¡tico (raÃ­z temÃ¡tica + temÃ¡tica concreta). Cada producto lleva de media 2,19 secundarias. `secondary_categories` excluÃ­a la primaria, asÃ­ que los 164 productos cuya primaria es una raÃ­z temÃ¡tica directa (`ACCESORIOS DISFRACES`, `CUMPLEAÃ‘OS Y FIESTAS TEMATICAS`, `DECORACION`, `DISFRACES`) aparecen sin chips: son los que quedan por revisar a mano para asignarles tipo.
20. **`subtree=1` en `getProducts`**: nuevo parÃ¡metro opcional que hace que `category_id` y `tipo` comparen **toda la rama** en vez del ID exacto, mediante un CTE recursivo. Sin Ã©l, `tipo=3` devolvÃ­a 2.563 (solo lo etiquetado con el ID `3`) en lugar de los 7.123 productos de la rama DecoraciÃ³n. Con `tipo` inexistente responde `{ success: false, error: "La categorÃ­a de tipo indicada no existe." }`.
21. **`category_id=0` = todos los productos**: valor nuevo para listar el catÃ¡logo completo sin filtro (era imposible: `0` se degradaba a `-1`, los huÃ©rfanos). Es lo que muestra la interfaz al abrir.
22. **Arranque de la interfaz arreglado**: `app.js` arrancaba siempre en el nodo sintÃ©tico `-1` Â«Sin CategorÃ­a Activa (HuÃ©rfanos)Â», que tiene 0 productos, asÃ­ que la tabla salÃ­a vacÃ­a aunque la API funcionase. Se aÃ±adiÃ³ una primera opciÃ³n Â«ðŸ“¦ Todos los productos (sin filtro)Â», se eliminÃ³ la doble carga inicial (dos peticiones en carrera) y la primera carga la dispara ahora `cargarCategorias()`.
23. **`api.php` abre la BD con ruta absoluta** (`__DIR__ . '/catalogo_tusdisfraces.db'`). Antes usaba `sqlite:catalogo_tusdisfraces.db`, que SQLite resuelve contra el *directorio de trabajo* del servidor: si se lanzaba desde otro sitio, la API devolvÃ­a `unable to open database`.
24. **Saneamiento del Ã¡rbol** (`depth` y `direct_product_count` recalculados en las 626 categorÃ­as; 0 ciclos, 0 huÃ©rfanos, 0 duplicados, 0 filas con categorÃ­a inexistente). Detalle de quÃ© se moviÃ³, renombrÃ³ y borrÃ³ en las tablas de las secciones anteriores.
25. **`deleteCategory` desvincula los casos dudosos** (`api.php`). El esquema declara `ON DELETE SET NULL` sobre `uncertain_cases.id_category_proposed`, pero **SQLite no aplica ninguna clave forÃ¡nea porque estÃ¡n desactivadas por defecto en cada conexiÃ³n** (`PRAGMA foreign_keys` vale 0). Al borrar una categorÃ­a, los casos dudosos que la proponÃ­an quedaban apuntando a una categorÃ­a inexistente. Ahora `deleteCategory` hace el `UPDATE â€¦ SET id_category_proposed = NULL` explÃ­cito antes del `DELETE`, que es lo que el esquema promete. TambiÃ©n se repararon las 4 referencias que ya estaban colgantes en la BD.
26. **`moveProduct` ya no crea una auto-fila** (`api.php`). Insertaba la nueva primaria en `product_categories`, algo que ningÃºn otro endpoint hace: en este modelo **la primaria vive solo en `products.default_category_id` y las filas de `product_categories` son siempre secundarias**. El efecto era que un producto movido desde la interfaz devolvÃ­a su propia primaria como Â«categorÃ­a secundariaÂ» en `getProductExtraCategories` (y habrÃ­a inflado el recuento de filas). Se quitÃ³ el `INSERT` y, por si quedaran auto-filas antiguas, `getProductExtraCategories` ahora excluye explÃ­citamente la primaria. Verificado: los contadores de `tipo=3/4/5/6/9/10` no cambian (2.563 / 3.201 / 931 / 5.491 / 4.117 / 3.993).
27. **BÃºsqueda insensible a acentos, Ã‘ y mayÃºsculas + comodines escapados + bÃºsqueda por EAN** (`api.php`, `normalizador.php`, `importar.php`, `esquema.sql`). Los tres fallos de la auditorÃ­a de filtros quedan arreglados:
    - **Acentos y Ã‘.** El `LIKE` de SQLite solo pliega mayÃºsculas ASCII, asÃ­ que escribir `CUMPLEAÃ‘OS` no encontraba **ninguno** de los productos de cumpleaÃ±os, y `cumpleanos` (sin Ã±) devolvÃ­a 8 en lugar de 1.727. Se aÃ±aden cuatro columnas con el texto ya normalizado â€”`products.name_norm`, `products.ref_norm`, `features.name_norm`, `feature_values.value_norm`â€” y el tÃ©rmino del usuario se pasa por la **misma** funciÃ³n (`normalizar_texto()`: minÃºsculas Unicode, sin tildes ni Ã‘, sin espacios mÃºltiples ni NBSP). Ahora las grafÃ­as son indistintas: `cumpleanos` / `cumpleaÃ±os` / `CUMPLEAÃ‘OS` / `CumpleÃÃ±os` â†’ **1.735** en los cuatro casos (antes 1.727, 1.727, 0 y 0). TambiÃ©n `MUÃ‘ECA` (antes 0 â†’ 29), `BEBÃ‰` (0 â†’ 379), `NIÃ‘O` (0 â†’ 729), `COMUNIÃ“N` (0 â†’ 337) y `feature_search=LÃTEX` (antes 37 frente a 1.038 de `latex` â†’ ahora 1.068 en ambas grafÃ­as). Afecta a **5.047 productos (26,5 %)** con acentos o Ã‘.
    - **Comodines.** `%` y `_` se interpolaban sin escapar, de modo que `search=%` o `search=_` devolvÃ­an los **19.033** productos del catÃ¡logo. Ahora el patrÃ³n se construye con `patron_like()` (`str_replace(['\\','%','_'], ['\\\\','\\%','\\_'])`) y la consulta usa `LIKE :search ESCAPE '\'`. `search=%` â†’ 3 productos (los tres con Â«100%Â» en el nombre), `search=50%` â†’ 0, `search=a%b` â†’ 0.
    - **EAN.** `search` ahora incluye `COALESCE(p.ean13,'')`, asÃ­ que un cÃ³digo de barras o un fragmento de referencia localiza el producto (antes habÃ­a que teclear la referencia entera y con las mayÃºsculas exactas).
    - **CÃ³mo se mantienen las columnas.** `importar.php` las rellena durante la importaciÃ³n (y `indexar_normas()` sirve para repararlas). Son columnas normales, no `GENERATED`, porque SQLite aborta con `parser stack overflow` a partir de unas 30 llamadas a `replace()` anidadas y el mapa completo no cabe. Si algÃºn dÃ­a se aÃ±ade un endpoint que renombre un producto o edite un valor de caracterÃ­stica, hay que volver a normalizar esa fila. Si las columnas no existen (BD sin migrar), `api.php` cae solo a la comparaciÃ³n cruda.
    - **Nada mÃ¡s cambia:** los 23 filtros de categorÃ­a y tipo verificados devuelven exactamente las mismas cifras, el coste de las consultas sube un 1â€“3 % (las pesadas, que son las del CTE recursivo, no se mueven) y la interfaz se comprobÃ³ de extremo a extremo (`_banco_busqueda.html`, 0 fallos).
    - Copia del estado previo en `catalogo_tusdisfraces.db.bak-norm-20261005`.
28. **AuditorÃ­a de filtros, fugas B, C y D** (`app.js`, `index.html`, `api.php`). Se aplican las tres fugas elegidas; E, F y G quedan descartadas (ver Â§8).
    - **B. La categorÃ­a de origen es una rama.** `app.js` envÃ­a `subtree=1` en todas las consultas de productos, asÃ­ que al elegir `[3] DecoraciÃ³n` se ven los 2.563 productos de su rama (antes 24). El desplegable de hijas conserva la opciÃ³n **Â«ðŸ“„ Solo directosÂ»** (`data-subtree="0"`) para forzar el modo exacto de antes.
    - **C. Selector de tipo (eje secundario).** Nuevo `<select id="selectTipo">` con las **413 categorÃ­as** de las ramas `[3]/[9]/[10]` (mÃ¡s Â«Todos los tiposÂ»), que llama a `tipo=`. Solo esas ramas son tipos fÃ­sicos reales: las **17.560 filas** de `product_categories` que caen fuera son **ancestros de la categorÃ­a principal** (comprobado con `po_fuera.php`: 0 coinciden con la primaria, 0 son ajenas, 17.560 son ancestro), no tipos independientes. Con el selector, `[3]` pasa de 2.563 (primaria) a 7.123 (primaria o secundaria).
    - **D. El contador del selector es el de la rama.** Cada `<option>` muestra `(directos Â· productos en rama)` cuando difieren. El total de rama se calcula en `app.js` sumando `direct_product_count` de todo el subÃ¡rbol (verificado idÃ©ntico al `COUNT` del CTE recursivo). Ej.: `[18]` pasa de `(0)` a `(0 Â· 1.533 en rama)`, `[-5] Globos` a `(0 Â· 1.100 en rama)`.
    - **Coherencia de `api.php`:** con `subtree=1`, el cruce de un `tipo` con la categorÃ­a de origen tambiÃ©n usa la rama (CTE `arb_cat`) en vez de la primaria exacta. Sin Ã©l, `tipo=3&category_id=9` devolvÃ­a 0; ahora devuelve 13.
    - **VerificaciÃ³n:** `php -l` limpio; `pd_final.php` (0 fallos) y `k6_final.php` (0 fallos); UI con Edge headless (selector de tipo con 414 opciones, 19.033 productos en la vista global, etiquetas `directo Â· rama` en origen). Sin cambios en los contadores de los filtros existentes.
29. **Ronda de disfraces: 12 categorÃ­as nuevas y 310 productos realineados** (2026-10-07; respaldo `catalogo_tusdisfraces.db.bak-disfraces-20261007`, script `apply_q.php`). Se aplicaron en un solo lote los acuerdos con el usuario sobre la rama `[4] HALLOWEEN`:
    - **Cazafantasmas.** Nueva `-626 Disfraz Cazafantasmas (Ghostbusters)` (bajo `-135`, hermana de Freddy y Familia Addams) con los 8 disfraces que estaban repartidos entre `-140 Disfraz fantasma` (5) y `-246` (3). Secundarias: `-135` â†’ `[4]` â†’ `[10]`.
    - **Freddy Krueger.** A `-623` llegan 6 productos que estaban fuera: `#43200` y `#19307` (mÃ¡scaras, salÃ­an de `-129`/`-130`) mÃ¡s `#5667`, `#5693`, `#23034` y `#18878` â†’ `-623` pasa de 20 a **26**.
    - **IT / Pennywise unificados con payaso.** `#43978`, `#37804`, `#43975` (desde `-135`) y `#52840` (desde `-286`) se mueven a `-71 Disfraz Payaso` (41 â†’ **45**); las 4 mÃ¡scaras IT (`#43981`, `#39314`, `#45510`, `#24318`) reciben `-71` como secundaria temÃ¡tica, con lo que quedan consultables desde el payaso y desde su subcategorÃ­a de mÃ¡scara.
    - **Monster High.** `#46142`, `#30448`, `#49630`, `#30449` pasan a `-137` (12 â†’ **16**), a la vez que `#46142`/`#49630` salÃ­an de `Maquillaje cara y pinturas` (87 â†’ 85) y de `Disfraz monstruos clÃ¡sicos` (37 â†’ 35).
    - **Sangre falsa.** `#18869`, `#18872`, `#2254`, `#18328` (maquillaje) â†’ `-133 Efectos especiales y sangre` (56 â†’ **60**), y `#53250 Rosa blanca con sangre` â†’ `-79 Complementos Halloween` (550 â†’ **551**). El resto de los 146 productos con Â«sangreÂ» en el nombre ya estaba bien ubicado (props, cuchillos, tela y mantel se quedan en Halloween).
    - **DivisiÃ³n de `-129 Mascaras completas`** (344 productos, d4): 11 subcategorÃ­as nuevas `-627`â€¦`-637` con reparto por palabra clave sobre `name_norm` + 8 correcciones manuales (Michael Myers capturado por Â«zombieÂ», mÃ¡scara Â«similar payasoÂ» de La Purga, antigÃ¡s Breaking Bad, catwoman, pico de la peste, conejo Fortnite, Â«serial killer piel descamadaÂ»). Resultado: payaso 45, terror de cine y series 79, La Purga 32, Scream 25, calaveras y catrinas 20, brujas y demonios 20, zombis 15, animales 13, superhÃ©roes y cÃ³mic 20, monstruos clÃ¡sicos 9, fantasmas 5, y **61 genÃ©ricas** que siguen en `-129`. Chucky (4) y Rorschach (1) subieron de esas genÃ©ricas a cine/sÃºper-hÃ©roes.
    - **ConvenciÃ³n aplicada** (la de `moveProduct`): la primaria solo vive en `products.default_category_id`, no se crea fila auto en `product_categories`; cada producto movido recibe como secundarias los **ancestros del nuevo primario** sin `[1]` ni `[2]` (308 filas nuevas en 302 productos) y se recalcularon todos los `direct_product_count`.
    - **VerificaciÃ³n:** 19.033 productos, 645 categorÃ­as, 42.200 filas, 0 huÃ©rfanos, 0 filas colgantes, 0 auto-filas, 0 duplicados, 0 contadores desfasados, `PRAGMA integrity_check = ok`; contadores por API (`getProducts`) comprobados uno a uno (`-129` 60 directos / 343 en rama, `tipo=-628` 79, `-128`+`tipo=-629&subtree=1` 32â€¦); `arbol_categorias.txt` regenerado.
30. **DivisiÃ³n de los dos cumpleaÃ±os de licencia grandes: 12 subcategorÃ­as y 327 productos** (2026-10-07; respaldo `catalogo_tusdisfraces.db.bak-cumple-20261007`, script `apply_cumple.php`):
    - **Motivo.** `-32 CumpleaÃ±os Patrulla canina` (154 productos) y `15 CumpleaÃ±os Mickey Mouse` (175) eran las dos ramas planas mÃ¡s grandes de `[18] CUMPLEAÃ‘OS INFANTIL LICENCIA`; las siguientes son `-36 Princesas Disney` (120), `-20 Frozen` (89), `-43 Spiderman` (73) y `-39 Vengadores` (72), y las otras 41 hermanas siguen planas. Cada una de las dos se dividiÃ³ en **6 hijas de `d5`** con posiciones 0â€“5, con el nombre del tema al final siguiendo la convenciÃ³n de `-624 DecoraciÃ³n Halloween`: **Globos Â· Vajilla y mesa Â· DecoraciÃ³n y guirnaldas Â· PiÃ±atas y fiesta Â· Ropa infantil Â· Complementos** (nuevas `-638`â€¦`-649`).
    - **Reparto** por palabra clave sobre `name_norm` (primera coincidencia en orden) + 4 correcciones manuales: `#12580` Â«PiÃ±ata â€¦ globos con antifazÂ» â†’ piÃ±atas, `#36649` y `#51407` Â«braga cuello + caretaÂ» â†’ ropa, `#20657` Sacapuntas â†’ complementos. **Patrulla:** ropa 54, mesa 42, complementos 19, globos 17, piÃ±atas y fiesta 12, decoraciÃ³n 10 (0 sin clasificar). **Mickey:** mesa 47, ropa 35, globos 31, piÃ±atas y fiesta 24, decoraciÃ³n 19, complementos 17; solo `#36638 EP2026-SU` y `#36651 HO7583-SU` (cÃ³digos internos sin nombre descriptivo) siguen en el padre.
    - **ConvenciÃ³n de `moveProduct`:** sin fila auto para la primaria; cada producto movido recibe como secundaria el contenedor (`-32`/`15`), que es lo Ãºnico que faltaba â€”`6` y `18` ya estaban en los 329â€”. Sus nodos de tipo existentes (Globos `-5`, Platos `-7`, Servilletas `-615`, PiÃ±ata `-8`â€¦) **se conservan**, de modo que siguen filtrables por tipo y ahora ademÃ¡s navegables por tema.
    - **VerificaciÃ³n:** 19.033 productos, **657** categorÃ­as, **42.527** filas, 0 huÃ©rfanos, 0 colgantes, 0 auto-filas, 0 duplicados, 0 contadores/`depth` desfasados, `PRAGMA integrity_check = ok`; API (`getProducts`) con los 12 contadores nuevos exactos, `-32&subtree=1` 154, `15&subtree=1` 175 y `15` exacto 2; UI con Edge headless pinta las 12 hijas con sus recuentos; `arbol_categorias.txt` regenerado (665 lÃ­neas).
31. **DivisiÃ³n de `-37 Primera ComuniÃ³n`: 9 subcategorÃ­as, 360 productos y arreglo de `slugify()`** (2026-10-07, hito 31; respaldo `catalogo_tusdisfraces.db.bak-comunion-20261007`, script `apply_comunion.php`):
    - **Motivo.** `-37 Primera ComuniÃ³n` (343 productos) era una hoja plana a `d4` dentro de `[-207] Fiestas especiales`, y ademÃ¡s **17 productos de comuniÃ³n de El Informal vivÃ­an en otras ramas** (confeti de cruces en `-230 Confeti`, 5 letras de corcho en `-229`, guirnalda y banderÃ­n en `-224`/`-6`, 7 globos en `-212`/`-215`, cajita en `-194 Azul`, vela figura en `-53 Unicornio`), asÃ­ que el catÃ¡logo de comuniÃ³n no estaba ni completo ni navegable.
    - **9 hijas de `d5`** con posiciones 0â€“8, nombre con el tema al final (convenciÃ³n de `-624 DecoraciÃ³n Halloween`): **Globos (57) Â· DecoraciÃ³n y guirnaldas (30) Â· Letras y letreros (57) Â· Vajilla y mesa (22) Â· PiÃ±atas y toppers (18) Â· Figuras (30) Â· Fotos y papelerÃ­a (61) Â· Bolsas y cajas (65) Â· Detalles y regalos (20)**. Nuevas `-650`â€¦`-658`; 0 productos sin clasificar.
    - **Reparto** por palabra clave sobre `name_norm` (primera coincidencia en orden) + 3 correcciones manuales: `#35723` Â«Toppers para cupcake â€¦ pinchosÂ» y `#21960`/`#21961` Â«Pinchos â€¦ 63 cmÂ» (El Informal los cataloga como *Toppers*) â†’ piÃ±atas y toppers. Los 17 estraviados se asignaron a su grupo y **conservaron su categorÃ­a de tipo original como secundaria** (p. ej. `-229`, `-215`, `-230`), ademÃ¡s de recibir `-37` â†’ `-207` â†’ `6` como contexto: 409 filas de secundaria nuevas en 360 productos.
    - **Renombrado** `[-37] Primera Comunion` â†’ **`Primera ComuniÃ³n`** (le faltaba la tilde; el slug `primera-comunion` ya era correcto).
    - **Arreglo de `slugify()`**: `createCategory` y `renameCategory` generaban el slug sustituyendo cada **byte** no ASCII por `-`, asÃ­ que Â«PiÃ±atasÂ» daba `pi-atas`, Â«CumpleaÃ±osÂ» `cumplea-os` y Â«JubilaciÃ³nÂ» `jubilaci-n`. Ahora hay una funciÃ³n `slugify()` en `api.php` que translitera acentos y Ã± y limpia los guiones sobrantes de los extremos (antes Â«Payaso (freak)Â» daba `-payaso-freak-`). Se corrigieron en la misma pasada los 8 slugs rotos que habÃ­a dejado el hito 30 (`patulla` â†’ `patrulla` y `decoracin-n-y-â€¦`/`pin-atas-y-â€¦`); quedan **288 slugs provisionales** con el viejo malformado, anotados en Â§8.
    - **VerificaciÃ³n:** 19.033 productos, **666** categorÃ­as, **42.936** filas, 0 huÃ©rfanos, 0 colgantes, 0 auto-filas, 0 duplicados, 0 contadores/`depth` desfasados, `PRAGMA integrity_check = ok`; API (`getProducts`) con los 9 contadores nuevos exactos, `-37` exacto 0 y `-37&subtree=1` 360, `-207&subtree=1` 809; `renameCategory` probado end-to-end (`JubilaciÃ³n` â†’ `jubilacion`); UI con Edge headless pinta la rama con `(0 Â· 360 en rama)` y las 9 hijas con sus recuentos; `arbol_categorias.txt` regenerado (674 lÃ­neas).

32. **`-112 Set decoracion y globos cumpleaÃ±os` â†’ `Globos cumpleaÃ±os`: 26 productos a su casa** (2026-10-07, hito 32; respaldo `catalogo_tusdisfraces.db.bak-globos-20261007`, script `apply_globos.php`):
    - **Motivo.** Era el nodo mÃ¡s grande de `[-211] Material de fiesta` por 5Ã— (510 frente a 107 de PiÃ±atas) y **el nombre mentÃ­a**: 494 de 510 llevaban Â«globoÂ» y el resto era decoraciÃ³n variada (kits de AÃ±o Nuevo, una camiseta, finger foodâ€¦), mientras todos los hermanos de `-211` son de un solo tipo. Los ejes ya estaban bien montados â€”sus productos llevan `-211,-5,6`, igual que `-116 Guirnalda` lleva `-211,-4,6`â€”, asÃ­ que la ronda solo limpiÃ³ el contenido. Se descartÃ³ migrar los globos a la rama `[-5] Globos` (1.085 productos): solo **1** nombre coincide entre ambas ramas y la rama `-5` tiene **0** productos con Â«cumpleaÃ±osÂ» en el nombre frente a **157** aquÃ­; son dos lÃ­neas distintas (material de fiesta de cumpleaÃ±os vs. decoraciÃ³n profesional) y `-5` ya estÃ¡ como secundaria de contexto.
    - **26 movimientos** (16 sin Â«globoÂ» + 10 de otro tipo inequÃ­voco): 4 espirales, estrella, hojas y panel de lentejuelas â†’ `-625 DecoraciÃ³n colgante` (23 â†’ 30); 2 sets de decoraciÃ³n, puntos adhesivos y el set Â«globos y fondoÂ» â†’ **`-617 Kits y conjuntos` (0 â†’ 4)**; 2 kits de AÃ±o Nuevo â†’ `-607 Nochevieja y AÃ±o Nuevo` (164 â†’ 166); camiseta Soy Luna â†’ `-549 Camisetas` (8 â†’ 9); set finger food y 2 moldes de cup cakes â†’ `-15 Mesa dulce y candy bar` (26 â†’ 29); 2 cuerdas â†’ `-168 Lazos y cintas` (5 â†’ 7); piÃ±ata â†’ `-114` (107 â†’ 108); 2 guirnaldas â†’ `-116` (76 â†’ 78); 2 cortinas â†’ `-167` (2 â†’ 4); 2 juguetes â†’ `-180 Juguetes de fiesta` (11 â†’ 13).
    - **Renombrado** `[-112] Set decoracion y globos cumpleaÃ±os` â†’ **`Globos cumpleaÃ±os`**, con slug `set-decoracion-y-globos-cumplea-os` â†’ `globos-cumpleanos` (regenerado igual que harÃ­a `renameCategory`; nada en el front referencia slugs). Quedan **484** productos, todos globos o accesorios de globos, y **todos con la secundaria de contexto `-5 Globos`** (a 8 se le aÃ±adiÃ³; el resto ya la tenÃ­a).
    - **Secundarias**: 17 filas nuevas con la cadena de ancestros del destino (`3`, `5`, `-440` + `9`â€¦) + 8 filas `-5`. Los 5 productos que ya tenÃ­an su destino como secundaria (los espirales en `-625` y un set en `-617`) generaron auto-filas al moverse: se borraron, porque la primaria solo vive en `products.default_category_id`.
    - **VerificaciÃ³n:** 666 categorÃ­as, 19.033 productos, **42.956** filas (42.936 + 25 âˆ’ 5), 0 huÃ©rfanos, 0 auto-filas, 0 contadores desfasados, `integrity_check = ok`; API `-112` exacto **484** = `-112&subtree=1` **484**, `-617` 4; rama `-211` 1.302 â†’ 1.285 (17 salen de la rama, 9 se mueven entre hermanos); UI con Edge headless pinta `-112 Globos cumpleaÃ±os (484)` en los desplegables; `arbol_categorias.txt` regenerado (674 lÃ­neas).

33. **`-16 Bromas`: 183 productos ajenos a su casa** (2026-10-07, hito 33; respaldo `catalogo_tusdisfraces.db.bak-bromas-20261007`, script `apply_bromas.php`):
    - **Motivo.** `-16 Bromas` (409 productos, `d3`, sin hijas) era la mayor hermana de `[3] DECORACION` y **la mitad de su contenido no era broma**: 149 piezas de confeti (sueltos, sacos, bolsas, serpentinas y escarcha) cuando ya existÃ­a la hermana `[-230] Confeti`, 37 caÃ±ones de confeti con su hermana `[257] CaÃ±ones de confeti`, 18 disfraces de broma/adulto cuando `[249] Despedidas` ya vive ese estilo (68 disfraces), y ropa, gorros y navidad que tienen casa propia. **No hizo falta renombrar**: Â«BromasÂ» sigue describiendo lo que queda (226 productos reales).
    - **183 movimientos**: 37 caÃ±ones â†’ `[257] CaÃ±ones de confeti` (24 â†’ **61**); 112 de confeti/serpentina/escarcha â†’ `[-230] Confeti` (61 â†’ **173**); 2 globos con confeti â†’ `[-212] Globos lÃ¡tex lisos` (532 â†’ 534); 18 disfraces gag/adultos (Â«3Âª piernaÂ», Â«bolsa de cannabisÂ», Â«water de gomaÂ», Â«pedorretaÂ»â€¦) â†’ `[249] Despedidas` (129 â†’ **147**); 7 delantales graciosos + tanga Caperucita â†’ `[-440] Vestuario y prendas` (55 â†’ 63); zuecos â†’ `[-572] Zapatos` (15 â†’ 16); gorra fontanero â†’ `[-412] Sombreros, gorros y tocados` (68 â†’ 69); gorro navidad â†’ `[-598] Gorrosâ€¦ navideÃ±os` (9 â†’ 10); 2 papeles de WC navideÃ±os â†’ `[-601] Accesorios navideÃ±os` (33 â†’ 35); diadema Feliz AÃ±o Nuevo â†’ `[-607] Nochevieja y AÃ±o Nuevo` (166 â†’ 167).
    - **Se quedan** los que son broma aunque mencionen una ocasiÃ³n o un disfraz: Â«Te picante broma nocheviejaÂ», Â«Broma picante tanga elefanteÂ», Â«Tanga broma espermatozoidesÂ», Â«Pollo de goma para broma o disfrazÂ», los 5 de amigo invisible y las ~200 bromas clÃ¡sicas (calambres, dientes, ventosas, sangre, skunk, disfrazes gagâ€¦). La otra `[-122] Confeti` (bajo `[-211]`) es de cumpleaÃ±os (moldes, caÃ±ones), asÃ­ que la casa por cercanÃ­a es `-230`, en la misma rama `[3]`.
    - **Secundarias**: **+53 filas** (43.009 = 42.956 + 53) con la cadena de ancestros del destino (`-207`,`6` para Despedidas; `9`; `5`; `-507`,`9`; `-5`). Los destinos de `[257]` y `-230` comparten la rama con el origen, asÃ­ que ahÃ­ los productos ya tenÃ­an su Ãºnico contexto (`3`) y no se aÃ±adiÃ³ nada; ninguna auto-fila que borrar.
    - **VerificaciÃ³n:** 666 categorÃ­as, 19.033 productos, **43.009** filas, 0 huÃ©rfanos, 0 auto-filas, 0 contadores desfasados, `integrity_check = ok`; API `-16` exacto **226** = `-16&subtree=1` **226**, `tipo=-16&subtree=1` 227 (los 226 + el externo `#52884 Billetes 500 falsos`, que lleva `-16` como secundaria desde `-587 Fiesta Casino`), contadores de los 10 destinos exactos y `getProducts` global 19.033; UI con Edge headless sin errores (`Fatal error`/`Uncaught`/`PDOException` = 0); Ã¡rbol sin cambios de estructura (674 lÃ­neas).

> âš ï¸ Nota para futuros scripts SQL: **no confÃ­es en `ON DELETE CASCADE` / `ON DELETE SET NULL`**. SQLite no las aplica con `PRAGMA foreign_keys = 0`, que es el valor por defecto de PHP. Hay que borrar o anular las referencias a mano, tanto en `product_categories` como en `uncertain_cases`, y comprobar **tambiÃ©n** `products.default_category_id`, que no tiene clave forÃ¡nea declarada.

> âš ï¸ Nota para futuros scripts SQL: **`LIKE` no es un buscador de texto en mayÃºsculas**. `lower()`/`UPPER()` de SQLite solo pliega ASCII, asÃ­ que `LIKE '%AÃ‘O%'` no encuentra `aÃ±o` ni `aÃ±o`. Para comparar textos usa siempre las columnas `*_norm` (mismas reglas que `normalizar_texto()` en `normalizador.php`) y recuerda `ESCAPE '\'` al construir el patrÃ³n.

---

## 8. Pasos siguientes (pendientes)

### Estado de la auditorÃ­a de filtros (hitos 27 y 28)

Se auditaron los filtros de productos y se encontraron diez fugas. EstÃ¡n resueltas la **A** (hito 27: acentos, comodines y EAN) y las **B, C y D** (hito 28: rama de origen, selector de tipo y contadores de rama); **E, F y G** quedan fuera de esta ronda:

- âœ… **B. `category_id` era exacto sobre `default_category_id`.** Resuelta en el hito 28: la interfaz manda `subtree=1` por defecto y el desplegable de hijas ofrece Â«Solo directosÂ» para el modo exacto.
- âœ… **C. El eje secundario no tenÃ­a vista propia.** Resuelta en el hito 28 con el selector de tipo (`tipo=`).
- âœ… **D. El `(n)` del selector era `direct_product_count`, no el total de la rama.** Resuelta en el hito 28: ahora muestra `(directos Â· rama)`.
- **E. 14.095 productos (74 %)** tienen la primaria a `depth 4`, lo que hace el Ã¡rbol muy profundo para llegar al producto. (Descartada en esta ronda.)
- **F. 131 productos sin caracterÃ­sticas** no aparecen nunca en `feature_search`. (Descartada en esta ronda.)
- **G. 436 productos con nombre duplicado** (208 nombres distintos) complican la lectura de la tabla. (Descartada en esta ronda.)

### Pendientes de la reorganizaciÃ³n

- **Asignar tipo fÃ­sico a los 157 productos sin ninguna secundaria** â€” Tampoco tienen ninguna fila en `product_categories`. Su primaria es casi siempre una raÃ­z temÃ¡tica directa: `ACCESORIOS DISFRACES` (93), `CUMPLEAÃ‘OS Y FIESTAS TEMATICAS` (34), `DECORACION` (23), `DISFRACES` (7). Hasta entonces no aparecen en ningÃºn filtro por tipo. (Son un subconjunto del punto siguiente: les falta **toda** la secundaria; si se les asignara una en una rama `[3] [9] [10]`, quedarÃ­an tipificados.)
- **Asignar tipo fÃ­sico a los 1.822 productos sin nodo de tipo** (9,6 % del catÃ¡logo): cajas, decoraciÃ³n, figuras, invitaciones, pijamas, papel, textiles, botellas, llaveros, estuches, puzzlesâ€¦ Del recuento original de 1.829 (los 7 de la ronda de disfraces ya quedaron tipificados), algo mÃ¡s de 700 tienen un patrÃ³n reconocible en el nombre y el resto hay que decidirlos a mano. **DefiniciÃ³n:** sin ninguna fila en `product_categories` que apunte a las **413 categorÃ­as de las ramas `[3] DecoraciÃ³n`, `[9] Accesorios` y `[10] Disfraces`**, que son donde vive el eje secundario. (Cuenta no nulos en `default_category_id`, asÃ­ que incluye productos cuya primaria es una raÃ­z temÃ¡tica.)
- âœ… **Pobladas las 6 temÃ¡ticas conservadas** (2026-10-07) â€” `-587` Casino (9), `-588` Oeste (25), `-590` Orgullo (30), `-591` GraduaciÃ³n (23), `-592` Baloncesto (7) y `-593` Caballos (7): **101 productos** en total. Para cada uno se usÃ³ su temÃ¡tica en El Informal como **primaria** y se conservaron como **secundarias** la primaria anterior (el tipo fÃ­sico) y el contexto de rama (contenedor â€”`-585` Fiestas temÃ¡ticas / `-207` Fiestas especiales / `-209` CumpleaÃ±os infantil sin licenciaâ€” y la raÃ­z `6` CUMPLEAÃ‘OS Y FIESTAS TEMATICAS). Candidatos extraÃ­dos de `consulta_informal.csv` por coincidencia de la categorÃ­a de El Informal; evidencia y respaldo en `bak-punto1y3-20261007`.
- **Usar El Informal como guÃ­a de asignaciÃ³n** â€” Al decidir la categorÃ­a destino de cada producto, consultar su hoja por defecto del Tienda 3 (columna Â«Cat. InformalÂ» o el aviso al pulsar la referencia).
- **Revisar `uncertain_cases`** â€” Atender los casos dudosos registrados durante la reorganizaciÃ³n.
- âœ… **Reintroducidas las 7 categorÃ­as borradas** (2026-10-07) â€” `12` TALLER PERSONALIZACION, `227` AM, `239` Fiesta blanco, `258` CumpleaÃ±os Mickey, `259` Accesorios cumpleaÃ±os, `270` Fiesta fubol y `312` Cubiertos, con su `id_category`, padre, posiciÃ³n y `depth` originales (restauradas desde `bak-lote-20261005`). Se comprobÃ³ que no habÃ­a ningÃºn `id_category_proposed` ni `default_category_id` apuntando a ellas (0 referencias colgantes), asÃ­ que solo hubo que insertar las filas; siguen vacÃ­as. Total de categorÃ­as: 626 â†’ **633**. Respaldo previo: `bak-punto1y3-20261007`.
- âœ… **Ronda de disfraces: `-626`â€¦`-637` y 310 productos realineados** (2026-10-07, hito 29) â€” Nuevas `-626 Disfraz Cazafantasmas (Ghostbusters)` y las 11 subcategorÃ­as `-627`â€¦`-637` con las que se dividiÃ³ `-129 Mascaras completas` (344 â†’ 61 genÃ©ricas + 283 repartidas). AdemÃ¡s: 6 productos Freddy â†’ `-623` (20 â†’ 26), 4 disfraces Monster High â†’ `-137` (12 â†’ 16), IT/Pennywise unificado en `-71 Payaso` (41 â†’ 45) con `-71` como secundaria de sus 4 mÃ¡scaras, y la sangre falsa de maquillaje â†’ `-133` (56 â†’ 60) + el ramo rosa â†’ `-79`. Total: 633 â†’ **645** categorÃ­as, 41.892 â†’ **42.200** filas, 0 huÃ©rfanos y `integrity_check = ok`. Respaldo previo: `catalogo_tusdisfraces.db.bak-disfraces-20261007`.
- âœ… **Divididos los dos cumpleaÃ±os de licencia grandes** (2026-10-07, hito 30) â€” `-32 CumpleaÃ±os Patrulla canina` (154) y `15 CumpleaÃ±os Mickey Mouse` (175), las dos ramas planas mÃ¡s grandes de `[18] CUMPLEAÃ‘OS INFANTIL LICENCIA`, ahora tienen 6 hijas cada una (nuevas `-638`â€¦`-649`: globos, vajilla y mesa, decoraciÃ³n y guirnaldas, piÃ±atas y fiesta, ropa infantil y complementos). 327 productos movidos + 327 filas de secundaria; `-32`/`15` pasan a ser contenedores. Las otras 44 ramas de `[18]` siguen planas (Princesas Disney 120, Frozen 89, Spiderman 73, Vengadores 72, Cars 70â€¦): candidatas a lo mismo si se quiere. Respaldo previo: `catalogo_tusdisfraces.db.bak-cumple-20261007`.
- âœ… **Primera ComuniÃ³n dividida en 9 y completada** (2026-10-07, hito 31) â€” `[-37] Primera ComuniÃ³n` (renombrada: le faltaba la tilde) pasa de 343 productos planos a **9 hijas** `-650`â€¦`-658` (globos 57, decoraciÃ³n y guirnaldas 30, letras y letreros 57, vajilla y mesa 22, piÃ±atas y toppers 18, figuras 30, fotos y papelerÃ­a 61, bolsas y cajas 65, detalles y regalos 20) y se lleva dentro a los **17 productos de comuniÃ³n** que estaban repartidos por `-229`, `-212`, `-215`, `-230`, `-224`, `-6`, `-194` y `-53` (343 â†’ **360 en rama**, 0 directos). 360 movimientos + 409 filas de secundaria; el tipo original de cada estraviado se conserva como secundaria. AdemÃ¡s se arreglÃ³ `slugify()` en `api.php` (los slugs salÃ­an `pi-atas`, `cumplea-os`, `jubilaci-n`) y los 8 slugs rotos del hito 30. Respaldo previo: `catalogo_tusdisfraces.db.bak-comunion-20261007`.
- âœ… **`-112` renombrada a `Globos cumpleaÃ±os` y limpia de tipos ajenos** (2026-10-07, hito 32) â€” 510 â†’ **484** productos: los 26 que no eran globos (espirales, estrella, panel, kits de AÃ±o Nuevo, camiseta, finger food, moldes, cuerdas, piÃ±ata, guirnaldas, cortinas, juguetesâ€¦) se repartieron entre `-625`, `-617 Kits y conjuntos` (**0 â†’ 4**), `-607 Nochevieja y AÃ±o Nuevo`, `-549 Camisetas`, `-15 Mesa dulce`, `-168 Lazos y cintas`, `-114 PiÃ±atas`, `-116 Guirnalda`, `-167 Cortinas` y `-180 Juguetes de fiesta`; los 484 restantes llevan todos la secundaria de contexto `-5 Globos`. Se descartÃ³ moverlos a la rama `[-5] Globos`: no hay duplicado (1 nombre en comÃºn) y son lÃ­neas distintas. Respaldo previo: `catalogo_tusdisfraces.db.bak-globos-20261007`.
- âœ… **`-16 Bromas` limpia de tipos ajenos** (2026-10-07, hito 33) â€” 409 â†’ **226** productos: 149 de confeti â†’ `[-230] Confeti` (61 â†’ 173), 37 caÃ±ones â†’ `[257] CaÃ±ones de confeti` (24 â†’ 61), 18 disfraces gag/adultos â†’ `[249] Despedidas` (129 â†’ 147), 2 globos con confeti â†’ `[-212]`, 8 prendas â†’ `[-440] Vestuario`, y 1 cada uno a `[-572] Zapatos`, `[-412] Sombreros`, `[-598] Gorros navideÃ±os`, `[-601] Accesorios navideÃ±os` y `[-607] Nochevieja`. El nombre no cambiÃ³: Â«BromasÂ» sigue siendo correcto. 183 movimientos + 53 filas de secundaria (43.009 en total). Respaldo previo: `catalogo_tusdisfraces.db.bak-bromas-20261007`.
- **Pendientes que dejÃ³ esta ronda**: poblar las categorÃ­as vacÃ­as de decoraciÃ³n/atajo (`-622` CorsÃ©s, `-621` Manos, `-620` LÃ¡pidas, `-619` TelaraÃ±as, `-618` Calaveras, `-616` Manteles, `-615` Servilletas, `-614` Vasos), valorar dividir tambiÃ©n `-130 Caretas y antifaces` (117 productos, el mismo problema que tenÃ­a `-129`) y revisar los nombres que quedaron en `-129` tras la divisiÃ³n.
- **Normalizar los 288 slugs provisionales malformados** â€” `slugify()` ya genera slugs correctos, pero los creados antes siguen con un `-` en lugar de la letra acentuada (`cumplea-os-frozen`, `pi-ata`, `tem-ticas`, `celebraciones-del-a-o`) o con guiones sobrantes en los extremos (`-payaso-`, `jedi-obi-wan-yoda-rey-leia-`, `gafas-de-broma-`). Es cosmÃ©tico: nada en el front referencia los slugs y los IDs â‰¥ 0 conservan los suyos de PrestaShop; se arregla de golpe con `slugify(name)` sobre las categorÃ­as con ID negativo.
- **Exportar la propuesta** â€” Preparar un archivo (SQL/CSV) con los cambios de categorÃ­a y las categorÃ­as nuevas para revisiÃ³n y rÃ©plica en PrestaShop (los IDs provisionales negativos deben traducirse a IDs reales).
- **Ideas opcionales**: copiar la referencia desde el modal, reordenar categorÃ­as hermanas (`position`), editar el nombre/slug de categorÃ­as reales (el de provisionales ya estÃ¡ disponible en la secciÃ³n 7), o un informe/mapa de huÃ©rfanos resueltos por lote.

