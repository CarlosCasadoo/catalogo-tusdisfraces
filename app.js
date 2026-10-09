let paginaActual = 1;
let totalPaginas = 1;
let todasLasCategorias = [];
let categoriasPorId = {};
let hijosPorPadre = {};
let timeoutBusqueda = null;
let ordenProductos = ''; // '' | 'name_asc' | 'name_desc'
// Total de productos de la rama (categoría + todos sus descendientes) por ID.
// Se calcula en el cliente sumando direct_product_count, que ya se verificó
// que coincide con COUNT(products) por categoría: es el mismo número que
// devuelve getProducts con subtree=1, así el (n) del selector y la tabla
// nunca dan cifras distintas.
let totalesRama = {};
// Ramas del eje secundario (tipos físicos): [3] Decoración, [9] Accesorios,
// [10] Disfraces. Su enumeración alimenta el selector de tipo (fuga C).
const RAICES_TIPO = [3, 9, 10];

// (D) Etiqueta «directos · en rama» para los contadores de los buscadores de
// categorías: refleja lo mismo que verá la tabla con subtree=1.
function etiquetaConteo(id) {
    const cat = categoriasPorId[id];
    const directo = cat ? (cat.direct_product_count || 0) : 0;
    const rama = totalesRama[id] !== undefined ? totalesRama[id] : directo;
    return rama > directo ? `${directo} · ${rama} en rama` : `${directo}`;
}

document.addEventListener('DOMContentLoaded', () => {
    inicializarEventos();
    // La 1ª carga de productos la dispara cargarCategorias() en cuanto el <select> ya tiene
    // opciones. Si se llamara aquí también, saldrían dos peticiones en vuelo y la que se
    // resolviera última (category_id=-1 -> huérfanos, 0 productos) vaciaba la tabla.
    cargarCategorias.pendienteCargaProductos = true;
    cargarCategorias();
});

