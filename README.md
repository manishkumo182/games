# Pocket Arcade

A Laravel 12 game portal with Blade, plain JavaScript, and responsive CSS. No Node.js process, frontend build, account registration, queue worker, or scheduler is required for the game and payment flows.

## Run locally

Requirements: PHP 8.2.30 or newer and Composer 2. PHP 8.3/8.4 are also supported. Enable Laravel's standard PHP extensions plus PDO SQLite for local development or PDO MySQL for hosting.

```sh
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. The supplied working directory already has dependencies, a local application key, and a migrated SQLite database. The distributable ZIP excludes local keys, databases, logs, and sessions. See DEPLOYMENT.md for cPanel.

## Included

- Hangman: three difficulty levels, curated random word lists, 8/7/6 wrong guesses, visible stick figure, touch and keyboard input, win/loss states.
- Blackjack: six complete decks; cryptographic random Fisher–Yates shuffle; cards removed as dealt; shoe retained across rounds and reshuffled below 78 cards only between rounds. Dealer stands on all 17s and peeks for blackjack. Natural blackjack pays 3:2. Double, split equal-value pairs up to four hands, double after split, split aces receive one card each, split 21 pays 1:1. Ties push. No insurance or surrender. Ten play chips per starting hand; no deposits, withdrawals, or cash prizes.
- Five free **started rounds per game**, independently counted on the server. Refreshes and concurrent starts resume the active round; splits count as one round. Rounds must finish before a new one starts.
- One-time $1 USD Stripe Checkout unlock for all games. No subscription. Signature-verified webhook and verified Checkout return both fulfill idempotently. Only paid USD 100-cent sessions for this product/visitor grant access.
- Encrypted HTTP-only guest cookie and database-backed state. Hidden words, shoe order, and dealer hole cards remain on the server. CSRF protection (webhook uses Stripe signatures instead), request throttles, database transactions and player row locks, plus session locking for game actions.
- Private recovery code for paid access on other browsers, shown in the pass dialog. Recovery attempts are rate limited. Codes are encrypted at rest and indexed by SHA-256; keep APP_KEY backed up.

## Behavior and limits

Trials are per browser identity, not provably per person. Clearing cookies/private browsing permits a fresh trial. This is the deliberate consequence of no-login access; no invasive fingerprinting is used. Paid access stays linked to the existing browser and is restored with the private code. Codes are bearer credentials: sharing one shares access. There is no email recovery system; save the code after checkout.

No real Stripe payment was taken during development. Automated tests use signed local webhook fixtures. Run a Stripe sandbox checkout on the deployed HTTPS domain before switching to live mode. Stripe credentials and cPanel access were not supplied, so the project is not publicly deployed and checkout is intentionally unavailable until configured.

Current implementation does not automatically revoke access after refunds/disputes and does not implement purchase-recovery email. Handle these manually or extend the payment lifecycle before adopting a refund policy that requires automatic revocation. A pass is a one-time entitlement, not an ongoing billing subscription.

Fonts are loaded from Google Fonts with local system fallbacks; games have no third-party JavaScript dependency.

## Tests

```sh
php artisan test
node --check public/arcade.js
```

Tests cover totals, aces, naturals, splits, doubles, dealer soft 17, hole-card masking, 1,500 full blackjack rounds with card conservation, Hangman results, free quotas, resume behavior, ownership, recovery, amount/currency validation, repeated fulfillment, signed webhook events, and forged signature rejection.

## Main files

- `app/Services/Hangman.php`, `app/Services/Blackjack.php`: pure game engines.
- `app/Http/Controllers/GameController.php`: transactional rounds and quotas.
- `app/Http/Controllers/PaymentController.php`: Stripe Checkout, fulfillment, recovery.
- `resources/views/arcade.blade.php`, `public/arcade.css`, `public/arcade.js`: UI.
- `database/migrations/2026_09_14_000001_create_arcade_tables.php`: portable MySQL/SQLite schema.

Implementation references: [Laravel deployment](https://laravel.com/docs/12.x/deployment), [Stripe Checkout fulfillment](https://docs.stripe.com/checkout/fulfillment), [Stripe webhook signatures](https://docs.stripe.com/webhooks/signature).

## Separate pages and Daily Word

The homepage `/` is a library of linked cards. Games live at `/games/hangman`, `/games/blackjack`, and `/games/daily`; links support browser history and direct entry. Payment and recovery are available on every page.

Daily Word is a Wordle-style puzzle with five letters, six guesses, and duplicate-aware green/yellow/gray scoring. A curated list of more than 600 five-letter words serves as the answer pool and accepted-guess dictionary; it is intentionally not an exhaustive English dictionary. Answers advance through a fixed shuffled sequence by UTC calendar date, shared by every player, with no cron, queue, job, or database seeder. The pool eventually cycles. Keep the word list order stable after launch so an in-progress daily answer does not change.

The puzzle changes at 00:00 UTC. An open page refreshes its status at the next reset and when returning to a previously hidden tab; the server always enforces the date. Unfinished old puzzles expire. Today’s guesses and results persist, including finished puzzles, and a unique per-player/day database constraint prevents extra daily attempts. The answer remains server-side until a win or loss. One puzzle per day applies to both free and paid players.

Daily Word has its own five-puzzle lifetime trial, counted when starting a day, independent of Hangman/Blackjack. Existing paid passes also unlock Daily Word. Run the new migration on an existing installation:

```sh
php artisan migrate --force
php artisan optimize
```

Validation: 26 tests pass, including midnight rollover, resuming a finished daily game, per-game quotas, invalid word rejection, six-guess loss, and repeated-letter scoring. JavaScript syntax checks and browser navigation/guess submission were also checked.

## Tic Tac Toe

Play at `/games/tictactoe`, linked from the four-card homepage. You play X and go first against the computer (O). The computer takes winning moves, blocks immediate threats, and otherwise prefers the center and corners; it is deliberately beatable. Wins, losses, draws, and winning lines are shown on the board. In-progress games resume after refresh. State and moves are validated on the server, including occupied cells and stale turns.

Five started games are free independently of the other games; existing $1 passes include unlimited Tic Tac Toe. Run `php artisan migrate --force` and `php artisan optimize` after deploying this update. No additional dependencies, cron, or queue are needed. All 31 tests pass; starting a game and the computer reply were checked in the browser.

## Fade Tac Toe

A separate game at `/games/fade`. You play X against the computer (O). Wins end immediately, including a win on the ninth square. When the board fills without a winner, the oldest two surviving X marks and oldest two surviving O marks fade away automatically. Other marks remain; chronological order and alternating turns continue across every cycle. The computer automatically plays after a player-triggered fade. Cycles do not consume extra free plays. Refresh resumes the settled board without replaying an old animation. Reduced-motion preferences shorten the transition. There is no cycle limit; play ends only on a win.

Five independent free games; included in existing unlimited passes. Deploy the new `fade_plays` migration with `php artisan migrate --force` then `php artisan optimize`. All 35 tests pass, including oldest-mark ordering across two cycles, ninth-square wins, automatic computer continuation, resume, quotas, and stale-turn rejection. The first full-board cycle was verified through browser play.

## Guest multiplayer

Visit `/multiplayer` or choose **Play with friends** from a game. Every browser receives a persistent, unique funny guest name. Copy the full name and invite it from a room; the recipient accepts or declines in the Invitations panel on any page. Alternatively share the room link: anyone holding it can join an available seat. Names and online presence are visible to other visitors; names are not authentication credentials. Invitations expire after ten minutes and repeat invitations are throttled.

Tic Tac Toe and Fade Tac Toe support two human players. Blackjack supports two to five players against one computer dealer. Each table round uses a fresh shuffled six-deck shoe shared by all players. The dealer reveals only after all hands finish, with the same split/double/payout rules as solo. Hangman and Daily Word remain solo; all existing computer modes remain available.

Everyone must choose Ready, then the host starts. Joining, invitations, refreshes, and Fade cycles cost no plays. A started round charges each participant once against the existing five-started-round allowance **per game and browser identity**, shared between solo and multiplayer. Existing $1 passes cover multiplayer. The allowance does not reset on a page refresh or a new room.

Room state lives on the server, with transactional room locks, player quota locks, per-hand/board revisions, and round identifiers rejecting replayed moves. Only members receive gameplay state; dealer hole cards and shoe order stay hidden. Each guest can occupy one room. A 120-second idle turn forfeits a board game or automatically stands a Blackjack hand. Refresh reconnects; waiting/finished-room seats expire after five minutes away, and host ownership transfers to a remaining player. Timeouts are evaluated by incoming requests.

The browser polls room state every two seconds (less frequently in background tabs), and invitations every six seconds. No WebSocket service, cron, queue, Node process, or new Composer package is required. This suits a modest cPanel portal; size hosting capacity for polling traffic before a large launch. Rooms and invitations are retained in the database; add a retention policy when needed.

For an existing deployment, upload the updated source and public assets, preserve `.env` and data, then run `php artisan migrate --force` and `php artisan optimize`. Test with separate browsers/devices: two tabs in one browser intentionally share one guest identity.

Current validation: 43 automated tests pass (3,394 assertions), including multi-guest invitations, consent, quotas, turn ownership, replay rejection, Fade clearing, five-seat capacity, shared-card conservation, dealer masking, idle timeouts, and host transfer. Multiplayer lobby creation and leaving a room were also verified in the browser.
