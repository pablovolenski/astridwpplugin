=== Astrid ===
Contributors: noosyoga
Tags: registration, activity, gutenberg, contact-form
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple activity registration with native Gutenberg blocks and automatic activity identification.

== Description ==

Put an Astrid block alongside a Heading inside a native WordPress Group. Astrid uses that heading to identify the activity in the registration form and the owner's email.

Two blocks:
* Astrid Registration: a simple registration button and form.
* Astrid Multiple options: a required dropdown plus the registration form. Enter one option per line in the block sidebar, including duration or price as appropriate.

Forms can open in a popup, appear below the button, or stay visible. The dashboard provides defaults; individual blocks can override their display mode and button text.

Buttons inherit the theme's native WordPress Button styling by default. Optional custom colors, corner radius, font size and padding are configurable in the Astrid dashboard.

Default form copy follows the WordPress site language. English and German are included. Other languages can be added with WordPress gettext catalogs. Dashboard text overrides remain exactly as written; clear a text field to restore the site-language default.

== Installation ==

1. Upload astrid.zip through Plugins > Add New > Upload Plugin and activate Astrid.
2. If replacing NOOS Yoga Anmelden, deactivate the old plugin first. Existing [yn_anmelden_button course="Activity name"] shortcodes remain supported. Saved recipient and legal-page settings are imported on Astrid's first activation when available.
3. Open Astrid in the WordPress dashboard. Verify the recipient email and terms/privacy page URLs.
4. In a page or post, add a Group, a Heading naming the activity, and either Astrid block inside that Group.
5. Publish and send a real test registration to verify delivery through your site's mail transport.

== Frequently Asked Questions ==

= How does Astrid identify the activity? =

Astrid finds the first non-empty Heading belonging to the nearest enclosing Group. It can find headings and registrations inside layout wrappers such as Columns. It skips headings belonging to separate nested Groups. If a Group has no heading, Astrid checks the next outer Group, then falls back to the page/post title. A Group's private List View name is not used.

The name is resolved when WordPress renders the blocks, so changing the heading does not require retyping a form. Each rendered form carries signed activity and option data; the email handler validates that data and only accepts a configured option.

= What does the email contain? =

Activity, selected option, source page title and URL, block type and instance, participant name/phone/email, message, consent wording and receipt time. The participant email is set as Reply-To.

= How do I configure multiple options? =

Select the Astrid Multiple options block and enter one option per line in its sidebar. For example:
4 weeks — EUR 60
8 weeks — EUR 100

Visitors must select an option. Astrid validates that choice in the browser and on the server. An unconfigured options block does not show a public registration form. Prices and durations are descriptive text, not payment or booking rules.

= How do editable form text placeholders work? =

Use {activity} and {option} in the form heading, introduction and thank-you message. Consent wording also supports those placeholders, plus {terms} and {privacy} for the configured links. Other field labels and button labels are plain text. Text overrides apply globally.

= Does Astrid store registrations or send participant confirmations? =

No. Astrid sends one email to the configured site recipient using wp_mail(). It does not create a registration database, take payments, enforce capacity or send participant confirmation emails. Your mail plugin may retain its own delivery logs. A success means wp_mail() accepted the email, not a guarantee of inbox delivery.

= Does it require FluentSMTP or Brevo? =

No. Astrid uses the site's existing WordPress mail transport. A properly configured SMTP/mail plugin is recommended for production delivery.

= What spam protection is included? =

A honeypot, signed form configuration, server validation, a short-lived salted IP rate-limit key, and five-minute suppression of immediate identical retries. The signature protects activity/option integrity and is not a CAPTCHA. Transients contain counters and hashes, not registration content or raw IPs. Advanced bot protection is not included.

= What happens on uninstall? =

Deleting Astrid removes its settings. Short-lived throttle/deduplication transients expire automatically. Existing content blocks and legacy-plugin settings are not deleted.

== Changelog ==

= 2.0.0 =
* Rename to Astrid and add two native Gutenberg blocks.
* Detect activities from Group headings, including nested layouts.
* Add required options, three display modes, editable form copy and theme/custom button styles.
* Add accessible native dialogs, independent forms, signed configuration and server-side option validation.
* Preserve the original registration shortcode.
