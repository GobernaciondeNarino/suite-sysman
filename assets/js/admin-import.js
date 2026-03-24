/**
 * SYSMAN Suite - Admin Import & Records Manager
 * Gobernación de Nariño
 */
(function ($) {
    'use strict';

    /* ========================================
       Import Manager
       ======================================== */
    const ImportManager = {
        init() {
            this.bindEvents();
            this.toggleAuxiliarOptions();
        },

        bindEvents() {
            $('#sysman-import-btn').on('click', () => this.startImport());
            $('#sysman-report').on('change', () => this.toggleReportOptions());
            $('#sysman-compania').on('change', () => this.toggleCustomCompania());
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
                tipo_cpte: 'Tipo Comprobante',
                fecha_importacion: 'Fecha Importación',
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
