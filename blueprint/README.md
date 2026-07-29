# Hoobert demo blueprint

[`blueprint.json`](blueprint.json) is a [WordPress Playground](https://wordpress.github.io/wordpress-playground/blueprints/)
blueprint that spins up a throwaway demo store in the browser, no install required.
One click gives you:

1. A fresh WordPress site (auto-logged-in as admin).
2. WooCommerce installed and activated.
3. Sample products, a variable product, customers and guests, 12 orders spread over the
   last three months in every status, refunds, reviews, and two coupons. The blueprint
   fetches [`scripts/seed-sample-data.php`](../scripts/seed-sample-data.php) into the site
   and runs it, so the demo store and local dev share one seeder. The seeder also installs
   a sample payment gateway as a must-use plugin, because no core WooCommerce gateway
   supports automatic refunds and the refund journeys would otherwise fail.
4. Hoobert installed and activated.
5. You land on the Hoobert settings page (**WooCommerce -> Hoobert**).

## The two copies

This file is the blueprint you launch by hand. A second copy lives at
[`.wordpress-org/blueprints/blueprint.json`](../.wordpress-org/blueprints/blueprint.json)
and drives the **Live Preview** button on the plugin directory listing; the release
workflow copies it to SVN `assets/`. They differ in one way that matters: the directory
copy must **not** install Hoobert itself. WordPress.org appends its own `installPlugin`
step for the reviewed version and serves the result, so a hand-written step for Hoobert
would install it twice, and there is no `"resource": "self"` in the Blueprint schema.
Everything else (WooCommerce, site options, the seeder) stays in both.

Validate a change against the schema before pushing it:

```bash
curl -sS https://playground.wordpress.net/blueprint-schema.json -o /tmp/blueprint-schema.json
npx ajv-cli validate -s /tmp/blueprint-schema.json -d blueprint/blueprint.json
```

## Before you share it

- The plugin URL points at `releases/latest/download/hoobert.zip`, which the **Release
  plugin zip** workflow attaches to each release. Pin it to a specific tag if you need
  the demo frozen on one version.
- The seeder is fetched at launch from `scripts/seed-sample-data.php` on the `main` branch
  (via the `writeFile` step), so `features.networking` must stay `true`. If you pin the
  blueprint to a release tag, point that URL at the same tag.
- Set the inference endpoint + key. The `hoobert_options` in the `setSiteOptions` step
  pre-fill the endpoint with the example value and leave `api_key` blank, so the command
  bar won't answer until you add a working project endpoint + `X-Api-Key`. Fill these on
  the settings page after launch, or bake real demo values into the blueprint.

## Launching

Open Playground with the blueprint URL-encoded (or hosted and referenced by URL):

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/antelligent-org/hoobert/main/blueprint/blueprint.json
```

You can also paste the JSON into the [Playground builder](https://playground.wordpress.net/builder/builder.html).
Playground sites are ephemeral; everything resets when the tab closes.
