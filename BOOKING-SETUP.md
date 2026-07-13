# Appointment booker setup

The booker on the Contact page reads availability from the Google Calendar of
`info@vandervolpi.com` and creates events on it. It authenticates with a
plain OAuth refresh token for that account (the Workspace organization policy
blocks service-account keys, so the earlier service-account approach is out).
One-time setup, roughly 15 minutes, all as `info@vandervolpi.com`.

## 1. Google Cloud: project + Calendar API + consent screen

1. Go to [console.cloud.google.com](https://console.cloud.google.com) and sign
   in as `info@vandervolpi.com`. Create a project (e.g. `vdv-website`) or reuse
   an existing one.
2. **APIs & Services > Library**: search for **Google Calendar API** and enable it.
3. **APIs & Services > OAuth consent screen** (newer consoles call it
   **Google Auth Platform > Branding/Audience**): configure it with app name
   e.g. `VDV booking`, your email as support/contact, and audience/user type
   **Internal**. Internal means only your Workspace can use it, nothing needs
   Google review, and the refresh token does not expire.

## 2. Create the OAuth client

1. **APIs & Services > Credentials > Create credentials > OAuth client ID**.
2. Application type **Web application**, name e.g. `vdv-booking`.
3. Under **Authorized redirect URIs** add exactly:

   ```
   http://localhost:8765/callback
   ```

   (Only used once, by the local consent helper in the next step.)
4. Create, then copy the **Client ID** and **Client secret** into the
   `google_oauth` array in `config.php` (see `config.example.php` for the shape).

## 3. Get the refresh token (one-time, on your own machine)

In a checkout of this repo with the `config.php` from step 2.4 present:

```
php -S localhost:8765 tools/google-oauth-consent.php
```

Open [http://localhost:8765](http://localhost:8765) in a browser, sign in as
`info@vandervolpi.com` and approve the two calendar permissions. The page then
shows the `refresh_token` line to paste into `google_oauth` in `config.php`.
Stop the helper with Ctrl+C.

Put the completed `google_oauth` values (client id, secret, refresh token)
into `config.php` **on the server** too. The token only allows managing
calendar events and reading free/busy times; you can revoke it any time at
[myaccount.google.com/connections](https://myaccount.google.com/connections).

## 4. Booking settings in config.php

| Key | Meaning | Default |
| --- | --- | --- |
| `booking_days` | Weekdays that take appointments (Mon=1 .. Sun=7) | Mon-Fri |
| `booking_window_start` / `_end` | Daily window for appointments | 09:00-17:00 |
| `booking_lead_hours` | No bookings sooner than this many hours from now | 48 |
| `booking_horizon_days` | How far ahead people can book | 28 |
| `booking_buffer_minutes` | Free time kept around existing calendar events | 30 |
| `booking_types` | The call types with event duration and price label | intake 20 min, training 60 min, legal 60 min |

Make sure `booking_disable_google` and `recaptcha_disable` are `false`.

## 5. Smoke test

1. On the live Contact page, pick a call type. Available days and times should
   appear; days that are fully booked in the calendar are grayed out.
2. Book a test slot with a personal email address. Expect:
   - the event on the `info@vandervolpi.com` calendar, with a Meet link and
     the visitor invited;
   - a Google Calendar invite in the personal mailbox;
   - the branded confirmation email in the personal mailbox;
   - the "New booking" notification in the business mailbox.
3. Delete the test event from the calendar and reload the booker: the slot
   should be offered again.
4. Create a normal calendar event somewhere in the coming weeks and confirm
   the overlapping slot (plus the 30-minute buffer around it) disappears.
5. Confirm `https://vandervolpi.com/data/google-token.json` returns 403.

## Troubleshooting

- **"Could not load availability right now"**: the server could not reach the
  Calendar API. Check the PHP error log. `invalid_grant` there means the
  refresh token was revoked or belongs to a different client id/secret pair;
  redo step 3.
- **`redirect_uri_mismatch` during step 3**: the redirect URI in the OAuth
  client does not exactly match `http://localhost:8765/callback`.
- **Consent shows but no refresh token comes back**: make sure you went
  through `tools/google-oauth-consent.php` (it forces `prompt=consent`), not
  a leftover browser session.
- **Slots show, booking fails**: check the error log; if the event insert is
  rejected, confirm the Calendar API is enabled and `booking_calendar_id` is
  right.
- Bookings are calendar events, nothing more: to cancel one, delete the event
  in Google Calendar (Google emails the visitor), and the slot frees up
  automatically.
