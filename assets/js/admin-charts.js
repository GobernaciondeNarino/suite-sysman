/**
 * SYSMAN Suite - Admin Chart Configuration Manager
 * Gobernacion de Narino
 * v2.1.1 - Fix: event delegation for dynamic Y-value column buttons
 */
(function ($) {
    'use strict';

    /**
     * Chart type configurations.
     * supportsMultiY: all types support multiple Y columns (each becomes a series)
     * needsColorColumn: only when single Y value, group by a categorical column
     * hasAxes: show axis title fields
     */
    const CHART_TYPE_CONFIG = {
        bar: {
            label: 'Barras',
            groupDesc: 'Columna categorica para el eje X (ej: anio, nombrerubro, destino).',
            valueDesc: 'Agregue 1 o mas columnas numericas. Cada una se convierte en una serie.',
            guidance: 'Grafico de barras. Agregue multiples valores Y para comparar metricas lado a lado (ej: Apropiacion Vigente vs Pagos por año).',
            hasAxes: true,
        },
        horizontal_bar: {
            label: 'Barras Horizontales',
            groupDesc: 'Columna categorica para el eje Y (las etiquetas a la izquierda).',
            valueDesc: 'Columnas numericas para la longitud de las barras.',
            guidance: 'Barras horizontales. Ideal cuando las etiquetas son largas. Soporta multiples valores Y.',
            hasAxes: true,
        },
        stacked_bar: {
            label: 'Barras Apiladas',
            groupDesc: 'Columna para el eje X (ej: anio). Las barras se apilan en cada posicion.',
            valueDesc: 'Agregue 2+ columnas numericas. Cada una sera un segmento apilado.',
            guidance: 'Barras apiladas: agregue 2 o mas valores Y. Cada valor se apila como un segmento de color diferente. Ideal para ver composicion y total.',
            hasAxes: true,
        },
        grouped_bar: {
            label: 'Barras Agrupadas',
            groupDesc: 'Columna para el eje X (ej: anio). Las barras se agrupan lado a lado.',
            valueDesc: 'Agregue 2+ columnas numericas. Cada una sera una barra lado a lado.',
            guidance: 'Barras agrupadas: agregue 2 o mas valores Y. Cada valor se muestra como barra separada lado a lado. Ideal para comparar series.',
            hasAxes: true,
        },
        line: {
            label: 'Lineas',
            groupDesc: 'Columna temporal o categorica para el eje X (ej: mes, anio).',
            valueDesc: 'Agregue 1 o mas columnas numericas. Cada una sera una linea.',
            guidance: 'Grafico de lineas. Multiples valores Y generan multiples lineas. Ideal para tendencias.',
            hasAxes: true,
        },
        area: {
            label: 'Area',
            groupDesc: 'Columna temporal o categorica para el eje X.',
            valueDesc: 'Columna numerica para el area.',
            guidance: 'Grafico de area. Rellena debajo de la linea.',
            hasAxes: true,
        },
        stacked_area: {
            label: 'Area Apilada',
            groupDesc: 'Columna temporal para el eje X (ej: mes, anio).',
            valueDesc: 'Agregue 2+ columnas numericas para apilar.',
            guidance: 'Area apilada: cada valor Y se apila como un area.',
            hasAxes: true,
        },
        pie: {
            label: 'Pie / Torta',
            groupDesc: 'Columna que define cada porcion (ej: destino, nombrerubro).',
            valueDesc: 'Una columna numerica para el tamano de cada porcion.',
            guidance: 'Grafico de torta. Solo 1 valor Y.',
            hasAxes: false,
        },
        donut: {
            label: 'Donut',
            groupDesc: 'Columna que define cada porcion.',
            valueDesc: 'Una columna numerica para el tamano de cada porcion.',
            guidance: 'Similar a torta con centro vacio.',
            hasAxes: false,
        },
        treemap: {
            label: 'Treemap',
            groupDesc: 'Columna de categoria para cada bloque.',
            valueDesc: 'Columna numerica para el tamano de cada bloque.',
            guidance: 'Treemap: bloques proporcionales al valor.',
            hasAxes: false,
        },
        radar: {
            label: 'Radar',
            groupDesc: 'Columna que define los ejes del radar (ej: nombrerubro).',
            valueDesc: 'Agregue 1 o mas columnas numericas. Cada una sera una serie en el radar.',
            guidance: 'Radar: cada valor Y genera una serie poligonal.',
            hasAxes: false,
        },
    };

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
        // Personal Nómina
        iddeempleado: 'text', apellido1: 'text', apellido2: 'text', nombres: 'text',
        numerodcto: 'text', expedida: 'text', fechancto: 'temporal', fechadeingreso: 'temporal',
        fechaderetiro: 'temporal', iddecargo: 'text', nombredelcargo: 'text',
        iddecategoria: 'text', nombrecategoria: 'text', escalafon: 'text',
        nombreescalafon: 'text', grado: 'text', decarrera: 'text',
        salariobaseibc: 'numeric', dependencianombre: 'text',
        emailcorporativo: 'text', emailpersonal: 'text', direccion: 'text', telefonos: 'text',
        fechacumplimientobonificacion: 'temporal',
        // Ingresos
        cuenta: 'text', codigo: 'text', nombre: 'text',
        tiporecurso: 'text', fuenterecurso: 'text',
        apropiado: 'numeric', modificaciones: 'numeric', totalpresupuesto: 'numeric',
        recaudosanteriores: 'numeric', recaudosmes: 'numeric', recaudosacumulados: 'numeric',
        porrecaudar: 'numeric', porcrecaudado: 'numeric',
    };

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

            // Dynamic Y-value columns (use event delegation for robustness)
            $(document).on('click', '#sysman-add-value-column', () => this.addValueColumn(''));
            $(document).on('click', '.sysman-remove-value-col', function () {
                $(this).closest('.sysman-value-col-row').remove();
                ChartConfigManager.updateFieldVisibility();
            });
            $(document).on('change', '.sysman-value-col-select', () => this.updateFieldVisibility());

            $(document).on('click', '#sysman-add-filter', () => this.addFilter());
            $(document).on('click', '.sysman-remove-filter', function () {
                $(this).closest('.sysman-filter-row').remove();
            });

            $('#sysman_chart_colors').on('input change', () => this.updateColorPreview());
            $(document).on('click', '#sysman-refresh-preview', () => this.refreshPreview());

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
         * Get all selected Y-value columns.
         */
        getValueColumns() {
            const cols = [];
            $('.sysman-value-col-select').each(function () {
                const v = $(this).val();
                if (v) cols.push(v);
            });
            return cols;
        },

        /**
         * Add a Y-value column row.
         */
        addValueColumn(selectedValue) {
            const index = $('.sysman-value-col-row').length;
            const exclude = ['id', 'fecha_importacion'];
            const filtered = this.currentColumns.filter(c => !exclude.includes(c));

            let options = '<option value="">-- Seleccionar columna --</option>';
            filtered.forEach(col => {
                const label = this.formatColumnName(col);
                const colType = COLUMN_TYPES[col] || 'unknown';
                const bg = colType === 'numeric' ? ' style="background-color:#d4edda"' :
                           colType === 'temporal' ? ' style="background-color:#fff3cd"' :
                           colType === 'text' ? ' style="background-color:#f8d7da"' : '';
                const sel = col === selectedValue ? ' selected' : '';
                options += `<option value="${col}"${bg}${sel}>${label}</option>`;
            });

            const html = `
                <div class="sysman-value-col-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                    <span class="sysman-value-col-badge" style="background:var(--sysman-primary,#1a5632);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">Y${index + 1}</span>
                    <select name="sysman_value_columns[]" class="sysman-value-col-select" style="flex:1;max-width:500px;">
                        ${options}
                    </select>
                    <button type="button" class="button sysman-remove-value-col" aria-label="Eliminar" style="padding:0 8px;color:#dc3232;">&times;</button>
                </div>`;
            $('#sysman-value-columns-list').append(html);
            this.renumberValueBadges();
            this.updateFieldVisibility();
        },

        renumberValueBadges() {
            $('.sysman-value-col-badge').each(function (i) {
                $(this).text('Y' + (i + 1));
            });
        },

        updateFieldVisibility() {
            const type = this.getSelectedChartType();
            const config = CHART_TYPE_CONFIG[type];
            if (!config) return;

            const valueCount = this.getValueColumns().length;

            // Color column: only show when 1 value column AND not pie/donut/treemap
            // When multiple Y columns exist, the series are automatically the column names
            const showColor = valueCount <= 1 && !['pie', 'donut', 'treemap'].includes(type);
            if (showColor) {
                $('#sysman-color-column-wrap').slideDown(200);
            } else {
                $('#sysman-color-column-wrap').slideUp(200);
                $('#sysman_color_column').val('');
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
            const filtered = columns.filter(c => !exclude.includes(c));

            const savedGroup  = $('#sysman-saved-group-column').val();
            const savedColor  = $('#sysman-saved-color-column').val();

            // Saved value columns (JSON array)
            let savedValues = [];
            try {
                savedValues = JSON.parse($('#sysman-saved-value-columns').val() || '[]');
            } catch (e) { /* ignore */ }

            // Group column
            const groupSelect = $('#sysman_group_column');
            groupSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filtered.forEach(col => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedGroup) opt.prop('selected', true);
                groupSelect.append(opt);
            });

            // Color/series column
            const colorSelect = $('#sysman_color_column');
            colorSelect.empty().append('<option value="">-- Sin serie adicional --</option>');
            filtered.forEach(col => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedColor) opt.prop('selected', true);
                colorSelect.append(opt);
            });

            // Y-value columns: rebuild from saved
            $('#sysman-value-columns-list').empty();
            if (savedValues.length > 0) {
                savedValues.forEach(v => this.addValueColumn(v));
            } else {
                // Add one empty row by default
                this.addValueColumn('');
            }
            // Clear saved so we don't re-read on next column load
            $('#sysman-saved-value-columns').val('[]');

            // Filter columns
            $('.sysman-filter-column').each(function () {
                const current = $(this).val();
                $(this).empty().append('<option value="">Columna</option>');
                filtered.forEach(col => {
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
            colors.forEach(color => {
                preview.append($('<span class="sysman-color-swatch">').css('background-color', color).attr('title', color));
            });
        },

        /**
         * Render live D3plus preview.
         * Sends all Y columns to the backend; the backend builds a UNION query.
         */
        refreshPreview() {
            const area = $('#sysman-chart-preview-area');
            const status = $('#sysman-preview-status');

            const dataTable   = $('#sysman_data_table').val();
            const groupColumn = $('#sysman_group_column').val();
            const valueColumns = this.getValueColumns();

            if (!dataTable || !groupColumn || valueColumns.length === 0) {
                area.html('<p style="text-align:center;padding:60px 20px;color:#999;">Seleccione tabla, columna de agrupacion y al menos un valor Y.</p>');
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
                    value_columns:  valueColumns,
                    color_column:   $('#sysman_color_column').val() || '',
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

        renderD3PlusPreview(container, data, meta) {
            container.empty();
            const canvasId = 'sysman-admin-preview-canvas';
            container.append(`<div id="${canvasId}" style="width:100%;min-height:350px;padding:10px;"></div>`);

            const chartType = meta.chart_type || 'bar';
            const colorsStr = meta.chart_colors || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));
            const height = parseInt(meta.chart_height) || 400;
            const hasGroups = data.some(d => d.group && d.group !== d.label);

            const chartData = data.map(d => ({
                label: String(d.label || ''),
                value: parseFloat(d.value) || 0,
                group: String(d.group || d.label || ''),
            }));

            const uniqueGroups = [...new Set(chartData.map(d => d.group))];
            const colorMap = {};
            uniqueGroups.forEach((g, i) => { colorMap[g] = colors[i % colors.length]; });
            const colorFn = d => colorMap[d.group || d.label] || colors[0];

            const yConfig = { title: meta.y_axis_title || '', tickFormat: d => NumberFormatter.format(d) };
            const xConfig = { title: meta.x_axis_title || '' };

            const tooltipConfig = {
                tbody: [
                    [meta.x_axis_title || 'Categoria', d => d.label],
                    [meta.y_axis_title || 'Valor', d => (d.value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2 })],
                ],
            };
            if (hasGroups) tooltipConfig.tbody.unshift(['Serie', d => d.group]);

            const selector = `#${canvasId}`;

            try {
                switch (chartType) {
                    case 'bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value').discrete('x')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'horizontal_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('value').y('label').discrete('y')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(xConfig).xConfig(yConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'stacked_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value').discrete('x').stacked(true)
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'grouped_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value').discrete('x').stacked(false)
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'line':
                        new d3plus.LinePlot()
                            .data(chartData).groupBy('group').x('label').y('value').discrete('x')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'area':
                        new d3plus.AreaPlot()
                            .data(chartData).groupBy('group').x('label').y('value').discrete('x')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'stacked_area':
                        new d3plus.StackedArea()
                            .data(chartData).groupBy('group').x('label').y('value').discrete('x')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
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
                            .data(chartData).groupBy('group').metric('label').value('value')
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
                // Personal
                iddeempleado: 'ID Empleado', apellido1: 'Primer Apellido', apellido2: 'Segundo Apellido',
                nombres: 'Nombres', numerodcto: 'Num. Documento', expedida: 'Expedida',
                fechancto: 'Fecha Nacimiento', fechadeingreso: 'Fecha Ingreso', fechaderetiro: 'Fecha Retiro',
                iddecargo: 'ID Cargo', nombredelcargo: 'Cargo', iddecategoria: 'ID Categoria',
                nombrecategoria: 'Categoria', escalafon: 'Escalafon', nombreescalafon: 'Nombre Escalafon',
                grado: 'Grado', decarrera: 'Carrera', salariobaseibc: 'Salario Base IBC',
                dependencianombre: 'Dependencia', emailcorporativo: 'Email Corporativo',
                emailpersonal: 'Email Personal', direccion: 'Direccion', telefonos: 'Telefonos',
                fechacumplimientobonificacion: 'Cumplimiento Bonificacion',
                // Ingresos
                cuenta: 'Cuenta', codigo: 'Codigo', nombre: 'Nombre',
                tiporecurso: 'Tipo Recurso', fuenterecurso: 'Fuente Recurso',
                apropiado: 'Apropiado', modificaciones: 'Modificaciones',
                totalpresupuesto: 'Total Presupuesto', recaudosanteriores: 'Recaudos Anteriores',
                recaudosmes: 'Recaudos Mes', recaudosacumulados: 'Recaudos Acumulados',
                porrecaudar: 'Por Recaudar', porcrecaudado: '% Recaudado',
            };
            return labels[col] || col.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },
    };

    $(document).ready(() => ChartConfigManager.init());
})(jQuery);
