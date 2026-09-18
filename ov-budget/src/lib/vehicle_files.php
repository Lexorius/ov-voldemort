<?php
declare(strict_types=1);

/*
 * Bilder und Dokumente zu Fahrzeugen und Aufträgen.
 *
 * Eine Datei gehört immer zu einem Fahrzeug; hängt sie an einem Auftrag,
 * steht zusätzlich dessen id daran. So zeigt die Fahrzeugakte alles, der
 * Auftrag nur das Seine.
 *
 * Fotos vom Handy sind oft mehrere Megabyte groß und tragen den Aufnahmeort
 * in den Metadaten. Ist GD verfügbar, werden sie deshalb neu gespeichert – das
 * entfernt die Metadaten. Galeriebilder werden dabei verkleinert und bekommen
 * ein Vorschaubild, Fotos als Dokument behalten ihre Auflösung.
 */

/** Dateiendungen für Bilder – SVG bewusst nicht, es kann Skript enthalten */
const VFILE_BILD_TYPEN = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

/** Bilder werden auf diese Kantenlänge begrenzt, Vorschauen auf diese */
const VFILE_BILD_MAX = 1600;
const VFILE_VORSCHAU_MAX = 480;

/** Fotos als Dokument (Fahrzeugschein, Prüfbericht) bleiben groß und scharf */
const VFILE_DOKUMENT_MAX = 6000;

function vfile_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'fahrzeuge';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Erlaubte Endungen für Dokumente (einstellbar) */
function vfile_document_types(): array
{
    $roh = (string)setting('fahrzeug_dokument_typen', 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,odt,ods,txt');
    $out = [];
    foreach (explode(',', $roh) as $t) {
        $t = strtolower(trim($t, " \t.\n"));
        // Ausführbares und Skriptfähiges kommt nie durch, auch wenn es eingetragen ist
        if ($t !== '' && !in_array($t, ['php', 'phtml', 'phar', 'html', 'htm', 'svg', 'js', 'exe', 'sh'], true)) {
            $out[] = $t;
        }
    }
    return $out ?: ['pdf'];
}

/**
 * Eine hochgeladene Datei prüfen – ohne Datenbank, damit leicht prüfbar.
 * Gibt null zurück, wenn sie passt, sonst den Grund.
 */
function vfile_check(string $name, int $groesse, string $mime, string $art, array $erlaubt, int $maxBytes): ?string
{
    if ($groesse <= 0) {
        return sprintf('„%s" ist leer.', $name);
    }
    if ($groesse > $maxBytes) {
        return sprintf('„%s" ist zu groß (höchstens %s).', $name, bytes_human($maxBytes));
    }
    $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($endung === '' || !in_array($endung, $erlaubt, true)) {
        return sprintf('„%s": Dateityp .%s ist hier nicht erlaubt (erlaubt: %s).',
            $name, $endung, implode(', ', $erlaubt));
    }
    if ($art === 'bild' && !str_starts_with($mime, 'image/')) {
        return sprintf('„%s" ist kein Bild.', $name);
    }
    // Endung und Inhalt müssen zusammenpassen, sonst ließe sich HTML als .jpg einschmuggeln
    if (in_array($endung, VFILE_BILD_TYPEN, true) && $mime !== '' && !str_starts_with($mime, 'image/')) {
        return sprintf('„%s" trägt eine Bild-Endung, ist aber keins.', $name);
    }
    if ($mime === 'text/html' || $mime === 'image/svg+xml') {
        return sprintf('„%s": dieser Inhalt ist nicht erlaubt.', $name);
    }
    return null;
}

function vfile_gd_available(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg');
}

/**
 * Bild laden, drehen (EXIF), verkleinern und neu schreiben.
 * Gibt [breite, hoehe] zurück oder null, wenn es nicht ging – dann bleibt
 * die Datei, wie sie ist.
 */
function vfile_process_image(string $quelle, string $ziel, string $mime, int $max, int $qualitaet = 82): ?array
{
    if (!vfile_gd_available()) {
        return null;
    }
    $bild = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($quelle),
        'image/png'  => @imagecreatefrompng($quelle),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($quelle) : false,
        'image/gif'  => @imagecreatefromgif($quelle),
        default      => false,
    };
    if (!$bild) {
        return null;
    }

    // Handyfotos liegen oft quer und tragen die Drehung nur in den Metadaten
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($quelle);
        $drehung = match ((int)($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };
        if ($drehung !== 0) {
            $gedreht = imagerotate($bild, $drehung, 0);
            if ($gedreht) {
                $bild = $gedreht;
            }
        }
    }

    $b = imagesx($bild);
    $h = imagesy($bild);
    $faktor = min(1.0, $max / max($b, $h));
    $nb = max(1, (int)round($b * $faktor));
    $nh = max(1, (int)round($h * $faktor));

    $neu = imagecreatetruecolor($nb, $nh);
    if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
        imagealphablending($neu, false);
        imagesavealpha($neu, true);
    }
    imagecopyresampled($neu, $bild, 0, 0, 0, 0, $nb, $nh, $b, $h);

    $ok = match ($mime) {
        'image/png'  => imagepng($neu, $ziel, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($neu, $ziel, $qualitaet) : imagejpeg($neu, $ziel, $qualitaet),
        'image/gif'  => imagegif($neu, $ziel),
        default      => imagejpeg($neu, $ziel, $qualitaet),
    };
    return $ok ? [$nb, $nh] : null;
}

