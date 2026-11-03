<?php
/*
Plugin Name: Cloudron OIDC
Plugin URI: https://github.com/cloudron-io/yourls-plugin-oidc
Description: Log in with the Cloudron OpenID Connect provider
Version: 1.2.0
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

// yourls_do_action() hands its arguments to callbacks as one array - https://github.com/YOURLS/YOURLS/blob/1.10.6/includes/functions-plugins.php#L242
function oidc_is_login_context( $args ) {
    return oidc_enabled() && ( (array) $args )[0] === 'login';
}

yourls_add_action( 'html_head_meta', 'oidc_login_meta' );
function oidc_login_meta( $args ) {
    if( !oidc_is_login_context( $args ) ) return;

    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";
}

// the login screen has no stylesheet hook, and the Cloudron package denies web access to user/, so the CSS is inlined
yourls_add_action( 'html_head', 'oidc_login_style' );
function oidc_login_style( $args ) {
    if( !oidc_is_login_context( $args ) ) return;

    echo '<style>' . file_get_contents( __DIR__ . '/login.css' );
    if( oidc_login_failed_locally() ) {
        echo 'body.login #error-message { color: white; background: var(--oidc-danger); }';
    } elseif( !isset( $_GET['action'] ) || $_GET['action'] !== 'logout' ) {
        // core shows "Please log in" on every visit, which reads like an error
        echo 'body.login #error-message { display: none; }';
    }
    echo '</style>' . "\n";
}

function oidc_login_failed_locally() {
    return isset( $_REQUEST['username'] ) || isset( $_REQUEST['password'] );
}

yourls_add_action( 'login_form_top', 'oidc_login_button' );
function oidc_login_button() {
    global $oidc_error;

    if( !oidc_enabled() ) return;

    $name = getenv( 'CLOUDRON_OIDC_PROVIDER_NAME' ) ?: 'Cloudron';
    $url = yourls_admin_url( '?oidc=login' );
    echo '<h2 class="oidc oidc-title">' . yourls_esc_html( yourls__( 'Log in' ) ) . '</h2>';
    echo '<p class="oidc oidc-subtitle">YOURLS: Your Own URL Shortener</p>';
    if( $oidc_error ) {
        echo '<p class="oidc oidc-error">' . yourls_esc_html( yourls_s( 'Login with %s failed. Please try again.', $name ) ) . '</p>';
    }
    echo '<p class="oidc"><a id="oidc-login" href="' . yourls_esc_url( $url ) . '">'
        . yourls_esc_html( yourls_s( 'Log in with %s', $name ) ) . '</a></p>';
    echo '<p class="oidc oidc-toggle" style="display: none;"><a id="oidc-local-login" href="#">'
        . yourls_esc_html( yourls__( 'Log in with local account' ) ) . '</a></p>';
}

// collapse with JS only, so the local form stays usable without JS. stays open after a failed local login
yourls_add_action( 'login_form_end', 'oidc_collapse_local_login' );
function oidc_collapse_local_login() {
    if( !oidc_enabled() || oidc_login_failed_locally() ) return;
    ?>
    <script>
    (function () {
        var form = document.getElementById('oidc-login').closest('form');
        var toggle = document.getElementById('oidc-local-login');
        form.classList.add('oidc-collapsed');
        toggle.parentNode.style.display = '';
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.remove('oidc-collapsed');
            toggle.parentNode.style.display = 'none';
            document.getElementById('username').focus();
        });
    })();
    </script>
    <?php
}

yourls_add_filter( 'is_valid_user', 'oidc_is_valid_user' );
function oidc_is_valid_user( $valid ) {
    global $yourls_user_passwords, $oidc_error;

    if( $valid || yourls_is_API() || !oidc_enabled() ) return $valid;
    if( !isset( $_GET['oidc'] ) || !in_array( $_GET['oidc'], [ 'login', 'callback' ], true ) ) return $valid;

    try {
        $oidc = oidc_client();
        $oidc->authenticate();
        $user = $oidc->requestUserInfo( 'preferred_username' );
    } catch( Exception $e ) {
        error_log( 'OIDC login failed: ' . $e->getMessage() );
        $oidc_error = true;
        return $valid;
    }

    if( !$user ) {
        error_log( 'OIDC login failed: no preferred_username claim' );
        $oidc_error = true;
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
