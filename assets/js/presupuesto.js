/**
 * SYSMAN Suite — Modulo Presupuesto
 * Gobernacion de Narino
 *
 * Renderiza los shortcodes [sysman_pre_*] contra la REST del modulo y
 * coordina el filtro compartido entre los componentes que declaran
 * enlazar="si". Vanilla JS: no depende de jQuery.
 */
(function () {
    'use strict';

    var CFG = window.sysmanPresupuesto || {};
    var CADENA = CFG.cadena || { DIS: 'Disponibilidad (CDP)', RES: 'Registro de compromiso (RP)', OBL: 'Obligacion', EGR: 'Egreso (pago)' };

    /* ── Coordinador de filtro compartido ─────────────────────────
       Un estado por "grupo" de pagina. Los componentes con enlazar="si"
       publican y escuchan; los demas quedan aislados.                */
    var Coord = {
        grupos: {},

        grupo: function (nombre) {
            if (!this.grupos[nombre]) {
                this.grupos[nombre] = { estado: { dependencia: '' }, subs: [] };
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

    /* ── Formato de numeros ───────────────────────────────────── */
    var Fmt = {
        moneda: function (v) {
            var n = parseFloat(v) || 0;
            return '$' + n.toLocaleString('es-CO', { maximumFractionDigits: 0 });
        },
        compacto: function (v) {
            var n = parseFloat(v) || 0;
            var a = Math.abs(n);
            if (a >= 1e12) { return (n / 1e12).toFixed(1).replace('.', ',') + ' Billones'; }
            if (a >= 1e9) { return (n / 1e9).toFixed(1).replace('.', ',') + ' MMll'; }
            if (a >= 1e6) { return (n / 1e6).toFixed(1).replace('.', ',') + ' Mll'; }
            if (a >= 1e3) { return (n / 1e3).toFixed(1).replace('.', ',') + ' Mil'; }
            return n.toLocaleString('es-CO');
        },
        entero: function (v) { return (parseInt(v, 10) || 0).toLocaleString('es-CO'); },
        pct: function (frac) { return ((parseFloat(frac) || 0) * 100).toFixed(1).replace('.', ',') + '%'; }
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

    function ctxDe(cfg) {
        return { compania: cfg.compania, anio: cfg.anio, mes: cfg.mes };
    }

    function cuerpoDe(el) { return el.querySelector('[data-rol="cuerpo"]'); }

    function pintarError(el, msg) {
        var c = cuerpoDe(el);
        if (c) { c.innerHTML = '<p class="sysman-pre__error">' + esc(msg) + '</p>'; }
    }

    function pintarVacio(el, msg) {
        var c = cuerpoDe(el);
        if (c) { c.innerHTML = '<p class="sysman-pre__vacio">' + esc(msg) + '</p>'; }
    }

    function cargando(el) {
        var c = cuerpoDe(el);
        if (c) { c.innerHTML = '<p class="sysman-pre__cargando">Cargando datos…</p>'; }
    }

    /* ── Treemap de dependencias (con drill-down a rubros) ─────── */
    function initTreemap(el, cfg) {
        if (typeof d3plus === 'undefined' || !d3plus.Treemap) {
            pintarError(el, 'No se pudo cargar la libreria de graficos (D3plus).');
            return;
        }

        var colores = (cfg.colores || '#1a5632,#0080c3,#ff7300,#ffc53b,#3eba6a,#844e80,#e74c3c,#9b59b6,#16a085,#d35400')
            .split(',').map(function (c) { return c.trim(); })
            .filter(function (c) { return /^#[0-9a-fA-F]{3,8}$/.test(c); });

        var depActual = '';

        function render() {
            cargando(el);
            var esDrill = !!depActual;
            var ruta = esDrill ? 'rubros' : 'dependencias';
            var params = Object.assign(ctxDe(cfg), { campo: cfg.campo, limite: cfg.limite || 0 });
            if (esDrill) { params.dependencia = depActual; }

            api(ruta, params).then(function (resp) {
                var filas = resp.data || [];
                if (!filas.length) {
                    pintarVacio(el, 'No hay datos de ' + (resp.meta.campo_label || cfg.campo) + ' para este periodo.');
                    return;
                }

                var datos = filas.map(function (f) {
                    return {
                        etiqueta: esDrill ? (f.codigo + ' · ' + f.nombre) : f.label,
                        nombre: esDrill ? f.nombre : f.label,
                        valor: Math.abs(parseFloat(f.value) || 0),
                        real: parseFloat(f.value) || 0
                    };
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
                    volver.textContent = '← Todas las dependencias · ' + depActual;
                    volver.addEventListener('click', function () {
                        depActual = '';
                        if (cfg.enlazar) { Coord.fijar(cfg.grupo, { dependencia: '' }); } else { render(); }
                    });
                    c.appendChild(volver);
                }

                var lienzo = document.createElement('div');
                lienzo.className = 'sysman-pre__lienzo';
                lienzo.style.height = (cfg.altura || 520) + 'px';
                c.appendChild(lienzo);

                var mapa = {};
                datos.forEach(function (d, i) { mapa[d.etiqueta] = colores[i % colores.length]; });

                new d3plus.Treemap()
                    .data(datos)
                    .groupBy('etiqueta')
                    .sum('valor')
                    .select(lienzo)
                    .color(function (d) { return mapa[d.etiqueta] || colores[0]; })
                    .tooltipConfig({
                        title: function (d) { return d.nombre || d.etiqueta; },
                        tbody: [
                            [(resp.meta.campo_label || 'Valor'), function (d) { return Fmt.moneda(d.real); }]
                        ]
                    })
                    .legend(false)
                    .height(cfg.altura || 520)
                    .locale('es-ES')
                    .on('click', function (d) {
                        if (esDrill) { return; }
                        var dep = d.etiqueta;
                        if (cfg.enlazar) {
                            Coord.fijar(cfg.grupo, { dependencia: dep });
                        } else {
                            depActual = dep;
                            render();
                        }
                    })
                    .render();
            }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.enlazar) {
            Coord.suscribir(cfg.grupo, function (estado) {
                if (estado.dependencia !== depActual) {
                    depActual = estado.dependencia;
                    render();
                }
            });
        }
        render();
    }

    /* ── Lista de dependencias ────────────────────────────────── */
    function initLista(el, cfg) {
        var filas = [];
        var seleccion = '';

        function pintar() {
            var c = cuerpoDe(el);
            var total = filas.reduce(function (a, f) { return a + (parseFloat(f.value) || 0); }, 0);
            var texto = (el.querySelector('[data-rol="buscar"]') || {}).value || '';
            var visibles = filas.filter(function (f) {
                return !texto || f.label.toLowerCase().indexOf(texto.toLowerCase()) > -1;
            });

            if (!visibles.length) {
                c.innerHTML = '<p class="sysman-pre__vacio">Ninguna dependencia coincide.</p>';
                return;
            }

            c.innerHTML = '<ul class="sysman-pre__lista" role="list">' + visibles.map(function (f) {
                var pct = total > 0 ? (parseFloat(f.value) || 0) / total : 0;
                var activa = f.label === seleccion ? ' is-activa' : '';
                return '<li><button type="button" class="sysman-pre__item' + activa + '" data-dep="' + esc(f.label) + '"'
                    + ' aria-pressed="' + (activa ? 'true' : 'false') + '">'
                    + '<span class="sysman-pre__item-nombre">' + esc(f.label) + '</span>'
                    + '<span class="sysman-pre__item-cifras">'
                    + '<span class="sysman-pre__item-rubros">' + Fmt.entero(f.rubros) + ' rubros</span>'
                    + '<span class="sysman-pre__item-valor">' + Fmt.moneda(f.value) + '</span>'
                    + '</span>'
                    + '<span class="sysman-pre__barra"><span style="width:' + (pct * 100).toFixed(2) + '%"></span></span>'
                    + '</button></li>';
            }).join('') + '</ul>';
        }

        function cargar() {
            cargando(el);
            api('dependencias', Object.assign(ctxDe(cfg), { campo: cfg.campo, limite: cfg.limite || 0 }))
                .then(function (resp) {
                    filas = resp.data || [];
                    if (!filas.length) {
                        pintarVacio(el, 'No hay dependencias con datos en este periodo.');
                        return;
                    }
                    pintar();
                }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.buscador) {
            var barra = document.createElement('div');
            barra.className = 'sysman-pre__buscador';
            barra.innerHTML = '<input type="search" data-rol="buscar" placeholder="Buscar dependencia…" aria-label="Buscar dependencia">';
            el.insertBefore(barra, cuerpoDe(el));
            barra.querySelector('input').addEventListener('input', pintar);
        }

        el.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.sysman-pre__item');
            if (!btn || !el.contains(btn)) { return; }
            var dep = btn.getAttribute('data-dep');
            seleccion = (seleccion === dep) ? '' : dep;
            pintar();
            if (cfg.enlazar) { Coord.fijar(cfg.grupo, { dependencia: seleccion }); }
        });

        if (cfg.enlazar) {
            Coord.suscribir(cfg.grupo, function (estado) {
                if (estado.dependencia !== seleccion) {
                    seleccion = estado.dependencia;
                    if (filas.length) { pintar(); }
                }
            });
        }
        cargar();
    }

    /* ── Panel de ejecucion (rubros + cadena documental) ──────── */
    function nodoCadena(nodo, nivel) {
        var tieneHijos = nodo.hijos && nodo.hijos.length;
        var html = '<li class="sysman-pre__doc sysman-pre__doc--' + esc(nodo.tipo.toLowerCase()) + '">'
            + '<div class="sysman-pre__doc-cab">'
            + '<span class="sysman-pre__doc-tipo">' + esc(nodo.tipo) + '</span>'
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

        if (tieneHijos) {
            html += '<ul class="sysman-pre__cadena sysman-pre__cadena--n' + nivel + '">'
                + nodo.hijos.map(function (h) { return nodoCadena(h, nivel + 1); }).join('')
                + '</ul>';
        }
        return html + '</li>';
    }

    function pintarDetalleRubro(caja, data) {
        var cons = data.consolidado || {};
        var mods = data.modificaciones || [];
        var cad = data.cadena || { documentos: [], huerfanos: [], conteo: {} };

        var claves = [
            ['apropiacionvigente', 'Apropiación vigente'],
            ['disponibilidades', 'Disponibilidades'],
            ['compromisos', 'Compromisos'],
            ['obligacion', 'Obligación'],
            ['pagos', 'Pagos'],
            ['saldodisponible', 'Saldo disponible']
        ];

        var html = '<div class="sysman-pre__consolidado">' + claves.map(function (k) {
            return '<div class="sysman-pre__kpi"><span class="sysman-pre__kpi-valor">'
                + Fmt.compacto(cons[k[0]] || 0) + '</span><span class="sysman-pre__kpi-label">'
                + esc(k[1]) + '</span></div>';
        }).join('') + '</div>';

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
        html += '</div>';

        caja.innerHTML = html;
    }

    function initEjecucion(el, cfg) {
        var dependencia = cfg.dependencia || '';

        function cargar() {
            if (!dependencia) {
                pintarVacio(el, 'Seleccione una dependencia para ver su ejecución.');
                return;
            }
            cargando(el);

            api('rubros', Object.assign(ctxDe(cfg), { dependencia: dependencia, campo: cfg.campo }))
                .then(function (resp) {
                    var filas = resp.data || [];
                    var c = cuerpoDe(el);
                    if (!filas.length) {
                        pintarVacio(el, 'La dependencia «' + dependencia + '» no tiene rubros con movimiento en este periodo.');
                        return;
                    }

                    c.innerHTML = '<p class="sysman-pre__resumen"><strong>' + esc(dependencia) + '</strong> · '
                        + Fmt.entero(filas.length) + ' rubros</p>'
                        + '<ul class="sysman-pre__rubros" role="list">' + filas.map(function (f) {
                            return '<li class="sysman-pre__rubro" data-codigo="' + esc(f.codigo) + '" aria-expanded="false">'
                                + '<button type="button" class="sysman-pre__rubro-tog">'
                                + '<span class="sysman-pre__rubro-flecha" aria-hidden="true">▶</span>'
                                + '<span class="sysman-pre__rubro-codigo">' + esc(f.codigo) + '</span>'
                                + '<span class="sysman-pre__rubro-nombre">' + esc(f.nombre) + '</span>'
                                + '<span class="sysman-pre__rubro-valor">' + Fmt.moneda(f.value) + '</span>'
                                + '</button>'
                                + '<div class="sysman-pre__rubro-cuerpo" hidden></div></li>';
                        }).join('') + '</ul>';
                }).catch(function (e) { pintarError(el, e.message); });
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

            caja.innerHTML = '<p class="sysman-pre__cargando">Cargando ejecución…</p>';
            api('rubro', Object.assign(ctxDe(cfg), { codigo: li.getAttribute('data-codigo') }))
                .then(function (resp) {
                    pintarDetalleRubro(caja, resp.data || {});
                    li.dataset.cargado = '1';
                }).catch(function (e) {
                    caja.innerHTML = '<p class="sysman-pre__error">' + esc(e.message) + '</p>';
                });
        });

        if (cfg.enlazar) {
            Coord.suscribir(cfg.grupo, function (estado) {
                if (estado.dependencia !== dependencia) {
                    dependencia = estado.dependencia;
                    cargar();
                }
            });
            // Si el shortcode traia dependencia fija, siembra el grupo.
            if (dependencia) { Coord.fijar(cfg.grupo, { dependencia: dependencia }); }
        }
        cargar();
    }

    /* ── Explorador maestro-detalle ───────────────────────────── */
    function initExplora(el, cfg) {
        var c = cuerpoDe(el);
        c.innerHTML = '<div class="sysman-pre__split">'
            + '<div class="sysman-pre__col sysman-pre__col--maestro" data-rol="maestro"></div>'
            + '<div class="sysman-pre__col sysman-pre__col--detalle" data-rol="detalle"></div>'
            + '</div>';

        if (cfg.altura) {
            c.querySelector('.sysman-pre__split').style.setProperty('--sysman-pre-alto', cfg.altura + 'px');
        }

        // Grupo propio cuando no esta enlazado a la pagina, para que el
        // maestro y el detalle sigan hablandose entre si.
        var grupo = cfg.enlazar ? cfg.grupo : 'explora-' + (el.id || Math.random().toString(36).slice(2));

        var maestro = c.querySelector('[data-rol="maestro"]');
        var detalle = c.querySelector('[data-rol="detalle"]');
        maestro.innerHTML = '<div data-rol="cuerpo"></div>';
        detalle.innerHTML = '<div data-rol="cuerpo"></div>';

        initLista(maestro, Object.assign({}, cfg, { enlazar: true, grupo: grupo, buscador: cfg.buscador }));
        initEjecucion(detalle, Object.assign({}, cfg, { enlazar: true, grupo: grupo, dependencia: '' }));
    }

    /* ── Analisis (descripcion / cualitativo / cuantitativo) ──── */
    function initAnalisis(el, cfg) {
        var dependencia = cfg.dependencia || '';

        function cargar() {
            cargando(el);
            // Al enlazarse, el analisis sigue lo que el usuario esta mirando:
            // con una dependencia elegida pasa a analizar sus rubros.
            var vista = dependencia ? 'rubros' : cfg.vista;

            api('analisis', Object.assign(ctxDe(cfg), {
                vista: vista,
                tipo: cfg.tipo,
                campo: cfg.campo,
                dependencia: dependencia
            })).then(function (resp) {
                var a = resp.data || {};
                var c = cuerpoDe(el);
                var html = '<div class="sysman-pre__analisis sysman-pre__analisis--' + esc(cfg.tipo) + '">'
                    + '<h4 class="sysman-pre__analisis-titulo">' + esc(a.titulo || '') + '</h4>';

                if (dependencia) {
                    html += '<p class="sysman-pre__analisis-ambito">Ámbito: ' + esc(dependencia) + '</p>';
                }
                (a.parrafos || []).forEach(function (p) {
                    html += '<p class="sysman-pre__analisis-parrafo">' + esc(p) + '</p>';
                });
                if ((a.metricas || []).length) {
                    html += '<dl class="sysman-pre__metricas">' + a.metricas.map(function (m) {
                        return '<div class="sysman-pre__metrica"><dt>' + esc(m.label) + '</dt><dd>' + esc(m.valor) + '</dd></div>';
                    }).join('') + '</dl>';
                }
                c.innerHTML = html + '</div>';
            }).catch(function (e) { pintarError(el, e.message); });
        }

        if (cfg.enlazar) {
            Coord.suscribir(cfg.grupo, function (estado) {
                if (estado.dependencia !== dependencia) {
                    dependencia = estado.dependencia;
                    cargar();
                }
            });
        }
        cargar();
    }

    /* ── Selector de filtro compartido ────────────────────────── */
    function initSelector(el, cfg) {
        cargando(el);
        api('dependencias', Object.assign(ctxDe(cfg), { campo: cfg.campo }))
            .then(function (resp) {
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
                    if (cfg.enlazar) { Coord.fijar(cfg.grupo, { dependencia: sel.value }); }
                });

                if (cfg.enlazar) {
                    sel.value = Coord.estado(cfg.grupo).dependencia || '';
                    Coord.suscribir(cfg.grupo, function (estado) {
                        if (sel.value !== estado.dependencia) { sel.value = estado.dependencia || ''; }
                    });
                }
            }).catch(function (e) { pintarError(el, e.message); });
    }

    /* ── Arranque ─────────────────────────────────────────────── */
    var INIT = {
        treemap: initTreemap,
        lista: initLista,
        ejecucion: initEjecucion,
        explora: initExplora,
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
                pintarVacio(el, 'No hay datos presupuestales importados todavía.');
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
