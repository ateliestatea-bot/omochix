<?php
/**
 * Contact form markup for the contact page.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_contact_status = isset($_GET['omochix_contact']) ? sanitize_key(wp_unslash($_GET['omochix_contact'])) : '';
$omochix_contact_reason = isset($_GET['reason']) ? sanitize_key(wp_unslash($_GET['reason'])) : '';
$omochix_privacy_url    = omochix_get_published_page_url('privacy-policy');

$omochix_contact_error_messages = [
    'validation' => __('入力内容をご確認ください。お名前・メールアドレス・お問い合わせ内容の入力と、プライバシーポリシーへの同意が必要です。', 'omochix'),
    'nonce'      => __('セッションの有効期限が切れた可能性があります。もう一度入力してお試しください。', 'omochix'),
    'mail'       => __('送信中に問題が発生しました。時間をおいて再度お試しください。', 'omochix'),
];
?>

<div class="contact-form">
    <?php if ('success' === $omochix_contact_status) : ?>
        <div class="contact-form__banner contact-form__banner--success" role="status">
            <p><?php esc_html_e('お問い合わせを受け付けました。内容を確認のうえ、担当者よりご連絡いたします。', 'omochix'); ?></p>
        </div>
    <?php elseif ('error' === $omochix_contact_status) : ?>
        <div class="contact-form__banner contact-form__banner--error" role="alert">
            <p>
                <?php
                echo esc_html(
                    $omochix_contact_error_messages[$omochix_contact_reason]
                        ?? __('送信できませんでした。入力内容をご確認のうえ、もう一度お試しください。', 'omochix')
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form class="contact-form__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="omochix_contact_submit">
        <?php wp_nonce_field('omochix_contact_submit', 'omochix_contact_nonce'); ?>

        <div class="contact-form__honeypot" aria-hidden="true">
            <label for="contact-form-website"><?php esc_html_e('Webサイト', 'omochix'); ?></label>
            <input type="text" id="contact-form-website" name="omochix_contact_website" tabindex="-1" autocomplete="off">
        </div>

        <div class="contact-form__field">
            <label for="contact-form-name"><?php esc_html_e('お名前', 'omochix'); ?> <span class="contact-form__required" aria-hidden="true">*</span></label>
            <input type="text" id="contact-form-name" name="omochix_contact_name" autocomplete="name" maxlength="200" required>
        </div>

        <div class="contact-form__field">
            <label for="contact-form-email"><?php esc_html_e('メールアドレス', 'omochix'); ?> <span class="contact-form__required" aria-hidden="true">*</span></label>
            <input type="email" id="contact-form-email" name="omochix_contact_email" autocomplete="email" required>
        </div>

        <div class="contact-form__field contact-form__field--message">
            <label for="contact-form-message"><?php esc_html_e('お問い合わせ内容', 'omochix'); ?> <span class="contact-form__required" aria-hidden="true">*</span></label>
            <textarea id="contact-form-message" name="omochix_contact_message" rows="6" maxlength="5000" required></textarea>
        </div>

        <div class="contact-form__consent">
            <label>
                <input type="checkbox" name="omochix_contact_consent" value="1" required>
                <?php if ($omochix_privacy_url) : ?>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: privacy policy link markup. */
                            __('%sに同意する', 'omochix'),
                            '<a href="' . esc_url($omochix_privacy_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('プライバシーポリシー', 'omochix') . '</a>'
                        ),
                        ['a' => ['href' => [], 'target' => [], 'rel' => []]]
                    );
                    ?>
                <?php else : ?>
                    <?php esc_html_e('プライバシーポリシーに同意する', 'omochix'); ?>
                <?php endif; ?>
            </label>
        </div>

        <button type="submit" class="static-page__button static-page__button--primary contact-form__submit">
            <?php esc_html_e('送信する', 'omochix'); ?>
        </button>
    </form>
</div>
