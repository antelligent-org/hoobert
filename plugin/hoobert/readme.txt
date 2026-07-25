=== Hoobert - AI Command Bar for WooCommerce ===
Contributors: anuragbhandari
Tags: woocommerce, ai, command palette, store management, productivity
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run your whole store from one prompt. Press Cmd/Ctrl-K, type what you want in plain English, and Hoobert does it in WooCommerce.

== Description ==

You know the click path. Orders, filter by status, find the order, scroll, Refund, line item, quantity, amount, Refund manually, confirm. Six screens to do one small thing.

Hoobert is a command bar for that. Press **Cmd/Ctrl-K** anywhere in wp-admin to open the WordPress command palette, pick **Ask Hoobert**, and type the thing you actually wanted:

* *"refund order 1042"*
* *"mark this order completed and add a note that the replacement shipped"*
* *"create a coupon SUMMER20 for 20% off"*
* *"add a Large / Red variation to this product at 54.99"*
* *"which products are out of stock"*
* *"who were my top customers last month"*
* *"approve the pending review on the espresso grinder"*

Hoobert reads the request, picks the matching WooCommerce action, shows you what it is about to do, and runs it. That is the whole product. No chat thread to babysit, no second dashboard to learn, no new place your data lives.

= He is an owl, and he is careful =

Hoobert is meant to be trusted with a live store, so the boring parts got the most attention.

* **Nothing runs until you see it.** Every request is resolved into a concrete action first and previewed back to you in plain English. Anything destructive - refunds, deletions, status changes, price and stock edits - needs a confirm click before it touches your data.
* **Your permissions, not the plugin's.** Actions execute in-process through WooCommerce's own REST API v3, under your logged-in admin session. Hoobert can never do something your own account could not do.
* **No keys in the browser.** The inference key is stored server-side and used server-side. Your browser never holds a credential, and Hoobert never creates or uses a WooCommerce consumer key/secret pair.
* **Everything is written down.** Every executed command is recorded in a store-wide audit log: the request, the exact REST call, the arguments, the outcome, and who ran it.
* **A fixed, small vocabulary.** Hoobert ships a set of 28 defined WooCommerce actions. It cannot invent a 29th, run arbitrary code, or reach any part of your site outside that set.

= How the language part works =

