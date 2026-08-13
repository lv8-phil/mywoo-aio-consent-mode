document.addEventListener('DOMContentLoaded', function() {
    var banner = document.getElementById('mywoo-consent-banner');
    if (!banner) return;

    var settings = (typeof mywooConsentSettings !== 'undefined') ? mywooConsentSettings : {};

    // Apply primary color from PHP
    if (settings.primaryColor) {
        banner.style.setProperty('--mywoo-primary-color', settings.primaryColor);
    }

    var btnSettings = document.getElementById('mywoo-consent-settings-btn');
    var btnReject = document.getElementById('mywoo-consent-reject-btn');
    var btnAccept = document.getElementById('mywoo-consent-accept-btn');
    var granularDiv = document.getElementById('mywoo-consent-granular');

    var cbAnalytics = document.getElementById('cb-analytics');
    var cbAds = document.getElementById('cb-ads');

    var lastFocusedElement = null;

    function getFocusableElements() {
        return Array.prototype.slice.call(
            banner.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])')
        ).filter(function(el) { return !el.disabled && el.offsetParent !== null; });
    }

    function trapFocus(e) {
        if (e.key === 'Escape') {
            saveConsentAndApply(false, false);
            return;
        }
        if (e.key !== 'Tab') return;

        var focusable = getFocusableElements();
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function hideBanner() {
        banner.classList.add('mywoo-consent-hidden');
        document.removeEventListener('keydown', trapFocus);
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }

    function showBanner() {
        lastFocusedElement = document.activeElement;
        banner.classList.remove('mywoo-consent-hidden');
        document.addEventListener('keydown', trapFocus);
        banner.focus();
    }

    function readConsentCookie() {
        var match = document.cookie.match(/(?:^|; )mywooaio_consent_v2=([^;]+)/);
        if (!match) return null;
        try {
            return JSON.parse(decodeURIComponent(match[1]));
        } catch (e) {
            return null;
        }
    }

    function saveConsentAndApply(analytics, ads) {
        var state = {
            analytics: analytics,
            ads: ads,
            version: settings.purposeVersion || '',
            timestamp: new Date().getTime()
        };

        // Save cookie for 365 days
        var d = new Date();
        d.setTime(d.getTime() + (365*24*60*60*1000));
        var secure = (location.protocol === 'https:') ? ';Secure' : '';
        document.cookie = "mywooaio_consent_v2=" + encodeURIComponent(JSON.stringify(state)) + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax" + secure;

        // Update Google Consent Mode v2
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                'ad_storage': ads ? 'granted' : 'denied',
                'ad_user_data': ads ? 'granted' : 'denied',
                'ad_personalization': ads ? 'granted' : 'denied',
                'analytics_storage': analytics ? 'granted' : 'denied'
            });
        }

        hideBanner();
    }

    btnSettings.addEventListener('click', function() {
        if (granularDiv.style.display === 'none') {
            granularDiv.style.display = 'block';
            btnSettings.setAttribute('aria-expanded', 'true');
            if (settings.saveOptionsLabel) btnSettings.innerText = settings.saveOptionsLabel;
        } else {
            saveConsentAndApply(cbAnalytics.checked, cbAds.checked);
        }
    });

    btnReject.addEventListener('click', function() {
        saveConsentAndApply(false, false);
    });

    btnAccept.addEventListener('click', function() {
        saveConsentAndApply(true, true);
    });

    // Show the banner if there's no cookie, or the stored consent predates the
    // current banner text/purposes (version mismatch forces re-consent).
    var stored = readConsentCookie();
    if (!stored || stored.version !== (settings.purposeVersion || '')) {
        setTimeout(showBanner, 500);
    }
});
