/**
 * Backend module: live Booking Engine URL preview and custom-domain field state.
 */
(function () {
    'use strict';

    var DEFAULT_IBE_BASE = 'https://bookingengine.casablanca.at';

    var previewIds = [
        'cb-tenant-id',
        'cb-ibe-base-url',
        'cb-ibe-context',
        'cb-default-culture',
        'cb-ibe-link-style'
    ];

    var patternLabels = {
        full_path: 'Pattern: {culture}/{tenant}/{space}',
        culture_space: 'Pattern: {culture}/{space} — tenant injected by proxy',
        culture_only: 'Pattern: {culture} — tenant and space injected by proxy'
    };

    function normalizeLinkStyle(style) {
        style = (style || 'full_path').toLowerCase();
        if (style === 'tenant_only') {
            return 'culture_space';
        }
        if (style === 'culture_space' || style === 'culture_only') {
            return style;
        }
        return 'full_path';
    }

    function getCustomDomainCheckbox() {
        return document.getElementById('cb-use-custom-ibe');
    }

    function getBaseUrlField() {
        return document.getElementById('cb-ibe-base-url');
    }

    function syncCustomDomainFieldState() {
        var checkbox = getCustomDomainCheckbox();
        var baseEl = getBaseUrlField();
        if (!baseEl) {
            return;
        }

        var defaultBase = baseEl.getAttribute('data-default-base') || DEFAULT_IBE_BASE;
        var useCustom = checkbox ? checkbox.checked : false;

        if (useCustom) {
            baseEl.removeAttribute('readonly');
            baseEl.classList.remove('cb-backend__field--readonly');
        } else {
            baseEl.value = defaultBase;
            baseEl.setAttribute('readonly', 'readonly');
            baseEl.classList.add('cb-backend__field--readonly');
        }
    }

    function buildPreviewSegments(base, culture, tenant, space, style) {
        var segments = [base, encodeURIComponent(culture)];

        if (style === 'culture_only') {
            return segments;
        }

        if (style === 'culture_space') {
            segments.push(encodeURIComponent(space || 'bookingengine'));
            return segments;
        }

        segments.push(encodeURIComponent(tenant || 'tenant-id'));
        segments.push(encodeURIComponent(space || 'bookingengine'));
        return segments;
    }

    function updateBookingEngineUrlPreview() {
        var preview = document.getElementById('cb-ibe-url-preview');
        var patternEl = document.getElementById('cb-ibe-url-pattern');
        if (!preview) {
            return;
        }

        var baseEl = getBaseUrlField();
        var tenantEl = document.getElementById('cb-tenant-id');
        var spaceEl = document.getElementById('cb-ibe-context');
        var cultureEl = document.getElementById('cb-default-culture');
        var styleEl = document.getElementById('cb-ibe-link-style');

        var base = baseEl ? baseEl.value.replace(/\/+$/, '') : '';
        var tenant = tenantEl ? tenantEl.value : '';
        var space = spaceEl ? spaceEl.value : 'bookingengine';
        var culture = cultureEl && cultureEl.value ? cultureEl.value : 'de';
        var style = normalizeLinkStyle(styleEl ? styleEl.value : 'full_path');

        if (!base) {
            preview.textContent = preview.getAttribute('data-placeholder') || '';
            preview.classList.add('cb-backend__url-preview--empty');
            if (patternEl) {
                patternEl.textContent = '';
            }
            return;
        }

        preview.classList.remove('cb-backend__url-preview--empty');

        var query = 'arrivalDate=2026-06-01&departureDate=2026-06-08&rooms_0__adults=2';
        var segments = buildPreviewSegments(base, culture, tenant, space, style);
        preview.textContent = segments.join('/') + '?' + query;

        if (patternEl) {
            patternEl.textContent = patternLabels[style] || patternLabels.full_path;
        }
    }

    previewIds.forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.addEventListener('input', updateBookingEngineUrlPreview);
        el.addEventListener('change', updateBookingEngineUrlPreview);
    });

    var customCheckbox = getCustomDomainCheckbox();
    if (customCheckbox) {
        customCheckbox.addEventListener('change', function () {
            syncCustomDomainFieldState();
            updateBookingEngineUrlPreview();
        });
    }

    syncCustomDomainFieldState();
    updateBookingEngineUrlPreview();
})();
