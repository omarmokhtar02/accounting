#!/usr/bin/env bash
# Deploy a CI-built image tag on the production host.
#   scripts/deploy.sh sha-abc1234
# Steps: backup -> pull -> migrate (one-off container) -> rolling restart -> health check -> rollback on failure.
set -euo pipefail

TAG="${1:?usage: deploy.sh <image-tag>}"
COMPOSE="docker compose -f docker-compose.prod.yml --env-file .env"
STATE_DIR=".deploy"
mkdir -p "$STATE_DIR"
PREVIOUS_TAG="$(cat "$STATE_DIR/current_tag" 2>/dev/null || echo "")"

echo "==> Backing up the database"
./scripts/db_backup.sh

echo "==> Pulling images for $TAG"
IMAGE_TAG="$TAG" $COMPOSE pull

echo "==> Running migrations (expand/contract: keep them backwards compatible, rollback does not undo them)"
IMAGE_TAG="$TAG" $COMPOSE run --rm --no-deps app php artisan migrate --force

echo "==> Restarting services"
IMAGE_TAG="$TAG" $COMPOSE up -d --remove-orphans

echo "==> Warming caches"
IMAGE_TAG="$TAG" $COMPOSE exec -T app sh -c "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache"

echo "==> Waiting for /health"
for i in $(seq 1 30); do
  if curl -fsS http://127.0.0.1/health >/dev/null; then
    echo "$TAG" > "$STATE_DIR/current_tag"
    echo "Deployed $TAG"
    exit 0
  fi
  sleep 2
done

echo "Health check failed."
if [ -n "$PREVIOUS_TAG" ]; then
  echo "==> Rolling back to $PREVIOUS_TAG"
  IMAGE_TAG="$PREVIOUS_TAG" $COMPOSE up -d
fi
exit 1
