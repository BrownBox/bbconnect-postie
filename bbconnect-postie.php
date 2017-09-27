<?php
/**
 * Plugin Name: Connexions Postie
 * Plugin URI: http://connexionscrm.com/
 * Description: Add notes and history to Connexions by sending emails
 * Version: 0.1.1
 * Author: Brown Box
 * Author URI: http://brownbox.net.au
 * License: Proprietary Brown Box
 *
 */
define('BBCONNECT_POSTIE_DIR', plugin_dir_path(__FILE__));
define('BBCONNECT_POSTIE_URL', plugin_dir_url(__FILE__));

function bbconnect_postie_init() {
    if (!defined('BBCONNECT_VER') || version_compare(BBCONNECT_VER, '2.5.3', '<') || !defined('POSTIE_VERSION')) {
        add_action('admin_init', 'bbconnect_postie_deactivate');
        add_action('admin_notices', 'bbconnect_postie_deactivate_notice');
        return;
    }
    if (is_admin()) {
        new BbConnectUpdates(__FILE__, 'BrownBox', 'bbconnect-postie');
    }
}
add_action('plugins_loaded', 'bbconnect_postie_init');

function bbconnect_postie_deactivate() {
    deactivate_plugins(plugin_basename(__FILE__));
}

function bbconnect_postie_deactivate_notice() {
    echo '<div class="updated"><p><strong>Connexions Postie</strong> has been <strong>deactivated</strong> as it requires both Postie and Connexions (v2.5.3 or higher) to be active.</p></div>';
    if (isset($_GET['activate'])) {
        unset($_GET['activate']);
    }
}

add_filter('postie_post_before', 'bbconnect_postie_process', 10, 2);
function bbconnect_postie_process($post, $headers) {
    $send_email_form_id = bbconnect_get_send_email_form();
    $crm_email = get_option('_bbconnect_postie_crm_email');
    if (isset($headers['bcc']['mailbox'])) { // Postie currently (as of version 1.9.1) only passes a single BCC address but we're catering for the possibility they may pass multiple in the future
        $headers['bcc'] = array($headers['bcc']);
    }
    foreach ($headers['bcc'] as $bcc) {
        $bcc_email = $bcc['mailbox'].'@'.$bcc['host'];
        if (!empty(get_option('_bbconnect_postie_crm_email')) && $bcc_email == $crm_email) {
            foreach ($headers['to'] as $to) {
                $firstname = $lastname = '';
                if (!empty($to['personal'])) {
                    list($firstname, $lastname) = explode(' ', $to['personal']);
                }
                $to_email = $to['mailbox'].'@'.$to['host'];
                $user = get_user_by('email', $to_email);
                if ($user instanceof WP_User) {
                    if (empty($firstname)) {
                        $firstname = $user->user_firstname;
                    }
                    if (empty($lastname)) {
                        $lastname = $user->user_lastname;
                    }
                } else {
                    // New contact!
                    if (empty($firstname)) {
                        $firstname = 'Unknown';
                    }
                    if (empty($lastname)) {
                        $lastname = 'Unknown';
                    }
                    $user = new WP_User();
                    $user->user_login = wp_generate_password(12, false);
                    $user->user_email = $to_email;
                    $user->user_firstname = $firstname;
                    $user->user_lastname = $lastname;
                    $user->user_pass = wp_generate_password();
                    $user->ID = wp_insert_user($user);
                }
                // Insert GF entry
                $_POST = array(); // Hack to allow multiple form submissions via API in single process
                $entry = array(
                        'input_2_3' => $firstname,
                        'input_2_6' => $lastname,
                        'input_3' => $to_email,
                        'input_4' => $post['post_title'],
                        'input_5' => $post['post_content'],
                        'input_6' => 'postie',
                        'input_7' => 'postie',
                        'agent_id' => $post['post_author'],
                        'date_created' => $post['post_date'], // @todo this doesn't seem to work :-(
                );
                GFAPI::submit_form($send_email_form_id, $entry);
            }
            return null; // Tells Postie to not save as post
        }
    }
    return $post; // Let Postie do its normal thing
}

add_filter('bbconnect_options_tabs', 'bbconnect_postie_options');
function bbconnect_postie_options($navigation) {
    $navigation['bbconnect_postie_settings'] = array(
			'title' => __('Postie', 'bbconnect'),
			'subs' => false,
    );
    return $navigation;
}

function bbconnect_postie_settings() {
    return array(
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => '_bbconnect_postie_crm_email',
                            'name' => __('CRM Email Address', 'bbconnect'),
                            'help' => '',
                            'options' => array(
                                    'field_type' => 'text',
                                    'req' => true,
                                    'public' => false,
                            ),
                    ),
            ),
    );
}

add_filter('bbconnect_activity_types', 'bbconnect_postie_activity_types');
function bbconnect_postie_activity_types($types) {
    $types['postie'] = 'Postie';
    return $types;
}

add_filter('bbconnect_activity_icon', 'bbconnect_postie_activity_icon', 10, 2);
function bbconnect_postie_activity_icon($icon, $activity_type) {
    if ($activity_type == 'postie') {
        $icon = plugin_dir_url(__FILE__).'images/activity-icon.png';
    }
    return $icon;
}
