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
        },

        loadColumns() {
            const table = $('#sisman_data_table').val();
            if (!table) return;

            // Extract table key (remove prefix)
            const tableKey = table.replace(/^[a-z0-9]+_/, '').replace(/^sisman_/, 'sisman_');
            // Get just the part after the WP prefix
            const parts = table.split('_');
            // Find sisman part
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

            // Populate column options
            this.loadColumns();
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
