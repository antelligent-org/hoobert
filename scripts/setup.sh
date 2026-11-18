#!/bin/sh
#
# Provisions the local Hoobert development stack. Runs inside the wpcli container
# on `docker compose up` (see docker-compose.yml) and is safe to re-run: every
# step checks state first. Re-run on demand (e.g. after building the plugin) with
#   docker compose run --rm wpcli
#
# It installs WordPress + WooCommerce into the shared volume, activates Hoobert
# (build the JS bundle first), seeds sample data, and mints a WooCommerce REST
# API key pair, printing the credentials to this container's log.
set -eu

WP_PATH=/var/www/html
SITE_URL="${WP_SITE_URL:-http://localhost:8080}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASS="${WP_ADMIN_PASSWORD:-admin_password}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
SITE_TITLE="${WP_SITE_TITLE:-Hoobert Dev Store}"

cd "$WP_PATH"

echo "==> Waiting for WordPress core files…"
i=0
until [ -f "$WP_PATH/wp-load.php" ]; do
	i=$((i + 1))
	if [ "$i" -gt 60 ]; then
		echo "!! WordPress core files never appeared in ${WP_PATH}."
		exit 1
	fi
	sleep 2
done

echo "==> Waiting for the database…"
until wp db check >/dev/null 2>&1; do
	sleep 2
done

if wp core is-installed >/dev/null 2>&1; then
	echo "==> WordPress already installed."
else
	echo "==> Installing WordPress at ${SITE_URL}…"
	wp core install \
		--url="$SITE_URL" \
		--title="$SITE_TITLE" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASS" \
		--admin_email="$ADMIN_EMAIL" \
		--skip-email
fi

echo "==> Installing + activating WooCommerce…"
wp plugin is-installed woocommerce >/dev/null 2>&1 || wp plugin install woocommerce
wp plugin activate woocommerce

echo "==> Configuring store basics…"
wp option update woocommerce_store_address "123 Sample Street" >/dev/null
wp option update woocommerce_default_country "US:CA" >/dev/null
wp option update woocommerce_currency "USD" >/dev/null
wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json >/dev/null || true
# Enable the legacy REST reports the sales/top-seller tools use.
wp option update woocommerce_analytics_enabled "yes" >/dev/null || true

echo "==> Activating Hoobert…"
if ! wp plugin activate hoobert 2>/dev/null; then
	echo "!! Hoobert did not activate. Build the JS bundle, then re-run provisioning:"
	echo "   (cd plugin/hoobert && npm install && npm run build) && docker compose run --rm wpcli"
fi

echo "==> Seeding sample data…"
wp eval-file /scripts/seed-sample-data.php

echo "==> Generating WooCommerce REST API keys…"
wp eval-file /scripts/generate-api-key.php

echo
echo "==> Done."
echo "    Store:  ${SITE_URL}"
echo "    Admin:  ${SITE_URL}/wp-admin  (${ADMIN_USER} / ${ADMIN_PASS})"
echo
echo "    Press Cmd/Ctrl-K in wp-admin. Hoobert answers on Fernfly's shared model;"
echo "    to use your own project, set it under WooCommerce → Hoobert."