/**
 * Hochgeladene Dateien aus $_FILES[$feld] speichern. Gibt [anzahl, fehler[]] zurück.
 *
 * $art: 'bild'     – nur Bilder, sie werden verkleinert und landen in der Galerie
 *       'dokument' – alles Erlaubte, unverändert (ein Scan behält seine Auflösung)
 *       'auto'     – Bild oder Dokument, je nach Inhalt (für Aufträge)
 */
function vfile_store_uploads(int $vehicleId, ?int $orderId, string $feld, string $art, string $titel, array $user): array
{
    $fehler = [];
    $anzahl = 0;
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        return [0, ['Keine Datei ausgewählt.']];
    }

    $erlaubt = $art === 'bild'
        ? VFILE_BILD_TYPEN
        : array_values(array_unique(array_merge(vfile_document_types(), VFILE_BILD_TYPEN)));
    $max = upload_max_bytes();
    $dir = vfile_dir();

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
        $grund = vfile_check($name, $groesse, $mime, $art === 'bild' ? 'bild' : 'dokument', $erlaubt, $max);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }

        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $basis = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
        $gespeichert = $basis . '.' . $endung;
        $vorschau = null;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;

        $istBild = in_array($endung, VFILE_BILD_TYPEN, true) && str_starts_with($mime, 'image/');
        $zielArt = match ($art) {
            'bild'  => 'bild',
            'auto'  => $istBild ? 'bild' : 'dokument',
            default => 'dokument',
        };
        // Galeriebilder werden verkleinert. Fotos als Dokument behalten ihre Größe,
        // werden aber ebenfalls neu gespeichert – sonst bliebe der Aufnahmeort darin.
        $verarbeitet = $istBild && ($zielArt === 'bild'
            ? vfile_process_image($tmp, $ziel, $mime, VFILE_BILD_MAX) !== null
            : vfile_process_image($tmp, $ziel, $mime, VFILE_DOKUMENT_MAX, 92) !== null);
        if ($verarbeitet && $zielArt === 'dokument') {
            @unlink($tmp);
            $groesse = (int)filesize($ziel);
        } elseif ($verarbeitet) {
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

        $id = db_insert('vehicle_files', [
            'vehicle_id'   => $vehicleId,
            'order_id'     => $orderId,
            'art'          => $zielArt,
            'titel'        => mb_substr(trim($titel) !== '' ? trim($titel) : pathinfo($name, PATHINFO_FILENAME), 0, 200),
            'orig_name'    => mb_substr($name, 0, 255),
            'stored_name'  => $gespeichert,
            'thumb_name'   => $vorschau,
            'mime'         => $mime,
            'size_bytes'   => $groesse,
            'uploaded_by'  => (int)$user['id'],
        ]);

        // Das erste Bild eines Fahrzeugs wird Titelbild
        if ($zielArt === 'bild' && $orderId === null && !vfile_cover($vehicleId)) {
            vfile_set_cover($vehicleId, $id);
        }

        journal_add($vehicleId, [
            'art'     => $orderId ? 'auftrag' : 'datei',
            'titel'   => ($zielArt === 'bild' ? 'Bild' : 'Dokument') . ' hinzugefügt: ' . $name,
            'ref_typ' => $orderId ? 'auftrag' : 'datei',
            'ref_id'  => $orderId ?? $id,
        ], $user);
        $anzahl++;
    }
    return [$anzahl, $fehler];
}

