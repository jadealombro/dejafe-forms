# Dejafe Forms

A lightweight contact form block for WordPress, built by [Dejafe](https://dejafe.dev/).

- **Contact form block** with settings in the block sidebar: recipient, subject, thank-you message or redirect page, and a confirmation email to the visitor.
- **Custom fields:** every field is its own block, so each site can have its own form.
- **Site-wide default** for what happens after submitting (message or thank-you page), which any form can override.
- **Spam protection:** Cloudflare Turnstile, a honeypot field, a minimum fill time and a per-visitor rate limit.
- **Entries backup** under *Form entries* (admins only), deleted automatically after a set number of days.
- **SMTP delivery** (e.g. Mailgun) configured in `wp-config.php`.
- **Updates from GitHub Releases** on every site it's installed on.

Requires WordPress 6.6+ and PHP 7.4+.

## Install

Download `dejafe-forms.zip` from the [latest release](https://github.com/jadealombro/dejafe-forms/releases/latest) and upload it under *Plugins > Add New > Upload Plugin*. Future versions then appear on the Plugins screen like any other update.

If this repository is private, each site also needs a fine-grained GitHub token (read access to this repo's contents) in `wp-config.php`:

```php
define( 'DEJAFE_FORMS_GITHUB_TOKEN', 'github_pat_...' );
```

## Build a form

Add the *Contact form* block. It starts with First Name, Last Name, Email, Phone Number and Message. Each of these is a *Form field* block: click one to change it, drag to reorder, or use the + button to add another.

Field settings: type (Text, Email, Phone, Paragraph, Dropdown, Multiple choice, Checkbox, Date), label, required, full or half width, placeholder and, for Dropdown and Multiple choice, the options (one per line). Two half-width fields in a row sit side by side; on phones every field is full width.

**Email tags.** Every field has a key built from its label (*Preferred Day* becomes `{preferred_day}`), shown under *Email tag* in the field settings. Use the tags in the subject, the thank-you message and the confirmation email, along with `{name}` (the sender's name, or their email when there's no name field) and `{site_name}`. Paragraph fields never appear in anything the visitor sees, since they can hold sensitive details. The confirmation email goes to the first Email field.

Forms made with 1.0 keep working as they are. Open the page in the editor to see their fields as blocks.

## Configure

*Form entries > Settings*: the default thank-you message or page, entry backup and retention, Turnstile keys, sender name and address, test email.

Mail through Mailgun (or any SMTP service), in `wp-config.php`:

```php
define( 'DEJAFE_SMTP_HOST', 'smtp.mailgun.org' ); // smtp.eu.mailgun.org for EU domains
define( 'DEJAFE_SMTP_PORT', 587 );
define( 'DEJAFE_SMTP_SECURE', 'tls' );
define( 'DEJAFE_SMTP_USER', 'postmaster@mg.example.com' );
define( 'DEJAFE_SMTP_PASS', '...' );
define( 'DEJAFE_MAIL_FROM', 'hello@example.com' );
define( 'DEJAFE_MAIL_FROM_NAME', 'Example' );
```

Turnstile keys can also be set as `DEJAFE_TURNSTILE_SITE_KEY` and `DEJAFE_TURNSTILE_SECRET_KEY`. For local development, Cloudflare's always-pass test keys work: site key `1x00000000000000000000AA`, secret `1x0000000000000000000000000000000AA`.

## Privacy

What the plugin stores on the site, for the site's privacy policy:

- **Form entries**, when *Store entries* is on (it is by default). Each entry keeps the fields the visitor filled in, the page the form was on, the date and whether the email was sent. Entries are private posts that only administrators can see under *Form entries*. They are deleted automatically after the number of days set under *Form entries > Settings* (90 by default, from 1 to 3650).
- **No IP address is stored.** For the rate limit, the plugin keeps a counter under a one-way hash of the visitor's IP address for 10 minutes, then it expires.
- **Emails.** The notification goes to the form's recipient (the site's admin email if none is set) and the confirmation to the visitor's email address. Both contain what the visitor typed, except that paragraph fields never appear in the confirmation or the thank-you message.
- **Settings** (one option, `dejafe_forms_settings`) and a cached copy of the latest GitHub release (`dejafe_forms_release`, kept for 6 hours).

Deleting the plugin removes all of this (see *Uninstall* below).

## Outside services

The plugin contacts these, and nothing else:

| Service | When | What is sent |
| --- | --- | --- |
| Cloudflare Turnstile (`challenges.cloudflare.com`) | Only when Turnstile keys are set. The visitor's browser loads Cloudflare's script on pages with a form, and the site checks each submission with Cloudflare. | The Turnstile token and the visitor's IP address, for the check. Cloudflare's own privacy policy covers its script. |
| The SMTP service set in `wp-config.php` (e.g. Mailgun) | Each email, only when `DEJAFE_SMTP_HOST` is set. Otherwise the server's own mail is used. | The emails described under *Privacy*. |
| GitHub (`api.github.com`, `github.com`) | The update check from wp-admin, at most every 6 hours, and the zip download when updating. | Nothing about the site's visitors. The request names the plugin and its version. |

## Uninstall

Deactivating keeps everything and only stops the daily clean-up. **Deleting** the plugin from the Plugins screen removes its settings, every form entry, the clean-up event, the cached release and the rate-limit counters (`uninstall.php`). Forms already placed in pages stay in the page content and work again if the plugin is reinstalled. Copy any entries you need before deleting.

## Release a new version

1. Bump the version in **both** places in `dejafe-forms.php`: the `Version:` header and `DEJAFE_FORMS_VERSION` (e.g. `1.1.0`).
2. Commit and push to `main`.

The *Release* workflow sees a version with no release yet, builds `dejafe-forms.zip` and publishes release `v1.1.0` with notes generated from the commits. Pushes that don't change the version are skipped. Sites pick the update up within 6 hours, or straight away via *Dashboard > Updates > Check again*.

A copy that is a git checkout (has a `.git` folder) never offers updates, because updating would replace the folder and delete `.git`. Pull from git instead.

Sites need to reach both `api.wordpress.org` and `api.github.com` to see updates. WordPress only asks GitHub after its own wordpress.org check succeeds, so a site that blocks outside requests (for example with `WP_HTTP_BLOCK_EXTERNAL`) won't see new versions.
