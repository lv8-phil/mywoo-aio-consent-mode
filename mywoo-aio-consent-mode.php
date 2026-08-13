<?php
/**
 * Plugin Name: MyWooAIO - Google Consent Mode v2
 * Plugin URI: https://mywooaio.gr
 * Description: Advanced Cookie Consent Banner fully compliant with Google Consent Mode v2. Includes granular controls and default 'denied' signals to Google tags.
 * Version: 5.0.0
 * Update URI: http://89.167.78.50/plugin-updates/mywoo-aio-consent-mode-info.json
 * Author: MyWooAIO
 * Author URI: https://mywooaio.gr
 * License: GPLv2 or later
 * Text Domain: mywoo-aio-consent-mode
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MYWOO_CONSENT_VERSION', '5.0.0' );
define( 'MYWOO_CONSENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MYWOO_CONSENT_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'mywoo_consent_init', 11 );

function mywoo_consent_is_license_valid() {
    $key = get_option( 'mywoo_aio_license_key', '' );
    $parts = explode( '-', strtoupper( $key ) );

    if ( count( $parts ) < 5 || $parts[0] !== 'MW' || $parts[1] !== 'AIO' ) {
        return false;
    }

    $scope = array_slice( $parts, 4 );
    return in_array( 'ALL', $scope, true ) || in_array( 'CON', $scope, true );
}

function mywoo_consent_init() {
    if ( ! mywoo_consent_is_license_valid() ) {
        add_action( 'admin_notices', 'mywoo_consent_license_notice' );
        return;
    }

    // Initialization logic
    add_action('wp_head', 'mywoo_consent_inject_default', 1); // VERY HIGH PRIORITY
    add_action('wp_footer', 'mywoo_consent_inject_banner', 999);
    add_action('wp_enqueue_scripts', 'mywoo_consent_enqueue_assets');
}

function mywoo_consent_license_notice() {
    echo '<div class="notice notice-error is-dismissible"><p><strong>Αδέσμευτο Πρόσθετο:</strong> Δεν εντοπίστηκε ενεργό MyWooAIO Suite License. Παρακαλώ εγκαταστήστε τον διαχειριστή MyWooAIO Manager και εισάγετε το License Key σας για να δοκιμάσετε το Cookie Consent v2.</p></div>';
}

/**
 * Settings Integration
 */
add_action( 'plugins_loaded', 'mywoo_consent_register_wc_settings' );
function mywoo_consent_register_wc_settings() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    add_filter( 'woocommerce_get_sections_advanced', 'mywoo_consent_add_section' );
    add_filter( 'woocommerce_get_settings_advanced', 'mywoo_consent_all_settings', 10, 2 );
}

function mywoo_consent_add_section( $sections ) {
    $sections['mywoo_consent'] = __( 'Consent Mode v2', 'mywoo-aio-consent-mode' );
    return $sections;
}

function mywoo_consent_all_settings( $settings, $current_section ) {
    if ( $current_section === 'mywoo_consent' ) {
        $settings = array();

        $settings[] = array(
            'name' => __( 'Google Advanced Consent Mode v2', 'mywoo-aio-consent-mode' ),
            'type' => 'title',
            'desc' => __( 'Configure your cookie banner text and colors. The plugin automatically emits the proper Google gtag dataLayer default and update signals.', 'mywoo-aio-consent-mode' ),
            'id'   => 'mywoo_consent_title'
        );

        $settings[] = array(
            'name'     => __( 'Banner Text', 'mywoo-aio-consent-mode' ),
            'id'       => 'mywoo_consent_text',
            'type'     => 'textarea',
            'css'      => 'min-width:400px; height: 100px;',
            'default'  => 'Χρησιμοποιούμε cookies για να βελτιώσουμε την εμπειρία σας, να εξατομικεύσουμε περιεχόμενο και να αναλύσουμε την επισκεψιμότητά μας.',
            'desc'     => 'The main text displayed on the cookie banner.'
        );

        $settings[] = array(
            'name'     => __( 'Privacy Policy URL', 'mywoo-aio-consent-mode' ),
            'id'       => 'mywoo_consent_policy_url',
            'type'     => 'text',
            'css'      => 'min-width:300px;',
            'default'  => '/privacy-policy/',
        );

        $settings[] = array(
            'name'     => __( 'Primary Brand Color', 'mywoo-aio-consent-mode' ),
            'id'       => 'mywoo_consent_primary_color',
            'type'     => 'color',
            'css'      => 'width:6em;',
            'default'  => '#0055FF',
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'mywoo_consent_title'
        );
    }
    return $settings;
}

/**
 * Version tying consent to the current banner text/policy, so edits to either
 * invalidate previously stored consent and force a re-prompt.
 */
function mywoo_consent_purpose_version() {
    return substr( md5( get_option( 'mywoo_consent_text', '' ) . '|' . get_option( 'mywoo_consent_policy_url', '' ) ), 0, 8 );
}

/**
 * Enqueue scripts and styles
 */
function mywoo_consent_enqueue_assets() {
    wp_enqueue_style('mywoo-consent-css', MYWOO_CONSENT_URL . 'assets/css/consent.css', array(), MYWOO_CONSENT_VERSION);
    wp_enqueue_script('mywoo-consent-js', MYWOO_CONSENT_URL . 'assets/js/consent.js', array(), MYWOO_CONSENT_VERSION, true);

    // Pass PHP settings to JS
    wp_localize_script('mywoo-consent-js', 'mywooConsentSettings', array(
        'primaryColor'    => get_option('mywoo_consent_primary_color', '#0055FF'),
        'purposeVersion'  => mywoo_consent_purpose_version(),
        'settingsLabel'   => __( 'Ρυθμίσεις', 'mywoo-aio-consent-mode' ),
        'saveOptionsLabel'=> __( 'Αποθήκευση Επιλογών', 'mywoo-aio-consent-mode' ),
    ));
}