function inicializarEventos() {
    document.getElementById('selectOrigen').addEventListener('change', () => {
        const catPadreId = parseInt(document.getElementById('selectOrigen').value, 10);
        actualizarSelectorHijas(catPadreId);
        cargarProductos(1);
    });

    const selectOrigenHija = document.getElementById('selectOrigenHija');
    if (selectOrigenHija) {
        selectOrigenHija.addEventListener('change', () => {
            cargarProductos(1);
        });
    }

    // (C) Selector del eje secundario (tipo físico): recalcula la tabla al cambiar
    const selectTipo = document.getElementById('selectTipo');
    if (selectTipo) {
        selectTipo.addEventListener('change', () => {
            cargarProductos(1);
        });
    }

    // 1. Filtro progresivo en tiempo real por nombre de producto
    const inputBuscar = document.getElementById('inputBuscarProducto');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', () => {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                cargarProductos(1);
            }, 250);
        });
    }

    // 2. Filtro progresivo en tiempo real por característica / feature
    const inputBuscarFeature = document.getElementById('inputBuscarFeature');
    if (inputBuscarFeature) {
        inputBuscarFeature.addEventListener('input', () => {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                cargarProductos(1);
            }, 250);
        });
    }

    // Al enfocar un buscador con texto ya escrito, seleccionar todo para borrarlo o sobrescribirlo directamente
    document.querySelectorAll('#inputBuscarProducto, #inputBuscarFeature, #buscarCatOrigen, #buscarCatDestino, #nuevoNombreCat').forEach(input => {
        input.addEventListener('focus', () => input.select());
    });

    // Ordenación por nombre: clic en el encabezado «Nombre» alterna A→Z / Z→A
    const thNombre = document.getElementById('thNombre');
    if (thNombre) {
        thNombre.addEventListener('click', () => {
            ordenProductos = ordenProductos === 'name_asc' ? 'name_desc' : 'name_asc';
            actualizarIndicadorOrden();
            cargarProductos(1);
        });
    }

    // 2. Buscador rápido de categorías de destino
    const inputBuscarCatDestino = document.getElementById('buscarCatDestino');
    const resultadosCatDestino = document.getElementById('resultadosCatDestino');
    if (inputBuscarCatDestino && resultadosCatDestino) {
        inputBuscarCatDestino.addEventListener('input', (e) => {
            const termino = e.target.value.trim().toLowerCase();
            if (!termino) {
                resultadosCatDestino.style.display = 'none';
                resultadosCatDestino.innerHTML = '';
                return;
            }

            const coincidencias = todasLasCategorias.filter(c => 
                c.id_category != -1 && c.name.toLowerCase().includes(termino)
            );

            if (coincidencias.length === 0) {
                resultadosCatDestino.innerHTML = '<div style="padding: 8px 12px; color: #888; font-size: 0.85rem;">No se encontraron categorías con ese nombre.</div>';
            } else {
                let html = '';
                coincidencias.forEach(cat => {
                    const tagProvisional = cat.is_new ? ' [Provisional]' : '';
                    html += `
                        <div class="category-result-item" data-id="${cat.id_category}" data-name="${escapeHtml(cat.name)}">
                            <span>📁 <strong>${escapeHtml(cat.name)}</strong>${tagProvisional}</span>
                            <span class="category-result-badge">ID: ${cat.id_category} (${etiquetaConteo(cat.id_category)} prods)</span>
                        </div>
                    `;
                });
                resultadosCatDestino.innerHTML = html;

                // Añadir evento click a cada resultado sugerido
                resultadosCatDestino.querySelectorAll('.category-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const idCat = item.getAttribute('data-id');
                        const nombreCat = item.getAttribute('data-name');
                        
                        const selectDestino = document.getElementById('selectDestino');
                        if (selectDestino) {
                            selectDestino.value = idCat;
                        }
                        inputBuscarCatDestino.value = nombreCat;
                        resultadosCatDestino.style.display = 'none';
                    });
                });
            }
            resultadosCatDestino.style.display = 'block';
        });

        // Cerrar lista al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!inputBuscarCatDestino.contains(e.target) && !resultadosCatDestino.contains(e.target)) {
                resultadosCatDestino.style.display = 'none';
            }
        });
    }

    // 1b. Buscador rápido de categorías de origen (filtrar origen por nombre)
    const inputBuscarCatOrigen = document.getElementById('buscarCatOrigen');
    const resultadosCatOrigen = document.getElementById('resultadosCatOrigen');
    if (inputBuscarCatOrigen && resultadosCatOrigen) {
        inputBuscarCatOrigen.addEventListener('input', (e) => {
            const termino = e.target.value.trim().toLowerCase();
            if (!termino) {
                resultadosCatOrigen.style.display = 'none';
                resultadosCatOrigen.innerHTML = '';
                return;
            }

            const coincidencias = todasLasCategorias.filter(c => c.name.toLowerCase().includes(termino));

            if (coincidencias.length === 0) {
                resultadosCatOrigen.innerHTML = '<div style="padding: 8px 12px; color: #888; font-size: 0.85rem;">No se encontraron categorías con ese nombre.</div>';
            } else {
                let html = '';
                coincidencias.forEach(cat => {
                    const tagProvisional = cat.is_new ? ' [Provisional]' : '';
                    html += `
                        <div class="category-result-item" data-id="${cat.id_category}" data-name="${escapeHtml(cat.name)}">
                            <span>📁 <strong>${escapeHtml(cat.name)}</strong>${tagProvisional}</span>
                            <span class="category-result-badge">ID: ${cat.id_category} (${etiquetaConteo(cat.id_category)} prods)</span>
                        </div>
                    `;
                });
                resultadosCatOrigen.innerHTML = html;

                // Elegir una sugerencia fija el origen, actualiza la rama hija y recarga productos
                resultadosCatOrigen.querySelectorAll('.category-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const idCat = item.getAttribute('data-id');
                        const nombreCat = item.getAttribute('data-name');
                        const selectOrigen = document.getElementById('selectOrigen');
                        if (!selectOrigen) return;
                        selectOrigen.value = idCat;
                        inputBuscarCatOrigen.value = nombreCat;
                        resultadosCatOrigen.style.display = 'none';
                        actualizarSelectorHijas(parseInt(idCat, 10));
                        cargarProductos(1);
                    });
                });
            }
            resultadosCatOrigen.style.display = 'block';
        });

        // Cerrar lista al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!inputBuscarCatOrigen.contains(e.target) && !resultadosCatOrigen.contains(e.target)) {
                resultadosCatOrigen.style.display = 'none';
            }
        });
    }

    document.getElementById('btnMover').addEventListener('click', moverSeleccionados);
    document.getElementById('btnAsignarSecundaria').addEventListener('click', asignarCategoriaSecundaria);
    document.getElementById('btnCrearCat').addEventListener('click', crearCategoria);
    document.getElementById('btnMoverCat').addEventListener('click', moverCategoria);
    document.getElementById('btnRegistrarDudaGeneral').addEventListener('click', registrarDudaGeneral);
    document.getElementById('checkTodos').addEventListener('change', toggleTodos);

    // Eliminar categoría provisional: se enlaza UNA sola vez aquí (no dentro de
    // cargarCategorias) para evitar que el confirm() se dispare varias veces.
    const selectEliminarCat = document.getElementById('selectEliminarCat');
    const btnEliminarCat = document.getElementById('btnEliminarCat');
    if (selectEliminarCat && btnEliminarCat) {
        selectEliminarCat.addEventListener('change', () => {
            btnEliminarCat.disabled = !selectEliminarCat.value;
        });
        btnEliminarCat.addEventListener('click', eliminarCategoriaProvisional);
    }

    // Editar nombre de categoría provisional: también se enlaza UNA sola vez
    const selectEditarCat = document.getElementById('selectEditarCat');
    const btnGuardarNombreCat = document.getElementById('btnGuardarNombreCat');
    if (selectEditarCat && btnGuardarNombreCat) {
        selectEditarCat.addEventListener('change', () => {
            const inputNombre = document.getElementById('nuevoNombreCat');
            if (!inputNombre) return;
            const catSel = categoriasPorId[parseInt(selectEditarCat.value, 10)];
            inputNombre.value = catSel ? catSel.name : '';
        });
        btnGuardarNombreCat.addEventListener('click', guardarNombreCategoriaProvisional);
    }
    
    // Control para mostrar/ocultar selector de rama padre según la ubicación elegida
    const tipoUbicacion = document.getElementById('tipoUbicacionCat');
    if (tipoUbicacion) {
        tipoUbicacion.addEventListener('change', (e) => {
            const contenedorPadre = document.getElementById('contenedorPadreCat');
            if (e.target.value === 'rama') {
                contenedorPadre.style.display = 'block';
            } else {
                contenedorPadre.style.display = 'none';
            }
        });
    }

    document.getElementById('btnAnterior').addEventListener('click', () => {
        if (paginaActual > 1) cargarProductos(paginaActual - 1);
    });
    document.getElementById('btnSiguiente').addEventListener('click', () => {
        if (paginaActual < totalPaginas) cargarProductos(paginaActual + 1);
    });
    // Botones Anterior/Siguiente adicionales arriba de la tabla (mismo comportamiento que los inferiores)
    const btnAnteriorArriba = document.getElementById('btnAnteriorArriba');
    if (btnAnteriorArriba) {
        btnAnteriorArriba.addEventListener('click', () => {
            if (paginaActual > 1) cargarProductos(paginaActual - 1);
        });
    }
    const btnSiguienteArriba = document.getElementById('btnSiguienteArriba');
    if (btnSiguienteArriba) {
        btnSiguienteArriba.addEventListener('click', () => {
            if (paginaActual < totalPaginas) cargarProductos(paginaActual + 1);
        });
    }

    // Modal del árbol de categorías (botón para abrirlo)
    const btnVerArbol = document.getElementById('btnVerArbol');
    if (btnVerArbol) {
        btnVerArbol.addEventListener('click', mostrarArbolCategorias);
    }

    // Cierre del modal de la categoría informal (botón × y clic fuera)
    const btnCerrarModal = document.getElementById('btnCerrarModalInformal');
    if (btnCerrarModal) {
        btnCerrarModal.addEventListener('click', cerrarModalInformal);
    }
    const overlayModal = document.getElementById('modalInformal');
    if (overlayModal) {
        overlayModal.addEventListener('click', (e) => {
            if (e.target === overlayModal) cerrarModalInformal();
        });
    }

    // Cierre del modal del árbol (botón × y clic fuera)
    const btnCerrarModalArbol = document.getElementById('btnCerrarModalArbol');
    if (btnCerrarModalArbol) {
        btnCerrarModalArbol.addEventListener('click', cerrarModalArbol);
    }
    const overlayArbol = document.getElementById('modalArbol');
    if (overlayArbol) {
        overlayArbol.addEventListener('click', (e) => {
            if (e.target === overlayArbol) cerrarModalArbol();
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarModalInformal();
            cerrarModalArbol();
        }
    });
}

