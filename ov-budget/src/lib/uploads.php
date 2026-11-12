<?php
declare(strict_types=1);

function upload_dir(): string
{
    $dir = (string)app_config('upload_dir', dirname(__DIR__, 2) . '/storage/uploads');
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return rtrim($dir, '/\\');
}

function upload_allowed_extensions(): array
{
    $raw = (string)setting('upload_erlaubte_typen', 'pdf,jpg,jpeg,png,webp');
    $out = [];
    foreach (explode(',', $raw) as $x) {
        $x = strtolower(trim($x, " \t.\n"));
        if ($x !== '') {
            $out[] = $x;
        }
    }
    return $out ?: ['pdf'];
}

/** Höchstens so viel lässt die Einstellung zu – PHP im Add-on erlaubt 40 MB je Datei, darunter bleibt Luft */
const UPLOAD_MAX_MB = 32;

function upload_max_bytes(): int
{
    return max(1, min(UPLOAD_MAX_MB, setting_int('upload_max_mb', UPLOAD_MAX_MB))) * 1024 * 1024;
}

/**
 * Hochgeladene Dokumente aus $_FILES[$feld] prüfen und in $dir ablegen – für Ablagen, die
 * Dokumente und Fotos gemischt annehmen. Fotos werden neu geschrieben (das entfernt den
 * Aufnahmeort aus den Metadaten), auf $bildMax Kantenlänge begrenzt und bekommen ein
 * Vorschaubild; alles andere bleibt, wie es ist.
 *
 * Gibt [Dateien[], Fehler[]] zurück. Je Datei: orig_name, titel (Dateiname ohne Endung),
 * stored_name, thumb_name, mime, size_bytes, art ('bild' oder 'dokument') – der Aufrufer
 * trägt sie in seine Tabelle ein.
 */
function upload_dokumente_ablegen(string $feld, string $dir, int $bildMax = VFILE_DOKUMENT_MAX, int $qualitaet = 92): array
{
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        // Ohne Feld kommt hier nichts an – das passiert auch, wenn die Datei größer ist als post_max_size von PHP
        return [[], ['Es kam keine Datei an. Bei sehr großen Dateien bricht der Browser den Upload ab, bevor die Anwendung ihn sieht.']];
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return [[], [sprintf('Der Ordner %s ist nicht beschreibbar.', $dir)]];
    }
    $erlaubt = array_values(array_unique(array_merge(vfile_document_types(), VFILE_BILD_TYPEN)));
    $max = upload_max_bytes();
    $dateien = [];
    $fehler = [];
    foreach ($_FILES[$feld]['name'] as $i => $name) {
        $name = (string)$name;
        $err = (int)$_FILES[$feld]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $fehler[] = sprintf('„%s" konnte nicht hochgeladen werden (Fehlercode %d).', $name, $err);
            continue;
        }
        $tmp = (string)$_FILES[$feld]['tmp_name'][$i];
        $groesse = (int)$_FILES[$feld]['size'][$i];
        // Nur Dateien, die PHP selbst entgegengenommen hat (die Tests schalten das ab)
        if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
            $fehler[] = sprintf('„%s" wurde nicht über das Formular hochgeladen.', $name);
            continue;
        }
        // finfo räumt sich selbst auf; finfo_close() ist seit PHP 8.5 veraltet
        $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $grund = vfile_check($name, $groesse, $mime, 'dokument', $erlaubt, $max);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $basis = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
        $gespeichert = $basis . '.' . $endung;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;
        $vorschau = null;
        $istBild = in_array($endung, VFILE_BILD_TYPEN, true) && str_starts_with($mime, 'image/');
        if ($istBild && vfile_process_image($tmp, $ziel, $mime, $bildMax, $qualitaet) !== null) {
            @unlink($tmp);
            $groesse = (int)filesize($ziel);
            $vorschau = $basis . '_vorschau.' . $endung;
            if (vfile_process_image($ziel, $dir . DIRECTORY_SEPARATOR . $vorschau, $mime, VFILE_VORSCHAU_MAX) === null) {
                $vorschau = null;
            }
        } elseif (!(defined('OVB_TEST_UPLOADS') ? rename($tmp, $ziel) : move_uploaded_file($tmp, $ziel))) {
            $fehler[] = sprintf('„%s" konnte nicht gespeichert werden.', $name);
            continue;
        }
        $dateien[] = [
            'orig_name'   => mb_substr($name, 0, 255),
            'titel'       => mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 200),
            'stored_name' => $gespeichert,
            'thumb_name'  => $vorschau,
            'mime'        => $mime,
            'size_bytes'  => $groesse,
            'art'         => $istBild ? 'bild' : 'dokument',
        ];
    }
    return [$dateien, $fehler];
}

/** Wurde im Formularfeld mindestens eine Datei ausgewählt? Reine Funktion über $_FILES. */
function upload_mitgeschickt(string $feld): bool
{
    foreach ((array)($_FILES[$feld]['name'] ?? []) as $n) {
        if ((string)$n !== '') {
            return true;
        }
    }
    return false;
}

/**
 * Anlagen eines Wunsches speichern ($_FILES['anlagen'] als Mehrfachfeld).
 * Gibt die Liste der Fehlermeldungen zurück.
 */
function wish_handle_uploads(int $wishId, int $userId, string $kind = 'angebot', ?float $betrag = null): array
{
    $errors = [];
    if (empty($_FILES['anlagen']) || !is_array($_FILES['anlagen']['name'])) {
        return $errors;
    }

    $allowed = upload_allowed_extensions();
    $maxBytes = upload_max_bytes();
    $dir = upload_dir();

    foreach ($_FILES['anlagen']['name'] as $i => $origName) {
        $err = (int)$_FILES['anlagen']['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE || $origName === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = sprintf('"%s" konnte nicht hochgeladen werden (Fehlercode %d).', $origName, $err);
            continue;
        }

        $tmp = $_FILES['anlagen']['tmp_name'][$i];
        $size = (int)$_FILES['anlagen']['size'][$i];

        if ($size > $maxBytes) {
            $errors[] = sprintf('"%s" ist zu groß (max. %s).', $origName, bytes_human($maxBytes));
            continue;
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $errors[] = sprintf('"%s": Dateityp .%s ist nicht erlaubt (erlaubt: %s).', $origName, $ext, implode(', ', $allowed));
            continue;
        }

        // finfo räumt sich selbst auf; finfo_close() ist seit PHP 8.5 veraltet
        $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';

        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $stored)) {
            $errors[] = sprintf('"%s" konnte nicht gespeichert werden.', $origName);
            continue;
        }

        $anlageId = db_insert('wish_attachments', [
            'wish_id'      => $wishId,
            'stored_name'  => $stored,
            'orig_name'    => mb_substr($origName, 0, 255),
            'mime'         => $mime,
            'size_bytes'   => $size,
            'kind'         => $kind,
            'betrag_netto' => $betrag,
            'uploaded_by'  => $userId,
        ]);
        if (function_exists('ki_einreihen')) {
            ki_einreihen('wunsch', $anlageId, str_starts_with($mime, 'image/') ? 'bild' : 'dokument');
        }
    }

    return $errors;
}

function attachment_delete(array $att): void
{
    $path = upload_dir() . DIRECTORY_SEPARATOR . $att['stored_name'];
    if (is_file($path)) {
        @unlink($path);
    }
    db_exec('DELETE FROM wish_attachments WHERE id = ?', [$att['id']]);
    if (function_exists('ki_vergessen')) {
        ki_vergessen('wunsch', (int)$att['id']);
    }
}
