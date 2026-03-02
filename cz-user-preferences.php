<?php
/**
 * Plugin Name: CZ User Preferences
 * Description: Gestione preferenze utente (dimensione testo, tema preferito) via shortcode e AJAX.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: CZ
 */

if (!defined('ABSPATH')) {
    exit;
}

function czup_get_meta_key_text_size() {
    return 'czup_text_size';
}

function czup_get_meta_key_theme() {
    return 'czup_theme';
}

function czup_get_meta_key_show_quotes() {
    return 'czup_show_quotes';
}

function czup_get_meta_key_continue_reading() {
    return 'czup_continue_reading';
}

function czup_get_meta_key_show_readingtime() {
    return 'czup_show_readingtime';
}

function czup_get_user_meta_with_default($user_id, $meta_key, $default = '') {
    if (!$user_id) {
        return $default;
    }

    $value = get_user_meta($user_id, $meta_key, true);

    return $value === '' ? $default : $value;
}

function czup_get_show_readingtime($user_id) {
    return czup_get_user_meta_with_default($user_id, czup_get_meta_key_show_readingtime(), '0');
}

function czup_normalize_text_size($value) {
    if ($value === '' || $value === null) {
        return '16';
    }

    if (!preg_match('/^\d+$/', (string) $value)) {
        return false;
    }

    $size = (int) $value;
    if ($size < 10 || $size > 20) {
        return false;
    }

    return (string) $size;
}

function czup_enqueue_assets() {
    if (!is_singular()) {
        return;
    }

    global $post;
    if (!$post) {
        return;
    }

    if (!has_shortcode($post->post_content, 'cz_user_preferences')) {
        return;
    }

    $handle = 'czup-frontend';

    $base_dir = plugin_dir_path(__FILE__);
    $base_url = plugin_dir_url(__FILE__);

    $js_path = $base_dir . 'assets/js/cz-user-preferences.min.js';
    $js_url = $base_url . 'assets/js/cz-user-preferences.min.js';
    if (!file_exists($js_path)) {
        $js_path = $base_dir . 'assets/js/cz-user-preferences.js';
        $js_url = $base_url . 'assets/js/cz-user-preferences.js';
    }

    $css_path = $base_dir . 'assets/css/cz-user-preferences.min.css';
    $css_url = $base_url . 'assets/css/cz-user-preferences.min.css';
    if (!file_exists($css_path)) {
        $css_path = $base_dir . 'assets/css/cz-user-preferences.css';
        $css_url = $base_url . 'assets/css/cz-user-preferences.css';
    }

    wp_enqueue_script($handle, $js_url, array('jquery'), file_exists($js_path) ? (string) filemtime($js_path) : false, true);
    wp_enqueue_style('czup-frontend', $css_url, array(), file_exists($css_path) ? (string) filemtime($css_path) : false);

    $current_user_id = get_current_user_id();
    $text_size_raw = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_text_size(), true) : '';
    $text_size = czup_normalize_text_size($text_size_raw);
    if ($text_size === false) {
        $text_size = '16';
    }
    $theme = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_theme(), true) : '';
    if ($theme === '') {
        $theme = 'auto';
    }
    $show_quotes = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_show_quotes(), true) : '';
    if ($show_quotes === '') {
        $show_quotes = '1';
    }
    $continue_reading = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_continue_reading(), true) : '';
    if ($continue_reading === '') {
        $continue_reading = '1';
    }

    wp_localize_script($handle, 'czupData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('czup_save_preferences'),
        'textSize' => $text_size,
        'theme' => $theme,
        'showQuotes' => $show_quotes,
        'continueReading' => $continue_reading,
        'showReadingtime' => czup_get_show_readingtime($current_user_id),
        'isLoggedIn' => is_user_logged_in() ? 1 : 0,
    ));
}
add_action('wp_enqueue_scripts', 'czup_enqueue_assets');

