=== RCP VAT ===
Contributors: munger41
Donate link: https://www.termel.fr
Tags: restrict,content,pro,value,added,tax,vat,tva,rcp
Requires at least: 3.6
Tested up to: 4.9
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

VAT management in Stripe for Restrict Content Pro plugin. Sell inside EU respecting the rules.

== Description ==

This plugin [implements EU VAT rules](http://europa.eu/youreurope/business/vat-customs/buy-sell/index_en.htm "EU VAT rules") for [Restrict Content Pro plugin](https://restrictcontentpro.com/ "RCP"), working with Stripe gateway.
Implements VIES VAT business number [on the fly validation](http://ec.europa.eu/taxation_customs/vies/vieshome.do?locale=fr "VIES") using [dannyvankooten php code](https://github.com/dannyvankooten/vat.php).

Make sure your web server uses [soap-php module](http://php.net/manual/en/book.soap.php "Soap PHP")

[Kasutan](https://github.com/Kasutan "Kasutan") upgraded the plugin with new features here : [https://github.com/Kasutan/rcp-vat-2](https://github.com/Kasutan/rcp-vat-2 "RCP VAT 2")

== Installation ==

As usual, but make sure your web server uses [soap-php module](http://php.net/manual/en/book.soap.php "Soap PHP")

== Frequently Asked Questions ==

== Screenshots ==

1. Parameters added into payment RCP tab settings.

== Changelog ==

* 1.2.4 - upgrade dependency to v1.1.2 : https://github.com/dannyvankooten/vat.php

* 1.2.3 - correct VAT number avoids tax

* 1.2.2 - code cleaning

* 1.2.1 : still a bug on non recurrent vat : fixed 

* 1.2 : fixes on tax percent field to include vat rate

* 1.1.2 : fix non reccurent price computation

* 1.1.1 : now prices updates via JS in registration form

* 1.1 : adding custom rates for all countries

* 1.0.2 : bug in some cases when billing country is set to country instead of code

* 1.0.1 : if no billing country, set it as business billing country

* 1.0.0 : First commit