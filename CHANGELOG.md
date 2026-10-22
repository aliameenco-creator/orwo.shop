# Changelog

All notable changes to the ORWO theme and the ORWO Core plugin.

## 0.2.0 — 2026-10-22

Trailers on disc products, and the film catalogue.

### Added

- **Trailer field** on DVD and Blu-ray products. It takes a YouTube or Vimeo link or a bare video ID, and shows
  the trailer below the product. Nothing is fetched from the video host until the visitor presses play, so the
  page costs no third-party request and sets no outside cookies on arrival; the embed then uses YouTube's
  no-cookie domain. The field is kept out of the spec table, and travels in the CSV templates automatically.
- **Film catalogue**: `tools/build_orwoshop_film_catalogue.py` turns the film records behind the fourteen film
  websites into shop CSVs, one DVD and one Blu-ray per film, with poster, director, genres, year, runtime,
  distributor, the opening line of the synopsis and the trailer. Momma Bird is left out: it is unreleased, has no
  poster or trailer, and its own site has no buy section either.

### Changed

- The spec table lists every genre a product has, not just the first.
- The chatbot matches whole words and prefixes instead of any substring, and stems plurals only when a real word
  is left. Before this, a nonsense question could match an unrelated title ("this" matched "things") and answer
  confidently instead of handing the visitor to the Ask a question form.

## 0.1.0 — 2026-10-22

First build: phases 1 to 4 of the ORWO Shop plan.

### Theme (ORWO)

- Design tokens, one stylesheet (5.8 KB compressed), self-hosted Poppins in two weights.
- Three-layer header: announcement strip, main bar with search and cart, format bar with a mega menu per format
  built from the catalogue. Fixed, with a spacer so hiding the format bar on scroll shifts nothing (CLS 0).
- Full-screen phone menu with expanding format rows; archive pages get a bottom filter sheet.
- Homepage: hero with the chat entry bar and quick-start chips, six format tiles, New arrivals, editorial band,
  Limited and back in stock, trust row, genre links, footer with the newsletter sign-up. Every row except the hero
  and editorial band fills itself from the catalogue and hides itself when empty.
- One archive template for the shop, formats, genres, creators, labels, new, sale and search.
- Product page with our own gallery (no flexslider, no zoom script, no jQuery) and a spec table built from the
  fields, using WooCommerce's own add-to-cart form.
- SEO without a plugin: title pattern, meta description from "Why we like it", Open Graph, product and breadcrumb
  structured data, canonicals, noindex on filtered views, derived taxonomies kept out of the sitemap.
- Homepage options screen, ORWO Setup screen, GitHub release updater with no repository hard-coded.
- WooCommerce's cart, checkout and account pages kept and styled; classic versions chosen after measuring both.

### Plugin (ORWO Core)

- Six formats with their own fields, declared once and read by the filter panel, spec table, CSV templates and the
  chatbot's guided finder.
- Taxonomies for format, genre, creator, label, decade and condition; decade derived from the Year field.
- Product screen panel that shows only the chosen format's fields, with suggestions for existing terms.
- Clean URLs resolved both by rewrite rules and in a `request` filter; old slugs 301 to the product and trashed
  products to their format page.
- Filter engine: whitelisted query variables, `pre_get_posts` for the no-JavaScript version, a REST endpoint
  returning the same grid plus live option counts, cached and cleared on save.
- Shop search that covers titles, creators, genres, labels and chat keywords.
- Ask ORWO index, chat answers, enquiries, newsletter sign-ups and the unanswered questions log.
- CSV import and export, one template per format, matched on SKU with a dry run.
- Login hardening: a private sign-in address, 404 for `/wp-login.php` and `/wp-admin`, attempt rate limiting, no
  username harvesting, with two documented ways back in through `wp-config.php`.
