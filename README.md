# Van der Volpi – website

The website of [vandervolpi.com](https://vandervolpi.com): legal training and hands-on legal support for brands, agencies and creators.

Version 2 – a full rebuild in HTML, CSS, JavaScript and PHP (no site builder, no framework), styled strictly to the [Van der Volpi brandbook](https://github.com/b-vdc/vdv-brandbook).

Pages are `.php`: the header, footer and `<head>` boilerplate live once in `includes/` and are shared via `include()`, instead of being copy-pasted into every page.

## Structure

| Path | What it is |
|---|---|
| `index.php` | Home |
| `Training.php` | Trainings: audiences, topics, the four tracks |
| `Advice.php` | Legal support for brands and creators (social media, IP, contracts) |
| `About.php` | About Elisa + brand values |
| `Contact.php` | Contact details, the contact form and the appointment booker |
| `contact-submit.php` | JSON endpoint for the contact form (reCAPTCHA v3 + PHPMailer, see `config.example.php`) |
| `booking-slots.php` | JSON endpoint: available booking slots (configured schedule minus Google Calendar busy times) |
| `booking-submit.php` | JSON endpoint: books a slot – creates the Google Calendar event (visitor invited, Meet link) and sends the confirmation emails |
| `includes/form-helpers.php` | Shared endpoint helpers: rate limiting, HTTP, reCAPTCHA verify, mailer |
| `includes/booking-lib.php` | Slot computation (weekdays, window, lead time, horizon, buffer) |
| `includes/google-calendar.php` | Minimal Google Calendar client (service account, freeBusy, event insert) – setup in [BOOKING-SETUP.md](BOOKING-SETUP.md) |
| `Privacy-Policy.php` · `Cookie-Policy.php` · `Disclaimer.php` | Legal pages (text carried over from v1) |
| `Social-Media-Rules.html` · `Intellectual-Property.html` · `Contracts.html` | Redirect stubs – these v1 pages merged into `Advice.php`. Plain static HTML (no shared markup, so no need for PHP) |
| `.htaccess` | Apache rewrite rules: serves clean lowercase URLs (`/about`) from the `.php` files and 301-redirects the old `.php`/capitalised URLs |
| `router.php` | Local-preview only: emulates `.htaccess` for PHP's built-in server (which ignores `.htaccess`) |
| `includes/head.php` | Shared `<head>`: doctype, meta, title/description, favicon, stylesheet, optional Open Graph tags |
| `includes/header.php` | Shared site header/nav, highlights the current page via an `$active` variable |
| `includes/footer.php` | Shared site footer, plus the closing `<script>`/`</body>`/`</html>` |
| `css/style.css` | Full design system (brandbook tokens, speech bubbles, layout) |
| `js/main.js` | Mobile nav, scroll reveals, footer year (vanilla JS) |
| `js/consent.js` | Cookie consent banner + reCAPTCHA loader |
| `js/contact.js` · `js/booking.js` | Contact form and appointment booker behavior (Contact page only) |
| `images/` | Photos + generated placeholder images |

## Conventions

- **Brand assets are not in this repo.** Logos and fonts load from the protected asset host `https://vandervolpi.com/assets/...` (documented in the brandbook), which is never overwritten by site deploys.
- **Placeholder images** are SVGs watermarked "PLACEHOLDER"; each one states what real image should replace it. Placeholder feature blocks (form, booking) carry a visible badge.
- No cookies, no analytics, no cookie banner in v1.
- Public URLs are clean, lowercase and extensionless (`/about`, `/privacy-policy`, `/` for home). Apache's `.htaccess` maps each URL to its `.php` file, so the files keep v1's capitalisation (`Training.php`, `Privacy-Policy.php`) internally while never exposing it. Old capitalised and/or `.php` URLs 301-redirect to the clean form, so existing inbound links keep working.
- Every page sets `$title`, `$description`, `$active` (and optionally `$ogDescription`, `$robots`) before including `includes/head.php`, then includes `includes/header.php` and `includes/footer.php` around its own `<main>` content.

## Local preview

Requires PHP. Use the built-in server with the router (so clean URLs and redirects work as in production):

```
php -S localhost:8123 router.php
```

then open http://localhost:8123. The `router.php` argument is required for the clean-URL routing; without it the built-in server ignores `.htaccess` and only the raw `.php` paths would resolve. A plain static server (e.g. `python -m http.server`) will not execute the `.php` pages – it will only serve their raw source.

The contact form and booker need `config.php` (copy `config.example.php`) and `composer install`. For local testing without real keys set `recaptcha_disable` and `booking_disable_google` to `true`. Deploying the booker for real requires the one-time Google setup in [BOOKING-SETUP.md](BOOKING-SETUP.md).
