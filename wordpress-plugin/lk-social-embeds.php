<?php
/**
 * Plugin Name: LK Social Embeds – Twitch (core)
 * Description: Embed del canal de Twitch (estado en vivo, perfil, clips) con shortcode [lk-twitch] y bloque Gutenberg.
 *              REST API (/wp-json/lk/v1/…), caché, cron de renovación segura de tokens y UI mejorada en Ajustes.
 * Version:     1.3.1
 * Author:      LaKattrina Devs
 * Text Domain: lk-social
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License: GPL-2.0-or-later
 */

// ────────────────────────────────────────────────────────────────────────────
// CONSTANTES BÁSICAS
// ────────────────────────────────────────────────────────────────────────────
if ( ! defined( 'ABSPATH' ) ) {
    exit; // acceso directo no permitido
}

define( 'LK_VERSION',        '1.3.1' );
define( 'LK_PLUGIN_FILE',    __FILE__ );
define( 'LK_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LK_PLUGIN_URL',     plugin_dir_url(  __FILE__ ) );

// ────────────────────────────────────────────────────────────────────────────
// TRADUCCIONES
// ────────────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'lk-social', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// ────────────────────────────────────────────────────────────────────────────
// AJUSTES (solo Twitch por ahora)
// ────────────────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'lk_register_settings_page' );
function lk_register_settings_page() {
    add_options_page( __( 'LK Social Embeds', 'lk-social' ), __( 'LK Social Embeds', 'lk-social' ), 'manage_options', 'lk-social-embeds', 'lk_render_settings_page' );
}

function lk_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'LK Social Embeds', 'lk-social' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'lk_social_settings' );
            do_settings_sections( 'lk-social-embeds' );
            submit_button();
            ?>
        </form>
        <hr />
        <h2><?php esc_html_e( 'Tokens disponibles', 'lk-social' ); ?></h2>
        <p><?php esc_html_e( 'Utiliza estos tokens en tu integración:', 'lk-social' ); ?></p>
        <ul class="lk-token-list">
            <li><code>[lk-twitch canal="{tu_canal}"]</code></li>
            <li><code>[lk-twitch-profile canal="{tu_canal}"]</code></li>
            <li><code>[lk-twitch-clips canal="{tu_canal}"]</code></li>
        </ul>
    </div>
    <?php
}

