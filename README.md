# Van der Volpi — website

The website of [vandervolpi.com](https://vandervolpi.com): legal training and hands-on legal support for brands, agencies and creators.

Version 2 — a full rebuild in plain HTML, CSS and JavaScript (no site builder, no framework), styled strictly to the [Van der Volpi brandbook](https://github.com/b-vdc/vdv-brandbook).

## Structure

| Path | What it is |
|---|---|
| `index.html` | Home |
| `Training.html` | Trainings: audiences, topics, the four tracks |
| `Advice.html` | Legal support for brands and creators (social media, IP, contracts) |
| `About.html` | About Elisa + brand values |
| `Contact.html` | Contact details + a working contact form. **Online booking is still a placeholder.** |
| `Privacy-Policy.html` · `Cookie-Policy.html` · `Disclaimer.html` | Legal pages (text carried over from v1, updated for the contact form + reCAPTCHA) |
| `Social-Media-Rules.html` · `Intellectual-Property.html` · `Contracts.html` | Redirect stubs — these v1 pages merged into `Advice.html` |
| `css/style.css` | Full design system (brandbook tokens, speech bubbles, layout) |
| `js/main.js` | Mobile nav, scroll reveals, footer year (vanilla JS) |
| `js/consent.js` | Cookie consent banner + reCAPTCHA loader, included on every page |
| `js/contact.js` | Contact form submit handling, Contact page only |
| `contact.php` | Server endpoint for the contact form: validates input, verifies reCAPTCHA, sends a notification to `info@vandervolpi.com` and a confirmation to the sender via Google Workspace SMTP |
| `config.example.php` | Template for `config.php` (gitignored secrets: SMTP App Password, reCAPTCHA secret key) |
| `composer.json` | Declares the PHPMailer dependency used by `contact.php` |
| `images/` | Photos + generated placeholder images |

## Conventions

- **Brand assets are not in this repo.** Logos and fonts load from the protected asset host `https://vandervolpi.com/assets/...` (documented in the brandbook), which is never overwritten by site deploys.
- **Placeholder images** are SVGs watermarked "PLACEHOLDER"; each one states what real image should replace it. The online-booking placeholder block carries a visible badge.
- No analytics, no advertising cookies. The only non-essential cookie is Google reCAPTCHA on the contact form, gated behind the cookie banner (`js/consent.js`); consent can be changed any time from the "Manage cookie preferences" button on the Cookie Policy page.
- Page filenames keep v1's capitalisation (`Training.html`, `Privacy-Policy.html`) so existing inbound links keep working.

## Local preview

Static files: any static server works, e.g.

```
python -m http.server 8123
```

then open http://localhost:8123. Without a PHP server running, the contact form's fetch to
`contact.php` will 404 — that's expected for a pure static preview.

To exercise the contact form locally:

1. `composer install` (fetches PHPMailer into `vendor/`).
2. `cp config.example.php config.php` and fill in real SMTP credentials, or set
   `'recaptcha_disable' => true` plus test SMTP details to try it without live reCAPTCHA keys.
3. `php -S localhost:8123` (serves both the static files and `contact.php` together).

## Deploying

After pulling changes on the server: run `composer install` (PHPMailer isn't committed to the
repo) and make sure `config.php` exists there with live SMTP + reCAPTCHA credentials — it is
gitignored and must be created directly on the server, not deployed from a branch.