// 1. Cargar lista de categorías estructuradas en árbol en selectores
function cargarCategorias(categoriaSeleccionadaDestino = null, categoriaEditarRestaurar = null) {
    fetch('api.php?action=getCategories')
        .then(res => res.json())
        .then(res => {
            if (!res.success) return mostrarAviso(res.error, 'error');
            
            todasLasCategorias = res.data;
            categoriasPorId = {};
            hijosPorPadre = {};

            // Mapear categorías e hijos directos
            todasLasCategorias.forEach(cat => {
                categoriasPorId[cat.id_category] = cat;
                const pId = cat.parent_id !== null ? parseInt(cat.parent_id, 10) : null;
                if (!hijosPorPadre[pId]) {
                    hijosPorPadre[pId] = [];
                }
                hijosPorPadre[pId].push(cat.id_category);
            });

            // Totales de rama (arreglo D): suma de direct_product_count del nodo
            // y de todos sus descendientes. Es el mismo número que devuelve
            // getProducts con subtree=1, así el contador del selector y la tabla
            // cuadran. La guarda por nodos en curso evita bucles si un parent_id
            // apuntara a un ancestro (el árbol es editable desde la interfaz).
            totalesRama = {};
            const nodosEnCurso = new Set();
            const calcularTotalRama = (id) => {
                if (totalesRama[id] !== undefined) return totalesRama[id];
                const cat = categoriasPorId[id];
                if (!cat) return 0;
                if (nodosEnCurso.has(id)) return 0;
                nodosEnCurso.add(id);
                let suma = cat.direct_product_count || 0;
                (hijosPorPadre[id] || []).forEach(h => { suma += calcularTotalRama(h); });
                nodosEnCurso.delete(id);
                totalesRama[id] = suma;
                return suma;
            };
            todasLasCategorias.forEach(cat => calcularTotalRama(cat.id_category));

            // (C) Eje secundario: solo las ramas de tipo (3, 9, 10) y sus
            // descendientes son tipos físicos reales. El resto de filas de
            // product_categories que caen fuera de esas ramas son ancestros de la
            // categoría principal del producto, no tipos independientes.
            const idsTipo = new Set();
            const marcarRamaTipo = (id) => {
                if (idsTipo.has(id)) return;
                idsTipo.add(id);
                (hijosPorPadre[id] || []).forEach(marcarRamaTipo);
            };
            RAICES_TIPO.forEach(marcarRamaTipo);

            // Preservar el tipo elegido antes de reconstruir el <select>.
            const selectTipo = document.getElementById('selectTipo');
            const tipoActual = selectTipo ? selectTipo.value : '';

            const selectOrigen = document.getElementById('selectOrigen');
            const selectDestino = document.getElementById('selectDestino');
            const selectPadreCat = document.getElementById('selectPadreCat');
            const selectCatMover = document.getElementById('selectCatMover');
            const selectNuevoPadre = document.getElementById('selectNuevoPadre');
            const selectEditarCat = document.getElementById('selectEditarCat');
            
            // "" = todavía no hay ninguna selección real (primera carga). Antes se usaba
            // "|| '-1'", que dejaba la interfaz siempre en «Sin Categoría Activa (Huérfanos)»
            // — un nodo que getCategories sintetiza con 0 productos — y por tanto la tabla
            // salía vacía al abrir.
            const valorOrigenActual = selectOrigen.value || "";
            const valorDestinoActual = categoriaSeleccionadaDestino || selectDestino.value || "";
            const valorPadreActual = selectPadreCat ? selectPadreCat.value : "";
            const valorMoverActual = selectCatMover ? selectCatMover.value : "";
            const valorNuevoPadreActual = selectNuevoPadre ? selectNuevoPadre.value : "";
            const valorEditarActual = (categoriaEditarRestaurar !== null && categoriaEditarRestaurar !== undefined)
                ? String(categoriaEditarRestaurar)
                : (selectEditarCat ? selectEditarCat.value : "");

            let htmlOpciones = '';
            let htmlOpcionesPadre = '';
            let htmlOpcionesHojas = '';

            // Primera opción: vista global. Antes la interfaz arrancaba en category_id=-1
            // (huérfanos) y, como no existe la categoría -1, el <select> se quedaba sin
            // valor -> la tabla salía vacía al abrir.
            htmlOpciones += '<option value="0">📦 Todos los productos (sin filtro)</option>';

            todasLasCategorias.forEach(cat => {
                const tagProvisional = cat.is_new ? ' [Provisional]' : '';
                const prefix = cat.tree_prefix || '';
                // (D) El contador de siempre (directo) más el total de la rama
                // cuando difieren: antes [18] enseñaba (0) y tenía 1.533
                // productos debajo. Con subtree=1 la tabla muestra la rama.
                const directo = cat.direct_product_count || 0;
                const rama = totalesRama[cat.id_category] !== undefined ? totalesRama[cat.id_category] : directo;
                const conteo = rama > directo ? `${directo} · ${rama} en rama` : `${directo}`;
                const opcionTexto = `${prefix}${escapeHtml(cat.name)}${tagProvisional} (${conteo})`;
                
                htmlOpciones += `<option value="${cat.id_category}">${opcionTexto}</option>`;
                
                // Para categorías padre válidas (excluimos huérfanos -1)
                if (cat.id_category != -1) {
                    htmlOpcionesPadre += `<option value="${cat.id_category}">${prefix}${escapeHtml(cat.name)}${tagProvisional}</option>`;
                }

                // Para categorías hoja (sin hijos, y que no sean Raíz 1 ni Inicio 2 ni Huérfanos -1)
                const tieneHijos = hijosPorPadre[cat.id_category] && hijosPorPadre[cat.id_category].length > 0;
                if (!tieneHijos && cat.id_category > 2) {
                    htmlOpcionesHojas += `<option value="${cat.id_category}">${escapeHtml(cat.name)}${tagProvisional} (ID: ${cat.id_category})</option>`;
                }
            });

            // Populate selectors
            selectOrigen.innerHTML = htmlOpciones;
            selectDestino.innerHTML = htmlOpciones;
            if (selectPadreCat) {
                selectPadreCat.innerHTML = htmlOpcionesPadre;
            }

            // (C) Poblar el selector del eje secundario (tipo físico) con las
            // ramas 3/9/10 y sus descendientes, respetando el orden del árbol.
            if (selectTipo) {
                let htmlTipo = '<option value="">Todos los tipos (sin filtro)</option>';
                todasLasCategorias.forEach(cat => {
                    if (!idsTipo.has(cat.id_category)) return;
                    const tagProvisional = cat.is_new ? ' [Provisional]' : '';
                    htmlTipo += `<option value="${cat.id_category}">${cat.tree_prefix || ''}${escapeHtml(cat.name)}${tagProvisional} (${cat.direct_product_count || 0})</option>`;
                });
                selectTipo.innerHTML = htmlTipo;
                if (tipoActual && selectTipo.querySelector(`option[value="${tipoActual}"]`)) {
                    selectTipo.value = tipoActual;
                }
            }

            // Build filtered options
            let htmlOpcionesUsuario = '';
            let htmlOpcionesProvisionales = '';
            todasLasCategorias.forEach(cat => {
                const tag = cat.is_new ? ' [Provisional]' : '';
                const pref = cat.tree_prefix || '';
                // Include all user categories: exclude system (1,2) and Huérfanos (-1), but allow provisional (id < 0)
                if (cat.id_category > 2 || cat.id_category < 0) {
                    htmlOpcionesUsuario += `<option value="${cat.id_category}">${pref}${escapeHtml(cat.name)}${tag}</option>`;
                }
                // Only provisional categories (id < 0) for deletion
                if (cat.id_category < 0) {
                    htmlOpcionesProvisionales += `<option value="${cat.id_category}">${pref}${escapeHtml(cat.name)}${tag}</option>`;
                }
            });

            if (selectNuevoPadre) {
                // Destination selector – allow categories with children, but hide system categories
                selectNuevoPadre.innerHTML = htmlOpcionesUsuario;
            }
            // Populate selector for moving category – allow any user category (excluding system)
            if (selectCatMover) {
                selectCatMover.innerHTML = htmlOpcionesUsuario;
            }

            // Populate selector for deleting provisional categories
            const selectEliminarCat = document.getElementById('selectEliminarCat');
            if (selectEliminarCat) {
                selectEliminarCat.innerHTML = htmlOpcionesProvisionales;
            }

            // Populate selector for renaming provisional categories (mismo listado)
            if (selectEditarCat) {
                selectEditarCat.innerHTML = htmlOpcionesProvisionales;
                if (valorEditarActual && selectEditarCat.querySelector(`option[value="${valorEditarActual}"]`)) {
                    selectEditarCat.value = valorEditarActual;
                }
                const inputNuevoNombre = document.getElementById('nuevoNombreCat');
                if (inputNuevoNombre && selectEditarCat.value) {
                    const catEd = categoriasPorId[parseInt(selectEditarCat.value, 10)];
                    inputNuevoNombre.value = catEd ? catEd.name : '';
                }
            }

            // Restaurar o fijar valores seleccionados
            if (valorOrigenActual && selectOrigen.querySelector(`option[value="${valorOrigenActual}"]`)) {
                selectOrigen.value = valorOrigenActual;
            } else {
                selectOrigen.value = "0";
            }

            if (valorDestinoActual && selectDestino.querySelector(`option[value="${valorDestinoActual}"]`)) {
                selectDestino.value = valorDestinoActual;
            }

            if (valorPadreActual && selectPadreCat && selectPadreCat.querySelector(`option[value="${valorPadreActual}"]`)) {
                selectPadreCat.value = valorPadreActual;
            }

            if (valorMoverActual && selectCatMover && selectCatMover.querySelector(`option[value="${valorMoverActual}"]`)) {
                selectCatMover.value = valorMoverActual;
            }

            if (valorNuevoPadreActual && selectNuevoPadre && selectNuevoPadre.querySelector(`option[value="${valorNuevoPadreActual}"]`)) {
                selectNuevoPadre.value = valorNuevoPadreActual;
            }

            // Actualizar el selector de subcategorías hijas para la categoría de origen activa
            actualizarSelectorHijas(parseInt(selectOrigen.value, 10));

            // cargarCategorias() es asíncrono: cuando arranca antes de que el <select> tenga
            // opciones, la carga inicial se fue con category_id=-1 (huérfanos = 0 productos)
            // y la tabla se quedaba vacía. Recargamos con la categoría ya resuelta.
            if (cargarCategorias.pendienteCargaProductos) {
                cargarCategorias.pendienteCargaProductos = false;
                cargarProductos(1);
            }
        });
}

