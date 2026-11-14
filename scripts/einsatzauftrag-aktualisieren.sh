#!/bin/sh
# Baut die Bibliothek "einsatzauftrag" (Einsatzauftrag Fb Fue 3 als PDF) fuer den Browser
# und holt jsPDF in der passenden Fassung.
#
#   einsatzauftrag  https://github.com/wattnpapa/einsatzauftrag
#                   Copyright (c) 2025-2026 Johannes Rudolph, EUPL-1.2
#   jsPDF           https://github.com/parallax/jsPDF
#                   Copyright (c) 2010-2025 James Hall, yWorks GmbH u. a., MIT
#
# Die Bibliothek wird NICHT veraendert: ihre TypeScript-Quellen (src/) werden nur in eine
# einzelne Browser-Datei uebersetzt. jsPDF kommt unveraendert aus dem npm-Paket.
# Lizenztexte liegen jeweils als LICENSE daneben und stehen in der Anwendung unter
# "Bibliotheken und Lizenzen" (ov-budget/src/lib/lizenzen.php) - dort den Stand nachziehen.
#
# Braucht: git, Node.js mit npm.
#   scripts/einsatzauftrag-aktualisieren.sh [Commit oder Tag] [jsPDF-Fassung]
set -e
STAND="${1:-f642655c20d75416f52412c1f3994618303ec0bf}"
JSPDF="${2:-4.2.1}"
ZIEL="$(cd "$(dirname "$0")/../ov-budget/public/assets/vendor" && pwd)"
BAU="$(mktemp -d)"
trap 'rm -rf "$BAU"' EXIT

git clone --quiet https://github.com/wattnpapa/einsatzauftrag.git "$BAU/quelle"
git -C "$BAU/quelle" checkout --quiet "$STAND"
COMMIT="$(git -C "$BAU/quelle" rev-parse HEAD)"
DATUM="$(git -C "$BAU/quelle" show -s --format=%cs HEAD)"

cd "$BAU"
printf '{"name":"bau","private":true}\n' > package.json
# keine Installationsskripte fremder Pakete ausfuehren
npm install --ignore-scripts --no-audit --no-fund "jspdf@$JSPDF" esbuild-wasm@0.28.2 >/dev/null

cat > kopf.txt <<EOF
/*!
 * einsatzauftrag – Einsatzauftrag (Fb Fü 3) als PDF mit jsPDF
 * Copyright (c) 2025-2026 Johannes Rudolph
 * Lizenz: EUPL-1.2 (European Union Public Licence, Version 1.2 oder später) – siehe LICENSE in diesem Ordner
 * Quelle: https://github.com/wattnpapa/einsatzauftrag, Stand $COMMIT ($DATUM)
 *
 * Unverändert aus den TypeScript-Quellen (src/) gebaut, nur in eine Browser-Datei übersetzt:
 * esbuild --bundle --format=iife --global-name=Einsatzauftrag. Siehe scripts/einsatzauftrag-aktualisieren.sh.
 */
EOF
node node_modules/esbuild-wasm/bin/esbuild quelle/src/index.ts --bundle --format=iife --global-name=Einsatzauftrag \
    --target=es2019 --charset=utf8 --banner:js="$(cat kopf.txt)" --outfile=einsatzauftrag.js

mkdir -p "$ZIEL/einsatzauftrag" "$ZIEL/jspdf"
cp einsatzauftrag.js "$ZIEL/einsatzauftrag/einsatzauftrag.js"
cp quelle/LICENSE "$ZIEL/einsatzauftrag/LICENSE"
cp node_modules/jspdf/dist/jspdf.umd.min.js "$ZIEL/jspdf/jspdf.umd.min.js"
cp node_modules/jspdf/LICENSE "$ZIEL/jspdf/LICENSE"

echo "einsatzauftrag $COMMIT ($DATUM) und jsPDF $JSPDF nach $ZIEL geschrieben."
echo "Bitte den Stand in ov-budget/src/lib/lizenzen.php nachziehen und die Lizenzkoepfe in den Dateien belassen."
