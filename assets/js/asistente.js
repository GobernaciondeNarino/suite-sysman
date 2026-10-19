/**
 * Asistente de voz (ElevenLabs) con agente privado.
 *
 * La clave de ElevenLabs nunca llega al navegador: el servidor pide una URL
 * firmada de corta duración y aquí se entrega al widget antes de cargarlo.
 */
(function () {
    'use strict';

    var CFG = window.sysmanAsistente || {};
    var cajas = document.querySelectorAll('[data-sysman-asistente-privado]');
    if (!cajas.length || !CFG.sesionUrl) { return; }

    function cargarWidget() {
        if (document.querySelector('script[data-sysman-convai]')) { return; }
        var s = document.createElement('script');
        s.src = CFG.widgetJs;
        s.async = true;
        s.type = 'text/javascript';
        s.setAttribute('data-sysman-convai', '1');
        document.body.appendChild(s);
    }

    fetch(CFG.sesionUrl, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
        .then(function (data) {
            if (!data || !data.signed_url) { throw new Error('sin URL firmada'); }
            cajas.forEach(function (caja) {
                var w = caja.querySelector('elevenlabs-convai');
                if (w) { w.setAttribute('signed-url', data.signed_url); }
            });
            cargarWidget();
        })
        .catch(function () {
            cajas.forEach(function (caja) {
                var p = document.createElement('p');
                p.className = 'sysman-asistente__error';
                p.textContent = 'El asistente de voz no está disponible en este momento.';
                caja.insertBefore(p, caja.firstChild);
            });
        });
})();