add_action( 'admin_init', 'lk_register_settings_fields' );
function lk_register_settings_fields() {
    register_setting( 'lk_social_settings', 'lk_twitch_client_id',     [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'lk_social_settings', 'lk_twitch_client_secret', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'lk_social_settings', 'lk_twitch_channel',       [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'LaKattrina' ] );

    add_settings_section( 'lk_twitch_section', __( 'Twitch', 'lk-social' ), '__return_false', 'lk-social-embeds' );

    add_settings_field( 'lk_twitch_client_id', __( 'Client ID', 'lk-social' ), 'lk_api_field_cb', 'lk-social-embeds', 'lk_twitch_section', [ 'label_for' => 'lk_twitch_client_id', 'type' => 'text' ] );
    add_settings_field( 'lk_twitch_client_secret', __( 'Client Secret', 'lk-social' ), 'lk_api_field_cb', 'lk-social-embeds', 'lk_twitch_section', [ 'label_for' => 'lk_twitch_client_secret', 'type' => 'password' ] );
    add_settings_field( 'lk_twitch_channel', __( 'Canal predeterminado', 'lk-social' ), 'lk_api_field_cb', 'lk-social-embeds', 'lk_twitch_section', [ 'label_for' => 'lk_twitch_channel', 'type' => 'text' ] );
}

/**
 * Callback que imprime input con botones 👁/📋.
 */
function lk_api_field_cb( $args ) {
    $value = get_option( $args['label_for'] );
    $type  = $args['type'] ?? 'text';
    echo '<div class="lk-api-field" style="display:flex;gap:4px;align-items:center;max-width:450px">';
    printf( '<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="regular-text" style="flex:1 1 auto;" />', esc_attr( $type ), esc_attr( $args['label_for'] ), esc_attr( $value ) );
    if ( 'password' === $type ) {
        echo '<button type="button" class="button lk-toggle" title="' . esc_attr__( 'Mostrar/Ocultar', 'lk-social' ) . '">👁</button>';
    }
    echo '<button type="button" class="button lk-paste" title="' . esc_attr__( 'Pegar desde portapapeles', 'lk-social' ) . '">📋</button>';
    echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'lk_admin_enqueue_assets' );
function lk_admin_enqueue_assets( $hook ) {
    if ( 'settings_page_lk-social-embeds' !== $hook ) {
        return;
    }
    wp_register_style( 'lk-admin-style', false );
    wp_enqueue_style(  'lk-admin-style' );
    wp_add_inline_style( 'lk-admin-style', '.lk-api-field .button{height:32px;padding:0 8px;font-size:13px;line-height:30px}' );
    wp_add_inline_style( 'lk-admin-style', '.lk-token-list{list-style:disc;margin-left:1.5em;} .lk-token-list code{background:#f1f1f1;padding:2px 4px;border-radius:3px;}' );

    wp_register_script( 'lk-admin-script', '', [], LK_VERSION, true );
    wp_enqueue_script(  'lk-admin-script' );
    wp_add_inline_script( 'lk-admin-script', 'document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll(".lk-api-field").forEach(f=>{let i=f.querySelector("input"),t=f.querySelector(".lk-toggle"),p=f.querySelector(".lk-paste");t&&t.addEventListener("click",()=>{i.type=i.type==="password"?"text":"password"});p&&p.addEventListener("click",async()=>{try{const txt=await navigator.clipboard.readText();if(txt){i.value=txt;i.dispatchEvent(new Event("change"));}}catch(e){alert("'.esc_js( __( 'No se pudo acceder al portapapeles.', 'lk-social' ) ).'" );}});});});' );
}

// ────────────────────────────────────────────────────────────────────────────
// TOKEN: cifrado seguro + cron con single‑event
// ────────────────────────────────────────────────────────────────────────────
const LK_CRON_HOOK = 'lk_twitch_refresh_token_event';

register_activation_hook( __FILE__, 'lk_activate_plugin' );
register_deactivation_hook( __FILE__, 'lk_deactivate_plugin' );

function lk_activate_plugin() {
    lk_refresh_twitch_token(); // token inicial
}
function lk_deactivate_plugin() {
    wp_clear_scheduled_hook( LK_CRON_HOOK );
}

function lk_set_secure_option( string $key, string $plain ): void {
    if ( function_exists( 'sodium_crypto_secretbox' ) && defined( 'AUTH_KEY' ) ) {
        $nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $secret = hash( 'sha256', AUTH_KEY, true );
        $cipher = sodium_crypto_secretbox( $plain, $nonce, $secret );
        update_option( $key, base64_encode( $nonce . $cipher ) );
    } else {
        update_option( $key, $plain );
    }
}
function lk_get_secure_option( string $key ): ?string {
    $stored = get_option( $key );
    if ( ! $stored ) { return null; }
    if ( function_exists( 'sodium_crypto_secretbox_open' ) && defined( 'AUTH_KEY' ) ) {
        $raw    = base64_decode( $stored );
        $nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $secret = hash( 'sha256', AUTH_KEY, true );
        $plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $secret );
        return $plain ?: null;
    }
    return $stored;
}

add_action( LK_CRON_HOOK, 'lk_refresh_twitch_token' );
function lk_refresh_twitch_token() {
    $client_id     = get_option( 'lk_twitch_client_id' );
    $client_secret = get_option( 'lk_twitch_client_secret' );
    if ( ! $client_id || ! $client_secret ) { return; }

    $resp = wp_remote_post( 'https://id.twitch.tv/oauth2/token', [
        'timeout' => 15,
        'body'    => [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'client_credentials',
        ],
    ] );
    if ( is_wp_error( $resp ) ) { error_log( $resp->get_error_message() ); return; }

    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( empty( $data['access_token'] ) ) { return; }

    lk_set_secure_option( 'lk_twitch_token', sanitize_text_field( $data['access_token'] ) );
    update_option( 'lk_twitch_token_expires', time() + intval( $data['expires_in'] ) );

    // Programar renovación 6 h antes de caducar
    wp_clear_scheduled_hook( LK_CRON_HOOK );
    wp_schedule_single_event( max( time() + 60, time() + intval( $data['expires_in'] ) - HOUR_IN_SECONDS * 6 ), LK_CRON_HOOK );
}

function lk_get_twitch_token(): ?string {
    $token   = lk_get_secure_option( 'lk_twitch_token' );
    $expires = intval( get_option( 'lk_twitch_token_expires' ) );
    if ( ! $token || ( $expires && time() + 300 >= $expires ) ) {
        lk_refresh_twitch_token();
        $token = lk_get_secure_option( 'lk_twitch_token' );
    }
    return $token;
}