function czup_shortcode_render() {
    $current_user_id = get_current_user_id();
    $text_size_raw = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_text_size(), true) : '';
    $text_size = czup_normalize_text_size($text_size_raw);
    if ($text_size === false) {
        $text_size = '16';
    }
    $theme = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_theme(), true) : '';
    if ($theme === '') {
        $theme = 'auto';
    }
    $show_quotes = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_show_quotes(), true) : '';
    if ($show_quotes === '') {
        $show_quotes = '1';
    }
    $continue_reading = $current_user_id ? get_user_meta($current_user_id, czup_get_meta_key_continue_reading(), true) : '';
    if ($continue_reading === '') {
        $continue_reading = '1';
    }
    $show_readingtime = czup_get_show_readingtime($current_user_id);

    ob_start();
    ?>
    <div class="czup-wrap" data-czup>
        <?php if (!is_user_logged_in()) : ?>
            <div class="czup-message">Effettua il login per salvare le preferenze.</div>
        <?php endif; ?>

        <form class="czup-form" data-czup-form>
            <div class="czup-field czup-field--range">
                <div class="czup-field__label">
                    <span class="czup-field__title">Dimensione Testo</span>
                    <small class="czup-field__description">Regola la dimensione base del testo visualizzato in tutto il sito.</small>
                </div>
                <div class="czup-range">
                    <input type="range" name="text_size" min="10" max="20" step="1" value="<?php echo esc_attr($text_size); ?>" aria-label="Dimensione del testo" <?php echo is_user_logged_in() ? '' : 'disabled'; ?>>
                    <output class="czup-range__value" data-czup-text-size-value aria-hidden="true"><?php echo esc_html($text_size); ?></output>
                </div>
            </div>

            <label class="czup-field">
                <div class="czup-field__label">
                    <span class="czup-field__title">Tema preferito</span>
                    <small class="czup-field__description">Imposta il tema predefinito tra automatico, chiaro e scuro.</small>
                </div>
                <select name="theme" <?php echo is_user_logged_in() ? '' : 'disabled'; ?>>
                    <option value="auto" <?php selected($theme, 'auto'); ?>>Automatico</option>
                    <option value="light" <?php selected($theme, 'light'); ?>>Chiaro</option>
                    <option value="dark" <?php selected($theme, 'dark'); ?>>Scuro</option>
                </select>
            </label>

            <div class="czup-field czup-field--switch">
                <div class="czup-field__label">
                    <span class="czup-field__title">Mostra Citazioni</span>
                    <small class="czup-field__description">Mostra o nascondi le citazioni di benvenuto nella homepage.</small>
                </div>
                <label class="czup-switch">
                    <input type="checkbox" name="show_quotes" value="1" <?php checked($show_quotes, '1'); ?> <?php echo is_user_logged_in() ? '' : 'disabled'; ?>>
                    <span class="czup-switch__track" aria-hidden="true"></span>
                </label>
            </div>

            <div class="czup-field czup-field--switch">
                <div class="czup-field__label">
                    <span class="czup-field__title">Continua a Leggere</span>
                    <small class="czup-field__description">Attiva o disattiva il salvataggio dei progressi di lettura.</small>
                </div>
                <label class="czup-switch">
                    <input type="checkbox" name="continue_reading" value="1" <?php checked($continue_reading, '1'); ?> <?php echo is_user_logged_in() ? '' : 'disabled'; ?>>
                    <span class="czup-switch__track" aria-hidden="true"></span>
                </label>
            </div>

            <div class="czup-field czup-field--switch">
                <div class="czup-field__label">
                    <span class="czup-field__title">Tempo di Lettura</span>
                    <small class="czup-field__description">Mostra o nascondi il tempo di lettura nelle pagine dei singoli articoli.</small>
                </div>
                <label class="czup-switch">
                    <input type="checkbox" name="show_readingtime" value="1" <?php checked($show_readingtime, '1'); ?> <?php echo is_user_logged_in() ? '' : 'disabled'; ?>>
                    <span class="czup-switch__track" aria-hidden="true"></span>
                </label>
            </div>

            <div class="czup-status" data-czup-status></div>

            <div class="czup-actions">
                <button type="button" class="czup-close" data-czup-close hidden>Chiudi</button>
            </div>
        </form>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('cz_user_preferences', 'czup_shortcode_render');

function czup_register_default_user_meta($user_id) {
    if (!$user_id) {
        return;
    }

    add_user_meta($user_id, czup_get_meta_key_show_readingtime(), '0', true);
}
add_action('user_register', 'czup_register_default_user_meta');

function czup_ajax_save_preferences() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Utente non autenticato.'), 401);
    }

    check_ajax_referer('czup_save_preferences', 'nonce');

    $user_id = get_current_user_id();
    $text_size_raw = isset($_POST['text_size']) ? sanitize_text_field(wp_unslash($_POST['text_size'])) : '';
    $text_size = czup_normalize_text_size($text_size_raw);
    $theme = isset($_POST['theme']) ? sanitize_text_field(wp_unslash($_POST['theme'])) : '';
    $show_quotes = isset($_POST['show_quotes']) ? sanitize_text_field(wp_unslash($_POST['show_quotes'])) : '0';
    $continue_reading = isset($_POST['continue_reading']) ? sanitize_text_field(wp_unslash($_POST['continue_reading'])) : '0';
    $show_readingtime = isset($_POST['show_readingtime']) ? sanitize_text_field(wp_unslash($_POST['show_readingtime'])) : '0';

    $allowed_themes = array('light', 'dark', 'auto', '');
    $allowed_show_quotes = array('0', '1');
    $allowed_continue_reading = array('0', '1');
    $allowed_show_readingtime = array('0', '1');

    if ($text_size === false) {
        wp_send_json_error(array('message' => 'Dimensione testo non valida.'), 400);
    }

    if (!in_array($theme, $allowed_themes, true)) {
        wp_send_json_error(array('message' => 'Tema non valido.'), 400);
    }

    if (!in_array($show_quotes, $allowed_show_quotes, true)) {
        wp_send_json_error(array('message' => 'Opzione citazioni non valida.'), 400);
    }
    if (!in_array($continue_reading, $allowed_continue_reading, true)) {
        wp_send_json_error(array('message' => 'Opzione continua a leggere non valida.'), 400);
    }
    if (!in_array($show_readingtime, $allowed_show_readingtime, true)) {
        wp_send_json_error(array('message' => 'Opzione tempo di lettura non valida.'), 400);
    }

    update_user_meta($user_id, czup_get_meta_key_text_size(), $text_size);
    update_user_meta($user_id, czup_get_meta_key_theme(), $theme);
    update_user_meta($user_id, czup_get_meta_key_show_quotes(), $show_quotes);
    update_user_meta($user_id, czup_get_meta_key_continue_reading(), $continue_reading);
    update_user_meta($user_id, czup_get_meta_key_show_readingtime(), $show_readingtime);

    wp_send_json_success(array('message' => 'Preferenze salvate.'));
}
add_action('wp_ajax_czup_save_preferences', 'czup_ajax_save_preferences');
