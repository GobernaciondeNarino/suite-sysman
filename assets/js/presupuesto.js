/**
 * SYSMAN Suite — Modulo Presupuesto (Gastos e Ingresos)
 * Gobernacion de Narino
 *
 * Renderiza los shortcodes [sysman_gastos_*] y [sysman_ingresos_*] contra la
 * REST del modulo y coordina el filtro compartido entre los componentes que
 * declaran enlazar="si". Vanilla JS: no depende de jQuery.
 */
(function () {
    'use strict';

    var CFG = window.sysmanPresupuesto || {};
    var CADENA = CFG.cadena || { DIS: 'Disponibilidad (CDP)', RES: 'Registro de compromiso (RP)', OBL: 'Obligacion', EGR: 'Egreso (pago)' };

    /* ── Coordinador de filtro compartido ─────────────────────────
       Un estado por grupo. Gastos e Ingresos usan espacios separados
       aunque compartan el nombre de grupo: sus dimensiones no son
       comparables, enlazarlos no tendria sentido.                    */
    var Coord = {
        grupos: {},

        clave: function (cfg) { return (cfg.modulo || 'gastos') + '/' + (cfg.grupo || 'principal'); },

        grupo: function (nombre) {
            if (!this.grupos[nombre]) {
                this.grupos[nombre] = { estado: { valor: '' }, subs: [] };
            }
            return this.grupos[nombre];
        },

        estado: function (nombre) {
            return Object.assign({}, this.grupo(nombre).estado);
        },

        suscribir: function (nombre, fn) {
            var g = this.grupo(nombre);
            g.subs.push(fn);
            return function () {
                var i = g.subs.indexOf(fn);
                if (i > -1) { g.subs.splice(i, 1); }
            };
        },

        fijar: function (nombre, cambios) {
            var g = this.grupo(nombre);
            var antes = JSON.stringify(g.estado);
            g.estado = Object.assign({}, g.estado, cambios);
            if (JSON.stringify(g.estado) === antes) { return; }
            var snapshot = Object.assign({}, g.estado);
            g.subs.slice().forEach(function (fn) {
                try { fn(snapshot); } catch (e) { console.error('[Presupuesto]', e); }
            });
        }
    };

    /* ── Formato ──────────────────────────────────────────────── */
    var Fmt = {
        moneda: function (v) {
            return '$' + (parseFloat(v) || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },
        compacto: function (v) {
            var n = parseFloat(v) || 0, a = Math.abs(n);
            if (a >= 1e12) { return (n / 1e12).toFixed(1).replace('.', ',') + ' Billones'; }
            if (a >= 1e9) { return (n / 1e9).toFixed(1).replace('.', ',') + ' MMll'; }
            if (a >= 1e6) { return (n / 1e6).toFixed(1).replace('.', ',') + ' Mll'; }
            if (a >= 1e3) { return (n / 1e3).toFixed(1).replace('.', ',') + ' Mil'; }
            return n.toLocaleString('es-CO');
        },
        entero: function (v) { return (parseInt(v, 10) || 0).toLocaleString('es-CO'); },
        pct: function (frac) {
            if (frac === null || frac === undefined) { return '—'; }
            return ((parseFloat(frac) || 0) * 100).toFixed(1).replace('.', ',') + '%';
        }
    };

    function esc(str) {
        return String(str === null || str === undefined ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ── Cliente REST ─────────────────────────────────────────── */
    function api(ruta, params) {
        var qs = Object.keys(params || {})
            .filter(function (k) { return params[k] !== '' && params[k] !== null && params[k] !== undefined; })
            .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
            .join('&');

        return fetch(CFG.restUrl + ruta + (qs ? '?' + qs : ''), { credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 429) { throw new Error('Demasiadas solicitudes. Intente de nuevo en un minuto.'); }
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            });
    }

    /** Parametros comunes de toda peticion. */
    function base(cfg) {
        return {
            modulo: cfg.modulo || 'gastos',
            dimension: cfg.dimension || '',
            longitud: cfg.longitud || 0,
            compania: cfg.compania,
            anio: cfg.anio,
            mes: cfg.mes,
            campo: cfg.campo,
            tooltip: (cfg.tooltip || []).join(',')
        };
    }

    function cuerpoDe(el) { return el.querySelector('[data-rol="cuerpo"]'); }
    function pintarError(el, m) { var c = cuerpoDe(el); if (c) { c.innerHTML = '<p class="sysman-pre__error">' + esc(m) + '</p>'; } }
    function pintarVacio(el, m) { var c = cuerpoDe(el); if (c) { c.innerHTML = '<p class="sysman-pre__vacio">' + esc(m) + '</p>'; } }
    function cargando(el) { var c = cuerpoDe(el); if (c) { c.innerHTML = '<p class="sysman-pre__cargando">Cargando datos…</p>'; } }

    /** "← Todas las dependencias" / "← Todos los tipos de recurso".
        El plural viene resuelto desde PHP: en espanol no basta con anadir "s"
        al final ("tipo de recurso" -> "tipos de recurso"). */
    function etiquetaTodas(cfg) {
        return '← ' + (cfg.etiqueta_todas || 'Todas las dependencias');
    }

    /* ── Treemap (con drill-down al detalle) ──────────────────── */
    function initTreemap(el, cfg) {
        if (typeof d3plus === 'undefined' || !d3plus.Treemap) {
            pintarError(el, 'No se pudo cargar la libreria de graficos (D3plus).');
            return;
        }

        var colores = (cfg.colores || '#348AFB,#1a5632,#ff7300,#ffc53b,#3eba6a,#844e80,#e74c3c,#9b59b6,#16a085,#d35400')
            .split(',').map(function (c) { return c.trim(); })
            .filter(function (c) { return /^#[0-9a-fA-F]{3,8}$/.test(c); });

        var actual = cfg.valor || '';
        var clave = Coord.clave(cfg);

        function render() {
            cargando(el);
            var esDrill = !!actual;
            var params = base(cfg);
            params.limite = cfg.limite || 0;
            if (esDrill) { params.valor = actual; }

            api(esDrill ? 'detalle' : 'dimensiones', params).then(function (resp) {
                var filas = resp.data || [];
                var meta = resp.meta || {};
                if (!filas.length) {
                    pintarVacio(el, 'No hay datos de ' + (meta.campo_label || cfg.campo) + ' para este periodo.');
                    return;
                }

                var datos = filas.map(function (f) {
                    // Sin drill, la etiqueta manda; pero si el grupo es un codigo
                    // de rubro con nombre, el tooltip muestra el nombre.
                    var nombre = esDrill ? (f.nombre || f.codigo) : (f.nombre || f.label);
                    var d = {
                        etiqueta: esDrill ? (f.codigo + ' · ' + (f.nombre || '')) : f.label,
                        nombre: nombre,
                        valor: Math.abs(parseFloat(f.value) || 0),
                        real: parseFloat(f.value) || 0
                    };
                    // Campos adicionales pedidos con el atributo `tooltip`.
                    (cfg.tooltip || []).forEach(function (k) { d[k] = parseFloat(f[k]) || 0; });
                    if (f.porcentaje_recaudado !== undefined) { d.porcentaje_recaudado = f.porcentaje_recaudado; }
                    if (f.rubros !== undefined) { d.rubros = f.rubros; }
                    return d;
                }).filter(function (d) { return d.valor > 0; });

                if (!datos.length) {
                    pintarVacio(el, 'Todos los valores del periodo son cero.');
                    return;
                }

                var c = cuerpoDe(el);
                c.innerHTML = '';

                if (esDrill) {
                    var volver = document.createElement('button');
                    volver.type = 'button';
                    volver.className = 'sysman-pre__breadcrumb';
                    volver.textContent = etiquetaTodas(cfg) + ' · ' + actual;
                    volver.addEventListener('click', function () {
                        // Ojo: no tocar `actual` antes de publicar. Si se limpia
                        // primero, el suscriptor ve el mismo valor y no repinta
                        // (era el motivo por el que el boton no volvia atras).
                        if (cfg.enlazar) {
                            Coord.fijar(clave, { valor: '' });
                        } else {
                            actual = '';
                            render();
                        }
                    });
                    c.appendChild(volver);
                }

                var lienzo = document.createElement('div');
                lienzo.className = 'sysman-pre__lienzo';
                lienzo.style.height = (cfg.altura || 520) + 'px';
                c.appendChild(lienzo);

                var mapa = {};
                datos.forEach(function (d, i) { mapa[d.etiqueta] = colores[i % colores.length]; });

                // Cuerpo del tooltip: la metrica principal y los campos extra.
                var cuerpoTip = [[(meta.campo_label || 'Valor'), function (d) { return Fmt.moneda(d.real); }]];
                var etiquetas = meta.tooltip_label || {};
                (cfg.tooltip || []).forEach(function (k) {
                    // La metrica principal ya encabeza el tooltip: no repetirla
                    // aunque el autor la incluya tambien en el atributo.
                    if (k === cfg.campo) { return; }
                    cuerpoTip.push([etiquetas[k] || k, function (d) { return Fmt.moneda(d[k]); }]);
                });
                if (!esDrill && datos[0] && datos[0].rubros !== undefined) {
                    cuerpoTip.push([cfg.modulo === 'ingresos' ? 'Cuentas' : 'Rubros', function (d) { return Fmt.entero(d.rubros); }]);
                }
                if (datos[0] && datos[0].porcentaje_recaudado !== undefined) {
                    cuerpoTip.push(['% Recaudado', function (d) { return Fmt.pct(d.porcentaje_recaudado); }]);
                }

                new d3plus.Treemap()
                    .data(datos)
                    .groupBy('etiqueta')
                    .sum('valor')
                    .select(lienzo)
                    .color(function (d) { return mapa[d.etiqueta] || colores[0]; })
                    .tooltipConfig({
                        title: function (d) { return d.nombre || d.etiqueta; },
                        tbody: cuerpoTip
                    })
                    .legend(false)
                    .height(cfg.altura || 520)
                    .locale('es-ES')
                    .on('click', function (d) {
                        if (esDrill) { return; }
                        if (cfg.enlazar) {
                            Coord.fijar(clave, { valor: d.etiqueta });
                        } else {
                            actual = d.etiqueta;
                            render();
                        }
                    })
                    .render();
            }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.enlazar) {
            Coord.suscribir(clave, function (estado) {
                if (estado.valor !== actual) { actual = estado.valor; render(); }
            });
        }
        render();
    }

    /* ── Lista de dimensiones ─────────────────────────────────── */
    function initLista(el, cfg) {
        var filas = [];
        var seleccion = cfg.valor || '';
        var clave = Coord.clave(cfg);
        var esIngresos = cfg.modulo === 'ingresos';

        function pintar() {
            var c = cuerpoDe(el);
            var total = filas.reduce(function (a, f) { return a + (parseFloat(f.value) || 0); }, 0);
            var campo = el.querySelector('[data-rol="buscar"]');
            var texto = campo ? campo.value : '';
            var visibles = filas.filter(function (f) {
                return !texto || f.label.toLowerCase().indexOf(texto.toLowerCase()) > -1;
            });

            if (!visibles.length) {
                c.innerHTML = '<p class="sysman-pre__vacio">Ningún resultado coincide.</p>';
                return;
            }

            c.innerHTML = '<ul class="sysman-pre__lista" role="list">' + visibles.map(function (f) {
                var pct = total > 0 ? (parseFloat(f.value) || 0) / total : 0;
                var activa = f.label === seleccion ? ' is-activa' : '';
                // Conteo y valor van en la misma linea del nombre, a la derecha.
                var cifras = Fmt.entero(f.rubros) + ' · ' + Fmt.moneda(f.value);
                // Cuando la etiqueta es un codigo de rubro, el nombre del
                // concepto va debajo: el codigo solo no dice nada al lector.
                var desc = (f.nombre && f.nombre !== f.label)
                    ? '<span class="sysman-pre__item-desc">' + esc(f.nombre) + '</span>' : '';
                return '<li><button type="button" class="sysman-pre__item' + activa + '" data-valor="' + esc(f.label) + '"'
                    + ' aria-pressed="' + (activa ? 'true' : 'false') + '"'
                    + ' title="' + esc(f.label + (f.nombre ? ' — ' + f.nombre : '') + ' — ' + Fmt.entero(f.rubros) + (esIngresos ? ' cuentas' : ' rubros')) + '">'
                    + '<span class="sysman-pre__item-nombre">' + esc(f.label) + '</span>'
                    + '<span class="sysman-pre__item-cifras">' + esc(cifras) + '</span>'
                    + desc
                    + '<span class="sysman-pre__barra"><span style="width:' + (pct * 100).toFixed(2) + '%"></span></span>'
                    + '</button></li>';
            }).join('') + '</ul>';
        }

        function cargar() {
            cargando(el);
            var params = base(cfg);
            params.limite = cfg.limite || 0;
            api('dimensiones', params).then(function (resp) {
                filas = resp.data || [];
                if (!filas.length) {
                    pintarVacio(el, 'No hay datos con movimiento en este periodo.');
                    return;
                }
                pintar();
            }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.buscador) {
            var barra = document.createElement('div');
            barra.className = 'sysman-pre__buscador';
            var etiqueta = esIngresos ? (cfg.etiqueta_dimension || 'recurso') : 'dependencia';
            barra.innerHTML = '<input type="search" data-rol="buscar" placeholder="Buscar ' + esc(etiqueta.toLowerCase()) + '…"'
                + ' aria-label="Buscar ' + esc(etiqueta.toLowerCase()) + '">';
            el.insertBefore(barra, cuerpoDe(el));
            barra.querySelector('input').addEventListener('input', pintar);
        }

        el.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.sysman-pre__item');
            if (!btn || !el.contains(btn)) { return; }
            var valor = btn.getAttribute('data-valor');
            seleccion = (seleccion === valor) ? '' : valor;
            pintar();
            if (cfg.enlazar) { Coord.fijar(clave, { valor: seleccion }); }
        });

        if (cfg.enlazar) {
            Coord.suscribir(clave, function (estado) {
                if (estado.valor !== seleccion) {
                    seleccion = estado.valor;
                    if (filas.length) { pintar(); }
                }
            });
        }
        cargar();
    }

    /* ── Cadena documental (solo Gastos) ──────────────────────── */
    function nodoCadena(nodo, nivel) {
        var html = '<li class="sysman-pre__doc sysman-pre__doc--' + esc(String(nodo.tipo).toLowerCase()) + '">'
            + '<div class="sysman-pre__doc-cab">'
            + '<span class="sysman-pre__doc-tipo" title="' + esc(CADENA[nodo.tipo] || nodo.tipo) + '">' + esc(nodo.tipo) + '</span>'
            + '<span class="sysman-pre__doc-num">' + esc(nodo.numero) + '</span>'
            + '<span class="sysman-pre__doc-tercero">' + esc(nodo.nombretercero || nodo.tercero || '—') + '</span>'
            + '<span class="sysman-pre__doc-valor">' + Fmt.moneda(nodo.valor) + '</span>'
            + '</div>';

        var detalle = [];
        if (nodo.fecha) { detalle.push('Fecha: ' + esc(nodo.fecha)); }
        if (nodo.nrodocumento) { detalle.push('Doc: ' + esc(nodo.nrodocumento)); }
        if (nodo.saldo) { detalle.push('Saldo: ' + Fmt.moneda(nodo.saldo)); }
        if (detalle.length || nodo.descripcion) {
            html += '<div class="sysman-pre__doc-meta">'
                + (nodo.descripcion ? '<span class="sysman-pre__doc-desc">' + esc(nodo.descripcion) + '</span>' : '')
                + (detalle.length ? '<span class="sysman-pre__doc-datos">' + detalle.join(' · ') + '</span>' : '')
                + '</div>';
        }

        if (nodo.hijos && nodo.hijos.length) {
            html += '<ul class="sysman-pre__cadena sysman-pre__cadena--n' + nivel + '">'
                + nodo.hijos.map(function (h) { return nodoCadena(h, nivel + 1); }).join('') + '</ul>';
        }
        return html + '</li>';
    }

    function kpis(cons, claves) {
        return '<div class="sysman-pre__consolidado">' + claves.map(function (k) {
            var v = cons[k[0]];
            var texto = k[2] === 'pct' ? Fmt.pct(v) : Fmt.compacto(v || 0);
            return '<div class="sysman-pre__kpi"><span class="sysman-pre__kpi-valor">' + esc(texto)
                + '</span><span class="sysman-pre__kpi-label">' + esc(k[1]) + '</span></div>';
        }).join('') + '</div>';
    }

    /** Detalle de un rubro de gastos. */
    function detalleGastos(caja, data) {
        var cons = data.consolidado || {};
        var mods = data.modificaciones || [];
        var cad = data.cadena || { documentos: [], huerfanos: [], conteo: {} };

        var html = kpis(cons, [
            ['apropiacionvigente', 'Apropiación vigente'],
            ['disponibilidades', 'Disponibilidades'],
            ['compromisos', 'Compromisos'],
            ['obligacion', 'Obligación'],
            ['pagos', 'Pagos'],
            ['saldodisponible', 'Saldo disponible']
        ]);

        if (mods.length) {
            html += '<div class="sysman-pre__bloque"><h5>Modificaciones presupuestales</h5>'
                + '<table class="sysman-pre__tabla"><tbody>' + mods.map(function (m) {
                    return '<tr><td>' + esc(m.label) + '</td><td class="num">' + Fmt.moneda(m.value) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        var conteo = Object.keys(CADENA).map(function (t) {
            return (cad.conteo && cad.conteo[t]) ? Fmt.entero(cad.conteo[t]) + ' ' + t : null;
        }).filter(Boolean).join(' · ');

        html += '<div class="sysman-pre__bloque"><h5>Cadena de ejecución'
            + (conteo ? ' <span class="sysman-pre__conteo">' + esc(conteo) + '</span>' : '') + '</h5>';

        var docs = (cad.documentos || []).concat(cad.huerfanos || []);
        if (!docs.length) {
            html += '<p class="sysman-pre__vacio">Sin documentos de ejecución para este rubro en el periodo. '
                + 'Si esperaba ver disponibilidades o compromisos, importe el informe «Auxiliar Presupuestal por Cuentas».</p>';
        } else {
            html += '<ul class="sysman-pre__cadena sysman-pre__cadena--n0">'
                + docs.map(function (d) { return nodoCadena(d, 1); }).join('') + '</ul>';
            if ((cad.huerfanos || []).length) {
                html += '<p class="sysman-pre__nota">Algunos documentos afectan a comprobantes de otro periodo y se muestran al final.</p>';
            }
        }
        caja.innerHTML = html + '</div>';
    }

    /** Detalle de una cuenta de ingresos: no hay cadena documental, sino recaudo. */
    function detalleIngresos(caja, data) {
        var cons = data.consolidado || {};
        var rec = data.recaudo || [];
        var cls = data.clasificacion || {};

        var html = kpis(cons, [
            ['apropiado', 'Apropiado'],
            ['modificaciones', 'Modificaciones'],
            ['totalpresupuesto', 'Presupuesto definitivo'],
            ['recaudosacumulados', 'Recaudo acumulado'],
            ['porrecaudar', 'Por recaudar'],
            ['porcentaje_recaudado', '% Recaudado', 'pct']
        ]);

        var pct = cons.porcentaje_recaudado;
        if (pct !== null && pct !== undefined) {
            var ancho = Math.max(0, Math.min(1, pct)) * 100;
            html += '<div class="sysman-pre__progreso" role="img"'
                + ' aria-label="Recaudado ' + esc(Fmt.pct(pct)) + ' del presupuesto definitivo">'
                + '<span class="sysman-pre__progreso-barra" style="width:' + ancho.toFixed(2) + '%"></span>'
                + '<span class="sysman-pre__progreso-texto">' + esc(Fmt.pct(pct)) + ' recaudado</span>'
                + '</div>';
        }

        if (rec.length) {
            html += '<div class="sysman-pre__bloque"><h5>Composición del recaudo</h5>'
                + '<table class="sysman-pre__tabla"><tbody>' + rec.map(function (m) {
                    return '<tr><td>' + esc(m.label) + '</td><td class="num">' + Fmt.moneda(m.value) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        var pares = [
            ['Cuenta', cls.cuenta],
            ['Tipo de recurso', cls.tiporecurso],
            ['Fuente de recurso', cls.fuenterecurso]
        ].filter(function (p) { return p[1]; });

        if (pares.length) {
            html += '<div class="sysman-pre__bloque"><h5>Clasificación</h5>'
                + '<table class="sysman-pre__tabla"><tbody>' + pares.map(function (p) {
                    return '<tr><td>' + esc(p[0]) + '</td><td>' + esc(p[1]) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        caja.innerHTML = html;
    }

    /* ── Panel de detalle (rubros o cuentas) ──────────────────── */
    function initEjecucion(el, cfg) {
        var valor = cfg.valor || '';
        var clave = Coord.clave(cfg);
        var esIngresos = cfg.modulo === 'ingresos';
        var filas = [];      // Filas cargadas del periodo, para filtrar en local.
        var mostrado = '';   // Dimension que se esta mostrando ahora mismo.

        /* Sin seleccion el panel arranca con la primera dimension (la de mayor
           valor) en vez de quedarse en blanco. Se resuelve en local: no publica
           nada al filtro compartido, asi que no arrastra a los demas
           shortcodes de la pagina. */
        function resolverValor() {
            if (valor) { return Promise.resolve(valor); }

            var params = base(cfg);
            params.limite = 1;
            return api('dimensiones', params).then(function (resp) {
                var primeras = resp.data || [];
                return primeras.length ? (primeras[0].label || '') : '';
            });
        }

        function cargar() {
            cargando(el);
            resolverValor().then(pedir).catch(function (e) { pintarError(el, e.message); });
        }

        function pedir(elegido) {
            if (!elegido) {
                filas = [];
                pintarVacio(el, esIngresos
                    ? 'No hay cuentas de ingreso con movimiento en este periodo.'
                    : 'No hay dependencias con movimiento en este periodo.');
                return;
            }

            var params = base(cfg);
            params.valor = elegido;

            api('detalle', params).then(function (resp) {
                filas = resp.data || [];
                mostrado = elegido;
                if (!filas.length) {
                    pintarVacio(el, '«' + elegido + '» no tiene registros con movimiento en este periodo.');
                    return;
                }
                pintar();
            }).catch(function (e) { pintarError(el, e.message); });
        }

        /* Repinta desde las filas ya cargadas: el buscador filtra en local,
           sin volver a consultar el REST. */
        function pintar() {
            var c = cuerpoDe(el);
            var campo = el.querySelector('[data-rol="buscar"]');
            var texto = campo ? campo.value.trim().toLowerCase() : '';
            var visibles = !texto ? filas : filas.filter(function (f) {
                return ((f.codigo || '') + ' ' + (f.nombre || '')).toLowerCase().indexOf(texto) > -1;
            });

            var cuantos = esIngresos
                ? (visibles.length === 1 ? 'cuenta' : 'cuentas')
                : (visibles.length === 1 ? 'rubro' : 'rubros');

            var resumen = '<p class="sysman-pre__resumen"><strong>' + esc(mostrado) + '</strong> · '
                + Fmt.entero(visibles.length) + ' ' + cuantos
                + (texto ? ' de ' + Fmt.entero(filas.length) : '') + '</p>';

            if (!visibles.length) {
                c.innerHTML = resumen + '<p class="sysman-pre__vacio">Ningún resultado coincide.</p>';
                return;
            }

            c.innerHTML = resumen
                + '<ul class="sysman-pre__rubros" role="list">' + visibles.map(function (f) {
                    var extra = (esIngresos && f.porcentaje_recaudado !== null && f.porcentaje_recaudado !== undefined)
                        ? '<span class="sysman-pre__rubro-pct">' + esc(Fmt.pct(f.porcentaje_recaudado)) + '</span>' : '';
                    // Codigo y valor en la primera linea; el nombre debajo,
                    // porque suele ser largo y empujaba la cifra fuera de vista.
                    return '<li class="sysman-pre__rubro" data-codigo="' + esc(f.codigo) + '" aria-expanded="false">'
                        + '<button type="button" class="sysman-pre__rubro-tog">'
                        + '<span class="sysman-pre__rubro-linea">'
                        + '<span class="sysman-pre__rubro-flecha" aria-hidden="true">▶</span>'
                        + '<span class="sysman-pre__rubro-codigo">' + esc(f.codigo) + '</span>'
                        + extra
                        + '<span class="sysman-pre__rubro-valor">' + Fmt.moneda(f.value) + '</span>'
                        + '</span>'
                        + '<span class="sysman-pre__rubro-nombre">' + esc(f.nombre) + '</span>'
                        + '</button>'
                        + '<div class="sysman-pre__rubro-cuerpo" hidden></div></li>';
                }).join('') + '</ul>';
        }

        if (cfg.buscador) {
            var barra = document.createElement('div');
            barra.className = 'sysman-pre__buscador';
            var que = esIngresos ? 'cuenta o código' : 'rubro o código';
            barra.innerHTML = '<input type="search" data-rol="buscar" placeholder="Buscar ' + que + '…"'
                + ' aria-label="Buscar ' + que + '">';
            el.insertBefore(barra, cuerpoDe(el));
            barra.querySelector('input').addEventListener('input', function () {
                if (filas.length) { pintar(); }
            });
        }

        el.addEventListener('click', function (ev) {
            var tog = ev.target.closest('.sysman-pre__rubro-tog');
            if (!tog || !el.contains(tog)) { return; }

            var li = tog.closest('.sysman-pre__rubro');
            var caja = li.querySelector('.sysman-pre__rubro-cuerpo');
            var abierto = li.getAttribute('aria-expanded') === 'true';

            li.setAttribute('aria-expanded', String(!abierto));
            caja.hidden = abierto;
            if (abierto || li.dataset.cargado) { return; }

            caja.innerHTML = '<p class="sysman-pre__cargando">Cargando detalle…</p>';
            var params = base(cfg);
            params.codigo = li.getAttribute('data-codigo');

            api('item', params).then(function (resp) {
                if (esIngresos) { detalleIngresos(caja, resp.data || {}); }
                else { detalleGastos(caja, resp.data || {}); }
                li.dataset.cargado = '1';
            }).catch(function (e) {
                caja.innerHTML = '<p class="sysman-pre__error">' + esc(e.message) + '</p>';
            });
        });

        if (cfg.enlazar) {
            Coord.suscribir(clave, function (estado) {
                if (estado.valor !== valor) { valor = estado.valor; cargar(); }
            });
            if (valor) { Coord.fijar(clave, { valor: valor }); }
        }
        cargar();
    }

    /* ── Explorador maestro-detalle ───────────────────────────── */
    function initExplora(el, cfg) {
        var c = cuerpoDe(el);
        c.innerHTML = '<div class="sysman-pre__split">'
            + '<div class="sysman-pre__col sysman-pre__col--maestro" data-rol="maestro"><div data-rol="cuerpo"></div></div>'
            + '<div class="sysman-pre__col sysman-pre__col--detalle" data-rol="detalle"><div data-rol="cuerpo"></div></div>'
            + '</div>';

        if (cfg.altura) {
            c.querySelector('.sysman-pre__split').style.setProperty('--sysman-pre-alto', cfg.altura + 'px');
        }

        // Grupo propio cuando no esta enlazado a la pagina, para que el
        // maestro y el detalle sigan hablandose entre si.
        var grupo = cfg.enlazar ? cfg.grupo : 'explora-' + (el.id || Math.random().toString(36).slice(2));

        initLista(c.querySelector('[data-rol="maestro"]'), Object.assign({}, cfg, { enlazar: true, grupo: grupo }));
        initEjecucion(c.querySelector('[data-rol="detalle"]'), Object.assign({}, cfg, { enlazar: true, grupo: grupo, valor: '' }));
    }

    /* ── Analisis ─────────────────────────────────────────────── */
    function initAnalisis(el, cfg) {
        var valor = cfg.valor || '';
        var clave = Coord.clave(cfg);

        function cargar() {
            cargando(el);
            // Al enlazarse, el analisis sigue lo que el usuario esta mirando:
            // con un valor elegido pasa a analizar su detalle.
            var params = base(cfg);
            // La vista de avance no cambia al elegir un valor: solo se acota.
            params.vista = cfg.vista === 'avance'
                ? 'avance'
                : (valor ? 'detalle' : cfg.vista);
            params.tipo = cfg.tipo;
            params.valor = valor;

            api('analisis', params).then(function (resp) {
                var a = resp.data || {};
                // Prosa continua para la ciudadania: solo parrafos. Ni titulo,
                // ni etiqueta de ambito, ni cuadro de metricas — el alcance y
                // las cifras van dentro del propio texto (ver Analysis.php).
                cuerpoDe(el).innerHTML = '<div class="sysman-pre__analisis sysman-pre__analisis--'
                    + esc(cfg.tipo) + '">'
                    + (a.parrafos || []).map(function (p) {
                        return '<p class="sysman-pre__analisis-parrafo">' + esc(p) + '</p>';
                    }).join('')
                    + '</div>';
            }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.enlazar) {
            Coord.suscribir(clave, function (estado) {
                if (estado.valor !== valor) { valor = estado.valor; cargar(); }
            });
        }
        cargar();
    }

    /* ── Selector ─────────────────────────────────────────────── */
    function initSelector(el, cfg) {
        var clave = Coord.clave(cfg);
        cargando(el);

        api('dimensiones', base(cfg)).then(function (resp) {
            var filas = resp.data || [];
            var c = cuerpoDe(el);
            var id = (el.id || 'sysman-pre-sel') + '-input';

            c.innerHTML = '<label class="sysman-pre__selector-label" for="' + esc(id) + '">'
                + esc(cfg.etiqueta || 'Dependencia:') + '</label>'
                + '<select id="' + esc(id) + '" class="sysman-pre__selector-input">'
                + '<option value="">' + esc(cfg.todas || '— Todas —') + '</option>'
                + filas.map(function (f) {
                    return '<option value="' + esc(f.label) + '">' + esc(f.label) + '</option>';
                }).join('') + '</select>';

            var sel = c.querySelector('select');
            sel.addEventListener('change', function () {
                if (cfg.enlazar) { Coord.fijar(clave, { valor: sel.value }); }
            });

            if (cfg.enlazar) {
                sel.value = Coord.estado(clave).valor || '';
                Coord.suscribir(clave, function (estado) {
                    if (sel.value !== estado.valor) { sel.value = estado.valor || ''; }
                });
            }
        }).catch(function (e) { pintarError(el, e.message); });
    }

    /* ── Avance de ejecucion (% comprometido / recaudado) ─────── */
    function initAvance(el, cfg) {
        if (typeof d3plus === 'undefined' || !d3plus.BarChart || !d3plus.LinePlot) {
            pintarError(el, 'No se pudo cargar la libreria de graficos (D3plus).');
            return;
        }

        var actual = cfg.valor || '';
        var clave = Coord.clave(cfg);
        var esIngresos = cfg.modulo === 'ingresos';
        var color = (cfg.colores || '').split(',')[0].trim();
        if (!/^#[0-9a-fA-F]{3,8}$/.test(color)) { color = '#348AFB'; }

        function render() {
            cargando(el);
            var params = base(cfg);
            params.limite = cfg.limite || 0;
            params.valor = actual;

            api('avance', params).then(function (resp) {
                var filas = (resp.data || []).filter(function (f) {
                    return f.porcentaje !== null && f.porcentaje !== undefined;
                });
                var meta = resp.meta || {};

                if (!filas.length) {
                    pintarVacio(el, 'No hay apropiación registrada en este periodo, así que no se puede calcular el avance.');
                    return;
                }

                var c = cuerpoDe(el);
                c.innerHTML = '';

                if (actual) {
                    var volver = document.createElement('button');
                    volver.type = 'button';
                    volver.className = 'sysman-pre__breadcrumb';
                    volver.textContent = etiquetaTodas(cfg) + ' · ' + actual;
                    volver.addEventListener('click', function () {
                        // Igual que el treemap: publicar primero, limpiar despues.
                        if (cfg.enlazar) {
                            Coord.fijar(clave, { valor: '' });
                        } else {
                            actual = '';
                            render();
                        }
                    });
                    c.appendChild(volver);
                }

                // Cifra global: el ponderado, no el promedio de porcentajes.
                var resumen = document.createElement('p');
                resumen.className = 'sysman-pre__resumen';
                resumen.innerHTML = '<strong>' + esc(Fmt.pct(meta.porcentaje)) + '</strong> '
                    + esc((meta.porcentaje_label || '% Ejecución').replace('%', '').trim().toLowerCase())
                    + ' · ' + esc(Fmt.moneda(meta.ejecutado)) + ' de ' + esc(Fmt.moneda(meta.base))
                    + ' · ' + Fmt.entero(filas.length) + ' '
                    + esc(actual
                        ? (esIngresos ? 'cuentas' : 'rubros')
                        : (esIngresos ? (cfg.etiqueta_plural || 'tipos de recurso') : 'dependencias'));
                c.appendChild(resumen);

                var lienzo = document.createElement('div');
                lienzo.className = 'sysman-pre__lienzo';
                lienzo.style.height = (cfg.altura || 460) + 'px';
                c.appendChild(lienzo);

                var esLinea = 'lineas' === cfg.tipo;

                // En barras el grafico se lee como un ranking, asi que manda el
                // porcentaje. En lineas se respeta el orden que llega (por
                // tamano del presupuesto): ordenar por avance dibujaria una
                // pendiente decreciente que no significa nada.
                if (!esLinea) {
                    filas = filas.slice().sort(function (a, b) {
                        return (parseFloat(a.porcentaje) || 0) - (parseFloat(b.porcentaje) || 0);
                    });
                }

                var datos = filas.map(function (f) {
                    return {
                        etiqueta: actual ? (f.codigo || f.label) : f.label,
                        nombre: (f.nombre && f.nombre !== f.label)
                            ? f.label + ' · ' + f.nombre
                            : f.label,
                        grupo: meta.porcentaje_label || '% Ejecución',
                        pct: parseFloat(f.porcentaje) || 0,
                        base: parseFloat(f.base) || 0,
                        ejecutado: parseFloat(f.ejecutado) || 0
                    };
                });

                var tooltip = {
                    title: function (d) { return d.nombre || d.etiqueta; },
                    tbody: [
                        [meta.porcentaje_label || '% Ejecución', function (d) { return Fmt.pct(d.pct); }],
                        [meta.base_label || 'Base', function (d) { return Fmt.moneda(d.base); }],
                        [meta.ejecutado_label || 'Ejecutado', function (d) { return Fmt.moneda(d.ejecutado); }]
                    ]
                };

                // El eje va en porcentaje y siempre de 0 a 100: con el dominio
                // ajustado al maximo, un 40% ocupaba toda la barra y el grafico
                // exageraba el avance.
                var ejeY = {
                    title: meta.porcentaje_label || '% Ejecución',
                    domain: [0, 1],
                    tickFormat: function (d) { return Math.round(d * 100) + '%'; }
                };

                function dibujar() {
                    var grafico = esLinea ? new d3plus.LinePlot() : new d3plus.BarChart();
                    // D3plus mide el ancho del contenedor al renderizar; si aun
                    // no esta maquetado cae a su ancho por defecto (300 px) y el
                    // grafico sale diminuto. Se lo pasamos medido.
                    var ancho = lienzo.clientWidth || c.clientWidth || el.clientWidth || 0;

                    grafico
                        .data(datos)
                        .groupBy('grupo')
                        .select(lienzo)
                        .color(function () { return color; })
                        .tooltipConfig(tooltip)
                        .legend(false)
                        .height(cfg.altura || 460)
                        .locale('es-ES');

                    if (ancho > 0) { grafico.width(ancho); }

                    // La etiqueta por defecto repite el nombre de la serie en
                    // cada barra; el dato util es el porcentaje.
                    grafico.label(esLinea ? false : function (d) { return Fmt.pct(d.pct); });

                    if ('columnas' === cfg.tipo || esLinea) {
                        // Categorias en el eje horizontal.
                        grafico.x('etiqueta').y('pct').discrete('x')
                            .yConfig(ejeY).xConfig({ title: '' });
                    } else {
                        // Barras horizontales: los nombres de dependencia son largos
                        // y en vertical se solapan o se recortan.
                        grafico.x('pct').y('etiqueta').discrete('y')
                            .xConfig(ejeY).yConfig({ title: '' });
                    }

                    grafico.on('click', function (d) {
                        if (actual) { return; }
                        var destino = d.nombre || d.etiqueta;
                        if (cfg.enlazar) {
                            Coord.fijar(clave, { valor: destino });
                        } else {
                            actual = destino;
                            render();
                        }
                    });

                    grafico.render();
                }

                // Un frame de margen para que el contenedor tenga ancho real.
                if (typeof requestAnimationFrame === 'function') {
                    requestAnimationFrame(dibujar);
                } else {
                    dibujar();
                }

                // Al cambiar el ancho de la ventana el SVG conserva el ancho
                // con el que se dibujo: se repinta, con un respiro.
                if (!el.dataset.avanceResize) {
                    el.dataset.avanceResize = '1';
                    var temporizador = null;
                    window.addEventListener('resize', function () {
                        clearTimeout(temporizador);
                        temporizador = setTimeout(render, 250);
                    });
                }

                // Textos que acompañan al grafico, en el mismo orden pedido.
                (cfg.analisis || []).forEach(function (tipo) {
                    var caja = document.createElement('div');
                    caja.className = 'sysman-pre__analisis sysman-pre__analisis--' + tipo;
                    caja.innerHTML = '<p class="sysman-pre__analisis-parrafo">Preparando análisis…</p>';
                    c.appendChild(caja);

                    var p = base(cfg);
                    p.vista = 'avance';
                    p.tipo = tipo;
                    p.valor = actual;

                    api('analisis', p).then(function (r) {
                        var a = (r && r.data) || {};
                        caja.innerHTML = (a.parrafos || []).map(function (t) {
                            return '<p class="sysman-pre__analisis-parrafo">' + esc(t) + '</p>';
                        }).join('');
                    }).catch(function () { caja.remove(); });
                });
            }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.enlazar) {
            Coord.suscribir(clave, function (estado) {
                if (estado.valor !== actual) { actual = estado.valor; render(); }
            });
        }
        render();
    }

    /* ── Arranque ─────────────────────────────────────────────── */
    var INIT = {
        treemap: initTreemap,
        lista: initLista,
        ejecucion: initEjecucion,
        explora: initExplora,
        avance: initAvance,
        analisis: initAnalisis,
        selector: initSelector
    };

    function arrancar() {
        document.querySelectorAll('[data-sysman-pre]').forEach(function (el) {
            if (el.dataset.iniciado) { return; }
            el.dataset.iniciado = '1';

            var tipo = el.getAttribute('data-sysman-pre');
            var bloque = el.querySelector('[data-rol="config"]');
            if (!bloque || !INIT[tipo]) { return; }

            var cfg;
            try {
                cfg = JSON.parse(bloque.textContent);
            } catch (e) {
                pintarError(el, 'Configuración del shortcode no válida.');
                return;
            }

            if (!cfg.anio || !cfg.mes) {
                pintarVacio(el, cfg.modulo === 'ingresos'
                    ? 'No hay datos de ingresos importados todavía.'
                    : 'No hay datos presupuestales importados todavía.');
                return;
            }

            try {
                INIT[tipo](el, cfg);
            } catch (e) {
                console.error('[Presupuesto]', e);
                pintarError(el, 'Error al iniciar el componente.');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }

    // Expuesto para depuracion y para integraciones de terceros.
    window.SysmanPresupuestoCoord = Coord;
})();
