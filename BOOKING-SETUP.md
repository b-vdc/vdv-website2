# Appointment booker setup

The booker on the Contact page reads availability from the Google Calendar of
`info@vandervolpi.com` and creates events on it. For that it needs a Google
Cloud **service account** that is allowed to act on behalf of that mailbox
(domain-wide delegation). One-time setup, roughly 15 minutes.

## 1. Google Cloud: service account + key

1. Go to [console.cloud.google.com](https://console.cloud.google.com) and sign
   in as `info@vandervolpi.com`. Create a project (e.g. `vdv-website`) or reuse
   an existing one.
2. **APIs & Services > Library**: search for **Google Calendar API** and enable it.
3. **IAM & Admin > Service Accounts > Create service account**. Name it e.g.
   `vdv-booking`. No roles or user access needed; just create it.
4. Open the new service account and note two things:
   - the **email** (`vdv-booking@...iam.gserviceaccount.com`)
   - the **Unique ID** (a long number; this is the client ID you need in step 2)
5. Tab **Keys > Add key > Create new key > JSON**. A `.json` file downloads.
   Treat it like a password.

## 2. Workspace Admin: allow the service account to act as you

1. Go to [admin.google.com](https://admin.google.com) (Workspace admin console).
2. **Security > Access and data control > API controls > Manage domain-wide
   delegation > Add new**.
3. **Client ID**: the Unique ID from step 1.4.
4. **OAuth scopes** (comma separated, exactly these two):

   ```
   https://www.googleapis.com/auth/calendar.events,https://www.googleapis.com/auth/calendar.freebusy
   ```

5. Authorise. These scopes only allow reading free/busy times and managing
   calendar events; the service account cannot read mail or anything else.

## 3. config.php on the server

Open the downloaded JSON key file in a text editor and copy its values into
the `google_service_account` array in `config.php` (see `config.example.php`
for the shape). The booker needs `client_email`, `private_key` and
`token_uri`; copying the whole file's fields is also fine.

Check the rest of the booking settings while you're there:

| Key | Meaning | Default |
| --- | --- | --- |
| `booking_days` | Weekdays that take appointments (Mon=1 .. Sun=7) | Mon-Fri |
| `booking_window_start` / `_end` | Daily window for appointments | 09:00-17:00 |
| `booking_lead_hours` | No bookings sooner than this many hours from now | 48 |
| `booking_horizon_days` | How far ahead people can book | 28 |
| `booking_buffer_minutes` | Free time kept around existing calendar events | 30 |
| `booking_types` | The call types with event duration and price label | intake 20 min, training 60 min, legal 60 min |

Make sure `booking_disable_google` and `recaptcha_disable` are `false`.

## 4. Smoke test

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
  Calendar API. Check the PHP error log; the most common causes are a wrong
  `private_key` in config.php (must keep its `\n` line breaks) or the
  domain-wide delegation client ID/scopes not matching step 2 exactly.
- **Slots show, booking fails**: same log; if the event insert is rejected
  check that the Calendar API is enabled and `booking_calendar_id` is right.
- Bookings are calendar events, nothing more: to cancel one, delete the event
  in Google Calendar (Google emails the visitor), and the slot frees up
  automatically.
