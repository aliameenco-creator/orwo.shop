# ORWO shop

A hand-built WooCommerce storefront for orwo.shop: six formats (photos, DVDs, Blu-ray, posters, vinyl and CDs) with
our own filters, clean URLs and a chatbot that needs no API key.

Two code units, as planned:

| Unit | Where in this repository | Holds |
| --- | --- | --- |
| `orwo-shop` (theme, named "ORWO") | the repository root | Design, templates, homepage, chatbot widget, SEO, security defaults |
| `orwo-core` (plugin) | `plugin/orwo-core/` | Formats and fields, taxonomies, URLs, filter engine, REST, chatbot index, enquiries, CSV import and export, login hardening |

The theme sits at the root so the release packages and the built-in updater line up with the other sites in this
account. Build both with `python scripts/package.py` and `python scripts/package-plugin.py`; the packages are
attached to each release rather than committed.

Keeping the catalogue in the plugin means a redesign never touches the products.

## Installing

1. Install and activate **WooCommerce**.
2. Install and activate **ORWO Core** (`dist/orwo-core.zip`).
3. Install and activate the **ORWO** theme (`dist/orwo-shop.zip`).
4. Go to **Appearance → ORWO Setup** and press *Run the ORWO setup*. It creates the About, Shipping, Returns, FAQ,
   Contact and Track order pages, leaves the privacy policy and terms as drafts for the owner to write, builds the
   three footer menus and seeds the first set of chat answers. Running it again never overwrites a page you edited.
5. Go to **Settings → ORWO security** and set a private sign-in address. **Write it down.**
6. Set the enquiry address under **Ask ORWO → Settings**, and the hero under **Appearance → Homepage options**.
7. When the shop is ready to open, switch off WooCommerce's *Coming soon* mode
   (WooCommerce → Settings → General → Site visibility). A new WooCommerce install turns this on by itself.

## URLs

Every product sits exactly two levels deep, and filters live in the query string so any view can be shared.

| Page | URL |
| --- | --- |
| Format | `/photos/` `/dvds/` `/blu-ray/` `/posters/` `/vinyl/` `/cds/` |
| Product | `/vinyl/night-ferry-the-harbour-trio/` |
| Genre, creator, label | `/genre/jazz/` `/by/miles-davis/` `/label/blue-note/` |
| Automatic archives | `/new/` `/sale/` |
| Search | `/search/?q=coltrane` |
| Filtered view | `/vinyl/?genre=jazz&condition=new&sort=price-asc` |
| WooCommerce's own | `/cart/` `/checkout/` `/my-account/` |

Rewrite rules are registered, but every custom path is also resolved in a `request` filter, so a host that never
flushes its rewrite rules still serves these URLs. Renaming a product keeps its old URL working (301), and a
trashed product redirects to its format page.

Filtered and search views are `noindex, follow` with a canonical link to the base page. Genre, creator and label
pages are indexable.

## Adding a product

Products → Add new → pick the **Format**; the screen then shows only that format's fields. Genre, creator and label
are typed with suggestions from the existing terms, which stops the catalogue collecting "Miles Davis" twice. Fill
the price, stock and condition, upload a main image (at least 1600 px on the long side) and up to five gallery
images, then publish. The product appears in its format page, in New arrivals, in the filter counts and in the
chatbot index with no further steps.

Bulk work: **Products → Import and export**, one CSV template per format, matched on SKU so a re-run updates rather
than duplicates. The importer has a dry run, which is on by default.

## How the filters work

1. `orwo-core` whitelists one query variable per filter; anything else in the query string is ignored.
2. `pre_get_posts` turns them into a `tax_query` and `meta_query` on the normal product query. **This is the
   version that works with JavaScript switched off.**
3. `/wp-json/orwo/v1/products` returns the grid HTML and the option counts for the same query, built by the same
   parser, so the two cannot drift apart. The script swaps the grid in place and updates the URL.
4. Counts are cached and cleared whenever a product is saved. Options with no results are disabled.
5. Price, stock, sale and best-selling read WooCommerce's product lookup table rather than meta queries. Sold-out
   items stay visible with a *Sold out* label and sort last.

Format, genre, creator, label, decade and condition are taxonomies rather than meta, so filtering and counting use
indexed term relationships. The decade is derived from the Year field on save.

