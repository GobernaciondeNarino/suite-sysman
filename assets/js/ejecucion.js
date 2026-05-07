(function(){
    'use strict';

    const root = document.querySelector('.gn-ejec');
    if (!root) return;

    const postId = root.dataset.postId;
    const nonce  = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '';
    const restBase = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.root : '/wp-json/';

    const COP = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

    function api(path, params) {
        const url = new URL(restBase + 'gn-sisman/v1' + path, window.location.origin);
        if (params) {
            Object.entries(params).forEach(function(entry) {
                url.searchParams.set(entry[0], entry[1]);
            });
        }
        return fetch(url, { headers: { 'X-WP-Nonce': nonce }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); });
    }

    root.addEventListener('click', function(e) {
        var rubroBtn = e.target.closest('.gn-ejec__rubro-toggle');
        if (rubroBtn) {
            toggleRubro(rubroBtn.parentElement);
            return;
        }

        var disBtn = e.target.closest('.gn-ejec__dis-toggle');
        if (disBtn) {
            toggleDis(disBtn.parentElement);
            return;
        }
    });

    function toggleRubro(li) {
        var expanded = li.getAttribute('aria-expanded') === 'true';
        li.setAttribute('aria-expanded', String(!expanded));
        var body = li.querySelector('.gn-ejec__rubro-body');
        body.hidden = expanded;
        if (expanded || li.dataset.loaded) return;

        body.innerHTML = '<p class="gn-ejec__loading">Cargando ejecución...</p>';
        var codigo = li.dataset.codigo;

        Promise.all([
            api('/ejecucion/' + postId + '/consolidado', { codigo: codigo }),
            api('/ejecucion/' + postId + '/dis', { codigocuenta: codigo })
        ]).then(function(results) {
            body.innerHTML = renderConsolidado(results[0]) + renderDisList(results[1]);
            li.dataset.loaded = '1';
        }).catch(function() {
            body.innerHTML = '<p class="gn-ejec__error">Error al cargar datos.</p>';
        });
    }

    function toggleDis(li) {
        var expanded = li.getAttribute('aria-expanded') === 'true';
        li.setAttribute('aria-expanded', String(!expanded));
        var body = li.querySelector('.gn-ejec__dis-body');
        body.hidden = expanded;
        if (expanded || li.dataset.loaded) return;

        body.innerHTML = '<p class="gn-ejec__loading">Cargando reservas...</p>';
        var numeroDis = li.dataset.numero;

        api('/ejecucion/' + postId + '/res', { numero_dis: numeroDis })
            .then(function(data) {
                body.innerHTML = renderResList(data);
                li.dataset.loaded = '1';
            }).catch(function() {
                body.innerHTML = '<p class="gn-ejec__error">Error al cargar reservas.</p>';
            });
    }

    function renderConsolidado(data) {
        if (!data || Object.keys(data).length === 0) {
            return '<p class="gn-ejec__no-data">Sin datos de ejecución consolidada para este rubro.</p>';
        }

        var labels = {
            apropiacioninicial:     'Aprob. Inicial',
            adicion:                'Adición',
            reduccion:              'Reducción',
            credito:                'Crédito',
            contracredito:          'Contracrédito',
            aplazamiento:           'Aplazamiento',
            desplazaminento:        'Desplazamiento',
            apropiacionvigente:     'Aprob. Vigente',
            disponibilidades:       'Disponibilidades',
            saldodisponible:        'Saldo Disponible',
            compromisos:            'Compromisos',
            disponibilidadesabiertas: 'Disp. Abiertas',
            obligacion:             'Obligación',
            pagos:                  'Pagos',
            obligacionesporpagar:   'Oblig. por Pagar'
        };

        var html = '<h4 class="gn-ejec__subtitle">Ejecución Consolidada</h4>';
        html += '<div class="gn-ejec__table-wrap"><table class="gn-ejec__table"><thead><tr>';

        var keys = Object.keys(labels);
        keys.forEach(function(k) {
            html += '<th>' + labels[k] + '</th>';
        });
        html += '</tr></thead><tbody><tr>';
        keys.forEach(function(k) {
            var val = parseFloat(data[k]) || 0;
            html += '<td class="num">' + COP.format(val) + '</td>';
        });
        html += '</tr></tbody></table></div>';
        return html;
    }

    function renderDisList(data) {
        if (!data || data.length === 0) {
            return '<p class="gn-ejec__no-data">Sin disponibilidades para este rubro.</p>';
        }

        var html = '<h4 class="gn-ejec__subtitle">Disponibilidades (DIS) &mdash; ' + data.length + ' registros</h4>';
        html += '<ul class="gn-ejec__dis-list">';

        data.forEach(function(dis) {
            var valor = COP.format(parseFloat(dis.valordebito) || 0);
            var saldo = COP.format(parseFloat(dis.saldoporejecutaresp) || 0);
            html += '<li class="gn-ejec__dis" data-numero="' + esc(dis.numero) + '" aria-expanded="false">';
            html += '<button type="button" class="gn-ejec__dis-toggle">';
            html += '<span class="gn-ejec__dis-arrow">&#9654;</span>';
            html += '<span class="gn-ejec__dis-numero">DIS ' + esc(dis.numero) + '</span>';
            html += '<span class="gn-ejec__dis-tercero">' + esc(dis.nombretercero) + '</span>';
            html += '<span class="gn-ejec__dis-valor">' + valor + '</span>';
            html += '<span class="gn-ejec__dis-saldo">Saldo: ' + saldo + '</span>';
            html += '<span class="gn-ejec__dis-fecha">' + esc(dis.fecha) + '</span>';
            html += '</button>';
            html += '<div class="gn-ejec__dis-body" hidden></div>';
            html += '</li>';
        });

        html += '</ul>';
        return html;
    }

    function renderResList(data) {
        if (!data || data.length === 0) {
            return '<p class="gn-ejec__no-data">Sin reservas asociadas a esta disponibilidad.</p>';
        }

        var html = '<h5 class="gn-ejec__subtitle gn-ejec__subtitle--res">Reservas (RES) &mdash; ' + data.length + ' registros</h5>';
        html += '<div class="gn-ejec__table-wrap"><table class="gn-ejec__table gn-ejec__table--res">';
        html += '<thead><tr><th>RES #</th><th>Tercero</th><th>Descripción</th><th>Doc.</th><th>Valor</th><th>Saldo Ejecutar</th><th>Fecha</th></tr></thead>';
        html += '<tbody>';

        data.forEach(function(res) {
            html += '<tr>';
            html += '<td>' + esc(res.numero) + '</td>';
            html += '<td>' + esc(res.nombretercero) + '</td>';
            html += '<td>' + esc(res.descripcion) + '</td>';
            html += '<td>' + esc(res.nrodocumento) + '</td>';
            html += '<td class="num">' + COP.format(parseFloat(res.valordebito) || 0) + '</td>';
            html += '<td class="num">' + COP.format(parseFloat(res.saldoporejecutaresp) || 0) + '</td>';
            html += '<td>' + esc(res.fecha) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

})();