// ────────────────────────────────────────────────────────────────────────────
// REST API (/wp-json/lk/v1/...)
// ────────────────────────────────────────────────────────────────────────────
add_action( 'rest_api_init', 'lk_register_rest_routes' );
function lk_register_rest_routes() {
    register_rest_route( 'lk/v1', '/live', [
        'methods'             => 'GET',
        'callback'            => 'lk_rest_live',
        'permission_callback' => '__return_true',
        'args'                => [ 'channel' => [ 'sanitize_callback' => 'sanitize_text_field' ] ],
    ] );
    register_rest_route( 'lk/v1', '/profile', [
        'methods'             => 'GET',
        'callback'            => 'lk_rest_profile',
        'permission_callback' => '__return_true',
        'args'                => [ 'channel' => [ 'sanitize_callback' => 'sanitize_text_field' ] ],
    ] );
    register_rest_route( 'lk/v1', '/clips', [
        'methods'             => 'GET',
        'callback'            => 'lk_rest_clips',
        'permission_callback' => '__return_true',
        'args'                => [
            'channel' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'first'   => [ 'sanitize_callback' => 'absint', 'default' => 5 ],
        ],
    ] );
}

function lk_get_user_id( $channel, $token, $client_id ) {
    $r = wp_remote_get( 'https://api.twitch.tv/helix/users?login=' . urlencode( $channel ), [
        'timeout' => 15,
        'headers' => [ 'Client-ID' => $client_id, 'Authorization' => 'Bearer ' . $token ],
    ] );
    $d = json_decode( wp_remote_retrieve_body( $r ), true );
    return $d['data'][0]['id'] ?? null;
}

