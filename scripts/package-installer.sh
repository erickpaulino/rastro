#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$ROOT/.build/installer"
ZIP_PATH="$ROOT/rastro-installer.zip"

echo "[+] Limpando diretórios anteriores..."
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

echo "[+] Copiando arquivos..."
rsync -a \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude '.build' \
  --exclude 'db_temp' \
  --exclude 'data/cache' \
  --exclude '.env' \
  --exclude '*.zip' \
  "$ROOT"/ "$BUILD_DIR"/

echo "[+] Gerando pacote..."
(
  cd "$BUILD_DIR"
  zip -qr "$ZIP_PATH" .
)

echo "[+] Pacote criado em $ZIP_PATH"
