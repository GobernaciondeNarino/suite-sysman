/**
 * SYSMAN Suite - Frontend Chart Renderer v1.5.0
 * Gobernación de Nariño
 * Uses D3plus for chart rendering, following secop-suite reference architecture.
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
            if (v >= 1e6)  return (value / 1e6).toFixed(2).replace('.', ',') + 'MMII';
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
                case 'colombian':  return this.colombiano(value);
                case 'abbreviated': return this.abreviado(value);
                case 'international': return this.internacional(value);
                default: return this.colombiano(value);
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

            if (!response.ok) throw new Error('Error al cargar los datos del gráfico');

            const result = await response.json();
            this.data = result.data || [];
            this.meta = result.meta || {};

            if (this.data.length === 0) throw new Error('No hay datos disponibles para este gráfico');
        }

        renderChart() {
            this.hideLoading();

            const config = this.config;
            const numberFormat = config.numberFormat || 'colombian';
            const colorsStr = config.colors || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{3,8}$/.test(c));

            // Prepare data for D3plus
            const chartData = this.data.map((d) => ({
                x: String(d.label || ''),
                y: parseFloat(d.value) || 0,
                group: String(d.label || ''),
            }));

            // Color scale function
            const colorMap = {};
            chartData.forEach((d, i) => {
                if (!colorMap[d.group]) {
                    colorMap[d.group] = colors[Object.keys(colorMap).length % colors.length];
                }
            });
            const colorFn = (d) => colorMap[d.group || d.x] || colors[0];

            // Base tooltip config
            const tooltipConfig = {
                tbody: [
                    [config.xAxisTitle || 'Categoría', (d) => d.x],
                    [config.yAxisTitle || 'Valor', (d) => NumberFormatter.fullFormat(d.y, numberFormat)],
                ],
            };

            // Axis configs
            const yConfig = {
                title: config.yAxisTitle || '',
                tickFormat: (d) => NumberFormatter.format(d, numberFormat),
            };
            const xConfig = {
                title: config.xAxisTitle || '',
            };

            const target = this.renderTarget;
            const showLegend = config.showLegend;
            const showTimeline = config.showTimeline;

            try {
                switch (config.type) {
                    case 'bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData)
                            .groupBy('group')
                            .x('x')
                            .y('y')
                            .select(target)
                            .color(colorFn)
                            .tooltipConfig(tooltipConfig)
                            .yConfig(yConfig)
                            .xConfig(xConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES');

                        if (showTimeline) {
                            this.chart.time('x').timeline(true);
                        }
                        this.chart.render();
                        break;

                    case 'line':
                        this.chart = new d3plus.LinePlot()
                            .data(chartData)
                            .groupBy('group')
                            .x('x')
                            .y('y')
                            .select(target)
                            .tooltipConfig(tooltipConfig)
                            .yConfig(yConfig)
                            .xConfig(xConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES')
                            .render();
                        break;

                    case 'area':
                        this.chart = new d3plus.AreaPlot()
                            .data(chartData)
                            .groupBy('group')
                            .x('x')
                            .y('y')
                            .select(target)
                            .tooltipConfig(tooltipConfig)
                            .yConfig(yConfig)
                            .xConfig(xConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES')
                            .render();
                        break;

                    case 'pie':
                    case 'donut':
                        this.chart = new d3plus.Pie()
                            .data(chartData)
                            .groupBy('group')
                            .value('y')
                            .select(target)
                            .color(colorFn)
                            .tooltipConfig(tooltipConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES');

                        if (config.type === 'donut') {
                            this.chart.innerRadius(80);
                        }
                        this.chart.render();
                        break;

                    case 'treemap':
                        this.chart = new d3plus.Treemap()
                            .data(chartData)
                            .groupBy('group')
                            .sum('y')
                            .select(target)
                            .color(colorFn)
                            .tooltipConfig(tooltipConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES')
                            .render();
                        break;

                    case 'stacked_bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData)
                            .groupBy('group')
                            .x('x')
                            .y('y')
                            .stacked(true)
                            .select(target)
                            .color(colorFn)
                            .tooltipConfig(tooltipConfig)
                            .yConfig(yConfig)
                            .xConfig(xConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES')
                            .render();
                        break;

                    case 'grouped_bar':
                        this.chart = new d3plus.BarChart()
                            .data(chartData)
                            .groupBy('group')
                            .x('x')
                            .y('y')
                            .stacked(false)
                            .select(target)
                            .color(colorFn)
                            .tooltipConfig(tooltipConfig)
                            .yConfig(yConfig)
                            .xConfig(xConfig)
                            .legend(showLegend || false)
                            .legendPosition('bottom')
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES')
                            .render();
                        break;

                    default:
                        this.chart = new d3plus.BarChart()
                            .data(chartData)
                            .groupBy('group')
                            .x('x')
                            .y('y')
                            .select(target)
                            .color(colorFn)
                            .height(parseInt(config.height) || 400)
                            .locale('es_ES')
                            .render();
                }
            } catch (err) {
                console.error('D3plus render error:', err);
                this.showError('Error al renderizar el gráfico');
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

            const tbody = modal.querySelector('tbody');
            tbody.innerHTML = '';

            this.data.forEach((row) => {
                const tr = document.createElement('tr');
                const label = String(row.label || '');
                const value = parseFloat(row.value) || 0;
                tr.innerHTML = `
                    <td>${this.escapeHtml(label)}</td>
                    <td>${NumberFormatter.fullFormat(value, this.config.numberFormat)}</td>
                `;
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
