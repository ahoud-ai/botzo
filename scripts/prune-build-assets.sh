#!/usr/bin/env bash
set -euo pipefail

APPLY_FLAG=""
BUILD_DIR="public/build"
FORMAT="text"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply)
      APPLY_FLAG="--apply"
      shift
      ;;
    --build-dir)
      BUILD_DIR="${2:-public/build}"
      shift 2
      ;;
    --format)
      FORMAT="${2:-text}"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

php scripts/prune-build-assets.php --build-dir="$BUILD_DIR" --format="$FORMAT" $APPLY_FLAG