function lk_rest_live( WP_REST_Request $req ) {
    $channel  = $req->get_param( 'channel' ) ?: get_option( 'lk_twitch_channel', 'LaKattrina' );
    $token    = lk_get_twitch_token();
    $client_id = get_option( 'lk_twitch_client_id' );
    if ( ! $token || ! $client_id ) {
        return new WP_Error( 'lk_no_credentials', __( 'Credenciales faltantes', 'lk-social' ), [ 'status' => 400 ] );
    }
    $resp = wp_remote_get( 'https://api.twitch.tv/helix/streams?user_login=' . urlencode( $channel ), [
        'timeout' => 15,
        'headers' => [ 'Client-ID' => $client_id, 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $resp ) ) { return $resp; }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    $live = ! empty( $data['data'] );
    $view = $live ? intval( $data['data'][0]['viewer_count'] ) : 0;
    return rest_ensure_response( [ 'live' => $live, 'viewer_count' => $view ] );
}

function lk_get_followers( $user_id, $token, $client_id ): int {
    $r = wp_remote_get( 'https://api.twitch.tv/helix/users/follows?to_id=' . intval( $user_id ) . '&first=1', [
        'timeout' => 15,
        'headers' => [ 'Client-ID' => $client_id, 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $r ) ) { return 0; }
    $d = json_decode( wp_remote_retrieve_body( $r ), true );
    return intval( $d['total'] ?? 0 );
}

function lk_rest_profile( WP_REST_Request $req ) {
    $channel = $req->get_param( 'channel' ) ?: get_option( 'lk_twitch_channel', 'LaKattrina' );
    $cache_key = 'lk_profile_' . md5( $channel );
    if ( false !== ( $cached = get_transient( $cache_key ) ) ) {
        return rest_ensure_response( $cached );
    }
    $token     = lk_get_twitch_token();
    $client_id = get_option( 'lk_twitch_client_id' );
    if ( ! $token || ! $client_id ) {
        return new WP_Error( 'lk_no_credentials', __( 'Credenciales faltantes', 'lk-social' ), [ 'status' => 400 ] );
    }
    $r = wp_remote_get( 'https://api.twitch.tv/helix/users?login=' . urlencode( $channel ), [
        'timeout' => 15,
        'headers' => [ 'Client-ID' => $client_id, 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $r ) ) { return $r; }
    $u = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( empty( $u['data'][0] ) ) {
        return new WP_Error( 'lk_not_found', __( 'Usuario no encontrado', 'lk-social' ), [ 'status' => 404 ] );
    }
    $user      = $u['data'][0];
    $followers = lk_get_followers( $user['id'], $token, $client_id );

    $profile = [
        'nombre'          => sanitize_text_field( $user['display_name'] ),
        'descripcion'     => sanitize_text_field( $user['description'] ),
        'imagen_perfil'   => esc_url_raw( $user['profile_image_url'] ),
        'seguidores'      => $followers,
        'clips'           => [], // placeholder; llenado por /clips
    ];
    set_transient( $cache_key, $profile, 300 );
    return rest_ensure_response( $profile );
}

function lk_rest_clips( WP_REST_Request $req ) {
    $channel = $req->get_param( 'channel' ) ?: get_option( 'lk_twitch_channel', 'LaKattrina' );
    $first   = absint( $req->get_param( 'first' ) ) ?: 5;
    $cache_key = 'lk_clips_' . md5( $channel . '|' . $first );
    if ( false !== ( $cached = get_transient( $cache_key ) ) ) {
        return rest_ensure_response( $cached );
    }
    $token     = lk_get_twitch_token();
    $client_id = get_option( 'lk_twitch_client_id' );
    if ( ! $token || ! $client_id ) {
        return new WP_Error( 'lk_no_credentials', __( 'Credenciales faltantes', 'lk-social' ), [ 'status' => 400 ] );
    }
    $user_id = lk_get_user_id( $channel, $token, $client_id );
    if ( ! $user_id ) {
        return new WP_Error( 'lk_not_found', __( 'Usuario no encontrado', 'lk-social' ), [ 'status' => 404 ] );
    }
    $r = wp_remote_get( add_query_arg( [
        'broadcaster_id' => $user_id,
        'first'          => min( $first, 100 ),
    ], 'https://api.twitch.tv/helix/clips' ), [
        'timeout' => 15,
        'headers' => [ 'Client-ID' => $client_id, 'Authorization' => 'Bearer ' . $token ],
    ] );
    if ( is_wp_error( $r ) ) { return $r; }
    $d     = json_decode( wp_remote_retrieve_body( $r ), true );
    $clips = array_map( static fn( $c ) => sanitize_text_field( $c['id'] ), $d['data'] ?? [] );
    set_transient( $cache_key, $clips, 300 );
    return rest_ensure_response( $clips );
}

// ────────────────────────────────────────────────────────────────────────────
// FRONTEND: assets condicionales + shortcode
// ────────────────────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'lk_enqueue_frontend_assets' );
function lk_enqueue_frontend_assets() {
    if ( ! is_singular() && ! is_page() && ! is_front_page() ) { return; }
    global $post; if ( empty( $post->post_content ) ) { return; }
    if ( ! has_shortcode( $post->post_content, 'lk-twitch' ) ) { return; }

    wp_enqueue_style ( 'lk-swiper',  'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0' );
    wp_enqueue_script( 'lk-swiper',  'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0', true );
    wp_enqueue_script( 'lk-twitch-player', 'https://player.twitch.tv/js/embed/v1.js', [], null, true );

    wp_register_script( 'lk-frontend', LK_PLUGIN_URL . 'assets/frontend.js', [], LK_VERSION, true );
    wp_enqueue_script(  'lk-frontend' );
    wp_localize_script( 'lk-frontend', 'lkSettings', [ 'rest_url' => esc_url_raw( rest_url( 'lk/v1' ) ) ] );
}

add_shortcode( 'lk-twitch', 'lk_twitch_shortcode' );
function lk_twitch_shortcode( $atts ) {
    $a = shortcode_atts( [
        'canal' => get_option( 'lk_twitch_channel', 'LaKattrina' ),
        'ancho' => '100%',
        'alto'  => '600',
    ], $atts, 'lk-twitch' );
    $id = 'lkProfile_' . wp_generate_password( 6, false );
    ob_start();
    ?>
    <div class="lk-profile-wrapper" style="max-width:<?php echo esc_attr( $a['ancho'] ); ?>;">
        <div id="<?php echo esc_attr( $id ); ?>" class="lk-profile" data-canal="<?php echo esc_attr( $a['canal'] ); ?>" data-alto="<?php echo esc_attr( $a['alto'] ); ?>">
            <?php esc_html_e( 'Cargando…', 'lk-social' ); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ────────────────────────────────────────────────────────────────────────────
// BLOQUE GUTENBERG (re‑utiliza render del shortcode)
// ────────────────────────────────────────────────────────────────────────────
add_action( 'init', 'lk_register_gutenberg_block' );
function lk_register_gutenberg_block() {
    if ( ! function_exists( 'register_block_type' ) ) { return; }
    register_block_type( __DIR__ . '/build', [ 'render_callback' => 'lk_twitch_shortcode' ] );
}

// ────────────────────────────────────────────────────────────────────────────
// FIN
// ────────────────────────────────────────────────────────────────────────────
