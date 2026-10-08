#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${DEPLOY_HOST:?}" "${DEPLOY_USER:?}" "${DEPLOY_PORT:?}" "${DEPLOY_SSH_KEY:?}" "${DEPLOY_KNOWN_HOSTS:?}" "${DEPLOY_WP_PATH:?}" "${DEPLOY_ROOT:?}" "${SITE_URL:?}" "${GITHUB_SHA:?}"
[[ "$DEPLOY_HOST" =~ ^[A-Za-z0-9.-]+$ && "$DEPLOY_USER" =~ ^[A-Za-z0-9_-]+$ && "$DEPLOY_PORT" =~ ^[0-9]+$ && "$GITHUB_SHA" =~ ^[a-f0-9]{40}$ ]]
[[ "$DEPLOY_WP_PATH" =~ ^/[A-Za-z0-9_./-]+$ && "$DEPLOY_ROOT" =~ ^/[A-Za-z0-9_./-]+$ && "$SITE_URL" =~ ^https://[A-Za-z0-9.-]+/?$ ]]
[[ "$DEPLOY_WP_PATH" != *'..'* && "$DEPLOY_ROOT" != *'..'* ]]
iaai_temp=$(mktemp -d)
trap 'rm -rf "$iaai_temp"' EXIT
printf '%s\n' "$DEPLOY_SSH_KEY" >"$iaai_temp/key"
printf '%s\n' "$DEPLOY_KNOWN_HOSTS" >"$iaai_temp/known_hosts"
iaai_ssh=(ssh -i "$iaai_temp/key" -p "$DEPLOY_PORT" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$iaai_temp/known_hosts")
iaai_scp=(scp -i "$iaai_temp/key" -P "$DEPLOY_PORT" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$iaai_temp/known_hosts")
"${iaai_ssh[@]}" "$DEPLOY_USER@$DEPLOY_HOST" "mkdir -p '$DEPLOY_ROOT/incoming' '$DEPLOY_ROOT/releases'"
tar -czf "$iaai_temp/$GITHUB_SHA.tar.gz" -C dist iaai-portal.zip iaai-base.zip checksums.json
"${iaai_scp[@]}" "$iaai_temp/$GITHUB_SHA.tar.gz" "$DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_ROOT/incoming/$GITHUB_SHA.tar.gz"
"${iaai_ssh[@]}" "$DEPLOY_USER@$DEPLOY_HOST" "bash -s -- '$DEPLOY_WP_PATH' '$DEPLOY_ROOT' '$GITHUB_SHA' '$SITE_URL'" <scripts/host-release.sh
python3 scripts/smoke.py "$SITE_URL" "$GITHUB_SHA"
