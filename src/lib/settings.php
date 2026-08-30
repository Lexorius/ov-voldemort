<?php
declare(strict_types=1);

/** Alle Einstellungen (gecacht pro Request) */
function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT * FROM settings ORDER BY sgroup, sort_order, skey') as $r) {
            $cache[$r['skey']] = $r;
        }
    }
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    if (!isset($all[$key])) {
        return $default;
    }
    $v = $all[$key]['svalue'];
    return ($v === null || $v === '') ? $default : $v;
}

function setting_bool(string $key, bool $default = false): bool
{
    $v = setting($key, $default ? '1' : '0');
    return in_array((string)$v, ['1', 'true', 'ja', 'yes', 'on'], true);
}

function setting_int(string $key, int $default = 0): int
{
    return (int)setting($key, $default);
}

function setting_float(string $key, float $default = 0.0): float
{
    return (float)str_replace(',', '.', (string)setting($key, $default));
}

function setting_save(string $key, ?string $value): void
{
    db_exec(
        'INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
        [$key, $value]
    );
}

/** Gruppierte Einstellungen für die Adminmaske */
function settings_grouped(): array
{
    $out = [];
    foreach (settings_all() as $s) {
        $out[$s['sgroup']][] = $s;
    }
    return $out;
}

/**
 * Zusätzliche Freifelder für Wünsche, im Admin als Text gepflegt:
 *   schluessel|Beschriftung|typ
 */
function wish_extra_fields(): array
{
    $raw = (string)setting('wunsch_extra_felder', '');
    $out = [];
    foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $key = slugify($parts[0] ?? '');
        if ($key === '') {
            continue;
        }
        $out[$key] = [
            'key'   => $key,
            'label' => $parts[1] ?? $parts[0],
            'type'  => in_array($parts[2] ?? 'text', ['text', 'textarea', 'number', 'bool', 'date'], true)
                ? $parts[2] : 'text',
        ];
    }
    return $out;
}
