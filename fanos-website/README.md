# FANOS public website — Phase 1

A warm, accessible, mobile-first WordPress site whose centrepiece is the
**Alchemizing Stories** library, with a clear SHARE overview, partner and contact
pathways, a newsletter signup, and a CMS the FANOS team can update without a developer.

This directory implements the build described in the FANOS Website Proposal (Phase 1:
*Alchemizing Stories Hub and public home*).

## Architecture

The site is split into a **plugin** (portable data model and integrations) and a
**theme** (presentation), which is the WordPress best-practice separation the proposal
calls for — content and integrations survive any future theme change.

```
fanos-website/
├── wp-content/
│   ├── plugins/fanos-core/        # Data model + integrations (unit-tested)
│   │   ├── fanos-core.php         # Plugin bootstrap
│   │   └── src/
│   │       ├── Plugin.php         # Module orchestrator
│   │       ├── PostTypes/         # Episode + Document post types
│   │       ├── Taxonomies/        # Series / Topic / Audience
│   │       ├── Library/           # QueryFilter (browse/search) + Youtube helper
│   │       ├── Forms/             # Sanitizer, Validator (incl. no-PHI), FormHandler
│   │       ├── Newsletter/        # Mailchimp / MailerLite adapters
│   │       ├── Analytics/         # GA4 event wiring (downloads, YouTube, signups, forms)
│   │       └── Seo/               # Meta descriptions, Open Graph, episode JSON-LD
│   └── themes/fanos/              # Presentation: templates, styles, JS
│       ├── theme.json             # Brand palette + typography (block-editor aware)
│       ├── front-page.php         # Home
│       ├── archive-episode.php    # Alchemizing Stories Hub (filter/search)
│       ├── single-episode.php     # Episode template
│       ├── page-share.php         # SHARE (with "in development" status)
│       ├── page-contact.php       # Contact (secure form, no PHI)
│       ├── page-partners.php      # Partners / Get Involved
│       ├── page-initiatives.php   # Initiatives overview
│       ├── page-updates.php       # Updates / Newsletter
│       └── inc/                   # Template tags + form rendering
└── tests/                         # PHPUnit unit tests (Brain Monkey)
```

### How the proposal scope maps to the code

| Proposal item | Where it lives |
|---|---|
| Alchemizing Stories library, browse by series/topic/audience/date + search | `Library/QueryFilter.php`, `archive-episode.php`, `inc/template-tags.php` |
| Episode template: YouTube embed (no autoplay), takeaways, transcript, sources, downloads, related | `single-episode.php`, `Library/Youtube.php` |
| Bilingual (English/Amharic) content on one page | Episode body + `fanos_language` meta |
| Secure contact / SHARE / partnership forms, spam protection, **no PHI** | `Forms/*`, `inc/forms.php` |
| Newsletter signup (Mailchimp / MailerLite) | `Newsletter/NewsletterSubscriber.php` |
| Basic analytics (GA4): downloads, YouTube, signups, form submits | `Analytics/Analytics.php`, `assets/analytics.js` |
| SEO foundations: titles, descriptions, sitemap, structured data | `Seo/Seo.php` (+ WordPress core `wp-sitemap.xml`) |
| Brand palette + accessibility (WCAG 2.1 AA target) | `theme.json`, `assets/css/main.css`, `style.css` |
| Content types the team manages without code | `PostTypes/*`, `Taxonomies/*` (all `show_in_rest`) |

## Requirements

- PHP 8.1+
- WordPress 6.4+
- Composer (development only — there are **no runtime Composer dependencies**; the
  plugin ships its own PSR-4 autoloader fallback)

## Local development

```bash
cd fanos-website
composer install        # installs dev tooling (PHPUnit, Brain Monkey)
composer test           # run the unit tests
composer lint           # php -l over every PHP file
```

## Deploying to WordPress

1. Copy `wp-content/plugins/fanos-core` into your site's `wp-content/plugins/` and
   `wp-content/themes/fanos` into `wp-content/themes/`.
2. Activate the **FANOS Core** plugin, then the **FANOS** theme.
3. Create pages with these slugs so the matching templates load automatically:
   `share`, `contact`, `partners`, `initiatives`, `updates`. Add an About page too.
4. Set a static front page (Settings → Reading) to use the Home template.
5. Configure options as needed:
   - Newsletter: `fanos_newsletter` option — `provider`, `api_key`, `list_id`.
   - Analytics: `fanos_ga4_id` option (e.g. `G-XXXXXXX`).
   - Form recipient: filter `fanos_form_recipient` (defaults to the admin email).
6. Add episodes and documents; tag them with Series / Topic / Audience.

## Testing

The unit tests exercise the plugin's business logic without a running WordPress,
using [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) to shim WordPress
functions. Coverage focuses on the logic that is easy to get wrong and expensive to
debug in production:

- **Library filtering** — request params → `WP_Query` args (taxonomies, dates, search,
  sort, pagination), including sanitisation and de-duplication.
- **Forms** — sanitisation, validation, spam honeypot, and the **no-PHI** safeguard.
- **Newsletter** — provider request payloads and response interpretation.
- **YouTube** — id extraction from every URL shape, privacy-enhanced embeds.
- **SEO** — meta-description truncation and episode structured data.
- **Analytics** — GA4 id validation and privacy defaults.
- **Content types / taxonomies** — registration arguments.

Run them with `composer test` (89 tests at time of writing). CI runs the same across
PHP 8.1–8.4 (see `.github/workflows/fanos-website.yml`).

## Not included in Phase 1

Per the proposal, portals, SHARE applications/logins, payments/donations, and the
third initiative's pages are future phases. The data model and templates are structured
so these can be added without rebuilding what exists.
