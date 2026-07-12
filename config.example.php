<?php
/**
 * Contact form config template.
 *
 * Copy this file to config.php (same folder) and fill in real values.
 * config.php is gitignored and must never be committed — it holds live
 * secrets. This example file is safe to commit because every value below
 * is a placeholder.
 */

return [
    // Google Workspace mailbox that sends both the notification and the
    // confirmation email. Requires 2-Step Verification on the account and
    // an App Password: Google Account > Security > 2-Step Verification >
    // App passwords.
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_user'     => 'info@vandervolpi.com',
    'smtp_password' => 'REPLACE_WITH_APP_PASSWORD',
    'from_email'    => 'info@vandervolpi.com',
    'from_name'     => 'Van der Volpi',

    // Where the notification email lands.
    'to_email' => 'info@vandervolpi.com',
    'to_name'  => 'Van der Volpi',

    // Google reCAPTCHA v3 secret key. Register vandervolpi.com,
    // dev.vandervolpi.com and localhost (for testing) at
    // https://www.google.com/recaptcha/admin
    'recaptcha_secret_key' => 'REPLACE_WITH_RECAPTCHA_SECRET_KEY',
    'recaptcha_min_score'  => 0.5,

    // Set to true only for local testing without real reCAPTCHA keys.
    // Must be false in every deployed environment (dev and live).
    'recaptcha_disable' => false,
];
