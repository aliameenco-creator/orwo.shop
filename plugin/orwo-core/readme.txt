=== ORWO Core ===
Contributors: orwo
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The ORWO catalogue: formats and fields, clean URLs, filters, the Ask ORWO index and login hardening.

== Description ==

ORWO Core holds everything about the catalogue that must outlive a redesign:

* The six formats and the fields each one needs, declared once and read by the filter panel, the product screen,
  the spec table, the CSV templates and the chatbot.
* Format, genre, creator, label, decade and condition taxonomies.
* Clean URLs: /format/product/, /genre/, /by/, /label/, /new/, /sale/ and /search/, with 301s for renamed and
  retired products.
* The filter engine and the /wp-json/orwo/v1/products endpoint, with live option counts.
* The Ask ORWO index, chat answers, enquiries, newsletter sign-ups and the unanswered questions log.
* CSV import and export, one template per format.
* Login hardening: a private sign-in address, with two documented ways back in through wp-config.php.

WooCommerce is required for prices, stock, cart and checkout.

== Changelog ==

= 0.2.0 =
Trailer field on the disc formats; all genres in the spec table.

= 0.1.0 =
First build.
