# Astrid

A WordPress activity-registration plugin based on the original NOOS Yoga registration form. The installable plugin is in `astrid/`; development tools and tests stay outside the distributable ZIP.

## Use

Upload `astrid.zip` to WordPress, activate it, and open **Astrid** in the dashboard to verify delivery settings. Add a native **Group** containing an activity **Heading** and either **Astrid Registration** or **Astrid Multiple options**. For multiple options, enter one choice per line in the block sidebar.

See [the plugin guide](astrid/readme.txt) for display modes, localization, migration, mail behavior and limitations. Requires WordPress 6.5+ and PHP 7.4+.

## Development

Editor code uses WordPress's bundled JavaScript packages directly; users need no npm build or extra PHP dependency.

```powershell
npm install
node scripts/build-translations.mjs
node scripts/test-wordpress.mjs
```

Integration tests use an isolated WordPress Playground instance. Mail is intercepted in that disposable site. No test email is delivered. Add `--serve` to keep the local test site available for browser checks; press Ctrl+C to stop it. Test transport and fixtures are never included in the plugin ZIP.

To package:

```powershell
Compress-Archive -LiteralPath astrid -DestinationPath astrid.zip -Force
```
