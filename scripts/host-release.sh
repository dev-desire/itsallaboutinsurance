#!/usr/bin/env bash
# Host-side managed-code release. Run through a verified SSH connection.
set -euo pipefail
umask 077
iaai_web_root=$1
iaai_release_root=$2
iaai_release=$3
iaai_expected_url=$4
[[ "$iaai_release" =~ ^[a-f0-9]{40}$ ]] || exit 2
[[ "$iaai_web_root" = /* && "$iaai_release_root" = /* ]] || exit 2
iaai_web_root=$(realpath "$iaai_web_root")
iaai_release_root=$(realpath "$iaai_release_root")
case "$iaai_release_root/" in "$iaai_web_root/"*) exit 2;; esac
for iaai_binary in wp php unzip zipinfo flock curl; do command -v "$iaai_binary" >/dev/null; done
exec 9>"$iaai_release_root/deploy.lock"
flock -w 45 9 || exit 3
iaai_wp() { wp --path="$iaai_web_root" "$@"; }
[[ "$(iaai_wp option get home)" = "$iaai_expected_url" ]] || { printf '%s\n' 'Deployment URL differs from the configured site'; exit 4; }
mkdir -p "$iaai_release_root/releases"
iaai_stage=$(mktemp -d "$iaai_release_root/releases/$iaai_release.XXXXXX")
tar -tzf "$iaai_release_root/incoming/$iaai_release.tar.gz" | while IFS= read -r iaai_name; do [[ "$iaai_name" = iaai-portal.zip || "$iaai_name" = iaai-base.zip || "$iaai_name" = checksums.json ]] || exit 5; done
tar -xzf "$iaai_release_root/incoming/$iaai_release.tar.gz" -C "$iaai_stage"
php -r '$p=$argv[1];$m=json_decode(file_get_contents($p."/checksums.json"),true,16,JSON_THROW_ON_ERROR);foreach(["iaai-portal.zip","iaai-base.zip"]as$f){if(!isset($m[$f])||!hash_equals($m[$f],hash_file("sha256",$p."/".$f)))exit(1);}' "$iaai_stage"
for iaai_zip in iaai-portal iaai-base; do
  zipinfo -1 "$iaai_stage/$iaai_zip.zip" | while IFS= read -r iaai_entry; do
    [[ "$iaai_entry" = "$iaai_zip/"* && "$iaai_entry" != *'..'* && "$iaai_entry" != *'\\'* ]] || exit 5
  done
  unzip -q "$iaai_stage/$iaai_zip.zip" -d "$iaai_stage/code"
done
mkdir -p "$iaai_stage/previous"
iaai_old_release=$(iaai_wp option get iaai_release 2>/dev/null || true)
iaai_runtime=$(iaai_wp eval 'echo defined("IAAI_RUNTIME_MODE") ? IAAI_RUNTIME_MODE : "plugin";')
iaai_maintenance=0
iaai_swapped=0
iaai_rollback() {
  iaai_status=$?
  if ((iaai_status != 0 && iaai_swapped)); then
    iaai_wp iaai rollback-templates "$iaai_release" || true
    for iaai_pair in 'plugins iaai-portal' 'themes iaai-base'; do
      read -r iaai_kind iaai_name <<<"$iaai_pair"
      if [[ -d "$iaai_stage/previous/$iaai_name" ]]; then
        if [[ -d "$iaai_web_root/wp-content/$iaai_kind/$iaai_name" ]]; then mv "$iaai_web_root/wp-content/$iaai_kind/$iaai_name" "$iaai_stage/failed-$iaai_name"; fi
        cp -a "$iaai_stage/previous/$iaai_name" "$iaai_web_root/wp-content/$iaai_kind/$iaai_name"
      fi
    done
    if [[ "$iaai_runtime" = snippets ]]; then iaai_wp iaai sync-snippets --activate || true; fi
    [[ -z "$iaai_old_release" ]] || iaai_wp option update iaai_release "$iaai_old_release" >/dev/null
  fi
  if ((iaai_maintenance)); then iaai_wp maintenance-mode deactivate >/dev/null || true; fi
  exit "$iaai_status"
}
trap iaai_rollback EXIT
for iaai_pair in 'plugins iaai-portal' 'themes iaai-base'; do
  read -r iaai_kind iaai_name <<<"$iaai_pair"
  [[ -d "$iaai_web_root/wp-content/$iaai_kind/$iaai_name" ]] || { printf '%s\n' 'Initial plugin/theme installation is required before automatic updates'; exit 6; }
  cp -a "$iaai_web_root/wp-content/$iaai_kind/$iaai_name" "$iaai_stage/previous/$iaai_name"
done
iaai_wp maintenance-mode activate >/dev/null
iaai_maintenance=1
iaai_swapped=1
for iaai_pair in 'plugins iaai-portal' 'themes iaai-base'; do
  read -r iaai_kind iaai_name <<<"$iaai_pair"
  mv "$iaai_web_root/wp-content/$iaai_kind/$iaai_name" "$iaai_stage/displaced-$iaai_name"
  mv "$iaai_stage/code/$iaai_name" "$iaai_web_root/wp-content/$iaai_kind/$iaai_name"
done
if [[ "$iaai_runtime" = snippets ]]; then iaai_wp iaai sync-snippets --activate; fi
iaai_wp iaai install-pages --release="$iaai_release"
iaai_wp iaai jobs
iaai_wp iaai health
[[ "$(iaai_wp option get iaai_release)" = "$iaai_release" ]]
iaai_wp maintenance-mode deactivate >/dev/null
iaai_maintenance=0
curl --fail --silent --show-error "$iaai_expected_url/" -o /dev/null
curl --fail --silent --show-error "$iaai_expected_url/hi/" -o /dev/null
curl --fail --silent --show-error "$iaai_expected_url/wp-json/iaai/v1/health" | php -r '$d=json_decode(stream_get_contents(STDIN),true);if(($d["release"]??"")!==$argv[1])exit(1);' "$iaai_release"
iaai_anon_status=$(curl --silent --show-error -D "$iaai_stage/private-headers" -o /dev/null -w '%{http_code}' "$iaai_expected_url/wp-json/iaai/v1/dashboard")
[[ "$iaai_anon_status" = 401 || "$iaai_anon_status" = 403 ]]
rg -qi 'cache-control:.*no-store' "$iaai_stage/private-headers" || grep -qi 'cache-control:.*no-store' "$iaai_stage/private-headers"
# Code checks and template reconciliation have completed. Database/user files were never restored.
printf '%s\n' "$iaai_release" >"$iaai_release_root/current-release"
iaai_swapped=0
printf '%s\n' 'Managed release completed'