// Actualizar el desplegable de categorías hijas cuando se selecciona un padre
function actualizarSelectorHijas(catPadreId) {
    const contenedorHijas = document.getElementById('contenedorOrigenHija');
    const selectHija = document.getElementById('selectOrigenHija');
    if (!contenedorHijas || !selectHija) return;

    // Verificar si la categoría tiene hijas directas o descendientes
    const tieneHijas = hijosPorPadre[catPadreId] && hijosPorPadre[catPadreId].length > 0;

    if (!tieneHijas || catPadreId === -1) {
        contenedorHijas.style.display = 'none';
        selectHija.innerHTML = '';
        return;
    }

    const catPadre = categoriasPorId[catPadreId] || { name: 'Padre', direct_product_count: 0 };
    const directoPadre = catPadre.direct_product_count || 0;
    const ramaPadre = totalesRama[catPadreId] !== undefined ? totalesRama[catPadreId] : directoPadre;
    const conteoPadre = ramaPadre > directoPadre ? `${directoPadre} · ${ramaPadre} en rama` : `${directoPadre}`;

    // (B) El origen se interpreta como rama completa por defecto (subtree=1);
    // la opción «Solo directos» fuerza subtree=0. Ambas comparten value (el id)
    // y se distinguen por data-subtree, que lee cargarProductos().
    let htmlHijas =
        `<option value="${catPadreId}" data-subtree="1">📌 Rama completa de: ${escapeHtml(catPadre.name)} (${conteoPadre})</option>` +
        `<option value="${catPadreId}" data-subtree="0">📄 Solo directos de: ${escapeHtml(catPadre.name)} (${directoPadre})</option>`;

    // Recorrer árbol de descendientes de este padre de forma recursiva
    const construirDescendientes = (idActual, nivel = 1) => {
        const hijos = hijosPorPadre[idActual] || [];
        hijos.forEach((hijoId, idx) => {
            const hijo = categoriasPorId[hijoId];
            if (!hijo) return;

            const isLast = (idx === hijos.length - 1);
            const tagProvisional = hijo.is_new ? ' [Provisional]' : '';
            const sangria = '&nbsp;'.repeat((nivel - 1) * 4);
            const simbolo = isLast ? '└── ' : '├── ';
            const directoHijo = hijo.direct_product_count || 0;
            const ramaHijo = totalesRama[hijoId] !== undefined ? totalesRama[hijoId] : directoHijo;
            const conteoHijo = ramaHijo > directoHijo ? `${directoHijo} · ${ramaHijo} en rama` : `${directoHijo}`;

            htmlHijas += `<option value="${hijo.id_category}" data-subtree="1">${sangria}${simbolo}${escapeHtml(hijo.name)}${tagProvisional} (${conteoHijo})</option>`;

            // Llamada recursiva para sub-hijas
            construirDescendientes(hijo.id_category, nivel + 1);
        });
    };

    construirDescendientes(catPadreId, 1);

    selectHija.innerHTML = htmlHijas;
    contenedorHijas.style.display = 'block';
}