## Ask ORWO

A guided finder plus a small search engine that runs in the visitor's browser over the shop's own catalogue and the
FAQ answers written in the admin. No outside requests, no API key.

- The index is one JSON file in `uploads/orwo-chat/`, rebuilt when a product is saved, trashed or its stock
  changes, and served as a static file so the CDN can cache it.
- The guided finder reads the same filter definitions the shop uses; they travel in the index.
- A typed question is cleaned, run through a synonym list ("record" → vinyl, "movie" → DVD and Blu-ray) and scored:
  title and creator first, then genre and format, then keywords. A weak match ends at the Ask a question form.
- Questions it could not match are logged, text only, under **Ask ORWO → Unanswered questions**.
- Enquiries are saved as records *and* emailed, so nothing is lost if mail fails.

This suits a few thousand products. Beyond that the same matching moves to a server endpoint and the widget stays
as it is.

## Security

`Settings → ORWO security` moves sign-in off `/wp-login.php`. Those addresses and `/wp-admin` then answer 404 to
anyone not signed in, so scanners find nothing; password resets, logout and `admin-ajax.php` keep working. Also on:
attempt rate limiting, vague login errors, no username harvesting through author archives or the users REST route,
XML-RPC off, no file editing in the admin, and customers sent to their account page rather than the admin.

**You cannot be locked out.** Put either line in `wp-config.php` over FTP or the file manager:

```php
define( 'ORWO_LOGIN_SLUG', 'your-own-address' ); // Override the stored address.
define( 'ORWO_LOGIN_DISABLE', true );            // Back to the standard WordPress addresses.
```

A private address is one layer, not the whole defence: two-step login for every administrator and shop manager,
through one reviewed plugin, is still the thing that matters most.

## Speed

Measured in the local sandbox:

| Measure | Budget | Measured |
| --- | --- | --- |
| CSS, one file | under 40 KB compressed | **5.8 KB** |
| JavaScript on browsing pages | under 60 KB compressed | **5.3 KB**, plus 5.9 KB for the chatbot loaded on idle |
| Fonts | — | 15.3 KB (Poppins 400 and 500, Latin, self-hosted) |
| jQuery on browsing pages | none | none (home, archives, simple product pages) |
| Layout shift while scrolling | CLS under 0.1 | 0.0000 |

WooCommerce's own styles and scripts load only on its own pages. jQuery appears in exactly two places: a *variable*
product page, where WooCommerce's variation script needs it, and cart/checkout. The cart count comes from a tiny
endpoint called only when a cart cookie exists, so browsing pages stay cacheable.

**Cart and checkout: classic, not blocks.** Measured in the sandbox:

| | Classic | Blocks |
| --- | --- | --- |
| `/cart/` | 108 KB JS, 57 KB CSS | 130 KB JS, 139 KB CSS |
| `/checkout/` | **124 KB JS**, 43 KB CSS | **929 KB JS**, 167 KB CSS |

The plan said to test both and keep the lighter one, so the pages use the classic shortcodes and the theme styles
that markup. Switching back is two page edits if a block-only payment method ever requires it.

## Testing

```powershell
python tools/check_orwoshop_theme.py     # 153 checks in the sandbox on port 8904
python wordpress-theme/orwo-shop/scripts/package.py         # dist/orwo-shop.zip
python wordpress-theme/orwo-shop/scripts/package-plugin.py  # dist/orwo-core.zip
```

The sandbox is built by `tools/bootstrap_orwoshop_wordpress.php` and filled by
`tools/seed_orwoshop_catalogue.php` (invented test data, not real stock).

## Updates

`Appearance → ORWO Updates` installs numbered GitHub releases, with the SHA-256 digest and the package contents
checked before anything is written. **No repository is hard-coded**: set it once on that screen or through
`ORWO_GITHUB_REPO` in `wp-config.php`. A private repository needs a read-only, repository-restricted token, pasted
once in the admin; it is stored in the site's database only and never forwarded to a download host.

## What is not built yet

Phases 5 and 6 of the plan need decisions and accounts, not code: Stripe and PayPal in test mode, the UK shipping
zone and rates, VAT settings, the order and customer emails, and the launch checks. The product templates, filters,
homepage and chatbot are done.
