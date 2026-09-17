#!/usr/bin/env bash
# Prepare .env for the no-services unit job. DB/Redis/MeiliSearch are
# pointed at 127.0.0.1 with nothing listening: connections fail fast
# (Connection refused) instead of hanging on getaddrinfo. Any test that
# actually needs a service fails loudly — that is the point of the suite.
set -euo pipefail

cp .env.example .env
sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=array/' .env
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env
sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=array/' .env
sed -i 's/^CHANNEL_NAME_SETTING=.*/CHANNEL_NAME_SETTING=/' .env
sed -i 's/^CHANNEL_NAME_MODEL_EVENT=.*/CHANNEL_NAME_MODEL_EVENT=/' .env
echo "SCOUT_DRIVER=null" >> .env
sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
sed -i 's/^REDIS_HOST=.*/REDIS_HOST=127.0.0.1/' .env
sed -i 's/^MEILISEARCH_HOST=.*/MEILISEARCH_HOST=127.0.0.1/' .env
