/**
 * SISMAN Suite - Admin Chart Configuration Manager
 * Gobernación de Nariño
 */
(function ($) {
    'use strict';

    const ChartConfigManager = {
        init() {
            if ($('.sisman-chart-config').length === 0) return;

            this.bindEvents();
            this.loadColumns();
            this.updateColorPreview();
        },

        bindEvents() {
            // Table change: reload columns
            $('#sisman_data_table').on('change', () => this.loadColumns());

            // Chart type selection
            $('.sisman-chart-type-option').on('click', function () {
                $('.sisman-chart-type-option').removeClass('active');
                $(this).addClass('active');
            });

            // Add filter
            $('#sisman-add-filter').on('click', () => this.addFilter());

            // Remove filter
            $(document).on('click', '.sisman-remove-filter', function () {
                $(this).closest('.sisman-filter-row').remove();
            });

            // Color palette preview
            $('#sisman_chart_colors').on('input change', () => this.updateColorPreview());

            // Collapsible sections
            $(document).on('click', '.sisman-toggle-section', function () {
                const body = $(this).closest('.sisman-collapsible').find('.sisman-collapsible-body');
                body.slideToggle(200);
                $(this).text(body.is(':visible') ? 'Colapsar' : 'Expandir');
            });

            // Preview button
            $('#sisman-refresh-preview').on('click', () => this.refreshPreview());

            // Show/hide step indicators on import page
            const report = $('#sisman-report');
            if (report.length) {
                report.on('change', () => {
                    const val = report.val();
                    if (val === 'all') {
                        $('#sisman-import-steps').show();
                    } else {
                        $('#sisman-import-steps').hide();
                    }
                });
            }
        },

        loadColumns() {
            const table = $('#sisman_data_table').val();
            if (!table) return;

            // Extract table key (remove prefix)
            const parts = table.split('_');
            let key = '';
            let found = false;
            for (const part of parts) {
                if (found || part === 'sisman') {
                    found = true;
                    key += (key ? '_' : '') + part;
                }
            }
            if (!key) key = table;

            $.ajax({
                url: `${sismanCharts.restUrl}columns/${key}`,
                headers: { 'X-WP-Nonce': sismanCharts.restNonce },
                success: (columns) => {
                    this.populateColumnSelects(columns);
                },
                error: () => {
                    console.error('Error loading columns for table:', key);
                },
            });
        },

        populateColumnSelects(columns) {
            const excludeColumns = ['id', 'fecha_importacion'];
            const filteredColumns = columns.filter(
                (col) => !excludeColumns.includes(col)
            );

            const savedGroup = $('#sisman-saved-group-column').val();
            const savedValue = $('#sisman-saved-value-column').val();

            // Group column select
            const groupSelect = $('#sisman_group_column');
            groupSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filteredColumns.forEach((col) => {
                const option = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedGroup) option.prop('selected', true);
                groupSelect.append(option);
            });

            // Value column select
            const valueSelect = $('#sisman_value_column');
            valueSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filteredColumns.forEach((col) => {
                const option = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedValue) option.prop('selected', true);
                valueSelect.append(option);
            });

            // Update filter column dropdowns
            $('.sisman-filter-column').each(function () {
                const current = $(this).val();
                $(this).empty().append('<option value="">Columna</option>');
                filteredColumns.forEach((col) => {
                    const option = $('<option>').val(col).text(ChartConfigManager.formatColumnName(col));
                    if (col === current) option.prop('selected', true);
                    $(this).append(option);
                });
            });
        },

        addFilter() {
            const index = $('.sisman-filter-row').length;
            const html = `
                <div class="sisman-filter-row" data-index="${index}">
                    <select name="sisman_filters[${index}][column]" class="sisman-filter-column">
                        <option value="">Columna</option>
                    </select>
                    <select name="sisman_filters[${index}][operator]">
                        <option value="=">=</option>
                        <option value="!=">!=</option>
                        <option value=">">&gt;</option>
                        <option value="<">&lt;</option>
                        <option value=">=">&gt;=</option>
                        <option value="<=">&lt;=</option>
                        <option value="LIKE">LIKE</option>
                    </select>
                    <input type="text" name="sisman_filters[${index}][value]" placeholder="Valor">
                    <button type="button" class="button sisman-remove-filter" aria-label="Eliminar filtro">&times;</button>
                </div>
            `;

            $('#sisman-filters-list').append(html);
            this.loadColumns();
        },

        updateColorPreview() {
            const input = $('#sisman_chart_colors');
            const preview = $('#sisman-color-preview');
            if (!input.length || !preview.length) return;

            const colors = input.val().split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));

            preview.empty();
            colors.forEach((color) => {
                preview.append(
                    $('<span class="sisman-color-swatch">').css('background-color', color).attr('title', color)
                );
            });
        },

        refreshPreview() {
            const area = $('#sisman-chart-preview-area');
            const postId = $('#post_ID').val();

            if (!postId) {
                area.html('<p class="sisman-preview-placeholder">Guarde la gráfica primero para ver la vista previa.</p>');
                return;
            }

            area.html('<div style="text-align:center;padding:40px;"><span class="spinner is-active" style="float:none;"></span><p>Cargando vista previa...</p></div>');

            $.ajax({
                url: `${sismanCharts.restUrl}chart/${postId}`,
                headers: { 'X-WP-Nonce': sismanCharts.restNonce },
                success: (response) => {
                    if (!response.data || response.data.length === 0) {
                        area.html('<p class="sisman-preview-placeholder">No hay datos disponibles. Verifique la configuración y que la tabla tenga registros.</p>');
                        return;
                    }

                    // Display data summary as preview
                    let html = '<div class="sisman-preview-data">';
                    html += `<p><strong>Tipo:</strong> ${response.meta.chart_type || 'bar'} | <strong>Registros:</strong> ${response.data.length}</p>`;
                    html += '<table class="widefat striped"><thead><tr><th>Etiqueta</th><th>Valor</th></tr></thead><tbody>';
                    response.data.slice(0, 10).forEach((row) => {
                        const value = parseFloat(row.value) || 0;
                        html += `<tr><td>${row.label || '-'}</td><td>${value.toLocaleString('es-CO')}</td></tr>`;
                    });
                    if (response.data.length > 10) {
                        html += `<tr><td colspan="2" style="text-align:center;color:#666;">... y ${response.data.length - 10} registros más</td></tr>`;
                    }
                    html += '</tbody></table></div>';

                    area.html(html);
                },
                error: () => {
                    area.html('<p class="sisman-preview-placeholder" style="color:#dc3232;">Error al cargar la vista previa. Guarde la gráfica e intente de nuevo.</p>');
                },
            });
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
            };
            return labels[col] || col.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },
    };

    $(document).ready(() => {
        ChartConfigManager.init();
    });
})(jQuery);