function vfile_find(int $id): ?array
{
    return db_row(
        'SELECT f.*, u.display_name AS hochgeladen_von, o.nummer AS auftrag_nummer
         FROM vehicle_files f
         LEFT JOIN users u ON u.id = f.uploaded_by
         LEFT JOIN vehicle_orders o ON o.id = f.order_id
         WHERE f.id = ?',
        [$id]
    );
}

/** Dateien eines Fahrzeugs; mit $orderId nur die eines Auftrags */
function vfile_list(int $vehicleId, ?int $orderId = null, ?string $art = null): array
{
    $w = ['f.vehicle_id = ?'];
    $p = [$vehicleId];
    if ($orderId !== null) {
        $w[] = 'f.order_id = ?';
        $p[] = $orderId;
    }
    if ($art !== null) {
        $w[] = 'f.art = ?';
        $p[] = $art;
    }
    return db_all(
        'SELECT f.*, u.display_name AS hochgeladen_von, o.nummer AS auftrag_nummer
         FROM vehicle_files f
         LEFT JOIN users u ON u.id = f.uploaded_by
         LEFT JOIN vehicle_orders o ON o.id = f.order_id
         WHERE ' . implode(' AND ', $w) . '
         ORDER BY f.is_cover DESC, f.created_at DESC, f.id DESC',
        $p
    );
}

function vfile_cover(int $vehicleId): ?array
{
    return db_row('SELECT * FROM vehicle_files WHERE vehicle_id = ? AND is_cover = 1 LIMIT 1', [$vehicleId]);
}

/** Titelbilder vieler Fahrzeuge auf einmal: [vehicle_id => Datei] */
function vfile_covers(array $vehicleIds): array
{
    $ids = array_values(array_filter(array_map('intval', $vehicleIds)));
    if (!$ids) {
        return [];
    }
    $platz = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (db_all("SELECT * FROM vehicle_files WHERE is_cover = 1 AND vehicle_id IN ($platz)", $ids) as $f) {
        $out[(int)$f['vehicle_id']] = $f;
    }
    return $out;
}

function vfile_set_cover(int $vehicleId, int $fileId): void
{
    db_exec('UPDATE vehicle_files SET is_cover = 0 WHERE vehicle_id = ?', [$vehicleId]);
    db_exec("UPDATE vehicle_files SET is_cover = 1 WHERE id = ? AND vehicle_id = ? AND art = 'bild'", [$fileId, $vehicleId]);
}

/** Datei entfernen; das Journal hält fest, dass es sie gab */
function vfile_delete(array $datei, array $user): void
{
    foreach ([$datei['stored_name'], $datei['thumb_name']] as $name) {
        if ($name) {
            $pfad = vfile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
        }
    }
    db_exec('DELETE FROM vehicle_files WHERE id = ?', [(int)$datei['id']]);

    // War es das Titelbild, rückt das nächste Bild nach
    if ((int)$datei['is_cover'] === 1) {
        $naechstes = db_val(
            "SELECT id FROM vehicle_files WHERE vehicle_id = ? AND art = 'bild' AND order_id IS NULL ORDER BY id LIMIT 1",
            [(int)$datei['vehicle_id']]
        );
        if ($naechstes) {
            vfile_set_cover((int)$datei['vehicle_id'], (int)$naechstes);
        }
    }

    journal_add((int)$datei['vehicle_id'], [
        'art'      => $datei['order_id'] ? 'auftrag' : 'datei',
        'titel'    => ($datei['art'] === 'bild' ? 'Bild' : 'Dokument') . ' entfernt: ' . $datei['orig_name'],
        'alt_wert' => (string)$datei['titel'],
        'ref_typ'  => $datei['order_id'] ? 'auftrag' : '',
        'ref_id'   => $datei['order_id'] ? (int)$datei['order_id'] : null,
    ], $user);
}

/** Pfad zur Datei oder zur Vorschau – oder null */
function vfile_path(array $datei, bool $vorschau = false): ?string
{
    $name = $vorschau && $datei['thumb_name'] ? $datei['thumb_name'] : $datei['stored_name'];
    $pfad = vfile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}
