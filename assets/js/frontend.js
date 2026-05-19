/**
 * SYSMAN Suite - Frontend Chart Renderer v2.0.0
 * Gobernacion de Narino
 * Uses D3plus for chart rendering with multi-series support.
 */
(function () {
    'use strict';

    /* ========================================
       Number Formatter (Colombian style)
       ======================================== */
    const NumberFormatter = {
        colombiano(value) {
            const v = Math.abs(value);
            if (v >= 1e12) return (value / 1e12).toFixed(1).replace('.', ',') + ' Billones';
            if (v >= 1e9)  return (value / 1e9).toFixed(1).replace('.', ',') + ' MMll';
            if (v >= 1e6)  return (value / 1e6).toFixed(2).replace('.', ',') + ' Mll';
            if (v >= 1e3)  return (value / 1e3).toFixed(1).replace('.', ',') + ' Mil';
            return value.toLocaleString('es-CO');
        },

        internacional(value) {
            return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        abreviado(value) {
            const v = Math.abs(value);
            if (v >= 1e12) return (value / 1e12).toFixed(1) + 'B';
            if (v >= 1e9)  return (value / 1e9).toFixed(1) + 'MM';
            if (v >= 1e6)  return (value / 1e6).toFixed(1) + 'M';
            if (v >= 1e3)  return (value / 1e3).toFixed(1) + 'K';
            return value.toFixed(2);
        },

        fullFormat(value, format) {
            return value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        format(value, format) {
            if (typeof value !== 'number' || isNaN(value)) return '0';
            switch (format) {
                case 'colombian':     return this.colombiano(value);
                case 'abbreviated':   return this.abreviado(value);
                case 'international': return this.internacional(value);
                default:              return this.colombiano(value);
            }
        },
    };

    /* ========================================
       ChartManager Class
       ======================================== */
    class ChartManager {
        constructor(container) {
            this.container = container;
            this.configEl = document.getElementById(container.id + '-config');
            if (!this.configEl) return;

            this.config = JSON.parse(this.configEl.textContent);
            this.renderTarget = container.querySelector('.sysman-chart-render');
            this.loadingEl = container.querySelector('.sysman-loading');
            this.errorEl = container.querySelector('.sysman-error-message');
            this.data = [];
            this.chart = null;

            this.init();
        }

        async init() {
            try {
                await this.fetchData();
                this.renderChart();
                this.bindToolbar();
            } catch (error) {
                this.showError(error.message);
            }
        }

        async fetchData() {
            const response = await fetch(
                `${this.config.restUrl}chart/${this.config.chartId}`,
                { headers: { 'X-WP-Nonce': this.config.nonce } }
            );

            if (!response.ok) throw new Error('Error al cargar los datos del grafico');

            const result = await response.json();
            this.data = result.data || [];
            this.meta = result.meta || {};

            if (this.data.length === 0) throw new Error('No hay datos disponibles para este grafico');
        }

        renderChart() {
            this.hideLoading();

            const config = this.config;
            const numberFormat = config.numberFormat || 'colombian';
            const colorsStr = config.colors || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));

            // Prepare data - support multi-series (group column)
            const chartData = this.data.map((d) => ({
                label: String(d.label || ''),
                value: parseFloat(d.value) || 0,
                group: String(d.group || d.label || ''),
            }));

            // Color scale
            const uniqueGroups = [...new Set(chartData.map(d => d.group))];
            const colorMap = {};
            uniqueGroups.forEach((g, i) => { colorMap[g] = colors[i % colors.length]; });
            const colorFn = (d) => colorMap[d.group || d.label] || colors[0];

            // Tooltip config with custom labels
            const tooltipCategoryLabel = config.tooltipLabelCategory || config.xAxisTitle || 'Categoria';
            const tooltipValueLabel    = config.tooltipLabelValue || config.yAxisTitle || 'Valor';
            const tooltipSeriesLabel   = config.tooltipLabelSeries || 'Serie';

            const tooltipConfig = {
                tbody: [
                    [tooltipCategoryLabel, (d) => d.label],
                    [tooltipValueLabel, (d) => NumberFormatter.fullFormat(d.value, numberFormat)],
                ],
            };

            if (this.meta.has_groups || chartData.some(d => d.group !== d.label)) {
                tooltipConfig.tbody.unshift([tooltipSeriesLabel, (d) => d.group]);
            }

            // Axis configs
            const yConfig = {
                title: config.yAxisTitle || '',
                tickFormat: (d) => NumberFormatter.format(d, numberFormat),
            };
            const xConfig = { title: config.xAxisTitle || '' };

            const target = this.renderTarget;
            const showLegend = config.showLegend;
            const showTimeline = config.showTimeline;
            const height = parseInt(config.height) || 400;

            try {
                switch (config.type) {
                    case 'bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES');
                        if (showTimeline) this.chart.time('label').timeline(true);
                        this.chart.render();
                        break;

                    case 'horizontal_bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('value').y('label')
                            .discrete('y')
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(xConfig).xConfig(yConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'stacked_bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x').stacked(true)
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'grouped_bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x').stacked(false)
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'line':
                        this.chart = new d3plus.LinePlot()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'area':
                        this.chart = new d3plus.AreaPlot()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'stacked_area':
                        this.chart = new d3plus.StackedArea()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .discrete('x')
                            .select(target).color(colorFn)
                            .tooltipConfig(tooltipConfig).yConfig(yConfig).xConfig(xConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'pie':
                        this.chart = new d3plus.Pie()
                            .data(chartData).groupBy('group').value('value')
                            .select(target).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'donut':
                        this.chart = new d3plus.Donut()
                            .data(chartData).groupBy('group').value('value')
                            .select(target).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'treemap':
                        this.chart = new d3plus.Treemap()
                            .data(chartData).groupBy('group').sum('value')
                            .select(target).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    case 'radar':
                        this.chart = new d3plus.Radar()
                            .data(chartData).groupBy('group')
                            .metric('label').value('value')
                            .select(target).color(colorFn).tooltipConfig(tooltipConfig)
                            .legend(showLegend || false).legendPosition('bottom')
                            .height(height).locale('es_ES')
                            .render();
                        break;

                    default:
                        this.chart = new d3plus.BarChart()
                            .data(chartData).groupBy('group').x('label').y('value')
                            .select(target).color(colorFn)
                            .height(height).locale('es_ES')
                            .render();
                }
            } catch (err) {
                console.error('D3plus render error:', err);
                this.showError('Error al renderizar el grafico');
            }
        }

        /* ========================================
           Toolbar Actions
           ======================================== */
        bindToolbar() {
            const container = this.container;

            container.querySelectorAll('.sysman-toolbar-btn[data-action]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    const action = btn.dataset.action;
                    switch (action) {
                        case 'detail': this.showDetailModal(); break;
                        case 'share':  this.showShareModal();  break;
                        case 'data':   this.showDataModal();   break;
                        case 'image':  this.exportImage();     break;
                        case 'download': this.downloadCSV();   break;
                    }
                });
            });

            // CSV export from data modal
            const csvBtn = container.querySelector('.sysman-btn-csv-export');
            if (csvBtn) {
                csvBtn.addEventListener('click', () => this.downloadCSV());
            }

            // Modal close handlers
            container.querySelectorAll('.sysman-modal-close, .sysman-modal-overlay').forEach((el) => {
                el.addEventListener('click', () => {
                    container.querySelectorAll('.sysman-modal').forEach((m) => {
                        m.style.display = 'none';
                    });
                });
            });

            // Copy link
            container.querySelectorAll('.sysman-copy-link').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const targetId = btn.dataset.target;
                    const input = document.getElementById(targetId);
                    if (input) {
                        input.select();
                        navigator.clipboard.writeText(input.value).then(() => {
                            this.showToast('Enlace copiado');
                        });
                    }
                });
            });

            // ESC to close modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    container.querySelectorAll('.sysman-modal').forEach((m) => {
                        m.style.display = 'none';
                    });
                }
            });
        }

        showDataModal() {
            const modal = this.container.querySelector('.sysman-data-modal');
            if (!modal) return;

            const thead = modal.querySelector('thead tr');
            const tbody = modal.querySelector('tbody');
            const hasGroups = this.data.some(d => d.group);

            // Build header
            thead.innerHTML = '';
            if (hasGroups) {
                thead.innerHTML += '<th>Serie</th>';
            }
            thead.innerHTML += '<th>Categoria</th><th>Valor</th>';

            tbody.innerHTML = '';
            this.data.forEach((row) => {
                const tr = document.createElement('tr');
                let html = '';
                if (hasGroups) {
                    html += `<td>${this.escapeHtml(String(row.group || ''))}</td>`;
                }
                html += `<td>${this.escapeHtml(String(row.label || ''))}</td>`;
                html += `<td>${NumberFormatter.fullFormat(parseFloat(row.value) || 0, this.config.numberFormat)}</td>`;
                tr.innerHTML = html;
                tbody.appendChild(tr);
            });

            modal.style.display = 'flex';
        }

        showShareModal() {
            const modal = this.container.querySelector('.sysman-share-modal');
            if (!modal) return;

            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(this.config.title || 'Datos Presupuestales SYSMAN');

            const fb = modal.querySelector('.sysman-share-facebook');
            if (fb) fb.href = `https://www.facebook.com/sharer/sharer.php?u=${url}`;

            const tw = modal.querySelector('.sysman-share-twitter');
            if (tw) tw.href = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;

            const li = modal.querySelector('.sysman-share-linkedin');
            if (li) li.href = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;

            const wa = modal.querySelector('.sysman-share-whatsapp');
            if (wa) wa.href = `https://wa.me/?text=${title}%20${url}`;

            modal.style.display = 'flex';
        }

        showDetailModal() {
            const modal = this.container.querySelector('.sysman-detail-modal');
            if (modal) modal.style.display = 'flex';
        }

        downloadCSV() {
            const url = `${this.config.restUrl}chart/${this.config.chartId}/csv`;
            const a = document.createElement('a');
            a.href = url;
            a.download = `sysman-chart-${this.config.chartId}.csv`;
            a.click();
        }

        exportImage() {
            const canvas = this.renderTarget;
            if (!canvas) return;

            if (typeof html2canvas !== 'undefined') {
                html2canvas(this.container).then((canvasEl) => {
                    const a = document.createElement('a');
                    a.href = canvasEl.toDataURL('image/png');
                    a.download = `sysman-chart-${this.config.chartId}.png`;
                    a.click();
                });
            } else {
                const svg = canvas.querySelector('svg');
                if (svg) {
                    const serializer = new XMLSerializer();
                    const svgStr = serializer.serializeToString(svg);
                    const blob = new Blob([svgStr], { type: 'image/svg+xml' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `sysman-chart-${this.config.chartId}.svg`;
                    a.click();
                    URL.revokeObjectURL(url);
                }
            }
        }

        /* ========================================
           UI Helpers
           ======================================== */
        hideLoading() {
            if (this.loadingEl) this.loadingEl.style.display = 'none';
        }

        showError(message) {
            this.hideLoading();
            if (this.errorEl) {
                const p = this.errorEl.querySelector('p');
                if (p) p.textContent = message;
                this.errorEl.style.display = '';
            }
        }

        showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'sysman-toast success';
            toast.textContent = message;
            toast.setAttribute('role', 'status');
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    }

    /* ========================================
       Initialize all charts with IntersectionObserver
       ======================================== */
    document.addEventListener('DOMContentLoaded', () => {
        const containers = document.querySelectorAll('.sysman-chart-container');

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            new ChartManager(entry.target);
                            observer.unobserve(entry.target);
                        }
                    });
                },
                { rootMargin: '200px' }
            );
            containers.forEach((c) => observer.observe(c));
        } else {
            containers.forEach((c) => new ChartManager(c));
        }
    });
})();
