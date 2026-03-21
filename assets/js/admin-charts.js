/**
 * SYSMAN Suite - Admin Chart Configuration Manager
 * Gobernacion de Narino
 * v2.0.0 - Dynamic fields per chart type, multi-series support, new chart types
 */
(function ($) {
    'use strict';

    /**
     * Chart type configurations: field requirements and guidance per type.
     * needsColorColumn: requires a secondary grouping column (series/color)
     * needsValue2: can use a second Y value
     * hasAxes: show axis title fields
     * showOrientation: show horizontal/vertical toggle
     */
    const CHART_TYPE_CONFIG = {
        bar: {
            label: 'Barras',
            groupDesc: 'Columna categorica para el eje X (ej: nombrerubro, destino, codigocuenta).',
            valueDesc: 'Columna numerica para la altura de las barras (ej: apropiacionvigente, pagos).',
            guidance: 'Grafico de barras verticales. Ideal para comparar valores entre categorias.',
            needsColorColumn: false,
            needsValue2: false,
            hasAxes: true,
            showOrientation: false,
        },
        horizontal_bar: {
            label: 'Barras Horizontales',
            groupDesc: 'Columna categorica para el eje Y (las etiquetas a la izquierda).',
            valueDesc: 'Columna numerica para la longitud de las barras.',
            guidance: 'Barras horizontales. Ideal cuando las etiquetas son largas o hay muchas categorias.',
            needsColorColumn: false,
            needsValue2: false,
            hasAxes: true,
            showOrientation: false,
        },
        stacked_bar: {
            label: 'Barras Apiladas',
            groupDesc: 'Columna para el eje X (ej: anio, mes). Las barras se apilan en cada posicion.',
            valueDesc: 'Columna numerica cuyo valor se apila (ej: apropiacionvigente, pagos).',
            colorDesc: 'Columna que define las series apiladas (ej: destino, nombrerubro). Cada valor unico genera un segmento de color diferente.',
            guidance: 'Barras apiladas: requiere Eje X + Valor Y + Columna de Serie. Cada serie se apila verticalmente. Ideal para ver composicion y total.',
            needsColorColumn: true,
            needsValue2: false,
            hasAxes: true,
            showOrientation: false,
        },
        grouped_bar: {
            label: 'Barras Agrupadas',
            groupDesc: 'Columna para el eje X (ej: anio, mes). Las barras se agrupan lado a lado.',
            valueDesc: 'Columna numerica para la altura de cada barra (ej: pagos, compromisos).',
            colorDesc: 'Columna que define las barras dentro de cada grupo (ej: destino). Cada valor unico genera una barra separada.',
            guidance: 'Barras agrupadas: requiere Eje X + Valor Y + Columna de Serie. Las series se muestran lado a lado. Ideal para comparar series.',
            needsColorColumn: true,
            needsValue2: false,
            hasAxes: true,
            showOrientation: false,
        },
        line: {
            label: 'Lineas',
            groupDesc: 'Columna temporal o categorica para el eje X (ej: mes, anio, fecha).',
            valueDesc: 'Columna numerica para el eje Y.',
            colorDesc: 'Columna que define multiples lineas (ej: destino). Opcional para linea unica.',
            guidance: 'Grafico de lineas. Ideal para tendencias en el tiempo. Puede tener multiples lineas con la columna de serie.',
            needsColorColumn: true,
            needsValue2: true,
            hasAxes: true,
            showOrientation: false,
        },
        area: {
            label: 'Area',
            groupDesc: 'Columna temporal o categorica para el eje X.',
            valueDesc: 'Columna numerica para el eje Y.',
            guidance: 'Grafico de area. Rellena debajo de la linea. Ideal para mostrar volumenes.',
            needsColorColumn: false,
            needsValue2: false,
            hasAxes: true,
            showOrientation: false,
        },
        stacked_area: {
            label: 'Area Apilada',
            groupDesc: 'Columna temporal para el eje X (ej: mes, anio).',
            valueDesc: 'Columna numerica para apilar.',
            colorDesc: 'Columna que define las areas apiladas (ej: destino, nombrerubro).',
            guidance: 'Area apilada: requiere Eje X + Valor Y + Columna de Serie. Las areas se apilan para mostrar composicion acumulada.',
            needsColorColumn: true,
            needsValue2: false,
            hasAxes: true,
            showOrientation: false,
        },
        pie: {
            label: 'Pie / Torta',
            groupDesc: 'Columna que define cada porcion (ej: destino, nombrerubro).',
            valueDesc: 'Columna numerica cuyo valor determina el tamano de cada porcion.',
            guidance: 'Grafico de torta. Ideal para mostrar proporciones de un total.',
            needsColorColumn: false,
            needsValue2: false,
            hasAxes: false,
            showOrientation: false,
        },
        donut: {
            label: 'Donut',
            groupDesc: 'Columna que define cada porcion.',
            valueDesc: 'Columna numerica para el tamano de cada porcion.',
            guidance: 'Similar a torta pero con centro vacio. Ideal para proporciones.',
            needsColorColumn: false,
            needsValue2: false,
            hasAxes: false,
            showOrientation: false,
        },
        treemap: {
            label: 'Treemap',
            groupDesc: 'Columna de categoria que define cada bloque (ej: nombrerubro, destino).',
            valueDesc: 'Columna numerica que define el tamano proporcional de cada bloque.',
            guidance: 'Treemap: muestra jerarquias como bloques proporcionales al valor.',
            needsColorColumn: false,
            needsValue2: false,
            hasAxes: false,
            showOrientation: false,
        },
        radar: {
            label: 'Radar',
            groupDesc: 'Columna que define los ejes/dimensiones del radar (ej: nombrerubro).',
            valueDesc: 'Columna numerica para la distancia radial en cada eje.',
            colorDesc: 'Columna que define multiples series en el radar (ej: anio, destino).',
            guidance: 'Radar: muestra multiples dimensiones en ejes radiales. Ideal para comparar perfiles.',
            needsColorColumn: true,
            needsValue2: false,
            hasAxes: false,
            showOrientation: false,
        },
    };

    /**
     * Column classification for heatmap hints.
     */
    const COLUMN_TYPES = {
        codigocuenta: 'text', nombrerubro: 'text', movimiento: 'text',
        destino: 'text', bpid: 'text', compania: 'text', nombrepred: 'text',
        idprede: 'text', rubro: 'text', tercero: 'text', descripcion: 'text',
        tipo_cpte: 'text', comprobante_afectado: 'text',
        apropiacioninicial: 'numeric', adicion: 'numeric', reduccion: 'numeric',
        credito: 'numeric', contracredito: 'numeric', aplazamiento: 'numeric',
        desplazamiento: 'numeric', apropiacionvigente: 'numeric',
        disponibilidades: 'numeric', saldodisponible: 'numeric',
        compromisos: 'numeric', disponibilidadesabiertas: 'numeric',
        obligacion: 'numeric', pagos: 'numeric', obligacionesporpagar: 'numeric',
        valordebito: 'numeric', valorcredito: 'numeric',
        saldoporejecutaresp: 'numeric', numero: 'numeric',
        anio: 'temporal', mes: 'temporal', fecha: 'temporal',
    };

    /**
     * Colombian number formatter for admin preview.
     */
    const NumberFormatter = {
        format(value) {
            if (typeof value !== 'number' || isNaN(value)) return '0';
            const v = Math.abs(value);
            if (v >= 1e12) return (value / 1e12).toFixed(1).replace('.', ',') + ' Billones';
            if (v >= 1e9)  return (value / 1e9).toFixed(1).replace('.', ',') + ' MMll';
            if (v >= 1e6)  return (value / 1e6).toFixed(2).replace('.', ',') + ' Mll';
            if (v >= 1e3)  return (value / 1e3).toFixed(1).replace('.', ',') + ' Mil';
            return value.toLocaleString('es-CO');
        },
    };

    const ChartConfigManager = {
        currentColumns: [],

        init() {
            if ($('.sysman-chart-config').length === 0) return;

            this.bindEvents();
            this.loadColumns();
            this.updateColorPreview();
            this.updateFieldVisibility();
            this.updateFieldGuidance();
        },

        bindEvents() {
            $('#sysman_data_table').on('change', () => this.loadColumns());

            $('.sysman-chart-type-option').on('click', function () {
                $('.sysman-chart-type-option').removeClass('active');
                $(this).addClass('active');
            });

            $('input[name="sysman_chart_type"]').on('change', () => {
                this.updateFieldVisibility();
                this.updateFieldGuidance();
                this.applyColumnHeatmap();
            });

            $('#sysman-add-filter').on('click', () => this.addFilter());

            $(document).on('click', '.sysman-remove-filter', function () {
                $(this).closest('.sysman-filter-row').remove();
            });

            $('#sysman_chart_colors').on('input change', () => this.updateColorPreview());

            $('#sysman-refresh-preview').on('click', () => this.refreshPreview());

            $(document).on('click', '.sysman-toggle-section', function () {
                const body = $(this).closest('.sysman-collapsible').find('.sysman-collapsible-body');
                body.slideToggle(200);
                $(this).text(body.is(':visible') ? 'Colapsar' : 'Expandir');
            });
        },

        getSelectedChartType() {
            return $('input[name="sysman_chart_type"]:checked').val() || 'bar';
        },

        /**
         * Show/hide dynamic fields based on chart type selection.
         */
        updateFieldVisibility() {
            const type = this.getSelectedChartType();
            const config = CHART_TYPE_CONFIG[type];
            if (!config) return;

            // Color/series column
            if (config.needsColorColumn) {
                $('#sysman-color-column-wrap').slideDown(200);
            } else {
                $('#sysman-color-column-wrap').slideUp(200);
            }

            // Second Y value
            if (config.needsValue2) {
                $('#sysman-value2-wrap').slideDown(200);
            } else {
                $('#sysman-value2-wrap').slideUp(200);
            }

            // Orientation (for bar type only)
            if (config.showOrientation) {
                $('#sysman-orientation-wrap').slideDown(200);
            } else {
                $('#sysman-orientation-wrap').slideUp(200);
            }

            // Axes fields
            if (config.hasAxes) {
                $('.sysman-field-axes').show();
            } else {
                $('.sysman-field-axes').hide();
            }
        },

        updateFieldGuidance() {
            const type = this.getSelectedChartType();
            const config = CHART_TYPE_CONFIG[type];
            if (!config) return;

            $('#sysman-field-guidance-text').text(config.guidance);
            $('#sysman-field-guidance').slideDown(200);
            $('#sysman-group-hint').text(config.groupDesc);
            $('#sysman-value-hint').text(config.valueDesc);

            if (config.colorDesc) {
                $('#sysman-color-hint').text(config.colorDesc);
            }

            this.applyColumnHeatmap();
        },

        applyColumnHeatmap() {
            const type = this.getSelectedChartType();
            const isTimeSeries = ['line', 'area', 'stacked_area'].includes(type);

            $('#sysman_group_column option, #sysman_color_column option').each(function () {
                const col = $(this).val();
                if (!col) return;
                const colType = COLUMN_TYPES[col] || 'unknown';
                $(this).css('background-color', '');
                if (colType === 'text') $(this).css('background-color', '#d4edda');
                else if (colType === 'temporal') $(this).css('background-color', isTimeSeries ? '#d4edda' : '#fff3cd');
                else if (colType === 'numeric') $(this).css('background-color', '#f8d7da');
            });

            $('#sysman_value_column option, #sysman_value_column_2 option').each(function () {
                const col = $(this).val();
                if (!col) return;
                const colType = COLUMN_TYPES[col] || 'unknown';
                $(this).css('background-color', '');
                if (colType === 'numeric') $(this).css('background-color', '#d4edda');
                else if (colType === 'temporal') $(this).css('background-color', '#fff3cd');
                else if (colType === 'text') $(this).css('background-color', '#f8d7da');
            });
        },

        loadColumns() {
            const table = $('#sysman_data_table').val();
            if (!table) return;

            const parts = table.split('_');
            let key = '', found = false;
            for (const part of parts) {
                if (found || part === 'sysman') { found = true; key += (key ? '_' : '') + part; }
            }
            if (!key) key = table;

            $.ajax({
                url: `${sysmanCharts.restUrl}columns/${key}`,
                headers: { 'X-WP-Nonce': sysmanCharts.restNonce },
                success: (columns) => {
                    this.currentColumns = columns;
                    this.populateColumnSelects(columns);
                    this.applyColumnHeatmap();
                },
                error: () => console.error('Error loading columns'),
            });
        },

        populateColumnSelects(columns) {
            const exclude = ['id', 'fecha_importacion'];
            const filtered = columns.filter((c) => !exclude.includes(c));

            const savedGroup  = $('#sysman-saved-group-column').val();
            const savedValue  = $('#sysman-saved-value-column').val();
            const savedColor  = $('#sysman-saved-color-column').val();
            const savedValue2 = $('#sysman-saved-value-column-2').val();

            // Group column
            const groupSelect = $('#sysman_group_column');
            groupSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filtered.forEach((col) => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedGroup) opt.prop('selected', true);
                groupSelect.append(opt);
            });

            // Value column
            const valueSelect = $('#sysman_value_column');
            valueSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filtered.forEach((col) => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedValue) opt.prop('selected', true);
                valueSelect.append(opt);
            });

            // Color/series column
            const colorSelect = $('#sysman_color_column');
            colorSelect.empty().append('<option value="">-- Sin serie (datos simples) --</option>');
            filtered.forEach((col) => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedColor) opt.prop('selected', true);
                colorSelect.append(opt);
            });

            // Second value column
            const value2Select = $('#sysman_value_column_2');
            value2Select.empty().append('<option value="">-- No usar --</option>');
            filtered.forEach((col) => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedValue2) opt.prop('selected', true);
                value2Select.append(opt);
            });

            // Filter columns
            $('.sysman-filter-column').each(function () {
                const current = $(this).val();
                $(this).empty().append('<option value="">Columna</option>');
                filtered.forEach((col) => {
                    const opt = $('<option>').val(col).text(ChartConfigManager.formatColumnName(col));
                    if (col === current) opt.prop('selected', true);
                    $(this).append(opt);
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
                </div>`;
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
                preview.append($('<span class="sysman-color-swatch">').css('background-color', color).attr('title', color));
            });
        },

        /**
         * Render live D3plus preview in admin.
         * Uses dedicated AJAX endpoint that builds query from current form values.
         */
        refreshPreview() {
            const area = $('#sysman-chart-preview-area');
            const status = $('#sysman-preview-status');

            const dataTable   = $('#sysman_data_table').val();
            const groupColumn = $('#sysman_group_column').val();
            const valueColumn = $('#sysman_value_column').val();

            if (!dataTable || !groupColumn || !valueColumn) {
                area.html('<p style="text-align:center;padding:60px 20px;color:#999;">Seleccione tabla, columna de agrupacion y columna de valor para ver la vista previa.</p>');
                return;
            }

            area.html('<div style="text-align:center;padding:80px 20px;"><span class="spinner is-active" style="float:none;"></span><p>Cargando datos...</p></div>');
            status.text('Cargando...');

            $.ajax({
                url: sysmanCharts.ajaxUrl,
                type: 'POST',
                data: {
                    action:         'sysman_preview_chart',
                    preview_nonce:  sysmanCharts.previewNonce,
                    data_table:     dataTable,
                    group_column:   groupColumn,
                    value_column:   valueColumn,
                    color_column:   $('#sysman_color_column').val() || '',
                    value_column_2: $('#sysman_value_column_2').val() || '',
                    aggregate:      $('#sysman_aggregate').val() || 'SUM',
                    chart_type:     this.getSelectedChartType(),
                    chart_height:   $('#sysman_chart_height').val() || 400,
                    chart_colors:   $('#sysman_chart_colors').val() || '',
                    show_legend:    $('input[name="sysman_show_legend"]').is(':checked') ? 'yes' : '',
                    y_axis_title:   $('#sysman_y_axis_title').val() || '',
                    x_axis_title:   $('#sysman_x_axis_title').val() || '',
                    filter_anio:    $('#sysman_filter_anio').val() || 0,
                    filter_mes:     $('#sysman_filter_mes').val() || 0,
                    filter_destino: $('#sysman_filter_destino').val() || '',
                },
                success: (response) => {
                    if (!response.success || !response.data.data || response.data.data.length === 0) {
                        area.html('<p style="text-align:center;padding:60px 20px;color:#999;">No hay datos disponibles. Verifique que la tabla tenga registros.</p>');
                        status.text('Sin datos');
                        return;
                    }
                    this.renderD3PlusPreview(area, response.data.data, response.data.meta);
                    status.text(`${response.data.data.length} registros`);
                },
                error: () => {
                    area.html('<p style="text-align:center;padding:60px 20px;color:#dc3232;">Error al cargar los datos de vista previa.</p>');
                    status.text('Error');
                },
            });
        },

        /**
         * Render D3plus chart in admin preview area.
         * Supports all chart types including multi-series.
         */
        renderD3PlusPreview(container, data, meta) {
            container.empty();
            const canvasId = 'sysman-admin-preview-canvas';
            container.append(`<div id="${canvasId}" style="width:100%;min-height:350px;padding:10px;"></div>`);

            const chartType = meta.chart_type || 'bar';
            const colorsStr = meta.chart_colors || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));
            const height = parseInt(meta.chart_height) || 400;
            const hasGroups = data.some(d => d.group && d.group !== d.label);

            // Build chart data based on whether we have group column
            const chartData = data.map((d) => ({
                label: String(d.label || ''),
                value: parseFloat(d.value) || 0,
                group: String(d.group || d.label || ''),
            }));

            // Color mapping
            const uniqueGroups = [...new Set(chartData.map(d => d.group))];
            const colorMap = {};
            uniqueGroups.forEach((g, i) => { colorMap[g] = colors[i % colors.length]; });
            const colorFn = (d) => colorMap[d.group || d.label] || colors[0];

            const yConfig = {
                title: meta.y_axis_title || '',
                tickFormat: (d) => NumberFormatter.format(d),
            };
            const xConfig = { title: meta.x_axis_title || '' };

            const tooltipConfig = {
                tbody: [
                    [meta.x_axis_title || 'Categoria', (d) => d.label],
                    [meta.y_axis_title || 'Valor', (d) => (d.value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2 })],
                ],
            };

            if (hasGroups) {
                tooltipConfig.tbody.unshift(['Serie', (d) => d.group]);
            }

            const selector = `#${canvasId}`;

            try {
                switch (chartType) {
                    case 'bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'horizontal_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('value').y('label')
                            .discrete('y')
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(xConfig).xConfig(yConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'stacked_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x').stacked(true)
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'grouped_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x').stacked(false)
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'line':
                        new d3plus.LinePlot()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'area':
                        new d3plus.AreaPlot()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'stacked_area':
                        new d3plus.StackedArea()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'pie':
                        new d3plus.Pie()
                            .data(chartData).groupBy('group').value('value')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'donut':
                        new d3plus.Donut()
                            .data(chartData).groupBy('group').value('value')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'treemap':
                        new d3plus.Treemap()
                            .data(chartData).groupBy('group').sum('value')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    case 'radar':
                        new d3plus.Radar()
                            .data(chartData).groupBy('group')
                            .metric('label').value('value')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;

                    default:
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .select(selector).color(colorFn)
                            .height(height).locale('es_ES').render();
                }
            } catch (err) {
                console.error('D3plus admin preview error:', err);
                container.html('<p style="text-align:center;padding:60px 20px;color:#dc3232;">Error al renderizar: ' + err.message + '</p>');
            }
        },

        formatColumnName(col) {
            const labels = {
                codigocuenta: 'Codigo Cuenta', nombrerubro: 'Nombre Rubro',
                movimiento: 'Movimiento', destino: 'Destino', bpid: 'BPID',
                apropiacioninicial: 'Apropiacion Inicial', adicion: 'Adicion',
                reduccion: 'Reduccion', credito: 'Credito', contracredito: 'Contracredito',
                aplazamiento: 'Aplazamiento', desplazamiento: 'Desplazamiento',
                apropiacionvigente: 'Apropiacion Vigente', disponibilidades: 'Disponibilidades',
                saldodisponible: 'Saldo Disponible', compromisos: 'Compromisos',
                disponibilidadesabiertas: 'Disponibilidades Abiertas', obligacion: 'Obligacion',
                pagos: 'Pagos', obligacionesporpagar: 'Obligaciones por Pagar',
                anio: 'Anio', mes: 'Mes', compania: 'Compania', numero: 'Numero',
                nombrepred: 'Nombre Predecesor', idprede: 'ID Predecesor',
                rubro: 'Rubro', fecha: 'Fecha', tercero: 'Tercero',
                descripcion: 'Descripcion', valordebito: 'Valor Debito',
                valorcredito: 'Valor Credito', saldoporejecutaresp: 'Saldo por Ejecutar',
                comprobante_afectado: 'Comprobante Afectado', tipo_cpte: 'Tipo Comprobante',
            };
            return labels[col] || col.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },
    };

    $(document).ready(() => ChartConfigManager.init());
})(jQuery);
