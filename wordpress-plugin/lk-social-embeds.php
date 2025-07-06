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

// Resto del plugin sin cambios (token, REST API, shortcode, bloque...)
