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
| `Contact.html` | Contact details; **placeholders** for the contact form and online booking (v1 ships without them) |
| `Privacy-Policy.html` · `Cookie-Policy.html` · `Disclaimer.html` | Legal pages (text carried over from v1) |
| `Social-Media-Rules.html` · `Intellectual-Property.html` · `Contracts.html` | Redirect stubs — these v1 pages merged into `Advice.html` |
| `css/style.css` | Full design system (brandbook tokens, speech bubbles, layout) |
| `js/main.js` | Mobile nav, scroll reveals, footer year (vanilla JS) |
| `images/` | Photos + generated placeholder images |

## Conventions

- **Brand assets are not in this repo.** Logos and fonts load from the protected asset host `https://vandervolpi.com/assets/...` (documented in the brandbook), which is never overwritten by site deploys.
- **Placeholder images** are SVGs watermarked "PLACEHOLDER"; each one states what real image should replace it. Placeholder feature blocks (form, booking) carry a visible badge.
- No cookies, no analytics, no cookie banner in v1.
- Page filenames keep v1's capitalisation (`Training.html`, `Privacy-Policy.html`) so existing inbound links keep working.

## Local preview

Any static server works, e.g.:

```
python -m http.server 8123
```

then open http://localhost:8123.
