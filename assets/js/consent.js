document.addEventListener('DOMContentLoaded', function() {
    var banner = document.getElementById('mywoo-consent-banner');
    if (!banner) return;

    // Apply primary color from PHP
    if (typeof mywooConsentSettings !== 'undefined' && mywooConsentSettings.primaryColor) {
        banner.style.setProperty('--mywoo-primary-color', mywooConsentSettings.primaryColor);
    }

    var btnSettings = document.getElementById('mywoo-consent-settings-btn');
    var btnReject = document.getElementById('mywoo-consent-reject-btn');
    var btnAccept = document.getElementById('mywoo-consent-accept-btn');
    var granularDiv = document.getElementById('mywoo-consent-granular');
    
    var cbAnalytics = document.getElementById('cb-analytics');
    var cbAds = document.getElementById('cb-ads');

    function hideBanner() {
        banner.classList.add('mywoo-consent-hidden');
    }

    function showBanner() {
        banner.classList.remove('mywoo-consent-hidden');
    }

    function saveConsentAndApply(analytics, ads) {
        var state = {
            analytics: analytics,
            ads: ads,
            timestamp: new Date().getTime()
        };
        
        // Save cookie for 365 days
        var d = new Date();
        d.setTime(d.getTime() + (365*24*60*60*1000));
        document.cookie = "mywooaio_consent_v2=" + encodeURIComponent(JSON.stringify(state)) + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax";
        
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
            btnSettings.innerText = 'Αποθήκευση Επιλογών';
            
            // Adjust height transition if needed
        } else {
            // Save selected Options
            saveConsentAndApply(cbAnalytics.checked, cbAds.checked);
        }
    });

    btnReject.addEventListener('click', function() {
        saveConsentAndApply(false, false);
    });

    btnAccept.addEventListener('click', function() {
        saveConsentAndApply(true, true);
    });

    // Check if cookie exists to decide if we show banner
    if (document.cookie.indexOf('mywooaio_consent_v2=') === -1) {
        // Show after a tiny delay for smooth animation
        setTimeout(showBanner, 500);
    }
});
