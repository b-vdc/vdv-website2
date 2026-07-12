<?php
/**
 * Site config template (contact form + appointment booker).
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

    // ---- Appointment booker (see BOOKING-SETUP.md) -----------------------

    // Calendar that availability is read from and events are created on,
    // and the Workspace user the service account acts as. Usually the same
    // mailbox for both.
    'booking_calendar_id' => 'info@vandervolpi.com',
    'booking_impersonate' => 'info@vandervolpi.com',

    // When appointments can be booked: ISO weekdays (Mon=1 .. Sun=7), the
    // daily window, how many hours ahead a booking must be made, and how
    // many days ahead the calendar is offered. Slots start on the hour.
    'booking_timezone'       => 'Europe/Brussels',
    'booking_days'           => [1, 2, 3, 4, 5],
    'booking_window_start'   => '09:00',
    'booking_window_end'     => '17:00',
    'booking_lead_hours'     => 48,
    'booking_horizon_days'   => 28,

    // Minutes kept free between a booking and any existing calendar event.
    'booking_buffer_minutes' => 30,

    // The three bookable call types. 'duration' is the calendar event
    // length in minutes; slots always start hourly regardless of duration.
    'booking_types' => [
        'intake'   => ['label' => 'Intake call',      'duration' => 20, 'price' => 'Free'],
        'training' => ['label' => 'Training booking', 'duration' => 60, 'price' => 'Free'],
        'legal'    => ['label' => 'Legal session',    'duration' => 60, 'price' => '€170/hour'],
    ],

    // Service account with domain-wide delegation for the scopes
    // https://www.googleapis.com/auth/calendar.events and
    // https://www.googleapis.com/auth/calendar.freebusy.
    // Paste the decoded contents of the downloaded JSON key file here
    // (BOOKING-SETUP.md walks through creating it). The three keys below
    // are the ones the booker uses; keeping the rest of the JSON is fine.
    'google_service_account' => [
        'client_email' => 'REPLACE@REPLACE.iam.gserviceaccount.com',
        'private_key'  => "-----BEGIN PRIVATE KEY-----\nREPLACE\n-----END PRIVATE KEY-----\n",
        'token_uri'    => 'https://oauth2.googleapis.com/token',
    ],

    // Set to true only for local testing without Google credentials: the
    // calendar acts empty and events are logged instead of created.
    // Must be false in every deployed environment (dev and live).
    'booking_disable_google' => false,
];
