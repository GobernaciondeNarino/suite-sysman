/**
 * SYSMAN Suite - Admin Chart Configuration Manager
 * Gobernación de Nariño
 * v1.4.0 - Live D3plus preview + field guidance
 */
(function ($) {
    'use strict';

    /**
     * D3plus chart type requirements and field guidance.
     * Based on D3plus v2 documentation:
     * - Plot-based (BarChart, LinePlot, AreaPlot): need x (label), y (value), groupBy
     * - Pie/Donut: need value, groupBy
     * - Treemap: need sum, groupBy
     */
    const CHART_TYPE_CONFIG = {
        bar: {
            label: 'Barras',
            d3class: 'BarChart',
            needsXY: true,
            groupDesc: 'Seleccione una columna categórica (ej: nombrerubro, destino, codigocuenta) para el eje X.',
            valueDesc: 'Seleccione una columna numérica (ej: apropiacionvigente, pagos, compromisos) para el eje Y.',
            guidance: 'El gráfico de barras requiere un eje X (categoría) y un eje Y (valor numérico). Ideal para comparar valores entre categorías.',
        },
        line: {
            label: 'Líneas',
            d3class: 'LinePlot',
            needsXY: true,
            groupDesc: 'Seleccione una columna temporal o categórica (ej: mes, anio, fecha) para el eje X.',
            valueDesc: 'Seleccione una columna numérica para el eje Y.',
            guidance: 'El gráfico de líneas requiere eje X (secuencial/temporal) y eje Y (valor). Ideal para tendencias en el tiempo.',
        },
        area: {
            label: 'Área',
            d3class: 'AreaPlot',
            needsXY: true,
            groupDesc: 'Seleccione una columna temporal o categórica para el eje X.',
            valueDesc: 'Seleccione una columna numérica para el eje Y.',
            guidance: 'El gráfico de área rellena debajo de la línea. Ideal para mostrar volúmenes acumulados.',
        },
        pie: {
            label: 'Pie / Torta',
            d3class: 'Pie',
            needsXY: false,
            groupDesc: 'Seleccione la columna que define cada porción (ej: destino, nombrerubro).',
            valueDesc: 'Seleccione la columna numérica cuyo valor determinará el tamaño de cada porción.',
            guidance: 'El gráfico de torta necesita una columna de agrupación y un valor. Ideal para mostrar proporciones.',
        },
        donut: {
            label: 'Donut',
            d3class: 'Pie',
            needsXY: false,
            groupDesc: 'Seleccione la columna que define cada porción.',
            valueDesc: 'Seleccione la columna numérica para el tamaño de cada porción.',
            guidance: 'Similar al gráfico de torta pero con centro vacío. Ideal para proporciones con espacio para información central.',
        },
        treemap: {
            label: 'Treemap',
            d3class: 'Treemap',
            needsXY: false,
            groupDesc: 'Seleccione la columna de categoría que define cada bloque (ej: nombrerubro, destino).',
            valueDesc: 'Seleccione la columna numérica que define el tamaño de cada bloque.',
            guidance: 'El treemap muestra jerarquías como bloques proporcionales. Ideal para comparar magnitudes relativas.',
        },
        stacked_bar: {
            label: 'Barras Apiladas',
            d3class: 'BarChart',
            needsXY: true,
            stacked: true,
            groupDesc: 'Seleccione la columna para el eje X (ej: destino, movimiento).',
            valueDesc: 'Seleccione la columna numérica para apilar.',
            guidance: 'Barras apiladas combinan múltiples series. Requiere eje X y eje Y.',
        },
        grouped_bar: {
            label: 'Barras Agrupadas',
            d3class: 'BarChart',
            needsXY: true,
            groupDesc: 'Seleccione la columna para el eje X.',
            valueDesc: 'Seleccione la columna numérica para agrupar.',
            guidance: 'Barras agrupadas muestran series lado a lado. Requiere eje X y eje Y.',
        },
    };

    /**
     * Column classification for heatmap coloring.
     * Maps known columns to their data type for smart recommendations.
     */
    const COLUMN_TYPES = {
        // Text/categorical columns (good for groupBy / labels)
        codigocuenta: 'text',
        nombrerubro: 'text',
        movimiento: 'text',
        destino: 'text',
        bpid: 'text',
        compania: 'text',
        nombrepred: 'text',
        idprede: 'text',
        rubro: 'text',
        tercero: 'text',
        descripcion: 'text',
        tipo_cpte: 'text',
        comprobante_afectado: 'text',
        // Numeric columns (good for values)
        apropiacioninicial: 'numeric',
        adicion: 'numeric',
        reduccion: 'numeric',
        credito: 'numeric',
        contracredito: 'numeric',
        aplazamiento: 'numeric',
        desplazamiento: 'numeric',
        apropiacionvigente: 'numeric',
        disponibilidades: 'numeric',
        saldodisponible: 'numeric',
        compromisos: 'numeric',
        disponibilidadesabiertas: 'numeric',
        obligacion: 'numeric',
        pagos: 'numeric',
        obligacionesporpagar: 'numeric',
        valordebito: 'numeric',
        valorcredito: 'numeric',
        saldoporejecutaresp: 'numeric',
        numero: 'numeric',
        // Temporal columns (good for x-axis in line/area)
        anio: 'temporal',
        mes: 'temporal',
        fecha: 'temporal',
    };

    const ChartConfigManager = {
        init() {
            if ($('.sysman-chart-config').length === 0) return;

            this.bindEvents();
            this.loadColumns();
            this.updateColorPreview();
            this.updateFieldGuidance();
        },

        bindEvents() {
            // Table change: reload columns
            $('#sysman_data_table').on('change', () => this.loadColumns());

            // Chart type selection
            $('.sysman-chart-type-option').on('click', function () {
                $('.sysman-chart-type-option').removeClass('active');
                $(this).addClass('active');
            });

            // Chart type change: update guidance and column heatmap
            $('input[name="sysman_chart_type"]').on('change', () => {
                this.updateFieldGuidance();
                this.applyColumnHeatmap();
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

        /**
         * Get currently selected chart type.
         */
        getSelectedChartType() {
            return $('input[name="sysman_chart_type"]:checked').val() || 'bar';
        },

        /**
         * Update field guidance text based on selected chart type.
         */
        updateFieldGuidance() {
            const type = this.getSelectedChartType();
            const config = CHART_TYPE_CONFIG[type];
            if (!config) return;

            const $guidance = $('#sysman-field-guidance');
            const $text = $('#sysman-field-guidance-text');
            const $groupHint = $('#sysman-group-hint');
            const $valueHint = $('#sysman-value-hint');

            $text.text(config.guidance);
            $guidance.slideDown(200);

            $groupHint.text(config.groupDesc);
            $valueHint.text(config.valueDesc);

            this.applyColumnHeatmap();
        },

        /**
         * Apply heatmap coloring to column select options.
         * Green = recommended, yellow = possible, gray = not ideal
         */
        applyColumnHeatmap() {
            const type = this.getSelectedChartType();
            const config = CHART_TYPE_CONFIG[type];
            if (!config) return;

            const isTimeSeries = ['line', 'area'].includes(type);

            // Style group column options
            $('#sysman_group_column option').each(function () {
                const col = $(this).val();
                if (!col) return;
                const colType = COLUMN_TYPES[col] || 'unknown';

                $(this).css('background-color', '');

                if (colType === 'text') {
                    $(this).css('background-color', '#d4edda'); // green - best for grouping
                } else if (colType === 'temporal') {
                    $(this).css('background-color', isTimeSeries ? '#d4edda' : '#fff3cd'); // green for time series, yellow otherwise
                } else if (colType === 'numeric') {
                    $(this).css('background-color', '#f8d7da'); // red - not ideal for grouping
                }
            });

            // Style value column options
            $('#sysman_value_column option').each(function () {
                const col = $(this).val();
                if (!col) return;
                const colType = COLUMN_TYPES[col] || 'unknown';

                $(this).css('background-color', '');

                if (colType === 'numeric') {
                    $(this).css('background-color', '#d4edda'); // green - best for values
                } else if (colType === 'temporal') {
                    $(this).css('background-color', '#fff3cd'); // yellow
                } else if (colType === 'text') {
                    $(this).css('background-color', '#f8d7da'); // red - not ideal for values
                }
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
                    this.applyColumnHeatmap();
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

        /**
         * Render a live D3plus chart preview using REST API data.
         */
        refreshPreview() {
            const area = $('#sysman-chart-preview-area');
            const status = $('#sysman-preview-status');
            const postId = $('#post_ID').val();

            if (!postId) {
                area.html('<p style="text-align:center;padding:60px 20px;color:#999;">Guarde la gráfica primero para ver la vista previa.</p>');
                return;
            }

            area.html('<div style="text-align:center;padding:80px 20px;"><span class="spinner is-active" style="float:none;"></span><p>Cargando datos y renderizando gráfico...</p></div>');
            status.text('Cargando...');

            $.ajax({
                url: `${sysmanCharts.restUrl}chart/${postId}`,
                headers: { 'X-WP-Nonce': sysmanCharts.restNonce },
                success: (response) => {
                    if (!response.data || response.data.length === 0) {
                        area.html('<p style="text-align:center;padding:60px 20px;color:#999;">No hay datos disponibles. Verifique la configuración y que la tabla tenga registros.</p>');
                        status.text('Sin datos');
                        return;
                    }

                    // Render actual D3plus chart
                    this.renderD3PlusPreview(area, response.data, response.meta);
                    status.text(`${response.data.length} registros renderizados`);
                },
                error: () => {
                    area.html('<p style="text-align:center;padding:60px 20px;color:#dc3232;">Error al cargar los datos. Guarde la gráfica e intente de nuevo.</p>');
                    status.text('Error');
                },
            });
        },

        /**
         * Render a D3plus chart inside the preview area.
         */
        renderD3PlusPreview(container, data, meta) {
            // Clear and create canvas
            container.empty();
            const canvasId = 'sysman-admin-preview-canvas';
            container.append(`<div id="${canvasId}" style="width:100%;min-height:350px;"></div>`);

            const chartType = meta.chart_type || 'bar';
            const colorsStr = meta.chart_colors || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));
            const height = parseInt(meta.chart_height) || 400;

            // Prepare data
            const chartData = data.map((d) => ({
                label: String(d.label || ''),
                value: parseFloat(d.value) || 0,
            }));

            const baseConfig = {
                data: chartData,
                groupBy: 'label',
                height: height,
            };

            // Axis config
            const axisConfig = {};
            if (meta.y_axis_title) {
                axisConfig.yConfig = { title: meta.y_axis_title };
            }
            if (meta.x_axis_title) {
                axisConfig.xConfig = { title: meta.x_axis_title };
            }

            const shapeConfig = {
                fill: (d, i) => colors[i % colors.length],
            };

            const tooltipConfig = {
                body: (d) => `Valor: ${parseFloat(d.value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            };

            try {
                const selector = `#${canvasId}`;

                switch (chartType) {
                    case 'bar':
                        new d3plus.BarChart()
                            .select(selector)
                            .config({ ...baseConfig, ...axisConfig, x: 'label', y: 'value', tooltipConfig, shapeConfig })
                            .render();
                        break;

                    case 'line':
                        new d3plus.LinePlot()
                            .select(selector)
                            .config({ ...baseConfig, ...axisConfig, x: 'label', y: 'value', tooltipConfig })
                            .render();
                        break;

                    case 'area':
                        new d3plus.AreaPlot()
                            .select(selector)
                            .config({ ...baseConfig, ...axisConfig, x: 'label', y: 'value', tooltipConfig })
                            .render();
                        break;

                    case 'pie':
                        new d3plus.Pie()
                            .select(selector)
                            .config({ ...baseConfig, value: 'value', tooltipConfig, shapeConfig })
                            .render();
                        break;

                    case 'donut':
                        new d3plus.Pie()
                            .select(selector)
                            .config({ ...baseConfig, value: 'value', innerRadius: 80, tooltipConfig, shapeConfig })
                            .render();
                        break;

                    case 'treemap':
                        new d3plus.Treemap()
                            .select(selector)
                            .config({ ...baseConfig, sum: 'value', tooltipConfig, shapeConfig })
                            .render();
                        break;

                    case 'stacked_bar':
                        new d3plus.BarChart()
                            .select(selector)
                            .config({ ...baseConfig, ...axisConfig, x: 'label', y: 'value', stacked: true, tooltipConfig, shapeConfig })
                            .render();
                        break;

                    case 'grouped_bar':
                        new d3plus.BarChart()
                            .select(selector)
                            .config({ ...baseConfig, ...axisConfig, x: 'label', y: 'value', stacked: false, tooltipConfig, shapeConfig })
                            .render();
                        break;

                    default:
                        new d3plus.BarChart()
                            .select(selector)
                            .config({ ...baseConfig, x: 'label', y: 'value' })
                            .render();
                }
            } catch (err) {
                console.error('D3plus admin preview error:', err);
                container.html('<p style="text-align:center;padding:60px 20px;color:#dc3232;">Error al renderizar el gráfico: ' + err.message + '</p>');
            }
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
