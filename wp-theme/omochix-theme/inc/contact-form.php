<?php
/**
 * Contact form submission handling.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve where to send the visitor back to after a submission attempt.
 *
 * @return string
 */
function omochix_get_contact_redirect_url() {
    $referer = wp_get_referer();
    if ($referer) {
        return $referer;
    }

    $contact_url = omochix_get_published_page_url('contact');

    return $contact_url ? $contact_url : home_url('/');
}

/**
 * Redirect back to the contact page with a status flag and stop execution.
 *
 * @param string $status 'success' or 'error'.
 * @param string $reason Optional machine-readable error reason.
 * @return void
 */
function omochix_contact_redirect($status, $reason = '') {
    $url  = remove_query_arg(['omochix_contact', 'reason'], omochix_get_contact_redirect_url());
    $args = ['omochix_contact' => $status];
    if ($reason) {
        $args['reason'] = $reason;
    }

    wp_safe_redirect(add_query_arg($args, $url));
    exit;
}

/**
 * Handle contact form submissions for logged-in and anonymous visitors alike.
 *
 * @return void
 */
function omochix_handle_contact_submission() {
    $nonce = isset($_POST['omochix_contact_nonce']) ? sanitize_text_field(wp_unslash($_POST['omochix_contact_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'omochix_contact_submit')) {
        omochix_contact_redirect('error', 'nonce');
    }

    // Honeypot: bots tend to fill every field that looks real. Pretend success without sending mail.
    if (!empty($_POST['omochix_contact_website'])) {
        omochix_contact_redirect('success');
    }

    $name    = isset($_POST['omochix_contact_name']) ? sanitize_text_field(wp_unslash($_POST['omochix_contact_name'])) : '';
    $email   = isset($_POST['omochix_contact_email']) && is_string($_POST['omochix_contact_email'])
        ? sanitize_email(wp_unslash($_POST['omochix_contact_email']))
        : '';
    $message = isset($_POST['omochix_contact_message']) ? sanitize_textarea_field(wp_unslash($_POST['omochix_contact_message'])) : '';
    $consent = isset($_POST['omochix_contact_consent']) && '1' === $_POST['omochix_contact_consent'];

    $name    = mb_substr($name, 0, 200);
    $message = mb_substr($message, 0, 5000);

    if ('' === $name || '' === $message || !is_email($email) || !$consent) {
        omochix_contact_redirect('error', 'validation');
    }

    $to = get_option('admin_email');
    if (!is_email($to)) {
        omochix_contact_redirect('error', 'mail');
    }

    $subject = sprintf(
        /* translators: %s: site name. */
        __('[%s] お問い合わせを受け付けました', 'omochix'),
        wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
    );

    $body = sprintf(
        "%s\n\n%s: %s\n%s: %s\n\n%s:\n%s\n",
        __('OmochiXのお問い合わせフォームから送信されました。', 'omochix'),
        __('お名前', 'omochix'),
        $name,
        __('メールアドレス', 'omochix'),
        $email,
        __('お問い合わせ内容', 'omochix'),
        $message
    );

    $header_name = str_replace(['<', '>', ',', "\r", "\n"], '', $name);
    $headers     = ['Content-Type: text/plain; charset=UTF-8'];
    $headers[]   = sprintf('Reply-To: %s <%s>', $header_name, $email);

    $sent = wp_mail($to, $subject, $body, $headers);

    if (!$sent) {
        omochix_contact_redirect('error', 'mail');
    }

    omochix_contact_redirect('success');
}
add_action('admin_post_omochix_contact_submit', 'omochix_handle_contact_submission');
add_action('admin_post_nopriv_omochix_contact_submit', 'omochix_handle_contact_submission');
