# Dejafe Forms

A lightweight contact form block for WordPress, built by [Dejafe](https://dejafe.dev/).

- **Contact form block** with settings in the block sidebar: recipient, subject, thank-you message or redirect page, and a confirmation email to the visitor.
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

## Configure

*Form entries > Settings*: entry backup and retention, Turnstile keys, sender name and address, test email.

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

1. Bump the version in **both** places in `dejafe-forms.php`: the `Version:` header and `DEJAFE_FORMS_VERSION`.
2. Commit and push.
3. Tag and push the tag:

   ```sh
   git tag v1.1.0
   git push origin v1.1.0
   ```

The *Release* workflow checks the versions match, builds `dejafe-forms.zip` and publishes the GitHub release. Sites pick it up within 6 hours, or straight away via *Dashboard > Updates > Check again*.
