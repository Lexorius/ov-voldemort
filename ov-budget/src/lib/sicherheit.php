<?php
declare(strict_types=1);

/**
 * Sicherheitslage der Laufzeit: Ist das PHP, auf dem die Anwendung (oder
 * ein Connector) läuft, gegen bekannte kritische Lücken gepatcht?
 *
 * PHP2Shell (CVE-2026-109618, CVE-2026-109619), Sicherheitsrelease vom
 * 21.10.2026: unauthentifizierte Codeausführung über Multipart-Anfragen und
 * den Sitzungs-Upload-Fortschritt – vor jedem Skript, unabhängig von der
 * Anwendung. Behoben ab 8.5.12, 8.4.27, 8.3.36, 8.2.35; 8.1 und älter
 * bekommen keine Releases mehr.
 */

/** Ab welcher Fassung je Zweig die Lücke geschlossen ist */
const PHP_SICHER_AB = ['8.5' => '8.5.12', '8.4' => '8.4.27', '8.3' => '8.3.36', '8.2' => '8.2.35'];
const PHP_LUECKE = 'PHP2Shell (CVE-2026-109618, CVE-2026-109619)';

/** Nur „8.4.21" aus Angaben wie „8.4.21-r0" oder „8.4.21 (fpm-fcgi)". Reine Funktion. */
function php_version_kern(string $version): string
{
    return preg_match('/\d+\.\d+\.\d+/', $version, $m) ? $m[0] : trim($version);
}

/**
 * Ist diese PHP-Fassung verwundbar? null = gepatcht, sonst der Grund für
 * Menschen. Unbekannte oder leere Angaben gelten als unbekannt (null mit
 * Hinweis über php_lage()). Reine Funktion.
 */
function php_verwundbar(string $version): ?string
{
    $v = php_version_kern($version);
    if (!preg_match('/^(\d+)\.(\d+)\.\d+$/', $v, $m)) {
        return null;
    }
    $zweig = $m[1] . '.' . $m[2];
    if (version_compare($v, '8.2.0', '<')) {
        return sprintf('PHP %s bekommt keine Sicherheitsupdates mehr – %s bleibt offen. Auf 8.4 oder 8.5 wechseln.', $v, PHP_LUECKE);
    }
    $ab = PHP_SICHER_AB[$zweig] ?? null;
    if ($ab === null) {
        return null;   // neuerer Zweig als bekannt – gilt als gepatcht
    }
    if (version_compare($v, $ab, '<')) {
        return sprintf('PHP %s ist verwundbar für %s – behoben ab %s.', $v, PHP_LUECKE, $ab);
    }
    return null;
}

/**
 * Lage der eigenen Laufzeit: Fassung, Urteil und welche Schutzmaßnahmen
 * greifen (für die Verwaltungsseite). Liest php.ini-Werte.
 */
function php_lage(): array
{
    $version = PHP_VERSION;
    $grund = php_verwundbar($version);
    $schutz = [
        'upload_progress' => !filter_var((string)ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN),
        'backtraces'      => version_compare($version, '8.5.0', '<') || !filter_var((string)ini_get('fatal_error_backtraces'), FILTER_VALIDATE_BOOLEAN),
        'memory_limit'    => (string)ini_get('memory_limit'),
        'post_max_size'   => (string)ini_get('post_max_size'),
    ];
    return [
        'version' => $version,
        'sapi'    => PHP_SAPI,
        'verwundbar' => $grund !== null,
        'grund'   => $grund,
        'sicher_ab' => PHP_SICHER_AB[preg_replace('/^(\d+\.\d+).*/', '$1', $version)] ?? null,
        'schutz'  => $schutz,
        'geschuetzt' => $schutz['upload_progress'] && $schutz['backtraces'],
    ];
}
