/**
 * SYSMAN Suite - Admin Chart Configuration Manager
 * Gobernación de Nariño
 */
(function ($) {
    'use strict';

    const ChartConfigManager = {
        init() {
            if ($('.sysman-chart-config').length === 0) return;

            this.bindEvents();
            this.loadColumns();
            this.updateColorPreview();
        },

        bindEvents() {
            // Table change: reload columns
            $('#sysman_data_table').on('change', () => this.loadColumns());

            // Chart type selection
            $('.sysman-chart-type-option').on('click', function () {
                $('.sysman-chart-type-option').removeClass('active');
                $(this).addClass('active');
            });

            // Add filter
            $('#sysman-add-filter').on('click', () => this.addFilter());

            // Remove filter
            $(document).on('click', '.sysman-remove-filter', function () {
                $(this).closest('.sysman-filter-row').remove();
            });

            // Color palette preview
            $('#sysman_chart_colors').on('input change', () => this.updateColorPreview());

            // Preview button
            $('#sysman-refresh-preview').on('click', () => this.refreshPreview());

            // Collapsible toggle (chart config page)
            $(document).on('click', '.sysman-toggle-section', function () {
                const body = $(this).closest('.sysman-collapsible').find('.sysman-collapsible-body');
                body.slideToggle(200);
                $(this).text(body.is(':visible') ? 'Colapsar' : 'Expandir');
            });
        },

        loadColumns() {
            const table = $('#sysman_data_table').val();
            if (!table) return;

            // Extract table key (remove prefix)
            const parts = table.split('_');
            let key = '';
            let found = false;
            for (const part of parts) {
                if (found || part === 'sysman') {
                    found = true;
                    key += (key ? '_' : '') + part;
                }
            }
            if (!key) key = table;

            $.ajax({
                url: `${sysmanCharts.restUrl}columns/${key}`,
                headers: { 'X-WP-Nonce': sysmanCharts.restNonce },
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

            const savedGroup = $('#sysman-saved-group-column').val();
            const savedValue = $('#sysman-saved-value-column').val();

            // Group column select
            const groupSelect = $('#sysman_group_column');
            groupSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filteredColumns.forEach((col) => {
                const option = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedGroup) option.prop('selected', true);
                groupSelect.append(option);
            });

            // Value column select
            const valueSelect = $('#sysman_value_column');
            valueSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filteredColumns.forEach((col) => {
                const option = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedValue) option.prop('selected', true);
                valueSelect.append(option);
            });

            // Update filter column dropdowns
            $('.sysman-filter-column').each(function () {
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
            const index = $('.sysman-filter-row').length;
            const html = `
                <div class="sysman-filter-row" data-index="${index}">
                    <select name="sysman_filters[${index}][column]" class="sysman-filter-column">
                        <option value="">Columna</option>
                    </select>
                    <select name="sysman_filters[${index}][operator]">
                        <option value="=">=</option>
                        <option value="!=">!=</option>
                        <option value=">">&gt;</option>
                        <option value="<">&lt;</option>
                        <option value=">=">&gt;=</option>
                        <option value="<=">&lt;=</option>
                        <option value="LIKE">LIKE</option>
                    </select>
                    <input type="text" name="sysman_filters[${index}][value]" placeholder="Valor">
                    <button type="button" class="button sysman-remove-filter" aria-label="Eliminar filtro">&times;</button>
                </div>
            `;

            $('#sysman-filters-list').append(html);
            this.loadColumns();
        },

        updateColorPreview() {
            const input = $('#sysman_chart_colors');
            const preview = $('#sysman-color-preview');
            if (!input.length || !preview.length) return;

            const colors = input.val().split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));

            preview.empty();
            colors.forEach((color) => {
                preview.append(
                    $('<span class="sysman-color-swatch">').css('background-color', color).attr('title', color)
                );
            });
        },

        refreshPreview() {
            const area = $('#sysman-chart-preview-area');
            const postId = $('#post_ID').val();

            if (!postId) {
                area.html('<p class="sysman-preview-placeholder">Guarde la gráfica primero para ver la vista previa.</p>');
                return;
            }

            area.html('<div style="text-align:center;padding:40px;"><span class="spinner is-active" style="float:none;"></span><p>Cargando vista previa...</p></div>');

            $.ajax({
                url: `${sysmanCharts.restUrl}chart/${postId}`,
                headers: { 'X-WP-Nonce': sysmanCharts.restNonce },
                success: (response) => {
                    if (!response.data || response.data.length === 0) {
                        area.html('<p class="sysman-preview-placeholder">No hay datos disponibles. Verifique la configuración y que la tabla tenga registros.</p>');
                        return;
                    }

                    // Display data summary as preview
                    let html = '<div class="sysman-preview-data">';
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
                    area.html('<p class="sysman-preview-placeholder" style="color:#dc3232;">Error al cargar la vista previa. Guarde la gráfica e intente de nuevo.</p>');
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
