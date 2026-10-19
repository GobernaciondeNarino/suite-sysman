/**
 * SYSMAN Suite - Admin Import & Records Manager
 * Gobernación de Nariño
 */
(function ($) {
    'use strict';

    const MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
        'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const nombrePeriodo = (anio, mes) => (mes > 0 ? (MESES[mes] || mes) + ' de ' : 'año ') + anio;
    const plural = (n, uno, varios) => Number(n).toLocaleString('es-CO') + ' ' + (Number(n) === 1 ? uno : varios);
    const enumerar = (a) => (a.length > 1 ? a.slice(0, -1).join(', ') + ' y ' + a[a.length - 1] : a.join(''));

    /* ========================================
       Import Manager
       ======================================== */
    const ImportManager = {
        init() {
            this.bindEvents();
            this.toggleReportOptions();
        },

        bindEvents() {
            $('#sysman-import-btn').on('click', () => this.startImport());
            $('#sysman-report').on('change', () => this.toggleReportOptions());
            $('#sysman-compania').on('change', () => this.toggleCustomCompania());
            $('#sysman-check-dupes').on('click', () => this.checkDuplicates());
            $('#sysman-dupes-result').on('click', '#sysman-repair-schema', () => this.repairSchema());
            $('#sysman-dupes-result').on('click', '.sysman-dedupe', (e) => this.removeDuplicates($(e.currentTarget)));
            $('#sysman-check-sync').on('click', () => this.checkSync());
            $('#sysman-sync-result').on('click', '.sysman-delete-months', (e) => this.deleteMonths($(e.currentTarget)));
        },

        toggleReportOptions() {
            const report = $('#sysman-report').val();
            $('.sysman-auxiliar-options').toggle(report === 'auxiliar');
        },

        toggleCustomCompania() {
            const val = $('#sysman-compania').val();
            if (val === 'custom') {
                $('#sysman-compania-custom').show().focus();
            } else {
                $('#sysman-compania-custom').hide().val('');
            }
        },

        getCompania() {
            const val = $('#sysman-compania').val();
            if (val === 'custom') {
                return $('#sysman-compania-custom').val() || '001';
            }
            return val;
        },

        /* Informe de integridad: filas frente a registros distintos. Si sobran
           filas, ese periodo se importó dos veces y las cifras salen infladas. */
        checkDuplicates(aviso = '') {
            const btn = $('#sysman-check-dupes');
            const caja = $('#sysman-dupes-result');

            btn.prop('disabled', true);
            caja.html('<p>Verificando…</p>');

            $.ajax({
                url: sysmanAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sysman_check_duplicates',
                    nonce: sysmanAdmin.nonce,
                    anio: $('#sysman-anio').val(),
                },
                success: (response) => {
                    if (!response.success) {
                        caja.html('<div class="notice notice-error inline"><p>'
                            + (response.data && response.data.message ? response.data.message : 'Error') + '</p></div>');
                        return;
                    }
                    caja.html(aviso + this.renderEstructura(response.data.estructura)
                        + this.renderDuplicates(response.data.periodos || [], response.data.anio));
                },
                error: () => {
                    caja.html('<div class="notice notice-error inline"><p>No se pudo verificar.</p></div>');
                },
                complete: () => btn.prop('disabled', false),
            });
        },

        /* Borra las copias de importaciones anteriores de un periodo (o de todos
           los marcados); las líneas que SYSMAN repite en un mismo informe se quedan. */
        removeDuplicates(btn) {
            const d = btn.data();
            const copias = d.copias === 1
                ? 'la copia de una importación anterior'
                : 'las ' + Number(d.copias).toLocaleString('es-CO') + ' copias de importaciones anteriores';
            const pregunta = '¿Eliminar ' + copias + ' '
                + (d.tabla ? 'de ' + d.tabla + ' en ' + nombrePeriodo(d.anio, d.mes) : 'en ' + plural(d.periodos, 'periodo', 'periodos')) + '?';

            if (!window.confirm(pregunta + '\n\nSolo se borran filas que tienen otra idéntica importada después; se conserva '
                + 'la última importación, con las líneas que SYSMAN repite en un mismo informe. Esta acción no se puede deshacer.')) {
                return;
            }

            const texto = btn.text();
            $('#sysman-dupes-result .sysman-dedupe').prop('disabled', true);
            btn.text('Eliminando…');

            $.post(sysmanAdmin.ajaxUrl, {
                action: 'sysman_remove_duplicates',
                nonce: sysmanAdmin.nonce,
                tabla: d.tabla || '',
                compania: d.compania || '',
                anio: d.anio || 0,
                mes: d.mes || 0,
            })
                .done((response) => {
                    if (!response.success) {
                        $('#sysman-dupes-result .sysman-dedupe').prop('disabled', false);
                        btn.text(texto);
                        window.alert(response.data && response.data.message ? response.data.message : 'Error');
                        return;
                    }
                    this.checkDuplicates('<div class="notice notice-success inline"><p>Se '
                        + (response.data.eliminadas === 1 ? 'eliminó ' : 'eliminaron ')
                        + plural(response.data.eliminadas, 'copia', 'copias') + ' de importaciones anteriores.</p></div>');
                })
                .fail(() => {
                    $('#sysman-dupes-result .sysman-dedupe').prop('disabled', false);
                    btn.text(texto);
                    window.alert('No se pudieron eliminar los duplicados.');
                });
        },

        /* Borra meses importados: uno solo (data-mes) o todos los del año salvo
           el que se conserva (data-conservar). */
        deleteMonths(btn) {
            const d = btn.data();
            const lista = enumerar(String(d.meses).split(',').map((m) => MESES[Number(m)] || m));

            if (!window.confirm('¿Eliminar los datos de ' + lista + ' de ' + d.anio + '?\n\n'
                + (d.vistas ? 'Es el mes que muestran hoy las vistas de ' + d.vistas + ': pasarán a mostrar otro mes. ' : '')
                + 'Se borran del plan presupuestal, la ejecución de gastos, el auxiliar y la ejecución de ingresos '
                + '(la nómina es anual y no se toca). Esos meses dejarán de aparecer en el selector de periodo, y las vistas '
                + 'o seguimientos fijados a ellos quedarán vacíos. Esta acción no se puede deshacer.')) {
                return;
            }

            const texto = btn.text();
            $('#sysman-sync-result .sysman-delete-months').prop('disabled', true);
            btn.text('Eliminando…');

            $.post(sysmanAdmin.ajaxUrl, {
                action: 'sysman_delete_months',
                nonce: sysmanAdmin.nonce,
                compania: d.compania,
                anio: d.anio,
                mes: d.mes || 0,
                conservar: d.conservar || 0,
            })
                .done((response) => {
                    if (!response.success) {
                        $('#sysman-sync-result .sysman-delete-months').prop('disabled', false);
                        btn.text(texto);
                        window.alert(response.data && response.data.message ? response.data.message : 'Error');
                        return;
                    }
                    const meses = enumerar((response.data.meses || []).map((m) => MESES[m] || m));
                    this.checkSync('<div class="notice notice-success inline"><p>Se '
                        + (response.data.eliminadas === 1 ? 'eliminó ' : 'eliminaron ') + plural(response.data.eliminadas, 'fila', 'filas')
                        + (meses ? ' de ' + meses + ' de ' + d.anio : '') + '.</p></div>');
                })
                .fail(() => {
                    $('#sysman-sync-result .sysman-delete-months').prop('disabled', false);
                    btn.text(texto);
                    window.alert('No se pudieron eliminar los meses.');
                });
        },

        /* Sincronización: meses por tabla y cruce plan ↔ ejecución ↔ auxiliar. */
        checkSync(aviso = '') {
            const btn = $('#sysman-check-sync');
            const caja = $('#sysman-sync-result');

            btn.prop('disabled', true);
            caja.html('<p>Verificando…</p>');

            $.post(sysmanAdmin.ajaxUrl, {
                action: 'sysman_check_sync',
                nonce: sysmanAdmin.nonce,
                compania: this.getCompania(),
                anio: $('#sysman-anio').val(),
                mes: $('#sysman-mes').val(),
            })
                .done((response) => {
                    if (!response.success) {
                        caja.html('<div class="notice notice-error inline"><p>'
                            + (response.data && response.data.message ? response.data.message : 'Error') + '</p></div>');
                        return;
                    }
                    caja.html(aviso + this.renderSync(response.data));
                })
                .fail(() => caja.html('<div class="notice notice-error inline"><p>No se pudo verificar.</p></div>'))
                .always(() => btn.prop('disabled', false));
        },

        renderSync(data) {
            const esc = (t) => $('<div>').text(t === null || t === undefined ? '' : t).html();
            const num = (n) => Number(n).toLocaleString('es-CO');
            const pesos = (n) => '$' + Number(n).toLocaleString('es-CO', { maximumFractionDigits: 0 });
            const tablas = {
                sysman_plan_presupuestal: 'Plan',
                sysman_ejecucion_gastos: 'Ejecución gastos',
                sysman_auxiliar_cuentas: 'Auxiliar',
                sysman_ejecucion_ingresos: 'Ingresos',
            };
            let html = '<h3>Sincronización entre tablas</h3>';
            if (data.nota) {
                html += '<p class="description">' + esc(data.nota) + '</p>';
            }
            const c = data.cruce;

            if (c) {
                const p = c.periodo;
                html += c.sincronizado
                    ? '<div class="notice notice-success inline"><p>Periodo ' + esc(p.anio) + '-' + esc(p.mes)
                      + ': el plan y la ejecución cuadran. Las vistas muestran toda la ejecución.</p></div>'
                    : '<div class="notice notice-error inline"><p><strong>Periodo ' + esc(p.anio) + '-' + esc(p.mes) + ':</strong></p><ul style="list-style:disc;margin-left:20px;">'
                      + c.avisos.map((a) => '<li>' + esc(a) + '</li>').join('') + '</ul></div>';

                html += '<table class="widefat striped" style="margin-top:12px;max-width:760px;"><tbody>'
                    + '<tr><th>Apropiación vigente en la ejecución</th><td style="text-align:right;">' + pesos(c.ejecucion.apropiacion) + ' (' + num(c.ejecucion.filas) + ' rubros)</td></tr>'
                    + '<tr><th>Apropiación que muestran las vistas</th><td style="text-align:right;">' + pesos(c.en_vistas.apropiacion) + '</td></tr>'
                    + '<tr><th>Fuera de las vistas (ejecución sin plan)</th><td style="text-align:right;">' + pesos(c.fuera_de_vistas)
                    + ' · ' + (c.porcentaje_fuera * 100).toLocaleString('es-CO', { maximumFractionDigits: 2 }) + ' %</td></tr>'
                    + '<tr><th>Rubros del plan sin ejecución</th><td style="text-align:right;">' + num(c.plan_sin_ejecucion.filas) + '</td></tr>'
                    + '<tr><th>Plan por tipo de vigencia</th><td style="text-align:right;">'
                    + Object.keys(c.plan_por_vigencia).map((k) => esc(k || '(vacío)') + ': ' + num(c.plan_por_vigencia[k])).join(' · ') + '</td></tr>'
                    + '</tbody></table>';

                const ejemplos = (titulo, lista) => (lista && lista.length
                    ? '<p><strong>' + titulo + ':</strong> ' + lista.map((x) => '<code>' + esc(x) + '</code>').join(' ') + '</p>' : '');
                html += ejemplos('Ejecución sin plan (mayor apropiación primero)', c.ejecucion_sin_plan.ejemplos)
                    + ejemplos('Códigos repetidos en el plan', c.plan_repetido.ejemplos)
                    + ejemplos('Rubros del auxiliar que no están en el plan', c.auxiliar_sin_plan.ejemplos);
            }

            const cob = data.cobertura || [];
            if (!cob.length) {
                return html + '<div class="notice notice-warning inline"><p>No hay datos importados.</p></div>';
            }

            // Se conserva el periodo revisado arriba y, aunque no coincida, el
            // que muestran por defecto las vistas: el mes revisado puede estar
            // a medio importar mientras las páginas publican el anterior.
            const attr = (t) => esc(t).replace(/"/g, '&quot;');
            const conservar = c ? c.periodo : null;
            const enVistas = data.en_vistas || {};
            const mismo = (a, b) => a && b && Number(a.anio) === Number(b.anio) && Number(a.mes) === Number(b.mes);
            const vistasDe = (p) => Object.keys(enVistas).filter((k) => mismo(enVistas[k], p))
                .map((k) => (k === 'gastos' ? 'Gastos' : 'Ingresos'));
            const otros = conservar
                ? cob.filter((p) => p.anio === conservar.anio && !mismo(p, conservar) && !vistasDe(p).length) : [];
            const protegidos = conservar
                ? cob.filter((p) => p.anio === conservar.anio && !mismo(p, conservar) && vistasDe(p).length) : [];
            const botonMeses = (anio, meses, extra, texto, clase) => '<button type="button" class="button ' + clase + ' sysman-delete-months"'
                + ' data-compania="' + attr(data.compania) + '" data-anio="' + attr(anio) + '" data-meses="' + attr(meses.join(',')) + '"'
                + extra + '>' + texto + '</button>';

            html += '<h4>Meses importados por tabla</h4>';
            if (otros.length) {
                html += '<div class="notice notice-warning inline"><p>Además de <strong>' + esc(nombrePeriodo(conservar.anio, conservar.mes))
                    + '</strong> (el periodo revisado arriba) hay datos de ' + plural(otros.length, 'mes', 'meses') + ' más de '
                    + esc(conservar.anio) + ': ' + enumerar(otros.map((p) => esc(MESES[p.mes] || p.mes))) + '. '
                    + 'Las vistas consultan un solo mes a la vez, así que los demás solo hacen falta si alguien consulta ese corte. '
                    + protegidos.map((p) => {
                        const mes = String(MESES[p.mes] || p.mes);
                        return esc(mes.charAt(0).toUpperCase() + mes.slice(1)) + ' no se incluye: es el mes que muestran hoy las vistas de '
                            + vistasDe(p).join(' e ') + '. ';
                    }).join('')
                    + 'Para conservar otro mes, elíjalo en «Año» y «Mes» y vuelva a verificar.</p>'
                    + '<p>' + botonMeses(conservar.anio, otros.map((p) => p.mes), ' data-conservar="' + attr(conservar.mes) + '"',
                        'Eliminar los otros meses de ' + esc(conservar.anio), 'button-secondary') + '</p></div>';
            }

            html += '<table class="widefat striped"><thead><tr><th>Periodo</th>'
                + Object.values(tablas).map((t) => '<th style="text-align:right;">' + t + '</th>').join('')
                + '<th>Observaciones</th><th>Acción</th></tr></thead><tbody>';
            cob.forEach((p) => {
                const vistas = vistasDe(p);
                const accion = mismo(p, conservar)
                    ? '<em>Se conserva</em>'
                    : (vistas.length ? '<em>En las vistas de ' + vistas.join(' e ') + '</em><br>' : '')
                      + botonMeses(p.anio, [p.mes], ' data-mes="' + attr(p.mes) + '" data-vistas="' + attr(vistas.join(' e ')) + '"',
                          'Eliminar', 'button-small');

                html += '<tr' + (p.avisos.length ? ' style="background:#fcf8e3;"' : '') + '><td style="white-space:nowrap;">' + esc(p.anio) + '-' + esc(p.mes) + '</td>'
                    + Object.keys(tablas).map((k) => {
                        const t = p.tablas[k];
                        return '<td style="text-align:right;"' + (t ? ' title="Importado: ' + esc(t.ultima) + '"' : '') + '>'
                            + (t ? num(t.filas) : '—') + '</td>';
                    }).join('')
                    + '<td>' + (p.avisos.length ? p.avisos.map(esc).join('<br>') : '—') + '</td>'
                    + '<td>' + accion + '</td></tr>';
            });
            return html + '</tbody></table>';
        },

        /* Añade las columnas e índices que falten (nunca borra nada). */
        repairSchema() {
            const btn = $('#sysman-repair-schema');
            btn.prop('disabled', true).text('Reparando…');

            $.post(sysmanAdmin.ajaxUrl, { action: 'sysman_repair_schema', nonce: sysmanAdmin.nonce })
                .done((response) => {
                    if (!response.success) {
                        btn.prop('disabled', false).text('Reparar estructura');
                        window.alert(response.data && response.data.message ? response.data.message : 'Error');
                        return;
                    }
                    $('#sysman-estructura').replaceWith(this.renderEstructura(response.data.estructura));
                })
                .fail(() => {
                    btn.prop('disabled', false).text('Reparar estructura');
                    window.alert('No se pudo reparar la estructura.');
                });
        },

        renderEstructura(estructura) {
            if (!estructura || !estructura.tablas) {
                return '';
            }
            const esc = (t) => $('<div>').text(t === null || t === undefined ? '' : t).html();
            const num = (n) => Number(n).toLocaleString('es-CO');
            const lista = (a) => (a && a.length ? a.map((x) => '<code>' + esc(x) + '</code>').join(' ') : '—');

            let reparable = false;
            let problemas = 0;
            let filas = '';

            estructura.tablas.forEach((t) => {
                if (!t.existe) {
                    problemas++;
                    filas += '<tr style="background:#fcf0f1;"><td><code>' + esc(t.tabla) + '</code></td>'
                        + '<td colspan="6"><strong>La tabla no existe.</strong> Desactive y active el plugin para crearla.</td></tr>';
                    return;
                }
                const faltan = t.columnas_faltantes.length + t.indices_faltantes.length;
                const trunc = Object.keys(t.posible_truncado || {});
                const mal = faltan > 0 || !t.transaccional || t.filas_invalidas > 0 || trunc.length > 0;
                if (faltan > 0) { reparable = true; }
                if (mal) { problemas++; }

                filas += '<tr' + (mal ? ' style="background:#fcf0f1;"' : '') + '>'
                    + '<td><code>' + esc(t.tabla) + '</code></td>'
                    + '<td style="text-align:right;">' + num(t.filas) + '</td>'
                    + '<td>' + esc(t.motor) + (t.transaccional ? '' : ' <strong>(sin transacciones)</strong>') + '</td>'
                    + '<td>' + lista(t.columnas_faltantes) + '</td>'
                    + '<td>' + lista(t.indices_faltantes) + '</td>'
                    + '<td style="text-align:right;">' + num(t.filas_invalidas) + '</td>'
                    + '<td>' + lista(trunc) + '</td>'
                    + '</tr>';
            });

            let html = '<div id="sysman-estructura"><h3 style="margin-top:0;">Estructura de las tablas</h3>';

            html += problemas
                ? '<div class="notice notice-error inline"><p><strong>' + problemas + ' tabla(s) con observaciones.</strong> '
                  + (reparable ? 'Faltan columnas o índices: el botón los añade sin tocar los datos.' : 'Revise el detalle.') + '</p>'
                  + (reparable ? '<p><button type="button" id="sysman-repair-schema" class="button button-primary">Reparar estructura</button></p>' : '')
                  + '</div>'
                : '<div class="notice notice-success inline"><p>Estructura completa: todas las columnas e índices están en su lugar.</p></div>';

            if (estructura.intercalacion_mixta) {
                html += '<div class="notice notice-warning inline"><p>Las columnas de cruce entre tablas usan intercalaciones distintas ('
                    + Object.keys(estructura.intercalaciones_cruce).map((k) => esc(k) + ': ' + esc(estructura.intercalaciones_cruce[k])).join(', ')
                    + '). Los cruces serán lentos o fallarán; unifique la intercalación de las tablas.</p></div>';
            }

            html += '<table class="widefat striped" style="margin-top:12px;"><thead><tr>'
                + '<th>Tabla</th><th style="text-align:right;">Filas</th><th>Motor</th>'
                + '<th>Columnas faltantes</th><th>Índices faltantes</th>'
                + '<th style="text-align:right;">Periodo inválido</th><th>Posible truncado</th></tr></thead><tbody>'
                + filas + '</tbody></table>'
                + '<p class="description">«Sin transacciones»: la importación no puede revertirse si falla a mitad; convierta la tabla a InnoDB. '
                + '«Posible truncado»: el valor más largo llena el campo, puede que la API envíe textos más largos de lo que cabe.</p></div>'
                + '<h3>Duplicados por periodo</h3>';

            return html;
        },

        renderDuplicates(periodos, anio) {
            if (!periodos.length) {
                return '<div class="notice notice-warning inline"><p>No hay datos importados para ese año.</p></div>';
            }

            const conExceso = periodos.filter((p) => p.exceso > 0);
            const conCopias = periodos.filter((p) => p.copias > 0);
            const esc = (t) => $('<div>').text(t === null || t === undefined ? '' : t).html();
            const attr = (t) => esc(t).replace(/"/g, '&quot;');
            const num = (n) => Number(n).toLocaleString('es-CO');
            const suma = (lista, campo) => lista.reduce((total, p) => total + p[campo], 0);
            const totalCopias = suma(conCopias, 'copias');
            const totalRepetidas = suma(conExceso, 'repetidas');
            const totalDistintas = Math.max(0, suma(conExceso, 'exceso') - totalCopias - totalRepetidas);

            let html = '';
            if (!conExceso.length) {
                html = '<div class="notice notice-success inline"><p>Sin duplicados: cada fila representa un registro distinto.</p></div>';
            }
            if (totalCopias > 0) {
                html += '<div class="notice notice-error inline"><p><strong>' + plural(totalCopias, 'fila es copia', 'filas son copias')
                    + ' de una importación anterior</strong> (' + plural(conCopias.length, 'periodo', 'periodos') + '): hay otra idéntica '
                    + 'importada después, así que el periodo se cargó más de una vez y las cifras salen infladas. Al eliminar las copias '
                    + 'se conserva la última importación.</p>'
                    + '<p><button type="button" class="button button-primary sysman-dedupe" data-anio="' + attr(anio || 0) + '"'
                    + ' data-copias="' + attr(totalCopias) + '" data-periodos="' + attr(conCopias.length) + '">'
                    + (totalCopias === 1 ? 'Eliminar la copia' : 'Eliminar las ' + num(totalCopias) + ' copias') + '</button></p></div>';
            }
            if (totalRepetidas + totalDistintas > 0) {
                html += '<div class="notice notice-warning inline"><p>'
                    + (totalRepetidas > 0 ? plural(totalRepetidas, 'fila llegó repetida', 'filas llegaron repetidas')
                        + ' dentro de una misma importación: así las entrega SYSMAN (por ejemplo, un comprobante con dos líneas iguales) '
                        + 'y se conservan. ' : '')
                    + (totalDistintas > 0 ? plural(totalDistintas, 'fila comparte', 'filas comparten') + ' la clave de otro registro pero '
                        + (totalDistintas === 1 ? 'cambia' : 'cambian') + ' en algún valor. ' : '')
                    + 'No se borran automáticamente porque pueden ser datos válidos; si cree que sobran, reimporte ese periodo marcando '
                    + '«Limpiar el periodo antes de importar».</p></div>';
            }

            html += '<table class="widefat striped" style="margin-top:12px;"><thead><tr>'
                + '<th>Tabla</th><th>Compañía</th><th>Año</th><th>Mes</th>'
                + '<th style="text-align:right;">Filas</th><th style="text-align:right;">Registros</th>'
                + '<th style="text-align:right;">De más</th>'
                + (conExceso.length
                    ? '<th style="text-align:right;">Copias de importaciones anteriores</th>'
                      + '<th style="text-align:right;">Repetidas en el origen</th><th>Acción</th>'
                    : '')
                + '</tr></thead><tbody>';

            periodos.forEach((p) => {
                const fondo = p.copias > 0 ? '#fcf0f1' : (p.exceso > 0 ? '#fcf8e3' : '');
                const boton = p.copias > 0
                    ? '<button type="button" class="button button-small sysman-dedupe" data-tabla="' + attr(p.tabla) + '"'
                      + ' data-compania="' + attr(p.compania) + '" data-anio="' + attr(p.anio) + '" data-mes="' + attr(p.mes) + '"'
                      + ' data-copias="' + attr(p.copias) + '">Eliminar copias</button>'
                    : '';

                html += '<tr' + (fondo ? ' style="background:' + fondo + ';"' : '') + '>'
                    + '<td><code>' + esc(p.tabla) + '</code></td>'
                    + '<td>' + esc(p.compania) + '</td>'
                    + '<td>' + esc(p.anio) + '</td>'
                    + '<td>' + (p.mes > 0 ? esc(p.mes) : '—') + '</td>'
                    + '<td style="text-align:right;">' + num(p.filas) + '</td>'
                    + '<td style="text-align:right;">' + num(p.unicas) + '</td>'
                    + '<td style="text-align:right;"><strong>' + (p.exceso > 0 ? num(p.exceso) : '0') + '</strong></td>'
                    + (conExceso.length
                        ? '<td style="text-align:right;">' + (p.exceso > 0 ? num(p.copias) : '—') + '</td>'
                          + '<td style="text-align:right;">' + (p.exceso > 0 ? num(p.repetidas) : '—') + '</td>'
                          + '<td>' + boton + '</td>'
                        : '')
                    + '</tr>';
            });

            return html + '</tbody></table>';
        },

        startImport() {
            const btn = $('#sysman-import-btn');
            const progress = $('#sysman-import-progress');
            const results = $('#sysman-import-results');

            if (!confirm(sysmanAdmin.strings.confirmImport)) {
                return;
            }

            // Disable button and show progress
            btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update sysman-spin"></span> ' + sysmanAdmin.strings.importing
            );
            progress.show();
            results.hide().empty();

            // Reset progress bar
            this.setProgress(0, 'Preparando importación...');

            const data = {
                action: 'sysman_start_import',
                nonce: sysmanAdmin.nonce,
                compania: this.getCompania(),
                anio: $('#sysman-anio').val(),
                mes: $('#sysman-mes').val(),
                report: $('#sysman-report').val(),
                tipo_cpte: $('#sysman-tipo-cpte').val(),
                limpiar: $('#sysman-limpiar').is(':checked') ? 1 : 0,
            };

            // Start polling for status updates
            this.statusInterval = setInterval(() => this.pollStatus(), 1500);

            $.ajax({
                url: sysmanAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                timeout: 600000, // 10 minutes
                success: (response) => {
                    this.stopPolling();
                    this.setProgress(100, sysmanAdmin.strings.complete);

                    if (response.success) {
                        this.showResults(response.data.results, 'success');
                    } else {
                        this.showResults(
                            { error: response.data?.message || sysmanAdmin.strings.error },
                            'error'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    this.stopPolling();

                    let errorMsg = sysmanAdmin.strings.error;
                    if (status === 'timeout') {
                        errorMsg = 'La importación excedió el tiempo máximo de espera. Revise los logs para más detalles.';
                    } else if (error) {
                        errorMsg += ': ' + error;
                    }

                    this.showResults({ error: errorMsg }, 'error');
                },
                complete: () => {
                    btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-download"></span> Iniciar Importación'
                    );
                    setTimeout(() => progress.fadeOut(400), 2500);
                },
            });
        },

        pollStatus() {
            $.ajax({
                url: sysmanAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sysman_import_status',
                    nonce: sysmanAdmin.nonce,
                },
                success: (response) => {
                    if (response.success && response.data.running) {
                        const d = response.data;
                        const pct = Math.round((d.step / d.total) * 100);
                        const stepLabel = d.report_label ? ` - ${d.report_label}` : '';
                        this.setProgress(
                            Math.min(pct, 95),
                            `Paso ${d.step} de ${d.total}${stepLabel}: ${d.message}`
                        );

                        // Update step indicators
                        this.updateStepIndicators(d);
                    }
                },
            });
        },

        stopPolling() {
            if (this.statusInterval) {
                clearInterval(this.statusInterval);
                this.statusInterval = null;
            }
        },

        setProgress(percent, message) {
            const container = $('#sysman-import-progress');
            const fill = container.find('.sysman-progress-fill');
            const text = container.find('.sysman-progress-text');
            const pctLabel = container.find('.sysman-progress-percent');

            fill.css('width', percent + '%');
            container.attr('aria-valuenow', Math.round(percent));
            text.text(message || '');
            if (pctLabel.length) {
                pctLabel.text(Math.round(percent) + '%');
            }
        },

        updateStepIndicators(data) {
            const steps = $('#sysman-import-steps');
            if (steps.length === 0) return;

            steps.find('.sysman-step').each(function (index) {
                const stepNum = index + 1;
                $(this).removeClass('active completed');
                if (stepNum < data.step) {
                    $(this).addClass('completed');
                } else if (stepNum === data.step) {
                    $(this).addClass('active');
                }
            });
        },

        showResults(data, type) {
            const container = $('#sysman-import-results');
            container.removeClass('success error').addClass(type).empty();

            if (type === 'error') {
                container.append(
                    $('<div class="sysman-result-header error">').html(
                        '<span class="dashicons dashicons-warning"></span> ' +
                        '<strong>Error en la importación</strong>'
                    )
                );
                container.append($('<p>').text(data.error));
            } else {
                container.append(
                    $('<div class="sysman-result-header success">').html(
                        '<span class="dashicons dashicons-yes-alt"></span> ' +
                        '<strong>Importación completada exitosamente</strong>'
                    )
                );

                const reportLabels = {
                    ejecucion: 'Ejecución Presupuestal de Gastos',
                    auxiliar: 'Auxiliar Presupuestal por Cuentas',
                    auxiliar_dis: 'Auxiliar — Disponibilidades (DIS)',
                    auxiliar_res: 'Auxiliar — Reservas (RES)',
                    plan: 'Plan Presupuestal',
                    personal: 'Personal Activo de Nómina',
                    ingresos: 'Ejecución de Ingresos',
                };

                const table = $('<table class="sysman-results-table"><thead><tr>' +
                    '<th>Informe</th><th>Estado</th><th>Registros</th><th>Detalles</th>' +
                    '</tr></thead><tbody></tbody></table>');

                let totalImported = 0;
                let totalRecords = 0;
                let hasErrors = false;

                for (const [key, result] of Object.entries(data)) {
                    const label = reportLabels[key] || key;
                    const row = $('<tr>');
                    row.append($('<td>').text(label));

                    if (result.success) {
                        const imported = parseInt(result.imported) || 0;
                        const total = parseInt(result.total) || 0;
                        totalImported += imported;
                        totalRecords += total;

                        row.append($('<td>').html('<span class="sysman-badge success">OK</span>'));
                        row.append($('<td>').html(`<strong>${imported.toLocaleString('es-CO')}</strong> / ${total.toLocaleString('es-CO')}`));
                        row.append($('<td>').text(imported === total ? 'Todos importados' : `${total - imported} omitidos (duplicados)`));
                    } else {
                        hasErrors = true;
                        row.append($('<td>').html('<span class="sysman-badge error">Error</span>'));
                        row.append($('<td>').text('-'));
                        row.append($('<td>').text(result.error || 'Error desconocido'));
                    }

                    table.find('tbody').append(row);
                }

                // Summary row
                if (Object.keys(data).length > 1) {
                    table.find('tbody').append(
                        $('<tr class="sysman-result-total">').html(
                            `<td><strong>Total</strong></td><td></td>` +
                            `<td><strong>${totalImported.toLocaleString('es-CO')}</strong> / ${totalRecords.toLocaleString('es-CO')}</td>` +
                            `<td></td>`
                        )
                    );
                }

                container.append(table);

                if (hasErrors) {
                    container.append(
                        $('<p class="sysman-result-note">').html(
                            '<span class="dashicons dashicons-info"></span> ' +
                            'Algunos informes presentaron errores. Revise los <a href="?page=sysman-logs">logs</a> para más detalles.'
                        )
                    );
                }
            }

            container.show();

            // Reload page after delay to update stats
            setTimeout(() => location.reload(), 4000);
        },
    };

    /* ========================================
       Records Manager
       ======================================== */
    const RecordsManager = {
        currentPage: 1,
        perPage: 20,
        totalPages: 0,

        init() {
            if ($('#sysman-records-container').length === 0) return;
            this.bindEvents();
            this.loadRecords();
        },

        bindEvents() {
            $('#sysman-table-select').on('change', () => {
                this.currentPage = 1;
                this.loadYears();
                this.loadRecords();
            });
            $('#sysman-filter-btn').on('click', () => {
                this.currentPage = 1;
                this.loadRecords();
            });
            $('#sysman-filter-search').on('keypress', (e) => {
                if (e.which === 13) {
                    this.currentPage = 1;
                    this.loadRecords();
                }
            });
            $('#sysman-prev-page').on('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadRecords();
                }
            });
            $('#sysman-next-page').on('click', () => {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadRecords();
                }
            });
            $('#sysman-export-csv-btn').on('click', () => this.exportCSV());

            // Modal close
            $(document).on('click', '.sysman-modal-close, .sysman-modal-overlay', () => {
                $('#sysman-record-modal').hide();
            });
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    $('#sysman-record-modal').hide();
                }
            });
        },

        loadYears() {
            const table = $('#sysman-table-select').val();
            if (!table) return;

            $.ajax({
                url: `${sysmanAdmin.restUrl}years/${table}`,
                headers: { 'X-WP-Nonce': sysmanAdmin.restNonce },
                success: (years) => {
                    const select = $('#sysman-filter-anio');
                    const current = select.val();
                    select.empty().append('<option value="0">Todos</option>');
                    years.forEach((year) => {
                        select.append(`<option value="${year}" ${year == current ? 'selected' : ''}>${year}</option>`);
                    });
                },
            });
        },

        loadRecords() {
            const table = $('#sysman-table-select').val();
            if (!table) return;

            const loading = $('#sysman-records-loading');
            loading.show();

            $.ajax({
                url: `${sysmanAdmin.restUrl}records/${table}`,
                headers: { 'X-WP-Nonce': sysmanAdmin.restNonce },
                data: {
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: $('#sysman-filter-search').val(),
                    anio: $('#sysman-filter-anio').val(),
                    mes: $('#sysman-filter-mes').val(),
                },
                success: (response, status, xhr) => {
                    const total = parseInt(xhr.getResponseHeader('X-WP-Total')) || response.total;
                    this.totalPages = parseInt(xhr.getResponseHeader('X-WP-TotalPages')) ||
                        Math.ceil(total / this.perPage);

                    this.renderTable(response.records);
                    this.updatePagination(total);
                },
                error: () => {
                    $('#sysman-records-tbody').html(
                        '<tr><td colspan="20" class="sysman-empty-message">Error al cargar los registros.</td></tr>'
                    );
                },
                complete: () => loading.hide(),
            });
        },

        renderTable(records) {
            const thead = $('#sysman-records-thead');
            const tbody = $('#sysman-records-tbody');

            thead.empty();
            tbody.empty();

            if (!records || records.length === 0) {
                tbody.html('<tr><td colspan="20" class="sysman-empty-message">No se encontraron registros.</td></tr>');
                return;
            }

            // Build headers (exclude internal fields)
            const excludeFields = ['id', 'fecha_importacion'];
            const columns = Object.keys(records[0]).filter(
                (col) => !excludeFields.includes(col)
            );

            let headerRow = '<tr>';
            columns.forEach((col) => {
                const label = this.formatColumnName(col);
                headerRow += `<th scope="col" title="${label}">${label}</th>`;
            });
            headerRow += '</tr>';
            thead.html(headerRow);

            // Build rows
            records.forEach((record) => {
                let row = '<tr tabindex="0" role="row">';
                columns.forEach((col) => {
                    let value = record[col] || '';
                    // Format numbers
                    if (this.isNumericColumn(col) && value) {
                        value = this.formatNumber(parseFloat(value));
                    }
                    row += `<td title="${this.escapeHtml(String(value))}">${this.escapeHtml(String(value))}</td>`;
                });
                row += '</tr>';

                const $row = $(row);
                $row.on('click keypress', (e) => {
                    if (e.type === 'click' || e.key === 'Enter') {
                        this.showDetail(record);
                    }
                });
                tbody.append($row);
            });
        },

        showDetail(record) {
            const modal = $('#sysman-record-modal');
            const body = $('#sysman-modal-body');

            let html = '<table role="presentation">';
            for (const [key, value] of Object.entries(record)) {
                let displayValue = value || '-';
                if (this.isNumericColumn(key) && value) {
                    displayValue = this.formatNumber(parseFloat(value));
                }
                html += `<tr>
                    <td>${this.formatColumnName(key)}</td>
                    <td>${this.escapeHtml(String(displayValue))}</td>
                </tr>`;
            }
            html += '</table>';

            body.html(html);
            modal.show();
            modal.find('.sysman-modal-close').trigger('focus');
        },

        updatePagination(total) {
            const pagination = $('#sysman-pagination');

            if (total === 0) {
                pagination.hide();
                return;
            }

            pagination.show();

            const start = (this.currentPage - 1) * this.perPage + 1;
            const end = Math.min(this.currentPage * this.perPage, total);

            $('#sysman-pagination-text').text(`Mostrando ${start}-${end} de ${total.toLocaleString('es-CO')} registros`);
            $('#sysman-page-info').text(`Página ${this.currentPage} de ${this.totalPages}`);
            $('#sysman-prev-page').prop('disabled', this.currentPage <= 1);
            $('#sysman-next-page').prop('disabled', this.currentPage >= this.totalPages);
        },

        exportCSV() {
            const table = $('#sysman-table-select').val();
            if (!table) return;

            // Get all visible records as CSV
            const rows = [];
            const headers = [];
            $('#sysman-records-thead th').each(function () {
                headers.push($(this).text());
            });
            rows.push(headers.join(','));

            $('#sysman-records-tbody tr').each(function () {
                const cells = [];
                $(this).find('td').each(function () {
                    cells.push(`"${$(this).text().replace(/"/g, '""')}"`);
                });
                if (cells.length) {
                    rows.push(cells.join(','));
                }
            });

            const csv = '\uFEFF' + rows.join('\n'); // BOM for Excel
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `sysman-${table}-${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        },

        formatColumnName(col) {
            const labels = {
                codigocuenta: 'Código Cuenta',
                nombrerubro: 'Nombre Rubro',
                movimiento: 'Movimiento',
                destino: 'Destino',
                bpid: 'BPID',
                apropiacioninicial: 'Apropiación Inicial',
                adicion: 'Adición',
                reduccion: 'Reducción',
                credito: 'Crédito',
                contracredito: 'Contracrédito',
                aplazamiento: 'Aplazamiento',
                desplazamiento: 'Desplazamiento',
                apropiacionvigente: 'Apropiación Vigente',
                disponibilidades: 'Disponibilidades',
                saldodisponible: 'Saldo Disponible',
                compromisos: 'Compromisos',
                disponibilidadesabiertas: 'Disponibilidades Abiertas',
                obligacion: 'Obligación',
                pagos: 'Pagos',
                obligacionesporpagar: 'Obligaciones por Pagar',
                anio: 'Año',
                mes: 'Mes',
                compania: 'Compañía',
                numero: 'Número',
                nombrepred: 'Nombre Predecesor',
                idprede: 'ID Predecesor',
                rubro: 'Rubro',
                fecha: 'Fecha',
                tercero: 'Tercero',
                descripcion: 'Descripción',
                valordebito: 'Valor Débito',
                valorcredito: 'Valor Crédito',
                saldoporejecutaresp: 'Saldo por Ejecutar',
                comprobante_afectado: 'Comprobante Afectado',
                tipocpte: 'Tipo Comprobante',
                tipo_cpte: 'Tipo Comprobante',
                nombretercero: 'Nombre Tercero',
                nombreplan: 'Nombre Plan',
                nrodocumento: 'Nro. Documento',
                debitoafectado: 'Débito Afectado',
                creditoafectado: 'Crédito Afectado',
                modificaciondebito: 'Modificación Débito',
                modificacioncredito: 'Modificación Crédito',
                tipocpteafect: 'Tipo Cpte. Afectado',
                cmpteafectado: 'Comprobante Afectado',
                comprobante_afectado: 'Comprobante Afectado',
                fecha_importacion: 'Fecha Importación',
                synced_at: 'Última Sincronización',
                // Personal Nómina
                iddeempleado: 'ID Empleado',
                apellido1: 'Primer Apellido',
                apellido2: 'Segundo Apellido',
                nombres: 'Nombres',
                numerodcto: 'Número Documento',
                expedida: 'Expedida',
                fechancto: 'Fecha Nacimiento',
                fechadeingreso: 'Fecha Ingreso',
                fechaderetiro: 'Fecha Retiro',
                iddecargo: 'ID Cargo',
                nombredelcargo: 'Cargo',
                iddecategoria: 'ID Categoría',
                nombrecategoria: 'Categoría',
                escalafon: 'Escalafón',
                nombreescalafon: 'Nombre Escalafón',
                grado: 'Grado',
                decarrera: 'Carrera',
                salariobaseibc: 'Salario Base IBC',
                dependencianombre: 'Dependencia',
                emailcorporativo: 'Email Corporativo',
                emailpersonal: 'Email Personal',
                direccion: 'Dirección',
                telefonos: 'Teléfonos',
                fechacumplimientobonificacion: 'Cumplimiento Bonificación',
                // Ingresos
                cuenta: 'Cuenta',
                codigo: 'Código',
                nombre: 'Nombre',
                tiporecurso: 'Tipo Recurso',
                fuenterecurso: 'Fuente Recurso',
                apropiado: 'Apropiado',
                modificaciones: 'Modificaciones',
                totalpresupuesto: 'Total Presupuesto',
                recaudosanteriores: 'Recaudos Anteriores',
                recaudosmes: 'Recaudos Mes',
                recaudosacumulados: 'Recaudos Acumulados',
                porrecaudar: 'Por Recaudar',
                porcrecaudado: '% Recaudado',
            };
            return labels[col] || col.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },

        isNumericColumn(col) {
            const numericCols = [
                'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
                'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
                'saldodisponible', 'compromisos', 'disponibilidadesabiertas', 'obligacion',
                'pagos', 'obligacionesporpagar', 'valordebito', 'valorcredito', 'saldoporejecutaresp',
                'debitoafectado', 'creditoafectado', 'modificaciondebito', 'modificacioncredito',
                'salariobaseibc', 'apropiado', 'modificaciones', 'totalpresupuesto',
                'recaudosanteriores', 'recaudosmes', 'recaudosacumulados', 'porrecaudar', 'porcrecaudado',
            ];
            return numericCols.includes(col);
        },

        formatNumber(num) {
            if (isNaN(num)) return '0';
            return num.toLocaleString('es-CO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },
    };

    /* ========================================
       Dashboard Helpers
       ======================================== */
    const DashboardManager = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Collapsible sections (API docs, etc.)
            $(document).on('click', '.sysman-card-header--collapsible', function () {
                const card = $(this).closest('.sysman-card');
                const body = card.find('.sysman-collapsible-content');
                const btn = $(this).find('.sysman-toggle-btn');
                body.slideToggle(200);
                if (body.is(':visible')) {
                    btn.text('Colapsar');
                } else {
                    btn.text('Expandir');
                }
            });

            // Show/hide step indicators based on report selection
            const report = $('#sysman-report');
            if (report.length) {
                report.on('change', function () {
                    const val = $(this).val();
                    if (val === 'all') {
                        $('#sysman-import-steps').show();
                    } else {
                        $('#sysman-import-steps').hide();
                    }
                });
            }
        },
    };

    /* ========================================
       Initialize
       ======================================== */
    $(document).ready(() => {
        ImportManager.init();
        RecordsManager.init();
        DashboardManager.init();
    });
})(jQuery);
