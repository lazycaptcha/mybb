<?php
/**
 * LazyCaptcha plugin for MyBB 1.8.x
 *
 * Protects registration, login, forgot-password, new thread, and new reply
 * with a LazyCaptcha challenge.
 *
 * @package     LazyCaptcha.MyBB
 * @license     MIT
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// -----------------------------------------------------------------------------
// Hook registration (at load)
// -----------------------------------------------------------------------------

$plugins->add_hook('member_register_start', 'lazycaptcha_render_registration');
$plugins->add_hook('member_do_register_start', 'lazycaptcha_verify_registration');

$plugins->add_hook('member_login', 'lazycaptcha_render_login');
$plugins->add_hook('member_do_login_start', 'lazycaptcha_verify_login');

$plugins->add_hook('member_lostpw_start', 'lazycaptcha_render_lostpw');
$plugins->add_hook('member_do_lostpw_start', 'lazycaptcha_verify_lostpw');

$plugins->add_hook('newthread_start', 'lazycaptcha_render_newthread');
$plugins->add_hook('newthread_do_newthread_start', 'lazycaptcha_verify_newthread');

$plugins->add_hook('newreply_start', 'lazycaptcha_render_newreply');
$plugins->add_hook('newreply_do_newreply_start', 'lazycaptcha_verify_newreply');

// -----------------------------------------------------------------------------
// Required MyBB plugin functions
// -----------------------------------------------------------------------------

function lazycaptcha_info()
{
    return [
        'name'          => 'LazyCaptcha',
        'description'   => 'Self-hostable CAPTCHA protection for MyBB forms using the LazyCaptcha service.',
        'website'       => 'https://lazycaptcha.com',
        'author'        => 'LazyCaptcha',
        'authorsite'    => 'https://lazycaptcha.com',
        'version'       => '0.1.0',
        'compatibility' => '18*',
        'codename'      => 'lazycaptcha',
    ];
}

function lazycaptcha_is_installed()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='lazycaptcha'", ['limit' => 1]);
    return (bool) $db->num_rows($query);
}

function lazycaptcha_install()
{
    global $db;

    // Create the setting group
    $group = [
        'name'        => 'lazycaptcha',
        'title'       => 'LazyCaptcha',
        'description' => 'Settings for the LazyCaptcha plugin.',
        'disporder'   => 9,
        'isdefault'   => 0,
    ];
    $db->insert_query('settinggroups', $group);
    $gid = (int) $db->insert_id();

    // Create settings
    $settings = [
        [
            'name'        => 'lazycaptcha_site_key',
            'title'       => 'Site Key',
            'description' => 'Your LazyCaptcha public site key (UUID). Get one at https://lazycaptcha.com',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 1,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_secret_key',
            'title'       => 'Secret Key',
            'description' => 'Your LazyCaptcha private secret key. Never share publicly.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 2,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_base_url',
            'title'       => 'LazyCaptcha URL',
            'description' => 'Your LazyCaptcha instance URL. Default is the hosted service.',
            'optionscode' => 'text',
            'value'       => 'https://lazycaptcha.com',
            'disporder'   => 3,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_type',
            'title'       => 'Challenge Type',
            'description' => 'Which challenge to present.',
            'optionscode' => "select\nauto=Auto\nimage_puzzle=Image puzzles\npow=Proof of Work (invisible)\nbehavioral=Behavioral (invisible)\ntext_math=Text / Math\npress_hold=Press and Hold\nrotate_align=Rotate to Align (high-friction)",
            'value'       => 'auto',
            'disporder'   => 4,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_theme',
            'title'       => 'Theme',
            'description' => 'Widget appearance.',
            'optionscode' => "select\nlight=Light\ndark=Dark",
            'value'       => 'light',
            'disporder'   => 5,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_enable_registration',
            'title'       => 'Protect registration',
            'description' => 'Require a CAPTCHA on new account registration.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 6,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_enable_login',
            'title'       => 'Protect login',
            'description' => 'Require a CAPTCHA on login.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 7,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_enable_lostpw',
            'title'       => 'Protect forgot password',
            'description' => 'Require a CAPTCHA on the lost-password form.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 8,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_enable_posting',
            'title'       => 'Protect posting (new threads & replies)',
            'description' => 'Require a CAPTCHA on new threads and replies. Auto-skipped for users with more than N posts.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 9,
            'gid'         => $gid,
        ],
        [
            'name'        => 'lazycaptcha_skip_posts_threshold',
            'title'       => 'Skip posting CAPTCHA after N posts',
            'description' => 'Users with at least this many posts skip the CAPTCHA on threads/replies. Set to 0 to never skip.',
            'optionscode' => 'text',
            'value'       => '10',
            'disporder'   => 10,
            'gid'         => $gid,
        ],
    ];

    foreach ($settings as $s) {
        $db->insert_query('settings', $s);
    }

    rebuild_settings();
}

function lazycaptcha_uninstall()
{
    global $db;

    $db->delete_query('settings', "name LIKE 'lazycaptcha_%'");
    $db->delete_query('settinggroups', "name='lazycaptcha'");

    rebuild_settings();
}

function lazycaptcha_activate()
{
    global $db;

    // Inject {$lazycaptcha} placeholder into templates
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'member_register',
        '#\{\$regerrors\}#',
        "{\$regerrors}\n{\$lazycaptcha}"
    );
    find_replace_templatesets(
        'member_login',
        '#\{\$login_container_end\}#',
        "{\$lazycaptcha}\n{\$login_container_end}"
    );
    find_replace_templatesets(
        'member_lostpw',
        '#\{\$errors\}#',
        "{\$errors}\n{\$lazycaptcha}"
    );
    find_replace_templatesets(
        'newthread',
        '#\{\$subscriptionmethod\}#',
        "{\$lazycaptcha}\n{\$subscriptionmethod}"
    );
    find_replace_templatesets(
        'newreply',
        '#\{\$subscriptionmethod\}#',
        "{\$lazycaptcha}\n{\$subscriptionmethod}"
    );
}

function lazycaptcha_deactivate()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets('member_register', '#\n?\{\$lazycaptcha\}#', '');
    find_replace_templatesets('member_login', '#\n?\{\$lazycaptcha\}#', '');
    find_replace_templatesets('member_lostpw', '#\n?\{\$lazycaptcha\}#', '');
    find_replace_templatesets('newthread', '#\n?\{\$lazycaptcha\}#', '');
    find_replace_templatesets('newreply', '#\n?\{\$lazycaptcha\}#', '');
}

// -----------------------------------------------------------------------------
// Render helpers
// -----------------------------------------------------------------------------

function lazycaptcha_widget_html()
{
    global $mybb, $lazycaptcha;

    $siteKey = trim((string) ($mybb->settings['lazycaptcha_site_key'] ?? ''));
    if ($siteKey === '') {
        return '';
    }

    $type  = htmlspecialchars_uni($mybb->settings['lazycaptcha_type'] ?? 'auto');
    $theme = htmlspecialchars_uni($mybb->settings['lazycaptcha_theme'] ?? 'auto');
    $base  = rtrim((string) ($mybb->settings['lazycaptcha_base_url'] ?? 'https://lazycaptcha.com'), '/');

    $html  = '<div class="lazycaptcha" data-sitekey="' . htmlspecialchars_uni($siteKey) . '"';
    $html .= ' data-type="' . $type . '" data-theme="' . $theme . '"></div>';
    $html .= '<script src="' . htmlspecialchars_uni($base) . '/api/captcha/v1/lazycaptcha.js" async defer></script>';

    return $html;
}

function lazycaptcha_verify_token(): array
{
    global $mybb;

    $token = $mybb->get_input('lazycaptcha-token', MyBB::INPUT_STRING);
    if ($token === '') {
        return ['success' => false, 'error' => 'missing_token'];
    }

    $secret = trim((string) ($mybb->settings['lazycaptcha_secret_key'] ?? ''));
    if ($secret === '') {
        return ['success' => false, 'error' => 'misconfigured'];
    }

    $base = rtrim((string) ($mybb->settings['lazycaptcha_base_url'] ?? 'https://lazycaptcha.com'), '/');
    $url  = $base . '/api/captcha/v1/verify';

    $payload = json_encode([
        'secret'    => $secret,
        'token'     => $token,
        'remote_ip' => get_ip(),
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);

    if ($err || $body === false) {
        return ['success' => false, 'error' => 'connection_failed'];
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'error' => 'invalid_response'];
    }

    return $decoded;
}

function lazycaptcha_should_skip_for_user(): bool
{
    global $mybb;

    $threshold = (int) ($mybb->settings['lazycaptcha_skip_posts_threshold'] ?? 0);
    if ($threshold <= 0) {
        return false;
    }

    if (empty($mybb->user['uid'])) {
        return false;
    }

    return ((int) ($mybb->user['postnum'] ?? 0)) >= $threshold;
}

// -----------------------------------------------------------------------------
// Registration
// -----------------------------------------------------------------------------

function lazycaptcha_render_registration()
{
    global $mybb, $lazycaptcha;
    if (empty($mybb->settings['lazycaptcha_enable_registration'])) {
        $lazycaptcha = '';
        return;
    }
    $lazycaptcha = lazycaptcha_widget_html();
}

function lazycaptcha_verify_registration()
{
    global $mybb, $errors;
    if (empty($mybb->settings['lazycaptcha_enable_registration'])) {
        return;
    }
    $result = lazycaptcha_verify_token();
    if (empty($result['success'])) {
        if (!is_array($errors)) $errors = [];
        $errors[] = 'CAPTCHA verification failed. Please try again.';
    }
}

// -----------------------------------------------------------------------------
// Login
// -----------------------------------------------------------------------------

function lazycaptcha_render_login()
{
    global $mybb, $lazycaptcha;
    if (empty($mybb->settings['lazycaptcha_enable_login'])) {
        $lazycaptcha = '';
        return;
    }
    $lazycaptcha = lazycaptcha_widget_html();
}

function lazycaptcha_verify_login()
{
    global $mybb;
    if (empty($mybb->settings['lazycaptcha_enable_login'])) {
        return;
    }
    $result = lazycaptcha_verify_token();
    if (empty($result['success'])) {
        error('CAPTCHA verification failed. Please try again.');
    }
}

// -----------------------------------------------------------------------------
// Lost password
// -----------------------------------------------------------------------------

function lazycaptcha_render_lostpw()
{
    global $mybb, $lazycaptcha;
    if (empty($mybb->settings['lazycaptcha_enable_lostpw'])) {
        $lazycaptcha = '';
        return;
    }
    $lazycaptcha = lazycaptcha_widget_html();
}

function lazycaptcha_verify_lostpw()
{
    global $mybb, $errors;
    if (empty($mybb->settings['lazycaptcha_enable_lostpw'])) {
        return;
    }
    $result = lazycaptcha_verify_token();
    if (empty($result['success'])) {
        if (!is_array($errors)) $errors = [];
        $errors[] = 'CAPTCHA verification failed. Please try again.';
    }
}

// -----------------------------------------------------------------------------
// New thread
// -----------------------------------------------------------------------------

function lazycaptcha_render_newthread()
{
    global $mybb, $lazycaptcha;
    if (empty($mybb->settings['lazycaptcha_enable_posting']) || lazycaptcha_should_skip_for_user()) {
        $lazycaptcha = '';
        return;
    }
    $lazycaptcha = lazycaptcha_widget_html();
}

function lazycaptcha_verify_newthread()
{
    global $mybb, $post_errors;
    if (empty($mybb->settings['lazycaptcha_enable_posting']) || lazycaptcha_should_skip_for_user()) {
        return;
    }
    $result = lazycaptcha_verify_token();
    if (empty($result['success'])) {
        if (!is_array($post_errors)) $post_errors = [];
        $post_errors[] = 'CAPTCHA verification failed. Please try again.';
    }
}

// -----------------------------------------------------------------------------
// New reply
// -----------------------------------------------------------------------------

function lazycaptcha_render_newreply()
{
    global $mybb, $lazycaptcha;
    if (empty($mybb->settings['lazycaptcha_enable_posting']) || lazycaptcha_should_skip_for_user()) {
        $lazycaptcha = '';
        return;
    }
    $lazycaptcha = lazycaptcha_widget_html();
}

function lazycaptcha_verify_newreply()
{
    global $mybb, $post_errors;
    if (empty($mybb->settings['lazycaptcha_enable_posting']) || lazycaptcha_should_skip_for_user()) {
        return;
    }
    $result = lazycaptcha_verify_token();
    if (empty($result['success'])) {
        if (!is_array($post_errors)) $post_errors = [];
        $post_errors[] = 'CAPTCHA verification failed. Please try again.';
    }
}
