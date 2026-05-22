=== Site Migrator ===
Contributors: octarinestudio
Tags: migration, clone, backup, transfer, multisite
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Pull-based WordPress site migration between installs using a shared auth code.

== Description ==

Site Migrator copies database tables, uploads, active theme, and active plugins from a source WordPress site to a target site. Install the plugin on both sites, share the site URL and auth code from the source, then run the guided wizard on the target.

Developed by [Octarine Studio](https://octarinestudio.uk).

Plugin home: [wordpress-site-migrator](https://octarinestudio.uk/wordpress-site-migrator)

== Security ==

* Source data is exposed only via authenticated REST endpoints (`X-Migrator-Auth` header).
* Admin actions require the `manage_options` capability and WordPress nonces.
* Treat the auth code like a password. Regenerate it after migration.
* See SECURITY.md in the plugin repository for the full threat model.

== Installation ==

1. Upload the `site-migrator` folder to `/wp-content/plugins/`.
2. Activate through the Plugins screen.
3. On the source site: Tools → Site Migrator — copy Site URL and Auth code.
4. On the target site: enter those values and follow the wizard.

== Frequently Asked Questions ==

= Can I migrate a multisite subsite to a single site? =

Yes, when pulling from the subsite's admin with the correct blog context.

= Is the auth code sent to the browser after reload? =

No. Resume uses server-side session data only; the auth code is not exposed in page source during resume.

== Changelog ==

= 1.0.0 =
* Initial public release.
