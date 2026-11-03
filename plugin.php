<?php
/*
Plugin Name: Cloudron OIDC
Plugin URI: https://github.com/cloudron-io/yourls-plugin-oidc
Description: Log in with the Cloudron OpenID Connect provider
Version: 1.0.0
Author: Cloudron
*/

if( !defined( 'YOURLS_ABSPATH' ) ) die();

require_once __DIR__ . '/vendor/autoload.php';

// unusable phpass entry: password_verify() always fails on it, but cookie and API signature checks only need the username
define( 'OIDC_NO_PASSWORD', 'phpass:!oidc-no-password' );

function oidc_enabled() {
    return (bool) getenv( 'CLOUDRON_OIDC_ISSUER' );
}

function oidc_client() {
    $oidc = new Jumbojett\OpenIDConnectClient(
        getenv( 'CLOUDRON_OIDC_ISSUER' ),
        getenv( 'CLOUDRON_OIDC_CLIENT_ID' ),
        getenv( 'CLOUDRON_OIDC_CLIENT_SECRET' )
    );
    $oidc->setRedirectURL( yourls_admin_url( '?oidc=callback' ) );
    $oidc->addScope( [ 'openid', 'profile', 'email' ] );
    return $oidc;
}

// YOURLS matches cookies and API signatures against the keys of $yourls_user_passwords
yourls_add_action( 'plugins_loaded', 'oidc_merge_users' );
function oidc_merge_users() {
    global $yourls_user_passwords;

    foreach( (array) yourls_get_option( 'oidc_users', [] ) as $user ) {
        if( !isset( $yourls_user_passwords[ $user ] ) ) {
            $yourls_user_passwords[ $user ] = OIDC_NO_PASSWORD;
        }
    }
}

yourls_add_action( 'login_form_end', 'oidc_login_button' );
function oidc_login_button() {
    if( !oidc_enabled() ) return;

    $name = getenv( 'CLOUDRON_OIDC_PROVIDER_NAME' ) ?: 'Cloudron';
    $url = yourls_admin_url( '?oidc=login' );
    echo '<p style="text-align: center;"><a id="oidc-login" class="button" href="' . yourls_esc_url( $url ) . '">'
        . yourls_esc_html( yourls_s( 'Log in with %s', $name ) ) . '</a></p>';
}

yourls_add_filter( 'is_valid_user', 'oidc_is_valid_user' );
function oidc_is_valid_user( $valid ) {
    global $yourls_user_passwords;

    if( $valid || yourls_is_API() || !oidc_enabled() ) return $valid;
    if( !isset( $_GET['oidc'] ) || !in_array( $_GET['oidc'], [ 'login', 'callback' ], true ) ) return $valid;

    try {
        $oidc = oidc_client();
        $oidc->authenticate();
        $user = $oidc->requestUserInfo( 'preferred_username' );
    } catch( Exception $e ) {
        error_log( 'OIDC login failed: ' . $e->getMessage() );
        return $valid;
    }

    if( !$user ) {
        error_log( 'OIDC login failed: no preferred_username claim' );
        return $valid;
    }

    $users = (array) yourls_get_option( 'oidc_users', [] );
    if( !in_array( $user, $users, true ) ) {
        $users[] = $user;
        yourls_update_option( 'oidc_users', $users );
    }

    if( !isset( $yourls_user_passwords[ $user ] ) ) {
        $yourls_user_passwords[ $user ] = OIDC_NO_PASSWORD;
    }

    yourls_set_user( $user );
    return true;
}

// core stores the auth cookie before this fires. drop code/state from the address bar
yourls_add_action( 'auth_successful', 'oidc_clean_redirect' );
function oidc_clean_redirect() {
    if( isset( $_GET['oidc'] ) && !yourls_is_API() ) {
        yourls_redirect( yourls_admin_url(), 302 );
        die();
    }
}
