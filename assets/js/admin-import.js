/**
 * SISMAN Suite - Admin Import & Records Manager
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
            $('#sisman-import-btn').on('click', () => this.startImport());
            $('#sisman-report').on('change', () => this.toggleAuxiliarOptions());
        },

        toggleAuxiliarOptions() {
            const report = $('#sisman-report').val();
            $('.sisman-auxiliar-options').toggle(report === 'auxiliar');
        },

        startImport() {
            const btn = $('#sisman-import-btn');
            const progress = $('#sisman-import-progress');
            const results = $('#sisman-import-results');

            if (!confirm(sismanAdmin.strings.confirmImport)) {
                return;
            }

            // Disable button and show progress
            btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update sisman-spin"></span> ' + sismanAdmin.strings.importing
            );
            progress.show();
            results.hide().empty();

            // Reset progress bar
            this.setProgress(0, 'Preparando importación...');

            const data = {
                action: 'sisman_start_import',
                nonce: sismanAdmin.nonce,
                compania: $('#sisman-compania').val(),
                anio: $('#sisman-anio').val(),
                mes: $('#sisman-mes').val(),
                report: $('#sisman-report').val(),
                tipo_cpte: $('#sisman-tipo-cpte').val(),
            };

            // Start polling for status updates
            this.statusInterval = setInterval(() => this.pollStatus(), 1500);

            $.ajax({
                url: sismanAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                timeout: 600000, // 10 minutes
                success: (response) => {
                    this.stopPolling();
                    this.setProgress(100, sismanAdmin.strings.complete);

                    if (response.success) {
                        this.showResults(response.data.results, 'success');
                    } else {
                        this.showResults(
                            { error: response.data?.message || sismanAdmin.strings.error },
                            'error'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    this.stopPolling();

                    let errorMsg = sismanAdmin.strings.error;
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
                url: sismanAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sisman_import_status',
                    nonce: sismanAdmin.nonce,
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
            const container = $('#sisman-import-progress');
            const fill = container.find('.sisman-progress-fill');
            const text = container.find('.sisman-progress-text');
            const pctLabel = container.find('.sisman-progress-percent');

            fill.css('width', percent + '%');
            container.attr('aria-valuenow', Math.round(percent));
            text.text(message || '');
            if (pctLabel.length) {
                pctLabel.text(Math.round(percent) + '%');
            }
        },

        updateStepIndicators(data) {
            const steps = $('#sisman-import-steps');
            if (steps.length === 0) return;

            steps.find('.sisman-step').each(function (index) {
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
            const container = $('#sisman-import-results');
            container.removeClass('success error').addClass(type).empty();

            if (type === 'error') {
                container.append(
                    $('<div class="sisman-result-header error">').html(
                        '<span class="dashicons dashicons-warning"></span> ' +
                        '<strong>Error en la importación</strong>'
                    )
                );
                container.append($('<p>').text(data.error));
            } else {
                container.append(
                    $('<div class="sisman-result-header success">').html(
                        '<span class="dashicons dashicons-yes-alt"></span> ' +
                        '<strong>Importación completada exitosamente</strong>'
                    )
                );

                const reportLabels = {
                    ejecucion: 'Ejecución Presupuestal de Gastos',
                    auxiliar: 'Auxiliar Presupuestal por Cuentas',
                    plan: 'Plan Presupuestal',
                };

                const table = $('<table class="sisman-results-table"><thead><tr>' +
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

                        row.append($('<td>').html('<span class="sisman-badge success">OK</span>'));
                        row.append($('<td>').html(`<strong>${imported.toLocaleString('es-CO')}</strong> / ${total.toLocaleString('es-CO')}`));
                        row.append($('<td>').text(imported === total ? 'Todos importados' : `${total - imported} omitidos (duplicados)`));
                    } else {
                        hasErrors = true;
                        row.append($('<td>').html('<span class="sisman-badge error">Error</span>'));
                        row.append($('<td>').text('-'));
                        row.append($('<td>').text(result.error || 'Error desconocido'));
                    }

                    table.find('tbody').append(row);
                }

                // Summary row
                if (Object.keys(data).length > 1) {
                    table.find('tbody').append(
                        $('<tr class="sisman-result-total">').html(
                            `<td><strong>Total</strong></td><td></td>` +
                            `<td><strong>${totalImported.toLocaleString('es-CO')}</strong> / ${totalRecords.toLocaleString('es-CO')}</td>` +
                            `<td></td>`
                        )
                    );
                }

                container.append(table);

                if (hasErrors) {
                    container.append(
                        $('<p class="sisman-result-note">').html(
                            '<span class="dashicons dashicons-info"></span> ' +
                            'Algunos informes presentaron errores. Revise los <a href="?page=sisman-logs">logs</a> para más detalles.'
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
            if ($('#sisman-records-container').length === 0) return;
            this.bindEvents();
            this.loadRecords();
        },

        bindEvents() {
            $('#sisman-table-select').on('change', () => {
                this.currentPage = 1;
                this.loadYears();
                this.loadRecords();
            });
            $('#sisman-filter-btn').on('click', () => {
                this.currentPage = 1;
                this.loadRecords();
            });
            $('#sisman-filter-search').on('keypress', (e) => {
                if (e.which === 13) {
                    this.currentPage = 1;
                    this.loadRecords();
                }
            });
            $('#sisman-prev-page').on('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadRecords();
                }
            });
            $('#sisman-next-page').on('click', () => {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadRecords();
                }
            });
            $('#sisman-export-csv-btn').on('click', () => this.exportCSV());

            // Modal close
            $(document).on('click', '.sisman-modal-close, .sisman-modal-overlay', () => {
                $('#sisman-record-modal').hide();
            });
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    $('#sisman-record-modal').hide();
                }
            });
        },

        loadYears() {
            const table = $('#sisman-table-select').val();
            if (!table) return;

            $.ajax({
                url: `${sismanAdmin.restUrl}years/${table}`,
                headers: { 'X-WP-Nonce': sismanAdmin.restNonce },
                success: (years) => {
                    const select = $('#sisman-filter-anio');
                    const current = select.val();
                    select.empty().append('<option value="0">Todos</option>');
                    years.forEach((year) => {
                        select.append(`<option value="${year}" ${year == current ? 'selected' : ''}>${year}</option>`);
                    });
                },
            });
        },

        loadRecords() {
            const table = $('#sisman-table-select').val();
            if (!table) return;

            const loading = $('#sisman-records-loading');
            loading.show();

            $.ajax({
                url: `${sismanAdmin.restUrl}records/${table}`,
                headers: { 'X-WP-Nonce': sismanAdmin.restNonce },
                data: {
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: $('#sisman-filter-search').val(),
                    anio: $('#sisman-filter-anio').val(),
                    mes: $('#sisman-filter-mes').val(),
                },
                success: (response, status, xhr) => {
                    const total = parseInt(xhr.getResponseHeader('X-WP-Total')) || response.total;
                    this.totalPages = parseInt(xhr.getResponseHeader('X-WP-TotalPages')) ||
                        Math.ceil(total / this.perPage);

                    this.renderTable(response.records);
                    this.updatePagination(total);
                },
                error: () => {
                    $('#sisman-records-tbody').html(
                        '<tr><td colspan="20" class="sisman-empty-message">Error al cargar los registros.</td></tr>'
                    );
                },
                complete: () => loading.hide(),
            });
        },

        renderTable(records) {
            const thead = $('#sisman-records-thead');
            const tbody = $('#sisman-records-tbody');

            thead.empty();
            tbody.empty();

            if (!records || records.length === 0) {
                tbody.html('<tr><td colspan="20" class="sisman-empty-message">No se encontraron registros.</td></tr>');
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
            const modal = $('#sisman-record-modal');
            const body = $('#sisman-modal-body');

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
            modal.find('.sisman-modal-close').trigger('focus');
        },

        updatePagination(total) {
            const pagination = $('#sisman-pagination');

            if (total === 0) {
                pagination.hide();
                return;
            }

            pagination.show();

            const start = (this.currentPage - 1) * this.perPage + 1;
            const end = Math.min(this.currentPage * this.perPage, total);

            $('#sisman-pagination-text').text(`Mostrando ${start}-${end} de ${total.toLocaleString('es-CO')} registros`);
            $('#sisman-page-info').text(`Página ${this.currentPage} de ${this.totalPages}`);
            $('#sisman-prev-page').prop('disabled', this.currentPage <= 1);
            $('#sisman-next-page').prop('disabled', this.currentPage >= this.totalPages);
        },

        exportCSV() {
            const table = $('#sisman-table-select').val();
            if (!table) return;

            // Get all visible records as CSV
            const rows = [];
            const headers = [];
            $('#sisman-records-thead th').each(function () {
                headers.push($(this).text());
            });
            rows.push(headers.join(','));

            $('#sisman-records-tbody tr').each(function () {
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
            a.download = `sisman-${table}-${new Date().toISOString().slice(0, 10)}.csv`;
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
            };
            return labels[col] || col.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },

        isNumericColumn(col) {
            const numericCols = [
                'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
                'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
                'saldodisponible', 'compromisos', 'disponibilidadesabiertas', 'obligacion',
                'pagos', 'obligacionesporpagar', 'valordebito', 'valorcredito', 'saldoporejecutaresp',
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
       Initialize
       ======================================== */
    $(document).ready(() => {
        ImportManager.init();
        RecordsManager.init();
    });
})(jQuery);
