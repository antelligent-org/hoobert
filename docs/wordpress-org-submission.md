# Publishing to the WordPress.org plugin directory

Everything needed to submit Hoobert, plus what still has to be decided. Sources:
[Add your plugin](https://wordpress.org/plugins/developers/add/),
[detailed guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/),
[plugin assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/),
[common issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/).

## The name

The plugin was called **Woobert** until July 2026. It could not have shipped under
that name: WordPress.org treats "woo" as restricted on behalf of Automattic, and a
slug is **permanent once approved**, so there would have been no fixing it later.

The mechanics, since they matter when naming anything else in this project. "woo"
is the only entry in Plugin Check's `PORTMANTEAUS` list (`Trademarks_Check.php`),
matched with `stripos( $slug, 'woo' ) === 0`. The bar is that a slug may not
_begin_ with "woo". The message the tool prints ("cannot be used at all")
overstates its own rule, but "woobert" failed either way. This sits under
[guideline 17](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#17-plugins-must-respect-trademarks-copyrights-and-project-names).

To screen a candidate name without renaming anything, extract `TRADEMARK_SLUGS`
and `PORTMANTEAUS` from that file and run the slug through the same two loops.
Terms ending in `-` are prefix-only; the rest match anywhere, minus the
`for-TRADEMARK` / `with-TRADEMARK` exceptions. "hoobert" is clear on both lists.

WooCommerce may still appear _after_ the product name, which is why the readme
title is `Hoobert - AI Command Bar for WooCommerce`. That "X for WooCommerce" form
is explicitly allowed.

## Assets

Sources live in `assets-src/`, outputs in `.wordpress-org/`. Regenerate with:

```bash
node scripts/build-wporg-assets.mjs
```

It rasterises through headless Chrome, so there are no npm dependencies. Set
`CHROME_BIN` if Chrome is somewhere unusual.

| File                                       | Size     | Source                       | Status |
| ------------------------------------------ | -------- | ---------------------------- | ------ |
| `.wordpress-org/icon-128x128.png`          | 128x128  | `assets-src/hoobert-owl.png` | done   |
| `.wordpress-org/icon-256x256.png`          | 256x256  | same                         | done   |
| `.wordpress-org/banner-772x250.png`        | 772x250  | `assets-src/banner.html`     | done   |
| `.wordpress-org/banner-1544x500.png`       | 1544x500 | same                         | done   |
| `.wordpress-org/screenshot-1..7.png`       | any      | shot by hand, see below      | done   |
| `.wordpress-org/blueprints/blueprint.json` | -        | hand-written                 | done   |

### About the Live Preview blueprint

`.wordpress-org/blueprints/blueprint.json` powers the **Live Preview** button. It must not
carry a step that installs Hoobert: WordPress.org appends its own `installPlugin` step for
the reviewed version, and the Blueprint schema has no `"resource": "self"` (a blueprint
using it fails validation and the preview refuses to boot). Keep it to WooCommerce, the
site options, and the seeder.

Check a change before release. wp.org serves the deployed copy at
`https://wordpress.org/plugins/wp-json/plugins/v1/plugin/hoobert/blueprint.json`, and any
blueprint can be validated against the published schema:

```bash
curl -sS https://playground.wordpress.net/blueprint-schema.json -o /tmp/blueprint-schema.json
npx ajv-cli validate -s /tmp/blueprint-schema.json -d .wordpress-org/blueprints/blueprint.json
```

### About the owl

`assets-src/hoobert-owl.png` is the original "funny owl" artwork by agustrisana
on Flaticon. It is the mark for **every** output: the directory icons, the owl in
the banner, and the copy shipped inside the plugin for the settings screen.

`assets-src/hoobert-owl.svg` is a vector redraw in the Fernfly palette, kept only
as a stand-in for when the original is missing. It is close but not identical, so
the two must never appear in the same build. The script enforces that: whichever
source it picks drives all outputs, and it prints which one it used.

That is also why `.wordpress-org/icon.svg` is **not** committed. wp.org serves
`icon.svg` to modern browsers with the PNGs as fallback, so shipping the redraw
next to PNGs of the original would give the listing two different icons depending
on the browser. The script emits `icon.svg` only when the redraw is the mark, and
deletes it otherwise. SVG is optional in the wp.org spec; the PNGs are not.

The plugin always ships `assets/hoobert-owl.png`, rasterised at 256px from
whichever source applies, so `class-settings.php` has one predictable filename to
reference.

The Flaticon free licence requires attribution wherever the icon appears. It is
in three places: the plugin settings screen (`Hoobert_Settings::render_credits`),
`readme.txt`, and the repository README.

## Compliance

Verified by running WordPress.org's own tool against the plugin:

```bash
docker compose run --rm --entrypoint wp wpcli plugin install plugin-check --activate
docker compose run --rm --entrypoint wp wpcli plugin check hoobert \
  --exclude-directories=node_modules,src,scripts,build --format=csv
```

Current result: **zero errors, zero warnings other than the naming ones above.**
Re-run it before every submission.

What the guidelines ask for, and where it is satisfied:

| Guideline                            | Where                                                                                                     |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------- |
| 1. GPL-compatible licence            | `LICENSE` (GPL-2.0), headers in `hoobert.php` and `readme.txt`                                            |
| 2. Stable tag matches a real version | `bump-version.js` keeps `Stable tag` in step with the plugin header                                       |
| 4. No obfuscation, source available  | `src/` is committed; readme links the repo; the build is `wp-scripts`                                     |
| 5. No trialware or locked features   | Everything in the plugin works; only the model is external                                                |
| 7. External services documented      | `== External service ==` in readme.txt, plus the settings screen                                          |
| 7. No calling home without consent   | No request until both settings are saved and the merchant runs a command                                  |
| 8. SaaS integration, not remote code | Only tool names and arguments come back; execution is local against `wc/v3`                               |
| 9. No tracking without opt-in        | None. No analytics, no telemetry, no phone-home                                                           |
| 10. "Powered by" is not forced       | No storefront output at all                                                                               |
| 11. Unique prefixes                  | `Hoobert_`, `HOOBERT_`, `hoobert_`, `hoobert/v1`                                                          |
| 12. No hidden files                  | `.DS_Store` removed; the release workflow strips them from the zip                                        |
| 13. No unneeded files                | The zip ships only `hoobert.php`, `includes/`, `assets/`, `build/`, `tools.json`, `readme.txt`, `LICENSE` |
| 17. Trademarks                       | Clear. `hoobert` passes both of Plugin Check's lists; see **The name** above                              |

Security specifics reviewers look for, all present: `ABSPATH` guards on every PHP
file, `permission_callback` on all three REST routes, `manage_woocommerce` checks,
`wp_rest` nonce on every request, `$wpdb->prepare()` on every query, escaped
output throughout, `wp_remote_post` rather than cURL, and no `ALLOW_UNFILTERED_UPLOADS`.

Three things changed during this pass, worth knowing about:

-   **The settings page no longer echoes the API key.** It used to render the stored
    key as the `value` of the password field, which put the secret in plain text in
    the DOM on every page load: readable in view-source, and captured by any
    screenshot or screen share of that page. The field now renders empty with a
    "Saved" placeholder, an empty submission keeps the existing key, and there is an
    explicit checkbox to remove it. This matters immediately, because a settings-page
    screenshot is going in the public listing.
-   **The bundle only loads for users who can use it.** `admin_enqueue_scripts` was
    enqueueing the palette JS and CSS on every wp-admin screen for every user,
    including ones without `manage_woocommerce` who get a dead command. It is now
    gated on the capability.
-   **A privacy policy suggestion is registered.** `Hoobert_Settings::privacy_policy_content()`
    hooks `wp_add_privacy_policy_content`, so the suggested wording shows up under
    Settings -> Privacy for merchants writing their own policy. Not required, but
    it is the expected courtesy for a plugin with an external service, and it saves
    a round of reviewer questions.

## Compatibility

Both ends of the declared range are verified, not assumed. Each was run against a
real install with the plugin active and WooCommerce alongside it.

|         | WordPress | WooCommerce | PHP | Result          |
| ------- | --------- | ----------- | --- | --------------- |
| Floor   | 6.6.2     | 9.4.3       | 8.2 | all checks pass |
| Ceiling | 7.0.2     | 10.9.4      | 8.5 | all checks pass |

Checked at both ends: the plugin activates, `tools.json` parses to 28 tools, core
registers `react-jsx-runtime` and `wp-commands`, the bundle enqueues _and is
actually printed_, every declared dependency resolves, all four `hoobert/v1`
routes register, the settings page renders, and the history table is created.

### Why the floor is 6.6, and how it fails below it

`build/index.asset.php` declares `react-jsx-runtime`, which core registers as a
script handle from 6.6 onwards. On 6.5 the failure is **silent**, which is why
this is worth stating plainly. Measured on 6.5.5:

```
core registers react-jsx-runtime      NO
core registers wp-commands            yes
core registers wp-data                yes
core registers wp-element             yes

enqueued flag        : yes      <- the plugin believes it worked
actually printed     : NO
bytes of script tag  : 0
PHP error raised     : none
```

`wp_enqueue_script()` succeeds and `wp_script_is( 'hoobert', 'enqueued' )` returns
true, but `do_items()` emits nothing because one dependency is unregistered. No
warning, no notice, no console error. The command bar simply never appears.

**Do not lower `Requires at least` below 6.6** without changing the JSX build to
stop depending on that handle.

Note the practical floor is higher than the declared one: current WooCommerce
requires WP 6.9, so a store on 6.6 is necessarily on an older WooCommerce. That is
why the floor was tested against WooCommerce 9.4.3 rather than the current
release. The `wc/v3` routes the plugin calls are stable across 8.x to 10.x.

## Submitting

1. Build the zip exactly as the release workflow does: a single top-level folder
   named after the slug, containing only the shipped files.
2. Upload at <https://wordpress.org/plugins/developers/add/> while logged in.
   Assets are **not** part of this zip; they go to SVN after approval.
3. Wait. Review takes 1 to 10 days, usually under 5 business days. Expect at least
   one round of questions, most likely about the external service.
4. On approval you get SVN write access and a permanent slug.

## After approval

Add `SVN_USERNAME` and `SVN_PASSWORD` as repository secrets and the release
workflow publishes every subsequent release automatically: `trunk`, a version tag,
and everything in `.wordpress-org/` copied to the SVN `assets/` directory. Until
those secrets exist the deploy step is skipped, so it is safe to leave enabled.

Update `SLUG` in that step if the name changes.

### Publishing a tag by hand

The automatic deploy fires only on the push that creates a release, so a release
tagged before the secrets existed, or one whose SVN commit failed, never reaches
the directory. Run the Release workflow manually against the tag instead:

```bash
gh workflow run release.yml -f tag=v0.2.2
```

It rebuilds that tag's own code, re-uploads the zip to the existing GitHub
release, and runs the SVN deploy. Re-running the original workflow run does
**not** work: release-please sees the tag already exists, leaves
`release_created` unset, and the publish job is skipped.

Note that directory assets are cached hard by the CDN. A new banner or icon can
take minutes, and up to six hours under load, to appear.
