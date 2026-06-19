(function() {
    'use strict';
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.gn-da-card__copy');
        if (!btn) return;
        var url = btn.dataset.copy;
        if (url && navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                var orig = btn.textContent;
                btn.textContent = '✓';
                setTimeout(function() { btn.textContent = orig; }, 1500);
            });
        }
    });
})();