// Actualizar el indicador ▲/▼ del encabezado «Nombre» según el orden activo
function actualizarIndicadorOrden() {
    const th = document.getElementById('thNombre');
    if (!th) return;
    const flecha = ordenProductos === 'name_asc' ? ' ▲' : ordenProductos === 'name_desc' ? ' ▼' : '';
    th.textContent = 'Nombre' + flecha;
}

// 2. Cargar tabla de productos (respetando subcategoría hija y búsqueda por texto)
function cargarProductos(pagina) {
    paginaActual = pagina;
    
    // Ojo: 0 = "Todos los productos" es un valor válido y NO debe degradarse a -1.
    const valorOrigen = document.getElementById('selectOrigen').value;
    let catId = valorOrigen === '' ? -1 : (parseInt(valorOrigen, 10) || 0);
    const contenedorHijas = document.getElementById('contenedorOrigenHija');
    const selectHija = document.getElementById('selectOrigenHija');

    // (B) El origen es una rama por defecto (subtree=1). Si el desplegable de
    // hijas ofrece «Solo directos» (data-subtree="0") se respeta esa elección.
    let subtree = true;
    if (contenedorHijas && contenedorHijas.style.display !== 'none' && selectHija && selectHija.value) {
        catId = parseInt(selectHija.value, 10);
        const opcion = selectHija.selectedOptions && selectHija.selectedOptions[0];
        subtree = !opcion || opcion.dataset.subtree !== '0';
    }

    // (C) Tipo del eje secundario (opcional).
    const selectTipo = document.getElementById('selectTipo');
    const tipo = selectTipo && selectTipo.value !== '' ? parseInt(selectTipo.value, 10) : null;

    const inputBuscar = document.getElementById('inputBuscarProducto');
    const textoBusqueda = inputBuscar ? encodeURIComponent(inputBuscar.value.trim()) : '';
    const inputBuscarFeature = document.getElementById('inputBuscarFeature');
    const featureBusqueda = inputBuscarFeature ? encodeURIComponent(inputBuscarFeature.value.trim()) : '';
    const sort = ordenProductos ? encodeURIComponent(ordenProductos) : '';
    const url = `api.php?action=getProducts&category_id=${catId}&page=${pagina}&search=${textoBusqueda}&subtree=${subtree ? 1 : 0}` +
                (tipo !== null ? `&tipo=${tipo}` : '') +
                (featureBusqueda ? `&feature_search=${featureBusqueda}` : '') +
                (sort ? `&sort=${sort}` : '');
    fetch(url)
        .then(res => res.json())
        .then(res => {
            if (!res.success) return mostrarAviso(res.error, 'error');
            
            totalPaginas = res.total_pages || 1;
            const tbody = document.getElementById('tablaProductos');
            tbody.innerHTML = '';

            if (res.data.length === 0) {
                const tr = document.createElement('tr');
                const mensaje = textoBusqueda !== '' 
                    ? 'No se encontraron productos que coincidan con la búsqueda.' 
                    : 'No hay productos en esta categoría.';
                tr.innerHTML = `<td colspan="7" style="text-align: center; color: #777; padding: 20px;">${mensaje}</td>`;
                tbody.appendChild(tr);
            } else {
                res.data.forEach(p => {
                    const tr = document.createElement('tr');

                    const tdCheck = document.createElement('td');
                    tdCheck.className = 'checkbox-col';
                    const check = document.createElement('input');
                    check.type = 'checkbox';
                    check.className = 'check-producto';
                    check.value = p.id_product;
                    tdCheck.appendChild(check);

                    const tdId = document.createElement('td');
                    tdId.textContent = p.id_product;

                    const tdNombre = document.createElement('td');
                    tdNombre.textContent = p.name;

                    const tdRef = document.createElement('td');
                    const tieneInformal = !!(p.informal_hoja || p.informal_familia);
                    if (p.reference) {
                        const spanRef = document.createElement('span');
                        spanRef.textContent = p.reference;
                        spanRef.className = 'ref-link';
                        spanRef.title = tieneInformal
                            ? 'Pulsa para ver la categoría de este producto en El Informal'
                            : 'Este producto no tiene categoría registrada en El Informal';
                        if (tieneInformal) {
                            spanRef.classList.add('ref-clickable');
                            spanRef.onclick = (e) => { e.stopPropagation(); mostrarCategoriaInformal(p); };
                        }
                        tdRef.appendChild(spanRef);
                    } else {
                        tdRef.textContent = '-';
                    }

                    const tdInformal = document.createElement('td');
                    tdInformal.className = 'col-informal';
                    if (p.informal_hoja) {
                        tdInformal.textContent = p.informal_hoja;
                        tdInformal.title = 'Categoría por defecto en El Informal (Tienda 3)';
                    } else {
                        tdInformal.textContent = '—';
                        tdInformal.title = 'Sin categoría en la consulta de El Informal';
                        tdInformal.classList.add('sin-informal');
                    }

                    const tdAcciones = document.createElement('td');
                    // Columna de categorías secundarias (chips con botón ✕ para quitarlas)
                    const tdSecundarias = document.createElement('td');
                    tdSecundarias.className = 'col-secundarias';
                    const secs = p.secondary_categories || [];
                    if (secs.length === 0) {
                        tdSecundarias.textContent = '—';
                        tdSecundarias.title = 'Sin categorías secundarias';
                        tdSecundarias.classList.add('sin-informal');
                    } else {
                        secs.forEach(sec => {
                            const chip = document.createElement('span');
                            chip.className = 'chip-secundaria';
                            chip.title = `Categoría secundaria: ${sec.name} (ID ${sec.id}) · pulsa ✕ para quitarla`;
                            const txt = document.createElement('span');
                            txt.textContent = sec.name;
                            const btnX = document.createElement('button');
                            btnX.type = 'button';
                            btnX.textContent = '✕';
                            btnX.className = 'chip-remove';
                            btnX.title = 'Quitar esta categoría secundaria';
                            btnX.onclick = (e) => {
                                e.stopPropagation();
                                quitarCategoriaSecundaria(p.id_product, sec.id, sec.name);
                            };
                            chip.appendChild(txt);
                            chip.appendChild(btnX);
                            tdSecundarias.appendChild(chip);
                        });
                    }
                    // Botón para marcar como dudoso
                    const btnDudoso = document.createElement('button');
                    btnDudoso.textContent = 'Marcar Dudoso';
                    btnDudoso.className = 'btn-warning btn-mini';
                    btnDudoso.onclick = (e) => { e.stopPropagation(); reportarCasoDudoso(p); };
                    tdAcciones.appendChild(btnDudoso);
                    // Botón para copiar referencia al portapapeles
                    const btnCopy = document.createElement('button');
                    btnCopy.textContent = 'Copiar Ref';
                    btnCopy.className = 'btn-copy btn-mini';
                    btnCopy.style.marginLeft = '5px';
                    btnCopy.onclick = (e) => { e.stopPropagation(); copyReference(p.reference); };
                    tdAcciones.appendChild(btnCopy);
                    // Click on row toggles checkbox (except button/checkbox)
                    tr.addEventListener('click', (e) => {
                        if (e.target.tagName !== 'BUTTON' && e.target.type !== 'checkbox') {
                            check.checked = !check.checked;
                        }
                    });

                    tr.appendChild(tdCheck);
                    tr.appendChild(tdId);
                    tr.appendChild(tdNombre);
                    tr.appendChild(tdRef);
                    tr.appendChild(tdInformal);
                    tr.appendChild(tdSecundarias);
                    tr.appendChild(tdAcciones);

                    tbody.appendChild(tr);
                });
            }

            document.getElementById('infoPagina').innerText = `Página ${res.page} de ${totalPaginas} (${res.total_records} productos)`;
            document.getElementById('btnAnterior').disabled = (paginaActual <= 1);
            document.getElementById('btnSiguiente').disabled = (paginaActual >= totalPaginas);
            const btnSiguienteArriba = document.getElementById('btnSiguienteArriba');
            if (btnSiguienteArriba) btnSiguienteArriba.disabled = (paginaActual >= totalPaginas);
            const btnAnteriorArriba = document.getElementById('btnAnteriorArriba');
            if (btnAnteriorArriba) btnAnteriorArriba.disabled = (paginaActual <= 1);
            document.getElementById('checkTodos').checked = false;
        });
}

