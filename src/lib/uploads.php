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

function upload_max_bytes(): int
{
    return max(1, setting_int('upload_max_mb', 10)) * 1024 * 1024;
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

        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
        }

        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $stored)) {
            $errors[] = sprintf('"%s" konnte nicht gespeichert werden.', $origName);
            continue;
        }

        db_insert('wish_attachments', [
            'wish_id'      => $wishId,
            'stored_name'  => $stored,
            'orig_name'    => mb_substr($origName, 0, 255),
            'mime'         => $mime,
            'size_bytes'   => $size,
            'kind'         => $kind,
            'betrag_netto' => $betrag,
            'uploaded_by'  => $userId,
        ]);
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
}
