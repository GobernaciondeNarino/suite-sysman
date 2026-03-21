/**
 * SYSMAN Suite - Admin Chart Configuration Manager
 * Gobernación de Nariño
 * v1.5.0 - Live D3plus preview + field guidance
 */
(function ($) {
    'use strict';

    /**
     * D3plus chart type requirements and field guidance.
     */
    const CHART_TYPE_CONFIG = {
        bar: {
            label: 'Barras',
            groupDesc: 'Seleccione una columna categórica (ej: nombrerubro, destino, codigocuenta) para el eje X.',
            valueDesc: 'Seleccione una columna numérica (ej: apropiacionvigente, pagos, compromisos) para el eje Y.',
            guidance: 'El gráfico de barras requiere un eje X (categoría) y un eje Y (valor numérico). Ideal para comparar valores entre categorías.',
        },
        line: {
            label: 'Líneas',
            groupDesc: 'Seleccione una columna temporal o categórica (ej: mes, anio) para el eje X.',
            valueDesc: 'Seleccione una columna numérica para el eje Y.',
            guidance: 'El gráfico de líneas requiere eje X (secuencial/temporal) y eje Y (valor). Ideal para tendencias.',
        },
        area: {
            label: 'Área',
            groupDesc: 'Seleccione una columna temporal o categórica para el eje X.',
            valueDesc: 'Seleccione una columna numérica para el eje Y.',
            guidance: 'El gráfico de área rellena debajo de la línea. Ideal para mostrar volúmenes acumulados.',
        },
        pie: {
            label: 'Pie / Torta',
            groupDesc: 'Seleccione la columna que define cada porción (ej: destino, nombrerubro).',
            valueDesc: 'Seleccione la columna numérica cuyo valor determinará el tamaño de cada porción.',
            guidance: 'El gráfico de torta necesita una columna de agrupación y un valor. Ideal para proporciones.',
        },
        donut: {
            label: 'Donut',
            groupDesc: 'Seleccione la columna que define cada porción.',
            valueDesc: 'Seleccione la columna numérica para el tamaño de cada porción.',
            guidance: 'Similar al gráfico de torta pero con centro vacío.',
        },
        treemap: {
            label: 'Treemap',
            groupDesc: 'Seleccione la columna de categoría que define cada bloque (ej: nombrerubro, destino).',
            valueDesc: 'Seleccione la columna numérica que define el tamaño de cada bloque.',
            guidance: 'El treemap muestra jerarquías como bloques proporcionales.',
        },
        stacked_bar: {
            label: 'Barras Apiladas',
            groupDesc: 'Seleccione la columna para el eje X.',
            valueDesc: 'Seleccione la columna numérica para apilar.',
            guidance: 'Barras apiladas combinan múltiples series. Requiere eje X y eje Y.',
        },
        grouped_bar: {
            label: 'Barras Agrupadas',
            groupDesc: 'Seleccione la columna para el eje X.',
            valueDesc: 'Seleccione la columna numérica para agrupar.',
            guidance: 'Barras agrupadas muestran series lado a lado.',
        },
    };

    /**
     * Column classification for heatmap.
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
            if (v >= 1e6)  return (value / 1e6).toFixed(2).replace('.', ',') + 'MMII';
            if (v >= 1e3)  return (value / 1e3).toFixed(1).replace('.', ',') + ' Mil';
            return value.toLocaleString('es-CO');
        },
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
            $('#sysman_data_table').on('change', () => this.loadColumns());

            $('.sysman-chart-type-option').on('click', function () {
                $('.sysman-chart-type-option').removeClass('active');
                $(this).addClass('active');
            });

            $('input[name="sysman_chart_type"]').on('change', () => {
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
            const isTimeSeries = ['line', 'area'].includes(type);

            $('#sysman_group_column option').each(function () {
                const col = $(this).val();
                if (!col) return;
                const colType = COLUMN_TYPES[col] || 'unknown';
                $(this).css('background-color', '');
                if (colType === 'text') $(this).css('background-color', '#d4edda');
                else if (colType === 'temporal') $(this).css('background-color', isTimeSeries ? '#d4edda' : '#fff3cd');
                else if (colType === 'numeric') $(this).css('background-color', '#f8d7da');
            });

            $('#sysman_value_column option').each(function () {
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
                    this.populateColumnSelects(columns);
                    this.applyColumnHeatmap();
                },
                error: () => console.error('Error loading columns'),
            });
        },

        populateColumnSelects(columns) {
            const exclude = ['id', 'fecha_importacion'];
            const filtered = columns.filter((c) => !exclude.includes(c));

            const savedGroup = $('#sysman-saved-group-column').val();
            const savedValue = $('#sysman-saved-value-column').val();

            const groupSelect = $('#sysman_group_column');
            groupSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filtered.forEach((col) => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedGroup) opt.prop('selected', true);
                groupSelect.append(opt);
            });

            const valueSelect = $('#sysman_value_column');
            valueSelect.empty().append('<option value="">-- Seleccionar columna --</option>');
            filtered.forEach((col) => {
                const opt = $('<option>').val(col).text(this.formatColumnName(col));
                if (col === savedValue) opt.prop('selected', true);
                valueSelect.append(opt);
            });

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
         * The key fix: save the post first via AJAX, then fetch data from REST API.
         */
        refreshPreview() {
            const area = $('#sysman-chart-preview-area');
            const status = $('#sysman-preview-status');
            const postId = $('#post_ID').val();

            if (!postId || postId === '0') {
                area.html('<p style="text-align:center;padding:60px 20px;color:#999;">Guarde la gráfica primero para ver la vista previa.</p>');
                return;
            }

            area.html('<div style="text-align:center;padding:80px 20px;"><span class="spinner is-active" style="float:none;"></span><p>Guardando configuración y cargando datos...</p></div>');
            status.text('Guardando...');

            // First, save the post silently via AJAX to persist current form values
            const formData = $('form#post').serialize();

            $.ajax({
                url: window.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData + '&action=editpost&_inline_edit=' + $('#_wpnonce').val(),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                complete: () => {
                    // Whether save succeeds or not, try to load preview from REST
                    status.text('Cargando datos...');
                    this.loadPreviewData(area, status, postId);
                },
            });
        },

        loadPreviewData(area, status, postId) {
            $.ajax({
                url: `${sysmanCharts.restUrl}chart/${postId}`,
                headers: { 'X-WP-Nonce': sysmanCharts.restNonce },
                success: (response) => {
                    if (!response.data || response.data.length === 0) {
                        area.html('<p style="text-align:center;padding:60px 20px;color:#999;">No hay datos disponibles. Verifique que la tabla tenga registros y que la configuración esté guardada.</p>');
                        status.text('Sin datos');
                        return;
                    }
                    this.renderD3PlusPreview(area, response.data, response.meta);
                    status.text(`${response.data.length} registros`);
                },
                error: (xhr) => {
                    let msg = 'Error al cargar los datos.';
                    if (xhr.status === 404) {
                        msg = 'Gráfico no encontrado. Publique o guarde el gráfico primero.';
                    }
                    area.html(`<p style="text-align:center;padding:60px 20px;color:#dc3232;">${msg}</p>`);
                    status.text('Error');
                },
            });
        },

        /**
         * Render D3plus chart in admin preview area.
         */
        renderD3PlusPreview(container, data, meta) {
            container.empty();
            const canvasId = 'sysman-admin-preview-canvas';
            container.append(`<div id="${canvasId}" style="width:100%;min-height:350px;padding:10px;"></div>`);

            const chartType = meta.chart_type || 'bar';
            const colorsStr = meta.chart_colors || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));
            const height = parseInt(meta.chart_height) || 400;

            const chartData = data.map((d) => ({
                x: String(d.label || ''),
                y: parseFloat(d.value) || 0,
                group: String(d.label || ''),
            }));

            const colorMap = {};
            chartData.forEach((d) => {
                if (!colorMap[d.group]) {
                    colorMap[d.group] = colors[Object.keys(colorMap).length % colors.length];
                }
            });
            const colorFn = (d) => colorMap[d.group || d.x] || colors[0];

            const yConfig = {
                title: meta.y_axis_title || '',
                tickFormat: (d) => NumberFormatter.format(d),
            };
            const xConfig = { title: meta.x_axis_title || '' };

            const tooltipConfig = {
                tbody: [
                    [meta.x_axis_title || 'Categoría', (d) => d.x],
                    [meta.y_axis_title || 'Valor', (d) => (d.y || 0).toLocaleString('es-CO', { minimumFractionDigits: 2 })],
                ],
            };

            const selector = `#${canvasId}`;

            try {
                switch (chartType) {
                    case 'bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('x').y('y')
                            .select(selector).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'line':
                        new d3plus.LinePlot()
                            .data(chartData).groupBy('group').x('x').y('y')
                            .select(selector).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'area':
                        new d3plus.AreaPlot()
                            .data(chartData).groupBy('group').x('x').y('y')
                            .select(selector).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'pie':
                        new d3plus.Pie()
                            .data(chartData).groupBy('group').value('y')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'donut':
                        new d3plus.Pie()
                            .data(chartData).groupBy('group').value('y')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .innerRadius(80)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'treemap':
                        new d3plus.Treemap()
                            .data(chartData).groupBy('group').sum('y')
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'stacked_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('x').y('y').stacked(true)
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    case 'grouped_bar':
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('x').y('y').stacked(false)
                            .select(selector).color(colorFn).tooltipConfig(tooltipConfig)
                            .yConfig(yConfig).xConfig(xConfig)
                            .legend(meta.show_legend || false).legendPosition('bottom')
                            .height(height).locale('es_ES').render();
                        break;
                    default:
                        new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('x').y('y')
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
                codigocuenta: 'Código Cuenta', nombrerubro: 'Nombre Rubro',
                movimiento: 'Movimiento', destino: 'Destino', bpid: 'BPID',
                apropiacioninicial: 'Apropiación Inicial', adicion: 'Adición',
                reduccion: 'Reducción', credito: 'Crédito', contracredito: 'Contracrédito',
                aplazamiento: 'Aplazamiento', desplazamiento: 'Desplazamiento',
                apropiacionvigente: 'Apropiación Vigente', disponibilidades: 'Disponibilidades',
                saldodisponible: 'Saldo Disponible', compromisos: 'Compromisos',
                disponibilidadesabiertas: 'Disponibilidades Abiertas', obligacion: 'Obligación',
                pagos: 'Pagos', obligacionesporpagar: 'Obligaciones por Pagar',
                anio: 'Año', mes: 'Mes', compania: 'Compañía', numero: 'Número',
                nombrepred: 'Nombre Predecesor', idprede: 'ID Predecesor',
                rubro: 'Rubro', fecha: 'Fecha', tercero: 'Tercero',
                descripcion: 'Descripción', valordebito: 'Valor Débito',
                valorcredito: 'Valor Crédito', saldoporejecutaresp: 'Saldo por Ejecutar',
                comprobante_afectado: 'Comprobante Afectado', tipo_cpte: 'Tipo Comprobante',
            };
            return labels[col] || col.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },
    };

    $(document).ready(() => ChartConfigManager.init());
})(jQuery);
