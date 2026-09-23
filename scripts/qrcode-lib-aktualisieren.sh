#!/bin/sh
# Holt die QR-Bibliothek in der angegebenen Fassung neu.
# Quelle: https://github.com/kazuhikoarase/qrcode-generator (MIT)
set -e
FASSUNG="${1:-1.4.4}"
ZIEL="$(dirname "$0")/../ov-budget/public/assets/js/qrcode.js"
curl -fsSL "https://cdn.jsdelivr.net/npm/qrcode-generator@${FASSUNG}/qrcode.js" -o "$ZIEL"
echo "qrcode-generator $FASSUNG nach $ZIEL geschrieben."
echo "Bitte den Lizenzkopf in der Datei belassen."
