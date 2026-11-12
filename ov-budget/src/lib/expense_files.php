<?php
declare(strict_types=1);

/*
 * Belege zu Ausgaben und Einnahmen: Rechnung, Quittung, Bescheid, Kontoauszug.
 *
 * Ein Beleg hängt an genau einer Buchung und lässt sich gleich beim Erfassen
 * mitgeben. Er liegt im Ordner „budget" der Ablage, geht durch die
 * KI-Auswertung (Ablage „buchung") und steht in der Dateiablage unter
 * Budget › Jahr › Ausgaben bzw. Einnahmen. Wird die Buchung gelöscht,
 * gehen ihre Belege mit.
 *
 * Sehen darf einen Beleg, wer die Buchungen sieht; anhängen und entfernen,
 * wer sie pflegt.
 */

function xfile_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'budget';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Belege einer Buchung, älteste zuerst */
function xfile_list(int $expenseId): array
{
    return db_all('SELECT f.*, u.display_name AS hochgeladen_von FROM expense_files f LEFT JOIN users u ON u.id = f.uploaded_by
                   WHERE f.expense_id = ? ORDER BY f.created_at, f.id', [$expenseId]);
}

/** Belege vieler Buchungen auf einmal: [expense_id => Belege[]] – für die Liste */
function xfile_fuer_buchungen(array $expenseIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $expenseIds))));
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (db_all('SELECT id, expense_id, titel, orig_name, mime FROM expense_files WHERE expense_id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY created_at, id', $ids) as $f) {
        $out[(int)$f['expense_id']][] = $f;
    }
    return $out;
}

/** Ein Beleg samt dem, was die Buchung über ihn sagt (Art, Jahr, Bezeichnung, Datum, Lieferant) */
function xfile_find(?int $id): ?array
{
    return $id ? db_row(
        'SELECT f.*, x.art AS buchung_art, x.jahr AS buchung_jahr, x.bezeichnung AS buchung_bezeichnung,
                x.datum AS buchung_datum, x.lieferant AS buchung_lieferant
         FROM expense_files f JOIN expenses x ON x.id = f.expense_id WHERE f.id = ?', [$id]) : null;
}

/** Pfad zur Datei oder zur Vorschau – oder null */
function xfile_path(array $datei, bool $vorschau = false): ?string
{
    $name = $vorschau && ($datei['thumb_name'] ?? null) ? $datei['thumb_name'] : $datei['stored_name'];
    $pfad = xfile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}

/**
 * Hochgeladene Belege an eine Buchung hängen. Gibt [anzahl, fehler[]] zurück.
 * Fotos (die abfotografierte Quittung) bleiben groß und lesbar, verlieren aber
 * den Aufnahmeort. Jeder Beleg geht danach in die KI-Auswertung.
 */
function xfile_store_uploads(int $expenseId, string $feld, string $titel, array $user): array
{
    [$dateien, $fehler] = upload_dokumente_ablegen($feld, xfile_dir());
    foreach ($dateien as $d) {
        $id = db_insert('expense_files', [
            'expense_id'  => $expenseId,
            'art'         => $d['art'],
            'titel'       => trim($titel) !== '' ? mb_substr(trim($titel), 0, 200) : $d['titel'],
            'orig_name'   => $d['orig_name'],
            'stored_name' => $d['stored_name'],
            'thumb_name'  => $d['thumb_name'],
            'mime'        => $d['mime'],
            'size_bytes'  => $d['size_bytes'],
            'uploaded_by' => (int)$user['id'],
        ]);
        if (function_exists('ki_einreihen')) {
            ki_einreihen('buchung', $id, $d['art']);
        }
        audit('buchung.beleg_angehaengt', 'expense', $expenseId, $d['orig_name']);
    }
    return [count($dateien), $fehler];
}

function xfile_delete(array $datei): void
{
    foreach ([$datei['stored_name'] ?? '', $datei['thumb_name'] ?? ''] as $name) {
        if ($name) {
            $pfad = xfile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
        }
    }
    db_exec('DELETE FROM expense_files WHERE id = ?', [(int)$datei['id']]);
    if (function_exists('ki_vergessen')) {
        ki_vergessen('buchung', (int)$datei['id']);
    }
    audit('buchung.beleg_entfernt', 'expense', (int)$datei['expense_id'], (string)($datei['titel'] ?: $datei['orig_name']));
}

/** Alle Belege einer Buchung entfernen – bevor die Buchung selbst gelöscht wird. Gibt die Zahl zurück. */
function xfile_delete_alle(int $expenseId): int
{
    $belege = xfile_list($expenseId);
    foreach ($belege as $f) {
        xfile_delete($f);
    }
    return count($belege);
}

/** „Ausgabe" oder „Einnahme" – wie die Buchung eines Belegs heißt. Reine Funktion. */
function xfile_buchung_wort(string $art): string
{
    return $art === 'einnahme' ? 'Einnahme' : 'Ausgabe';
}