/**
 * Inject the Consent Mode default script before Google Analytics tags
 */
function mywoo_consent_inject_default() {
    $purpose_version = mywoo_consent_purpose_version();
    ?>
    <!-- MyWooAIO - Google Consent Mode v2 (Default) -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        var mywooPurposeVersion = <?php echo wp_json_encode( $purpose_version ); ?>;

        var mywooCData = null;
        try {
            var cMatch = document.cookie.match(new RegExp('(^| )mywooaio_consent_v2=([^;]+)'));
            if (cMatch) {
                var parsed = JSON.parse(decodeURIComponent(cMatch[2]));
                if (parsed && parsed.version === mywooPurposeVersion) {
                    mywooCData = parsed;
                }
            }
        } catch(e) {}

        if (!mywooCData) {
            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'analytics_storage': 'denied',
                'wait_for_update': 500
            });
            gtag('set', 'url_passthrough', true);
        } else {
            gtag('consent', 'default', {
                'ad_storage': mywooCData.ads ? 'granted' : 'denied',
                'ad_user_data': mywooCData.ads ? 'granted' : 'denied',
                'ad_personalization': mywooCData.ads ? 'granted' : 'denied',
                'analytics_storage': mywooCData.analytics ? 'granted' : 'denied',
                'wait_for_update': 500
            });
        }
    </script>
    <!-- End Consent Mode v2 -->
    <?php
}

/**
 * Inject Banner HTML into footer
 */
function mywoo_consent_inject_banner() {
    $text = get_option('mywoo_consent_text', 'Χρησιμοποιούμε cookies για να βελτιώσουμε την εμπειρία σας, να εξατομικεύσουμε περιεχόμενο και να αναλύσουμε την επισκεψιμότητά μας.');
    $policy = get_option('mywoo_consent_policy_url', '/privacy-policy/');
    ?>
    <div id="mywoo-consent-banner" class="mywoo-consent-hidden" role="dialog" aria-modal="true" aria-labelledby="mywoo-consent-title" tabindex="-1">
        <div class="mywoo-consent-content">
            <h3 id="mywoo-consent-title"><?php esc_html_e( 'Ρυθμίσεις Απορρήτου & Cookies', 'mywoo-aio-consent-mode' ); ?></h3>
            <p><?php echo wp_kses_post($text); ?> <a href="<?php echo esc_url($policy); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Πολιτική Απορρήτου', 'mywoo-aio-consent-mode' ); ?></a>.</p>

            <div id="mywoo-consent-granular" style="display: none;">
                <!-- Granular Options -->
                <div class="mywoo-consent-option">
                    <div class="mywoo-consent-option-text">
                        <strong><?php esc_html_e( 'Απαραίτητα (Λειτουργικά)', 'mywoo-aio-consent-mode' ); ?></strong>
                        <span><?php esc_html_e( 'Απαραίτητα για την σωστή λειτουργία του e-shop.', 'mywoo-aio-consent-mode' ); ?></span>
                    </div>
                    <label class="mywoo-consent-switch">
                        <input type="checkbox" checked disabled>
                        <span class="mywoo-consent-slider mywoo-consent-round"></span>
                    </label>
                </div>
                <div class="mywoo-consent-option">
                    <div class="mywoo-consent-option-text">
                        <strong><?php esc_html_e( 'Αναλυτικά (Analytics)', 'mywoo-aio-consent-mode' ); ?></strong>
                        <span><?php esc_html_e( 'Μας βοηθούν να βελτιώσουμε τις υπηρεσίες μας (π.χ. Google Analytics).', 'mywoo-aio-consent-mode' ); ?></span>
                    </div>
                    <label class="mywoo-consent-switch">
                        <input type="checkbox" id="cb-analytics">
                        <span class="mywoo-consent-slider mywoo-consent-round"></span>
                    </label>
                </div>
                <div class="mywoo-consent-option">
                    <div class="mywoo-consent-option-text">
                        <strong><?php esc_html_e( 'Διαφήμιση (Ads)', 'mywoo-aio-consent-mode' ); ?></strong>
                        <span><?php esc_html_e( 'Επιτρέπουν την προβολή προσωποποιημένων διαφημίσεων.', 'mywoo-aio-consent-mode' ); ?></span>
                    </div>
                    <label class="mywoo-consent-switch">
                        <input type="checkbox" id="cb-ads">
                        <span class="mywoo-consent-slider mywoo-consent-round"></span>
                    </label>
                </div>
            </div>

            <div class="mywoo-consent-actions">
                <button id="mywoo-consent-settings-btn" class="mywoo-consent-btn mywoo-consent-btn-secondary" aria-expanded="false" aria-controls="mywoo-consent-granular"><?php esc_html_e( 'Ρυθμίσεις', 'mywoo-aio-consent-mode' ); ?></button>
                <div class="mywoo-consent-main-actions">
                    <button id="mywoo-consent-reject-btn" class="mywoo-consent-btn mywoo-consent-btn-secondary"><?php esc_html_e( 'Απόρριψη', 'mywoo-aio-consent-mode' ); ?></button>
                    <button id="mywoo-consent-accept-btn" class="mywoo-consent-btn mywoo-consent-btn-primary"><?php esc_html_e( 'Αποδοχή', 'mywoo-aio-consent-mode' ); ?></button>
                </div>
            </div>
            
        </div>
    </div>
    <?php
}
