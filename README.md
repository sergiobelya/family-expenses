# Family Expenses Telegram Bot

## Run locally with Docker

Requirements: Docker Desktop (or Docker Engine) with the Compose plugin.

1. Create `.env.local` in the project root if it does not exist, then set `TELEGRAM_BOT_TOKEN` to the token from BotFather. Keep the token secret; do not commit it or share it. The bot username and webhook secret can also be set there.
2. Start the supporting services:

   ```bash
   docker compose up -d postgres redis rabbitmq
   ```

3. Install PHP dependencies in the project container:

   ```bash
   docker compose run --rm php-app composer install
   ```

4. Create or update the database schema:

   ```bash
   docker compose run --rm php-app php bin/console doctrine:migrations:migrate
   ```

5. If the bot currently has a webhook configured, remove it before polling. Export the token in your shell first:

   ```bash
   export TELEGRAM_BOT_TOKEN='your-bot-token'
   curl -sS -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/deleteWebhook" \
     -d drop_pending_updates=false
   ```

6. Start the bot's long-polling service in the background:

   ```bash
   docker compose --profile bot up -d php-telegram-poll
   ```

   Follow its logs:

   ```bash
   docker compose --profile bot logs -f php-telegram-poll
   ```

   Stop polling with `docker compose --profile bot stop php-telegram-poll`. The container remains managed by Compose and can be started again with `docker compose --profile bot start php-telegram-poll`.

The PHP service is a CLI container, not a web server; interact with the application through Telegram.
Long polling and webhooks cannot run at the same time for one bot.
To switch to webhooks, stop polling and register a webhook URL that is publicly reachable over HTTPS.
The webhook endpoint is `/webhook/telegram`.

To stop the supporting services:

```bash
docker compose down
```

The database and service data are stored in Docker volumes and are preserved by this command.

## Debug with Xdebug

The development PHP image includes Xdebug, configured to connect to the IDE on the host at port `9003`. Rebuild it after changing the Dockerfile:

```bash
docker compose build php-app
```

In IDE, start listening for Xdebug connections, then set `XDEBUG_TRIGGER=1` when starting the PHP command. For the persistent polling service, recreate it with the trigger enabled:

```bash
XDEBUG_TRIGGER=1 docker compose --profile bot up -d --force-recreate php-telegram-poll
```

Follow the service logs in another terminal:

```bash
docker compose --profile bot logs -f php-telegram-poll
```

Stop and start it normally to disable the trigger:

```bash
docker compose --profile bot stop php-telegram-poll
docker compose --profile bot up -d php-telegram-poll
```

Without the trigger, Xdebug stays inactive and does not pause normal development commands.

## Run tests

```bash
docker compose run --rm php-app php vendor/bin/phpunit --configuration phpunit.dist.xml
```