// 3. Mover selección masiva de productos
function moverSeleccionados() {
    const checkboxes = document.querySelectorAll('.check-producto:checked');
    const newCatId = document.getElementById('selectDestino').value;

    if (checkboxes.length === 0) return mostrarAviso('Selecciona al menos un producto.', 'info');
    if (!newCatId) return mostrarAviso('Selecciona una categoría destino.', 'info');

    const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value, 10));

    fetch('api.php?action=moveProduct', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_ids: selectedIds, new_category_id: parseInt(newCatId, 10) })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            mostrarAviso('Productos movidos correctamente.', 'success');
            cargarCategorias();
            cargarProductos(paginaActual);
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// 3b. Asignar categoría secundaria a la selección (sin cambiar su categoría principal)
function asignarCategoriaSecundaria() {
    const checkboxes = document.querySelectorAll('.check-producto:checked');
    const newCatId = document.getElementById('selectDestino').value;

    if (checkboxes.length === 0) return mostrarAviso('Selecciona al menos un producto.', 'info');
    if (!newCatId) return mostrarAviso('Selecciona una categoría destino.', 'info');

    const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value, 10));

    fetch('api.php?action=addSecondaryCategory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_ids: selectedIds, category_id: parseInt(newCatId, 10) })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            mostrarAviso(res.message, 'success');
            // No se recarga la lista: la categoría principal no cambia.
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// Quitar a un producto una categoría secundaria (el ✕ del chip)
function quitarCategoriaSecundaria(productId, categoryId, nombreCat) {
    if (!nombreCat) { nombreCat = `ID ${categoryId}`; }
    if (!confirm(`¿Quitar la categoría secundaria «${nombreCat}» de este producto?`)) return;
    fetch('api.php?action=removeSecondaryCategory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_ids: [productId], category_id: categoryId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            mostrarAviso(res.message, 'success');
            cargarProductos(paginaActual);
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// 4. Crear nueva categoría provisional (en Raíz o en Rama)
function crearCategoria() {
    const nombreInput = document.getElementById('nuevaCatNombre');
    const nombre = nombreInput.value.trim();
    const tipoUbicacion = document.getElementById('tipoUbicacionCat').value;

    if (!nombre) return mostrarAviso('Ingresa un nombre para la categoría.', 'info');

    let parentId = 2; // Por defecto Inicio (2) para categorías raíz
    if (tipoUbicacion === 'rama') {
        const selectPadre = document.getElementById('selectPadreCat');
        parentId = selectPadre ? parseInt(selectPadre.value, 10) : 2;
    }

    fetch('api.php?action=createCategory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: nombre, parent_id: parentId })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            mostrarAviso(`Categoría provisional creada con éxito (ID: ${res.data.id_category})`, 'success');
            nombreInput.value = '';
            cargarCategorias(res.data.id_category);
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// 5. Mover una categoría hoja a otra rama padre
function moverCategoria() {
    const selectMover = document.getElementById('selectCatMover');
    const selectNuevoPadre = document.getElementById('selectNuevoPadre');

    if (!selectMover || !selectNuevoPadre) return;

    const catId = parseInt(selectMover.value, 10);
    const newParentId = parseInt(selectNuevoPadre.value, 10);

    if (!catId) return mostrarAviso('Selecciona la categoría que deseas mover.', 'info');
    if (!newParentId) return mostrarAviso('Selecciona el nuevo padre de destino.', 'info');
    if (catId === newParentId) return mostrarAviso('Una categoría no puede ser movida dentro de sí misma.', 'info');
const categoriasSistema = [1, 2, -1]; // Raíz, Inicio y Huérfanos
if (categoriasSistema.includes(catId)) return mostrarAviso('No está permitido mover categorías del sistema.', 'info');
if (categoriasSistema.includes(newParentId)) return mostrarAviso('No está permitido seleccionar una categoría del sistema como nuevo padre.', 'info');
// Prevent moving a category that has subcategories
if (hijosPorPadre[catId] && hijosPorPadre[catId].length > 0) {
    return mostrarAviso('No puedes mover una categoría que tiene subcategorías.', 'info');
}

    const catNombre = categoriasPorId[catId] ? categoriasPorId[catId].name : `ID ${catId}`;
    const padreNombre = categoriasPorId[newParentId] ? categoriasPorId[newParentId].name : `ID ${newParentId}`;

    if (!confirm(`¿Confirmas que deseas mover la categoría "${catNombre}" para que sea hija de "${padreNombre}"?`)) {
        return;
    }

    fetch('api.php?action=moveCategory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category_id: catId, new_parent_id: newParentId })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            mostrarAviso(res.message, 'success');
            cargarCategorias(catId);
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// 6. Enviar producto individual a la tabla de uncertain_cases
function reportarCasoDudoso(producto) {
    const motivo = prompt(`Indica el motivo de la duda para "${producto.name}":`);
    if (!motivo) return;

    const alternativas = prompt("Categorías alternativas propuestas (opcional):");
    const idCatPropuesta = document.getElementById('selectDestino').value;

    fetch('api.php?action=addUncertainCase', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_product: producto.id_product,
            id_category_proposed: parseInt(idCatPropuesta, 10) || null,
            reason: motivo,
            alternatives: alternativas,
            raw_data: producto
        })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            mostrarAviso('El caso dudoso ha sido registrado correctamente.', 'success');
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// 7. Enviar incertidumbre general (para decisiones de lote o de creación de categoría)
function registrarDudaGeneral() {
    const motivo = prompt("Describe la duda o caso no claro en la reorganización:");
    if (!motivo) return;

    const alternativas = prompt("Alternativas consideradas (opcional):");
    const idCatPropuesta = document.getElementById('selectDestino').value;

    fetch('api.php?action=addUncertainCase', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_category_proposed: parseInt(idCatPropuesta, 10) || null,
            reason: motivo,
            alternatives: alternativas
        })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            mostrarAviso('Incertidumbre registrada correctamente.', 'success');
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

function copyReference(ref) {
    if (!ref) {
        console.warn('No hay referencia para copiar.');
        return;
    }
    navigator.clipboard.writeText(ref).then(() => {
        mostrarAviso('Referencia copiada al portapapeles', 'success');
    }).catch(err => {
        console.error('Error al copiar al portapapeles', err);
        mostrarAviso('No se pudo copiar la referencia.', 'error');
    });
}

// 9. Avisos auto-desaparecientes (toast) para confirmaciones y errores
// tipo: 'success' (verde), 'error' (rojo), 'info' (neutro). Sin botón de aceptar.
function mostrarAviso(mensaje, tipo = 'info', duracionMs) {
    const contenedor = document.getElementById('toast-container');
    if (!contenedor) return;

    const toast = document.createElement('div');
    toast.className = 'toast ' + (tipo === 'success' ? 'toast-success' : tipo === 'error' ? 'toast-error' : 'toast-info');
    toast.textContent = mensaje;
    contenedor.appendChild(toast);

    // Fuerza el reflow para que la transición de entrada se dispare
    requestAnimationFrame(() => toast.classList.add('show'));

    const duracion = duracionMs || (tipo === 'error' ? 5000 : tipo === 'success' ? 3000 : 3500);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duracion);
}

// Eliminar una categoría provisional (IDs negativos). Los productos pasan a Huérfanos.
function eliminarCategoriaProvisional() {
    const selectEliminarCat = document.getElementById('selectEliminarCat');
    if (!selectEliminarCat) return;
    const catId = parseInt(selectEliminarCat.value, 10);
    if (!catId) return mostrarAviso('Selecciona una categoría provisional para eliminar.', 'info');
    if (!confirm('¿Eliminar esta categoría provisional? Los productos pasarán a Huérfanos.')) return;
    fetch('api.php?action=deleteCategory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category_id: catId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            mostrarAviso(res.message, 'success');
            cargarCategorias(); // refresh selectors
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

function toggleTodos(event) {
    document.querySelectorAll('.check-producto').forEach(cb => cb.checked = event.target.checked);
}

// Renombrar una categoría provisional (IDs negativos)
function guardarNombreCategoriaProvisional() {
    const selectEditarCat = document.getElementById('selectEditarCat');
    const inputNombre = document.getElementById('nuevoNombreCat');
    if (!selectEditarCat || !inputNombre) return;
    const catId = parseInt(selectEditarCat.value, 10);
    if (!catId) return mostrarAviso('Selecciona una categoría provisional para renombrar.', 'info');
    const nuevoNombre = inputNombre.value.trim();
    if (!nuevoNombre) return mostrarAviso('Escribe el nuevo nombre de la categoría.', 'info');
    if (nuevoNombre.length > 90) return mostrarAviso('El nombre no puede superar 90 caracteres.', 'info');
    const catActual = categoriasPorId[catId];
    if (catActual && catActual.name === nuevoNombre) return mostrarAviso('El nombre no ha cambiado.', 'info');
    if (catActual && !confirm(`¿Renombrar la categoría provisional «${catActual.name}» a «${nuevoNombre}»?`)) return;
    fetch('api.php?action=renameCategory', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category_id: catId, name: nuevoNombre })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            mostrarAviso(res.message, 'success');
            cargarCategorias(null, catId); // refrescar selectores y conservar la categoría renombrada
        } else {
            mostrarAviso(res.error, 'error');
        }
    });
}

// 8. Mostrar aviso con la categoría de El Informal (Tienda 3) al pulsar la referencia
function mostrarCategoriaInformal(p) {
    const overlay = document.getElementById('modalInformal');
    if (!overlay) return;

    document.getElementById('modalInformalTitulo').textContent = 'Categoría en El Informal (Tienda 3)';
    const contenido = document.getElementById('modalInformalContenido');

    let html = '';
    html += `<p class="modal-prod"><strong>${escapeHtml(p.name)}</strong></p>`;
    html += `<p class="modal-prod-ref">Ref: ${escapeHtml(p.reference || '-')} · ID: ${p.id_product}</p>`;

    const tieneDatos = !!(p.informal_hoja || p.informal_familia || p.informal_subcategoria || p.informal_detalle);

    if (!tieneDatos) {
        html += '<p class="modal-sin-datos">Este producto no tiene categoría registrada en la consulta de El Informal.</p>';
    } else {
        const niveles = [
            { label: 'Nivel 1 (Familia)',     value: p.informal_familia },
            { label: 'Nivel 2 (Subcategoría)', value: p.informal_subcategoria },
            { label: 'Nivel 3 (Detalle)',      value: p.informal_detalle }
        ].filter(n => n.value);

        html += '<div class="modal-ruta">';
        niveles.forEach((nivel, idx) => {
            html += `<div class="modal-ruta-item">`
                 +   `<div><span class="modal-ruta-label">${nivel.label}:</span> <strong>${escapeHtml(nivel.value)}</strong></div>`
                 + `</div>`;
        });
        html += '</div>';

        if (p.informal_hoja) {
            html += `<p class="modal-hoja">📌 Hoja por defecto: <strong>${escapeHtml(p.informal_hoja)}</strong></p>`;
        }
        if (p.informal_todas) {
            html += `<p class="modal-todas"><span class="modal-ruta-label">Todas las categorías asignadas:</span><br>${escapeHtml(p.informal_todas)}</p>`;
        }
    }

    contenido.innerHTML = html;
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function cerrarModalInformal() {
    const overlay = document.getElementById('modalInformal');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
    }
}

// 9. Mostrar el árbol de categorías completo en un modal grande y claro
function mostrarArbolCategorias() {
    const overlay = document.getElementById('modalArbol');
    if (!overlay) return;

    const contenido = document.getElementById('modalArbolContenido');
    const resumen = document.getElementById('modalArbolResumen');
    if (!contenido || !resumen) return;

    const totalCats = todasLasCategorias.length;
    const provisionales = todasLasCategorias.filter(c => c.is_new && c.id_category < 0 && c.id_category !== -1).length;
    const nodoHuerfanos = todasLasCategorias.find(c => c.id_category === -1);
    const huerfanosProds = nodoHuerfanos ? nodoHuerfanos.direct_product_count : 0;

    resumen.innerHTML =
        `<strong>${totalCats}</strong> categorías · <strong>${provisionales}</strong> provisionales · ` +
        `<strong>${huerfanosProds}</strong> productos huérfanos<br>` +
        `<span style="color:#888;">📁 = tiene subcategorías · 📄 = sin subcategorías · 📦 = huérfanos · (n) = productos directos</span>`;

    let html = '';
    todasLasCategorias.forEach(cat => {
        const nivel = cat.tree_level || 0;
        const esHuerfanos = cat.id_category === -1;
        const tieneHijos = hijosPorPadre[cat.id_category] && hijosPorPadre[cat.id_category].length > 0;
        const icono = esHuerfanos ? '📦' : tieneHijos ? '📁' : '📄';
        const esRama = tieneHijos && !esHuerfanos;
        const esProvisional = cat.is_new && cat.id_category < 0 && !esHuerfanos;
        const esSistema = (cat.id_category === 1 || cat.id_category === 2);

        html += `<div class="tree-item${nivel === 0 ? ' tree-nivel0' : ''}" style="padding-left: ${nivel * 22}px;">` +
                `<span class="tree-icon">${icono}</span> ` +
                `<span class="${esRama ? 'tree-rama' : 'tree-hoja'}">${escapeHtml(cat.name)}</span>` +
                (esProvisional ? ' <span class="tree-provisional">[Provisional]</span>' : '') +
                (esSistema ? ' <span class="tree-sistema">(sistema)</span>' : '') +
                ` <span class="tree-prods">(${cat.direct_product_count})</span>` +
                `</div>`;
    });

    contenido.innerHTML = html;
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function cerrarModalArbol() {
    const overlay = document.getElementById('modalArbol');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
    }
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}