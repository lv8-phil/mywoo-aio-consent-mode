<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'mywoo_consent_text' );
delete_option( 'mywoo_consent_policy_url' );
delete_option( 'mywoo_consent_primary_color' );
