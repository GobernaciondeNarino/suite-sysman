(function(){
    'use strict';

    var nonce = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '';
    var restBase = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
        ? wpApiSettings.root
        : (typeof gnEjecFront !== 'undefined' ? gnEjecFront.restUrl : '/wp-json/');

    var COP = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

    function api(path, params) {
        var url = new URL(restBase + 'gn-sisman/v1' + path, window.location.origin);
        if (params) {
            Object.entries(params).forEach(function(entry) {
                url.searchParams.set(entry[0], entry[1]);
            });
        }
        var headers = {};
        if (nonce) { headers['X-WP-Nonce'] = nonce; }
        return fetch(url, { headers: headers, credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) throw new Error(r.status);
                return r.json();
            });
    }

    function initInstance(root) {
        var postId = root.dataset.postId;

        root.addEventListener('click', function(e) {
            var bpidItem = e.target.closest('.gn-ejec__bpid-item');
            if (bpidItem && root.contains(bpidItem)) {
                selectBpid(root, bpidItem);
                return;
            }

            var rubroBtn = e.target.closest('.gn-ejec__rubro-toggle');
            if (rubroBtn && root.contains(rubroBtn)) {
                toggleRubro(rubroBtn.parentElement, postId);
                return;
            }

            var disBtn = e.target.closest('.gn-ejec__dis-toggle');
            if (disBtn && root.contains(disBtn)) {
                toggleDis(disBtn.parentElement, postId);
                return;
            }
        });
    }

    function selectBpid(root, item) {
        var list = root.querySelector('.gn-ejec__bpid-list');
        if (!list) return;

        list.querySelectorAll('.gn-ejec__bpid-item').forEach(function(el) {
            el.classList.remove('active');
        });
        item.classList.add('active');

        var bpid = item.dataset.bpid;
        root.querySelectorAll('.gn-ejec__bpid-group').forEach(function(g) {
            g.classList.toggle('active', g.dataset.bpidGroup === bpid);
        });
    }

    function toggleRubro(li, postId) {
        var expanded = li.getAttribute('aria-expanded') === 'true';
        li.setAttribute('aria-expanded', String(!expanded));
        var body = li.querySelector('.gn-ejec__rubro-body');
        body.hidden = expanded;
        if (expanded || li.dataset.loaded) return;

        body.innerHTML = '<p class="gn-ejec__loading">Cargando ejecución...</p>';
        var codigo = li.dataset.codigo;
        var codigobpin = li.dataset.codigobpin || '';

        var calls = [
            api('/ejecucion/' + postId + '/consolidado', { codigo: codigo }),
            api('/ejecucion/' + postId + '/dis', { codigocuenta: codigo })
        ];

        if (codigobpin) {
            calls.push(api('/ejecucion/' + postId + '/proyecto', { codigobpin: codigobpin }));
        }

        Promise.all(calls).then(function(results) {
            var html = '';
            if (results[2]) {
                html += renderProyecto(results[2], codigobpin);
            }
            html += renderConsolidado(results[0]);
            html += renderDisList(results[1], codigo);
            body.innerHTML = html;
            li.dataset.loaded = '1';
        }).catch(function() {
            body.innerHTML = '<p class="gn-ejec__error">Error al cargar datos.</p>';
        });
    }

    function toggleDis(li, postId) {
        var expanded = li.getAttribute('aria-expanded') === 'true';
        li.setAttribute('aria-expanded', String(!expanded));
        var body = li.querySelector('.gn-ejec__dis-body');
        body.hidden = expanded;
        if (expanded || li.dataset.loaded) return;

        body.innerHTML = '<p class="gn-ejec__loading">Cargando registros de compromiso...</p>';
        var numeroDis = li.dataset.numero;
        var rubro = li.dataset.rubro || '';

        api('/ejecucion/' + postId + '/res', { numero_dis: numeroDis, rubro: rubro })
            .then(function(data) {
                body.innerHTML = renderResList(data);
                li.dataset.loaded = '1';
            }).catch(function() {
                body.innerHTML = '<p class="gn-ejec__error">Error al cargar registros de compromiso.</p>';
            });
    }

    function renderProyecto(data, bpin) {
        if (!data || !data.nombre_proyecto) {
            return '';
        }

        var html = '<div class="gn-ejec__proyecto">';
        html += '<h4 class="gn-ejec__subtitle gn-ejec__subtitle--proyecto">Proyecto BPIN ' + esc(bpin) + '</h4>';
        html += '<div class="gn-ejec__proyecto-body">';
        html += '<div class="gn-ejec__proyecto-field"><strong>Nombre del Proyecto:</strong> ' + esc(data.nombre_proyecto) + '</div>';
        if (data.metas) {
            html += '<div class="gn-ejec__proyecto-field"><strong>Metas:</strong>';
            html += renderCommaList(data.metas);
            html += '</div>';
        }
        if (data.odss) {
            html += '<div class="gn-ejec__proyecto-field"><strong>ODS:</strong>';
            html += renderCommaList(data.odss);
            html += '</div>';
        }
        html += '</div></div>';
        return html;
    }

    function renderCommaList(str) {
        var items = str.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        if (items.length <= 1) {
            return ' ' + esc(str);
        }
        var html = '<ul class="gn-ejec__meta-list">';
        items.forEach(function(item) {
            html += '<li>' + esc(item) + '</li>';
        });
        html += '</ul>';
        return html;
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

    function renderDisList(data, rubroCodigo) {
        if (!data || data.length === 0) {
            return '<p class="gn-ejec__no-data">Sin disponibilidades para este rubro.</p>';
        }

        var html = '<h4 class="gn-ejec__subtitle">Disponibilidades (DIS) &mdash; ' + data.length + ' registros</h4>';
        html += '<ul class="gn-ejec__dis-list">';

        data.forEach(function(dis) {
            var valor = COP.format(parseFloat(dis.valordebito) || 0);
            var saldo = COP.format(parseFloat(dis.saldoporejecutaresp) || 0);
            var docHtml = renderDocLink(dis.nrodocumento, dis.contract_url);
            html += '<li class="gn-ejec__dis" data-numero="' + esc(dis.numero) + '" data-rubro="' + esc(rubroCodigo) + '" aria-expanded="false">';
            html += '<button type="button" class="gn-ejec__dis-toggle">';
            html += '<span class="gn-ejec__dis-arrow">&#9654;</span>';
            html += '<span class="gn-ejec__dis-numero">DIS ' + esc(dis.numero) + '</span>';
            html += '<span class="gn-ejec__dis-tercero">' + esc(dis.nombretercero) + '</span>';
            html += '<span class="gn-ejec__dis-valor">' + valor + '</span>';
            html += '<span class="gn-ejec__dis-saldo">Saldo: ' + saldo + '</span>';
            if (docHtml) {
                html += '<span class="gn-ejec__dis-doc">' + docHtml + '</span>';
            }
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
            return '<p class="gn-ejec__no-data">Sin registros de compromiso asociados a esta disponibilidad.</p>';
        }

        var html = '<h5 class="gn-ejec__subtitle gn-ejec__subtitle--res">Registros de Compromiso (RES) &mdash; ' + data.length + ' registros</h5>';
        html += '<div class="gn-ejec__table-wrap"><table class="gn-ejec__table gn-ejec__table--res">';
        html += '<thead><tr><th>RES #</th><th>Tercero</th><th>Descripción</th><th>Doc.</th><th>Valor</th><th>Saldo Ejecutar</th><th>Fecha</th></tr></thead>';
        html += '<tbody>';

        data.forEach(function(res) {
            var docHtml = renderDocLink(res.nrodocumento, res.contract_url);
            html += '<tr>';
            html += '<td>' + esc(res.numero) + '</td>';
            html += '<td>' + esc(res.nombretercero) + '</td>';
            html += '<td>' + esc(res.descripcion) + '</td>';
            html += '<td>' + (docHtml || esc(res.nrodocumento)) + '</td>';
            html += '<td class="num">' + COP.format(parseFloat(res.valordebito) || 0) + '</td>';
            html += '<td class="num">' + COP.format(parseFloat(res.saldoporejecutaresp) || 0) + '</td>';
            html += '<td>' + esc(res.fecha) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    function renderDocLink(nrodocumento, contractUrl) {
        if (!nrodocumento) return '';
        if (contractUrl) {
            return '<a href="' + esc(contractUrl) + '" target="_blank" rel="noopener" class="gn-ejec__contract-link" title="Ver contrato SECOP">' + esc(nrodocumento) + '</a>';
        }
        return esc(nrodocumento);
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    document.querySelectorAll('.gn-ejec').forEach(function(root) {
        initInstance(root);
    });

})();
