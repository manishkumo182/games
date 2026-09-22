# cPanel deployment

## 1. Hosting requirements

Use PHP 8.2.30 or newer (8.3/8.4 supported), Composer 2, Apache mod_rewrite, HTTPS, and MySQL 8 / MariaDB with InnoDB. Enable ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, pdo_mysql, session, tokenizer, and xml. This app uses Blade and static JS/CSS, so Node.js is not required on the host.

The `pocket-arcade-cpanel.zip` archive includes production Composer dependencies. For a source deployment, run `composer install --no-dev --optimize-autoloader` in the app directory instead. Do not run development servers on cPanel.

## 2. Upload outside the public web root

Extract the app into a private directory such as `/home/CPANEL_USER/pocket-arcade`. In cPanel Domains, point the site/subdomain document root to `/home/CPANEL_USER/pocket-arcade/public`. Only the contents of `public/` should be web-accessible. Include its hidden `.htaccess` file.

If cPanel will not let you change the main domain's document root: keep the application at `/home/CPANEL_USER/pocket-arcade`, copy the contents of `public/` (including `.htaccess`) into `public_html/`, and edit the three paths in `public_html/index.php` to reference the private app:

```php
if (file_exists($maintenance = __DIR__.'/../pocket-arcade/storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../pocket-arcade/vendor/autoload.php';
$app = require_once __DIR__.'/../pocket-arcade/bootstrap/app.php';
```

Preserve the remaining `index.php` code, including `$app->handleRequest(Request::capture());`. Never copy `.env`, `vendor`, `storage`, or application source into `public_html`. Use a domain or subdomain root; this UI currently assumes root-relative asset/API paths, not installation into a URL subfolder.

## 3. Database and environment

Create a database and database user in cPanel MySQL Databases; grant that user permissions on that database. Copy `.env.example` to `.env` in the private app directory and configure:

```dotenv
APP_NAME="Pocket Arcade"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://games.example.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpanel_arcade
DB_USERNAME=cpanel_arcade
DB_PASSWORD="your-database-password"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=sync

STRIPE_SECRET=sk_test_your_key
STRIPE_WEBHOOK_SECRET=whsec_your_endpoint_secret
```

Use your real domain, database credentials, and Stripe sandbox values. Leave SESSION_DOMAIN unset unless you explicitly need cross-subdomain cookies. Set your site timezone if desired; game and payment logic does not depend on it.

In cPanel Terminal or SSH, from the app directory:

```sh
php artisan key:generate
php artisan migrate --force
php artisan optimize
```

If `php` points to the wrong version, use your host's configured PHP executable. Ensure `storage/` and `bootstrap/cache/` are writable by the account's PHP process. Use the host's standard permissions, typically directories 755/775 and files 644/664, not 777. Do not expose `.env` publicly. Back up the database and APP_KEY together; do not regenerate the key on later deployments.

If your plan has no Terminal/SSH, ask the host to run the three Artisan commands. Do not add a public install script or migration URL.

## 4. Stripe

1. Enter the Stripe sandbox secret key in `.env`.
2. Create an HTTPS webhook endpoint `https://games.example.com/stripe/webhook` in Stripe Workbench. Subscribe to `checkout.session.completed` and `checkout.session.async_payment_succeeded`.
3. Put that endpoint's signing secret into STRIPE_WEBHOOK_SECRET and run `php artisan config:cache`.
4. Complete a real sandbox Checkout from this website. Use Stripe's documented test card, confirm that the charged amount is $1 USD, and verify the pass activates after payment.
5. Verify cancellation preserves free plays, an unpaid session does not unlock, webhook retries do not create duplicate purchases, and the recovery code restores access in another browser. Also test closing Checkout after payment before returning; the webhook should grant the original browser access on refresh.
6. For launch, replace the sandbox key with your live secret key, configure a separate live webhook endpoint/signing secret, and run `php artisan config:cache` again.

This app creates its $1 USD line item server-side, so no Stripe Price ID or publishable key is needed. It uses hosted Checkout and never handles card details itself. Missing credentials produce a clear unavailable message rather than a fake payment success.

The browser success URL alone cannot grant access. The backend retrieves the Stripe session, verifies ownership, and checks the same payment criteria as the signature-verified webhook. The webhook must be configured even though the return route also fulfills, because a customer may never return to the website.

## 5. Smoke check and maintenance

- Check both games on desktop and mobile; finish five Hangman rounds and five blackjack rounds to exercise the independent paywalls.
- Verify an unfinished round survives refresh and guesses/dealer hole cards are not leaked.
- Visit `/up` for the Laravel health route. Review `storage/logs/laravel.log` if needed; never share raw logs containing secrets.
- Back up MySQL and APP_KEY. Paid access and recovery depend on them.
- For updates, back up first, deploy source/dependencies, run `php artisan migrate --force` and `php artisan optimize`. Keep the existing `.env` and data.

No queue worker or cron is necessary for normal gameplay/fulfillment. Finished rounds are retained; add a retention policy if storage growth warrants it. Refund/dispute revocation is currently manual. Stripe sandbox/live verification and actual cPanel deployment remain to be done with your hosting and Stripe account.

## Updating to the game-library release

This release adds `/games/hangman`, `/games/blackjack`, and `/games/daily`, a homepage of cards, and a new `daily_plays`/`puzzle_date` migration. Upload the updated source and static files, keep your production `.env` and database, then run `php artisan migrate --force` and `php artisan optimize`. Existing guests, purchases, and saved classic games remain valid.

Daily Word changes at midnight UTC using the request date. Do not configure cron or queue workers for it. There is one puzzle per day; the $1 pass includes future daily puzzles as well as unlimited classic rounds. Five daily puzzles are free. The browser shows the UTC reset schedule.

## Tic Tac Toe update

Deploy the updated application and static assets, preserving `.env` and the database. Run `php artisan migrate --force` (adds `tictactoe_plays`) and `php artisan optimize`. The new game is at `/games/tictactoe`; existing purchases automatically include it.

## Fade Tac Toe update

Upload the updated files, preserve `.env` and your database, and run `php artisan migrate --force` and `php artisan optimize`. This adds the `fade_plays` counter and `/games/fade`. No additional runtime dependencies, scheduled jobs, or workers are required. Existing passes include the new game.

## Multiplayer update

Deploy the new multiplayer migration and all updated source/public files, then run `php artisan migrate --force` and `php artisan optimize`. Keep the existing APP_KEY and database. No worker or scheduled task is needed. Allow same-origin GET/POST requests to `/api/social`, `/api/rooms/*`, and `/api/invitations/*`; do not cache these responses in a CDN. Use InnoDB so room and quota row locks work across PHP requests.

Smoke test using two separate browsers/devices: copy a guest name, send and accept an invitation, mark both Ready, and start as host. Check turn updates, refresh reconnection, a completed round, and shared solo/multiplayer quotas. For Blackjack, verify all players see the same dealer after the last hand stands. Links from localhost only work on that computer; deployed links use the site’s HTTPS domain.

Dots and Boxes adds player quotas and a saved room board size. Apply both 2026-09-22 migrations with `php artisan migrate --force`, then `php artisan optimize`. Upload the new dots-board.js and dots.js along with the updated shared assets and views.
