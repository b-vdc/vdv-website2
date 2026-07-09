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
| `Contact.php` | Contact details; **placeholders** for the contact form and online booking (v1 ships without them) |
| `Privacy-Policy.php` · `Cookie-Policy.php` · `Disclaimer.php` | Legal pages (text carried over from v1) |
| `Social-Media-Rules.html` · `Intellectual-Property.html` · `Contracts.html` | Redirect stubs – these v1 pages merged into `Advice.php`. Plain static HTML (no shared markup, so no need for PHP) |
| `includes/head.php` | Shared `<head>`: doctype, meta, title/description, favicon, stylesheet, optional Open Graph tags |
| `includes/header.php` | Shared site header/nav, highlights the current page via an `$active` variable |
| `includes/footer.php` | Shared site footer, plus the closing `<script>`/`</body>`/`</html>` |
| `css/style.css` | Full design system (brandbook tokens, speech bubbles, layout) |
| `js/main.js` | Mobile nav, scroll reveals, footer year (vanilla JS) |
| `images/` | Photos + generated placeholder images |

## Conventions

- **Brand assets are not in this repo.** Logos and fonts load from the protected asset host `https://vandervolpi.com/assets/...` (documented in the brandbook), which is never overwritten by site deploys.
- **Placeholder images** are SVGs watermarked "PLACEHOLDER"; each one states what real image should replace it. Placeholder feature blocks (form, booking) carry a visible badge.
- No cookies, no analytics, no cookie banner in v1.
- Page filenames keep v1's capitalisation (`Training.php`, `Privacy-Policy.php`) so existing inbound links keep working, aside from the `.html` → `.php` extension change.
- Every page sets `$title`, `$description`, `$active` (and optionally `$ogDescription`, `$robots`) before including `includes/head.php`, then includes `includes/header.php` and `includes/footer.php` around its own `<main>` content.

## Local preview

Requires PHP. Use the built-in server:

```
php -S localhost:8123
```

then open http://localhost:8123. A plain static server (e.g. `python -m http.server`) will not execute the `.php` pages – it will only serve their raw source.
