<?php
declare(strict_types=1);

/**
 * Web-App-Manifest: damit sich die Anwendung am Handy und am Rechner wie eine
 * App „installieren" lässt (Zum Startbildschirm hinzufügen, App installieren).
 *
 * Das Manifest ist eine eigene Datei neben index.php (public/manifest.php),
 * nicht eine Route: Der Browser holt es ohne Sitzung, und alle Pfade darin
 * sind relativ zur Manifest-Adresse – so stimmen sie auch hinter dem Ingress
 * von Home Assistant, wo der Vorbau der Adresse je Aufruf anders ist.
 */

/** Die Symbole, die das Manifest nennt – Pfade relativ zu public/ */
const MANIFEST_SYMBOLE = [
    ['src' => 'assets/icon/app.svg',              'sizes' => 'any',     'type' => 'image/svg+xml', 'purpose' => 'any'],
    ['src' => 'assets/icon/app-192.png',          'sizes' => '192x192', 'type' => 'image/png',     'purpose' => 'any'],
    ['src' => 'assets/icon/app-512.png',          'sizes' => '512x512', 'type' => 'image/png',     'purpose' => 'any'],
    ['src' => 'assets/icon/app-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png',     'purpose' => 'maskable'],
];

/**
 * Baut das Manifest aus Name, Kurzname und Akzentfarbe.
 *
 * Reine Funktion ohne Datenbank – so lässt sie sich prüfen. Der Kurzname
 * steht unter dem Symbol auf dem Startbildschirm, dort passen etwa zwölf
 * Zeichen; ist keiner gesetzt, wird der Name auf diese Länge gekürzt.
 */
function manifest_daten(string $name, string $kurz, string $farbe): array
{
    $name = trim($name) !== '' ? trim($name) : 'OV-Multitool';
    $kurz = trim($kurz);
    if ($kurz === '') {
        $kurz = mb_strlen($name) > 12 ? rtrim(mb_substr($name, 0, 12)) : $name;
    }
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', trim($farbe))) {
        $farbe = '#003399';
    }
    $farbe = strtolower(trim($farbe));

    return [
        'id'               => './',
        'name'             => $name,
        'short_name'       => $kurz,
        'description'      => 'Das Werkzeug für den THW-Ortsverband',
        'lang'             => 'de',
        'start_url'        => 'index.php?p=dashboard',
        'scope'            => './',
        'display'          => 'standalone',
        'orientation'      => 'any',
        'background_color' => '#f1f5f9',
        'theme_color'      => $farbe,
        'icons'            => MANIFEST_SYMBOLE,
    ];
}

/**
 * Antwort auf GET manifest.php: [Statuscode, Kopfzeilen, Rumpf].
 *
 * Ohne eingerichtete Datenbank (Einrichtungsassistent) kommen die
 * Standardwerte – die Datei soll nie mit einem Fehler antworten.
 */
function manifest_antwort(string $methode, bool $mitDatenbank): array
{
    if ($methode !== 'GET' && $methode !== 'HEAD') {
        return [405, ['Content-Type' => 'text/plain; charset=utf-8', 'Allow' => 'GET, HEAD'], "Nur GET\n"];
    }

    $name  = 'OV-Multitool';
    $kurz  = '';
    $farbe = '#003399';
    if ($mitDatenbank) {
        try {
            $name  = (string)setting('app_name', $name);
            $kurz  = (string)setting('ov_kurz', '');
            $farbe = (string)setting('theme_color', $farbe);
        } catch (Throwable $e) {
            // Datenbank gerade nicht erreichbar: Standardwerte reichen für das Manifest
        }
    }

    $json = json_encode(manifest_daten($name, $kurz, $farbe), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    return [200, [
        'Content-Type'  => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ], $json . "\n"];
}
