# Changelog

## [0.2.1](https://github.com/antelligent-org/hoobert/compare/v0.2.0...v0.2.1) (2026-07-25)


### Refactors

* fix prettier issue in eslint config ([785e68e](https://github.com/antelligent-org/hoobert/commit/785e68e9180b55110810d2be13d13340e43b2044))


### Documentation

* add a start-issue skill for github issues ([a46b18e](https://github.com/antelligent-org/hoobert/commit/a46b18e9d21f8d6c272b2ad963b9e8ad8f73735a))
* add a start-issue skill for github issues ([e76cdb5](https://github.com/antelligent-org/hoobert/commit/e76cdb5bd3f183abab45fea969a6a93d2f61f2d1))
* instruct claude to work by default in feature branches instead of main ([98efd6e](https://github.com/antelligent-org/hoobert/commit/98efd6e1e9772f2312796a317ff42eeed64a64cf))

## [0.2.0](https://github.com/antelligent-org/hoobert/compare/v0.1.2...v0.2.0) (2026-07-24)


### ⚠ BREAKING CHANGES

* the plugin folder and slug are now "hoobert". Existing installs from a GitHub release will see a separate plugin rather than an upgrade; activating it carries the old settings and history across.

### Features

* add wordpress.org directory assets and their build script ([0ac4256](https://github.com/antelligent-org/hoobert/commit/0ac42565017490f13bb09d3ae959d0c40340c402))
* document the inference service on the settings page ([e4afe90](https://github.com/antelligent-org/hoobert/commit/e4afe90adeefda3b1625576728e24d68cb474979))
* rename the plugin from woobert to hoobert ([8997247](https://github.com/antelligent-org/hoobert/commit/8997247d0d0d4860581eb316813afcc27979d92f))


### Bug Fixes

* build every directory asset from the original owl artwork ([72617e1](https://github.com/antelligent-org/hoobert/commit/72617e1d742fe9ad76d595e53e92d8aa2f383784))
* lower the minimum wordpress version to 6.6 ([3eece4c](https://github.com/antelligent-org/hoobert/commit/3eece4cbbf1523ed079de18cc63513ca3daf6374))
* only load the command bar for users who can use it ([df7b113](https://github.com/antelligent-org/hoobert/commit/df7b113aa87210771969e150aa4adc9d6ac8f970))
* stop rendering the saved api key into the settings page ([2b49d80](https://github.com/antelligent-org/hoobert/commit/2b49d8063f8b313d775aaa102a98d33c25fe9244))


### Refactors

* clear the remaining plugin check findings ([070dfca](https://github.com/antelligent-org/hoobert/commit/070dfcab88670f5943d42652fdcb5059734d1ceb))


### Documentation

* add the plugin directory screenshots ([6c35178](https://github.com/antelligent-org/hoobert/commit/6c35178216683ebbb0b55b789a155f41d019a21f))
* add the wordpress.org submission playbook ([16db663](https://github.com/antelligent-org/hoobert/commit/16db663c90902f5804d76ba66dbcb7880c01c509))
* clean up wporg guide ([084dfd1](https://github.com/antelligent-org/hoobert/commit/084dfd13529fca666d312ab22adf33f8a5e0cd5d))
* correct how wp.org matches the restricted term "woo" ([d40d509](https://github.com/antelligent-org/hoobert/commit/d40d509c87cb37c7ee56ff3206efb6700706c6a3))
* fix fernfly create project instruction ([80f12cb](https://github.com/antelligent-org/hoobert/commit/80f12cbd524eb88de43c81d046af5d2e46d2d211))
* record compatibility verified across the declared range ([aa1c07e](https://github.com/antelligent-org/hoobert/commit/aa1c07ea527e1dadca3811f9907b399e122b6ed9))
* rewrite readme.txt as the plugin directory listing ([d1f8f1b](https://github.com/antelligent-org/hoobert/commit/d1f8f1b38cb5f255b56dbf09511447ab792c0209))

## [0.1.2](https://github.com/antelligent-org/woobert/compare/v0.1.1...v0.1.2) (2026-07-10)


### Bug Fixes

* make sample data consistent and refundable ([60b0a58](https://github.com/antelligent-org/woobert/commit/60b0a587e75479c7b9e6c87d5021dedf09bd43be))


### Documentation

* use the canonical repo owner in the changelog compare link ([9010a9a](https://github.com/antelligent-org/woobert/commit/9010a9aec12099faa292d1c13ce8ecc82122f065))

## [0.1.1](https://github.com/antelligent-org/woobert/compare/v0.1.0...v0.1.1) (2026-07-09)

### Features

* show a plain-English confirmation for write actions
* require confirmation on product, stock, and coupon updates

### Bug Fixes

* remount the flow modal per query to avoid a stale-result flash
* hide the write-confirmation message for direct-execute tools

### Refactors

* share one seeder between blueprint and WP-CLI, fix landing redirect

## 0.1.0 (2026-07-08)

### Features

* initial release: "Ask Woobert" command in the WordPress command palette, resolve/execute inference proxy, confirmation for destructive actions.
