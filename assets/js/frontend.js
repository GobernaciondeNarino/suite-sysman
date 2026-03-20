/**
 * SISMAN Suite - Frontend Chart Renderer
 * Gobernación de Nariño
 * Uses D3plus for chart rendering
 */
(function () {
    'use strict';

    class SismanChart {
        constructor(wrapper) {
            this.wrapper = wrapper;
            this.chartId = wrapper.dataset.chartId;
            this.chartType = wrapper.dataset.chartType || 'bar';
            this.chartHeight = parseInt(wrapper.dataset.chartHeight) || 400;
            this.showLegend = wrapper.dataset.showLegend === 'true';
            this.showLabels = wrapper.dataset.showLabels === 'true';
            this.numberFormat = wrapper.dataset.numberFormat || 'colombian';
            this.canvas = wrapper.querySelector('.sisman-chart-canvas');
            this.loading = wrapper.querySelector('.sisman-chart-loading');
            this.data = [];

            this.init();
        }

        async init() {
            try {
                await this.fetchData();
                this.renderChart();
                this.bindEvents();
            } catch (error) {
                this.showError(error.message);
            }
        }

        async fetchData() {
            const response = await fetch(
                `${sismanFrontend.restUrl}chart/${this.chartId}`,
                {
                    headers: {
                        'X-WP-Nonce': sismanFrontend.restNonce,
                    },
                }
            );

            if (!response.ok) {
                throw new Error('Error al cargar los datos del gráfico');
            }

            const result = await response.json();
            this.data = result.data || [];
            this.meta = result.meta || {};

            if (this.data.length === 0) {
                throw new Error('No hay datos disponibles para este gráfico');
            }
        }

        renderChart() {
            this.hideLoading();

            // Prepare data for D3plus
            const chartData = this.data.map((d) => ({
                label: String(d.label || ''),
                value: parseFloat(d.value) || 0,
            }));

            // Color palette inspired by Gobernación de Nariño
            const colors = [
                '#1a5632', '#2d7a4a', '#3498db', '#c8a415',
                '#e74c3c', '#9b59b6', '#1abc9c', '#e67e22',
                '#2c3e50', '#27ae60', '#f39c12', '#8e44ad',
            ];

            const config = {
                data: chartData,
                groupBy: 'label',
                height: this.chartHeight,
            };

            // Apply number formatting
            const formatNumber = (num) => this.formatNumber(num);

            try {
                switch (this.chartType) {
                    case 'bar':
                        new d3plus.BarChart()
                            .select(this.canvas)
                            .config({
                                ...config,
                                x: 'label',
                                y: 'value',
                                tooltipConfig: {
                                    body: (d) => `Valor: ${formatNumber(d.value)}`,
                                },
                                shapeConfig: {
                                    fill: (d, i) => colors[i % colors.length],
                                },
                            })
                            .render();
                        break;

                    case 'line':
                        new d3plus.LinePlot()
                            .select(this.canvas)
                            .config({
                                ...config,
                                x: 'label',
                                y: 'value',
                                tooltipConfig: {
                                    body: (d) => `Valor: ${formatNumber(d.value)}`,
                                },
                            })
                            .render();
                        break;

                    case 'area':
                        new d3plus.AreaPlot()
                            .select(this.canvas)
                            .config({
                                ...config,
                                x: 'label',
                                y: 'value',
                            })
                            .render();
                        break;

                    case 'pie':
                    case 'donut':
                        new d3plus.Pie()
                            .select(this.canvas)
                            .config({
                                ...config,
                                value: 'value',
                                innerRadius: this.chartType === 'donut' ? 80 : 0,
                                tooltipConfig: {
                                    body: (d) => `Valor: ${formatNumber(d.value)}`,
                                },
                                shapeConfig: {
                                    fill: (d, i) => colors[i % colors.length],
                                },
                            })
                            .render();
                        break;

                    case 'treemap':
                        new d3plus.Treemap()
                            .select(this.canvas)
                            .config({
                                ...config,
                                sum: 'value',
                                tooltipConfig: {
                                    body: (d) => `Valor: ${formatNumber(d.value)}`,
                                },
                                shapeConfig: {
                                    fill: (d, i) => colors[i % colors.length],
                                },
                            })
                            .render();
                        break;

                    case 'stacked_bar':
                    case 'grouped_bar':
                        new d3plus.BarChart()
                            .select(this.canvas)
                            .config({
                                ...config,
                                x: 'label',
                                y: 'value',
                                stacked: this.chartType === 'stacked_bar',
                                tooltipConfig: {
                                    body: (d) => `Valor: ${formatNumber(d.value)}`,
                                },
                                shapeConfig: {
                                    fill: (d, i) => colors[i % colors.length],
                                },
                            })
                            .render();
                        break;

                    default:
                        new d3plus.BarChart()
                            .select(this.canvas)
                            .config({
                                ...config,
                                x: 'label',
                                y: 'value',
                            })
                            .render();
                }
            } catch (err) {
                console.error('D3plus render error:', err);
                this.showError('Error al renderizar el gráfico');
            }
        }

        bindEvents() {
            // Data modal
            const dataBtn = this.wrapper.querySelector('.sisman-btn-data');
            if (dataBtn) {
                dataBtn.addEventListener('click', () => this.showDataModal());
            }

            // CSV download
            const downloadBtn = this.wrapper.querySelector('.sisman-btn-download');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', () => this.downloadCSV());
            }

            // Image export
            const imageBtn = this.wrapper.querySelector('.sisman-btn-image');
            if (imageBtn) {
                imageBtn.addEventListener('click', () => this.exportImage());
            }

            // Share
            const shareBtn = this.wrapper.querySelector('.sisman-btn-share');
            if (shareBtn) {
                shareBtn.addEventListener('click', () => this.showShareModal());
            }

            // Fullscreen
            const fsBtn = this.wrapper.querySelector('.sisman-btn-fullscreen');
            if (fsBtn) {
                fsBtn.addEventListener('click', () => this.toggleFullscreen());
            }

            // Modal close handlers
            this.wrapper.querySelectorAll('.sisman-modal-close, .sisman-modal-overlay').forEach((el) => {
                el.addEventListener('click', () => {
                    this.wrapper.querySelectorAll('.sisman-chart-modal').forEach((m) => {
                        m.style.display = 'none';
                    });
                });
            });

            // Copy link
            this.wrapper.querySelectorAll('.sisman-copy-link').forEach((btn) => {
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
                    this.wrapper.querySelectorAll('.sisman-chart-modal').forEach((m) => {
                        m.style.display = 'none';
                    });
                    if (this.wrapper.classList.contains('fullscreen')) {
                        this.wrapper.classList.remove('fullscreen');
                    }
                }
            });
        }

        showDataModal() {
            const modal = this.wrapper.querySelector('.sisman-data-modal');
            if (!modal) return;

            const tbody = modal.querySelector('tbody');
            tbody.innerHTML = '';

            this.data.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${this.escapeHtml(String(row.label || ''))}</td>
                    <td>${this.formatNumber(parseFloat(row.value) || 0)}</td>
                `;
                tbody.appendChild(tr);
            });

            modal.style.display = 'flex';
            modal.querySelector('.sisman-modal-close').focus();
        }

        showShareModal() {
            const modal = this.wrapper.querySelector('.sisman-share-modal');
            if (!modal) return;

            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(this.meta.title || 'Datos Presupuestales SISMAN');

            const facebookBtn = modal.querySelector('.sisman-share-facebook');
            if (facebookBtn) {
                facebookBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            }

            const twitterBtn = modal.querySelector('.sisman-share-twitter');
            if (twitterBtn) {
                twitterBtn.href = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
            }

            const linkedinBtn = modal.querySelector('.sisman-share-linkedin');
            if (linkedinBtn) {
                linkedinBtn.href = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
            }

            const whatsappBtn = modal.querySelector('.sisman-share-whatsapp');
            if (whatsappBtn) {
                whatsappBtn.href = `https://wa.me/?text=${title}%20${url}`;
            }

            modal.style.display = 'flex';
            modal.querySelector('.sisman-modal-close').focus();
        }

        downloadCSV() {
            const url = `${sismanFrontend.restUrl}chart/${this.chartId}/csv`;
            const a = document.createElement('a');
            a.href = url;
            a.download = `sisman-chart-${this.chartId}.csv`;
            a.click();
        }

        exportImage() {
            const canvas = this.canvas;
            if (!canvas) return;

            // Use html2canvas if available, otherwise use SVG serialization
            if (typeof html2canvas !== 'undefined') {
                html2canvas(canvas).then((canvasEl) => {
                    const a = document.createElement('a');
                    a.href = canvasEl.toDataURL('image/png');
                    a.download = `sisman-chart-${this.chartId}.png`;
                    a.click();
                });
            } else {
                // Fallback: serialize SVG
                const svg = canvas.querySelector('svg');
                if (svg) {
                    const serializer = new XMLSerializer();
                    const svgStr = serializer.serializeToString(svg);
                    const blob = new Blob([svgStr], { type: 'image/svg+xml' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `sisman-chart-${this.chartId}.svg`;
                    a.click();
                    URL.revokeObjectURL(url);
                }
            }
        }

        toggleFullscreen() {
            this.wrapper.classList.toggle('fullscreen');
            if (this.wrapper.classList.contains('fullscreen')) {
                const container = this.wrapper.querySelector('.sisman-chart-container');
                container.style.height = 'calc(100vh - 120px)';
            } else {
                const container = this.wrapper.querySelector('.sisman-chart-container');
                container.style.height = this.chartHeight + 'px';
            }
        }

        hideLoading() {
            if (this.loading) {
                this.loading.style.display = 'none';
            }
        }

        showError(message) {
            this.hideLoading();
            this.canvas.innerHTML = `<div class="sisman-error">${this.escapeHtml(message)}</div>`;
        }

        formatNumber(num) {
            if (isNaN(num)) return '0';

            switch (this.numberFormat) {
                case 'colombian':
                    return num.toLocaleString('es-CO', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                case 'abbreviated':
                    return this.abbreviateNumber(num);
                case 'international':
                default:
                    return num.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
            }
        }

        abbreviateNumber(num) {
            const abs = Math.abs(num);
            if (abs >= 1e12) return (num / 1e12).toFixed(1) + 'B';
            if (abs >= 1e9) return (num / 1e9).toFixed(1) + 'MM';
            if (abs >= 1e6) return (num / 1e6).toFixed(1) + 'M';
            if (abs >= 1e3) return (num / 1e3).toFixed(1) + 'K';
            return num.toFixed(2);
        }

        showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'sisman-toast success';
            toast.textContent = message;
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
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
       Initialize all charts on page
       ======================================== */
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.sisman-chart-wrapper').forEach((wrapper) => {
            new SismanChart(wrapper);
        });
    });
})();
