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

## Release a new version

1. Bump the version in **both** places in `dejafe-forms.php`: the `Version:` header and `DEJAFE_FORMS_VERSION` (e.g. `1.1.0`).
2. Commit and push to `main`.

The *Release* workflow sees a version with no release yet, builds `dejafe-forms.zip` and publishes release `v1.1.0` with notes generated from the commits. Pushes that don't change the version are skipped. Sites pick the update up within 6 hours, or straight away via *Dashboard > Updates > Check again*.
