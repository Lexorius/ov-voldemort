<?php
/**
 * Nachbildung der benötigten mb_*-Funktionen – ausschließlich für lokale Tests.
 *
 * Eine Windows-Anwendungssteuerungsrichtlinie blockiert php_mbstring.dll auf
 * dem Entwicklungsrechner. Im Container läuft das echte mbstring; diese Datei
 * wird nur per auto_prepend_file in Testläufe eingebunden.
 * Grundlage sind die fest einkompilierten Erweiterungen iconv und PCRE.
 */
declare(strict_types=1);

if (function_exists('mb_strtolower')) {
    return;
}

function mb_check_encoding(?string $value = null, ?string $encoding = null): bool
{
    return preg_match('//u', (string)$value) === 1;
}

function mb_convert_encoding(string $string, string $to, ?string $from = null): string
{
    $alias = ['WINDOWS-1252' => 'CP1252', 'ISO-8859-1' => 'ISO-8859-1', 'UTF-16LE' => 'UTF-16LE',
              'UTF-16BE' => 'UTF-16BE', 'UTF-8' => 'UTF-8'];
    $von = $alias[strtoupper((string)$from)] ?? (string)$from;
    $nach = $alias[strtoupper($to)] ?? $to;
    $out = @iconv($von, $nach . '//IGNORE', $string);
    return $out === false ? '' : $out;
}

/** In Zeichen zerlegen */
function mb_shim_chars(string $s): array
{
    return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function mb_strlen(string $string, ?string $encoding = null): int
{
    return count(mb_shim_chars($string));
}

function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
{
    return implode('', array_slice(mb_shim_chars($string), $start, $length));
}

/**
 * Kleinschreibung für ASCII und den Latin-1-Zusatzbereich (À–Þ ohne ×) –
 * deckt deutsche Umlaute und die üblichen Akzentbuchstaben ab.
 */
function mb_strtolower(string $string, ?string $encoding = null): string
{
    return (string)preg_replace_callback('/[A-Z\x{00C0}-\x{00DE}]/u', static function (array $m): string {
        $cp = mb_shim_ord($m[0]);
        if ($cp === 0xD7) {
            return $m[0];
        }
        return mb_shim_chr($cp + 0x20);
    }, $string);
}

function mb_strtoupper(string $string, ?string $encoding = null): string
{
    return (string)preg_replace_callback('/[a-z\x{00E0}-\x{00FE}]/u', static function (array $m): string {
        $cp = mb_shim_ord($m[0]);
        if ($cp === 0xF7) {
            return $m[0];
        }
        return mb_shim_chr($cp - 0x20);
    }, $string);
}

function mb_shim_ord(string $c): int
{
    $u = iconv('UTF-8', 'UCS-4BE', $c);
    return $u === false ? 0 : unpack('N', $u)[1];
}

function mb_shim_chr(int $cp): string
{
    return (string)iconv('UCS-4BE', 'UTF-8', pack('N', $cp));
}

function mb_internal_encoding(?string $encoding = null): string|bool
{
    return $encoding === null ? 'UTF-8' : true;
}

// Unbedingt deklariert wie die übrigen: PHP hebt solche Funktionen beim Übersetzen
// an, bedingte Blöcke nach dem frühen return oben würden nie erreicht.
function mb_strimwidth(string $s, int $start, int $breite, string $ende = '', ?string $enc = null): string
{
    // Grobe Nachbildung für die Tests (Zeichen statt Anzeigebreite)
    $s = mb_substr($s, $start);
    if (mb_strlen($s) <= $breite) {
        return $s;
    }
    return mb_substr($s, 0, max(0, $breite - mb_strlen($ende))) . $ende;
}