Hoobert is powered by **Fern**, a family of small, fine-tuned function-calling models from [Fernfly](https://fernfly.com). Fern is not a general-purpose chatbot. It does exactly one job: turn a merchant sentence into one of Hoobert's 28 WooCommerce actions with the arguments filled in. Because the job is narrow, the model is small, and small means fast.

You bring your own Fernfly project, so the connection is yours. See **Connecting a model** below.

= What it can do =

* **Orders** - list and search, look one up, change status, refund in full or in part, read and add order notes.
* **Products** - list and search, look one up, create, edit, update stock, delete.
* **Variations** - list and create variations on a variable product.
* **Coupons** - create, list, update.
* **Customers** - list and search, look one up, rank by lifetime spend.
* **Reviews** - list and moderate.
* **Taxonomy** - list and create product categories and tags.
* **Reports** - sales over a period, top sellers, top customers.

= "This order" just works [coming soon] =

Open an order and say *"refund this"*. Open a product and say *"drop the price to 39.99"*. Hoobert reads the order or product id off the screen you are on, so you rarely have to type one.

= Connecting a model =

Hoobert needs a Fernfly project to read requests. It takes a few minutes, and the free tier is enough to try it:

1. Create a free account at [fernfly.com](https://fernfly.com) and start a new project. No credit card required.
2. Choose the WooCommerce template in the new project wizard for a zero-configuration flow. You could also choose the "from scratch" option and import Hoobert's WooCommerce tool set into the project. It ships with this plugin as `tools.json`, in the plugin folder.
3. Train and deploy the project. Fernfly generates the training data; you do not need any ML background.
4. Copy the project's **infer URL** and **API key** into **WooCommerce -> Hoobert** and save.

Until both fields are set, the command bar tells you it is not configured. Nothing is sent anywhere before then.

= External service =

Hoobert sends your typed request to a Fernfly inference endpoint so the model can turn it into a WooCommerce action. This is required for the plugin to function, and it is the only external service it uses.

* **Service:** Fernfly - [fernfly.com](https://fernfly.com)
* **When:** only when you run a command from the command bar. Never on page load, on a schedule, or in the background.
* **What is sent:** the text you typed, and the id of the order or product on the screen you are on, so that "this order" resolves. Nothing else. No customer records, no order contents, no site credentials, no analytics, no telemetry.
* **What comes back:** the name of one of Hoobert's 28 actions and its arguments. The action is then run locally, by your own site.
* **Where it goes:** the endpoint URL you configure. You choose the project; Hoobert has no default and ships with no key.
* **Terms of service:** [fernfly.com/terms-of-service](https://fernfly.com/terms-of-service)
* **Privacy policy:** [fernfly.com/privacy-policy](https://fernfly.com/privacy-policy)

= Open source =

Hoobert is GPL, all of it, including the front-end sources and the build tooling. Development happens in the open at [github.com/antelligent-org/hoobert](https://github.com/antelligent-org/hoobert). Issues and pull requests welcome.

The owl is by agustrisana: [Funny owl icons created by agustrisana - Flaticon](https://www.flaticon.com/free-icons/funny-owl).

== Installation ==

1. Install and activate **WooCommerce** if you have not already. Hoobert will not activate without it.
2. Install Hoobert from **Plugins -> Add New**, or upload the plugin folder to `/wp-content/plugins/`.
3. Activate it through the **Plugins** screen.
4. Go to **WooCommerce -> Hoobert** and enter your Fernfly inference endpoint URL and API key. See **Connecting a model** in the description if you do not have them yet.
5. Press **Cmd/Ctrl-K** anywhere in wp-admin, start typing, and pick **Ask Hoobert**.

== Frequently Asked Questions ==

= Do I need an OpenAI or Anthropic key? =

No. Hoobert does not use a general-purpose LLM. It talks to one Fernfly inference endpoint running a small model fine-tuned on Hoobert's own WooCommerce actions, and that is the only outside service involved.

= Is it free? =

The plugin is free and GPL, forever. The inference endpoint is a Fernfly project, which has a free tier that is enough for normal single-store use. There is no locked feature, no trial timer, and no upsell inside the plugin.

= Can Hoobert break my store? =

It is constrained three ways at once. It can only run one of 28 predefined WooCommerce actions; it runs them under your own admin capabilities, so it cannot exceed what you could do by hand; and anything destructive requires an explicit confirm click after you have seen exactly what will happen. Everything it does run is logged.

That said, a refund is a refund. Read the confirmation before you click it, the same as you would in WooCommerce itself.

= What data leaves my site? =

Only the sentence you type, plus the order or product id of the screen you are on. Nothing is sent unless you run a command. Customer data, order contents, and your credentials never reach the inference endpoint. The model only needs your words to decide which action you meant; the action itself runs locally.

= Who can use the command bar? =

Only logged-in users with the `manage_woocommerce` capability, meaning shop managers and administrators. Hoobert's REST routes enforce this on every request, not just in the UI.

= Does it work with my theme or page builder? =

Yes. Hoobert lives entirely in wp-admin and does not touch your storefront, your theme, or your front-end at all. Visitors never load a byte of it.

= It says "not configured". What now? =

Both the inference endpoint URL and the API key must be filled in under **WooCommerce -> Hoobert**. If both are set and you still see it, check that the URL is your project's full infer route (it ends in `/infer`) and that the key was copied without surrounding whitespace.

= Hoobert misunderstood me. =

Rephrase closer to the action you want: "refund order 1042" beats "sort out that customer's money". If a request is outside the 28 actions, Hoobert says so rather than guessing. But if you genuinely believe Hoobert got it wrong, head over to Fernfly > Your project > Retrain, find the utterance that it got wrong, correct the function-call JSON in the textbox, save that utterance-function pair, and click the Retrain button. After your model has finished training, Hoobert will start behaving as expected.

= Can I add my own actions? =

Yes. The tool set is `tools.json` in the plugin folder: an OpenAI function schema per action, plus a small block describing the REST call to dispatch. Add your entry there, register the matching schema with your Fernfly project, and retrain. The [repository README](https://github.com/antelligent-org/hoobert) walks through it.

= Where is the command history? =

**WooCommerce -> Hoobert**, below the settings, shows every command every admin has run on this store, newest first. The palette itself also has a **Hoobert: Query history** command showing your own recent ones.

= Does it support languages other than English? =

The interface is translatable. What the model understands depends on the Fernfly project you train, so a non-English store can train on non-English utterances.

= Does it work on multisite? =

Yes. Settings and command history are per-site, so each store in a network has its own endpoint and its own audit log.

= Can I try it without installing anything? =

Yes. The repository has a [WordPress Playground](https://github.com/antelligent-org/hoobert/tree/main/blueprint) blueprint that spins up a throwaway store in your browser, with WooCommerce, Hoobert, and sample data already set up.

== Screenshots ==

1. Press Cmd/Ctrl-K anywhere in wp-admin, type in plain English, and pick "Ask Hoobert".
2. The resolved action, with the exact call and arguments it will run.
3. Destructive actions - refunds, deletes, status changes - need a confirm click.
4. The result, rendered as a readable summary rather than raw JSON.
5. Reports: sales over a period, summarised in the palette.
6. Settings: connect your Fernfly project with an endpoint URL and API key.
7. The store-wide audit log records every command, who ran it, and what it did.

== Changelog ==

= 0.2.1 =

**Refactors**

* fix prettier issue in eslint config

**Documentation**

* add a start-issue skill for github issues
* add a start-issue skill for github issues
* instruct claude to work by default in feature branches instead of main

= 0.2.0 =

**⚠ BREAKING CHANGES**

* the plugin folder and slug are now "hoobert". Existing installs from a GitHub release will see a separate plugin rather than an upgrade; activating it carries the old settings and history across.

**Features**

* add wordpress.org directory assets and their build script
* document the inference service on the settings page
* rename the plugin from woobert to hoobert

**Bug Fixes**

* build every directory asset from the original owl artwork
* lower the minimum wordpress version to 6.6
* only load the command bar for users who can use it
* stop rendering the saved api key into the settings page

**Refactors**

* clear the remaining plugin check findings

**Documentation**

* add the plugin directory screenshots
* add the wordpress.org submission playbook
* clean up wporg guide
* correct how wp.org matches the restricted term "woo"
* fix fernfly create project instruction
* record compatibility verified across the declared range
* rewrite readme.txt as the plugin directory listing

= 0.1.2 =

**Bug Fixes**

* make sample data consistent and refundable

**Documentation**

* use the canonical repo owner in the changelog compare link

= 0.1.1 =

**Features**

* show a plain-English confirmation for write actions
* require confirmation on product, stock, and coupon updates

**Bug Fixes**

* remount the flow modal per query to avoid a stale-result flash
* hide the write-confirmation message for direct-execute tools

**Refactors**

* share one seeder between blueprint and WP-CLI, fix landing redirect

= 0.1.0 =
* Initial release: "Ask Hoobert" command in the WordPress command palette, resolve/execute inference proxy, confirmation for destructive actions.

