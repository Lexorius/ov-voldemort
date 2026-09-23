<?php
declare(strict_types=1);

/*
 * Fahrzeugdaten aus Divera 24/7.
 *
 * Zwei Abrufe mit eigenem Takt:
 *   Funkstatus  GET /api/v2/pull/vehicle-status   (System-Accesskey)
 *               FMS-Status mit Zeitpunkt und Freitext, Position, Besatzung
 *               – alle paar Minuten; jeder Wechsel kommt ins Journal
 *   Stammdaten  GET /api/v3/vehicles               (persönlicher Accesskey, Beta)
 *               OPTA, RIC, Kennzeichen, ISSI – etwa stündlich
 *
 * Divera und die Anwendung kennen dieselben Fahrzeuge unter eigenen Nummern.
 * Zugeordnet wird über die gemerkte Divera-id, sonst über ISSI, Kennzeichen
 * oder Funkrufname – nur wenn genau ein Fahrzeug passt.
 */

/** FMS-Status nach Divera-Dokumentation */
const FMS_STATUS = [
    0 => 'Notruf',
    1 => 'Einsatzbereit über Funk',
    2 => 'Einsatzbereit auf Wache',
    3 => 'Einsatz übernommen',
    4 => 'Einsatzstelle an',
    5 => 'Sprechwunsch',
    6 => 'Nicht einsatzbereit',
    7 => 'Patient aufgenommen',
    8 => 'Am Transportziel',
    9 => 'Sonstiger Status',
];

/** Farben der FMS-Status für die Anzeige */
const FMS_FARBEN = [
    0 => '#b91c1c', 1 => '#15803d', 2 => '#15803d', 3 => '#c2410c', 4 => '#b91c1c',
    5 => '#7c3aed', 6 => '#64748b', 7 => '#0284c7', 8 => '#0284c7', 9 => '#64748b',
];

function divera_vehicles_enabled(): bool
{
    return setting_bool('divera_aktiv', false)
        && setting_bool('divera_fahrzeuge_aktiv', false)
        && trim((string)setting('divera_accesskey', '')) !== '';
}

function fms_label(?int $status): string
{
    if ($status === null) {
        return '';
    }
    return 'Status ' . $status . (isset(FMS_STATUS[$status]) ? ' – ' . FMS_STATUS[$status] : '');
}

function fms_color(?int $status): string
{
    return FMS_FARBEN[$status ?? -1] ?? '#94a3b8';
}

/* ==================================================================== *
 * Zuordnung (ohne Datenbank)
 * ==================================================================== */

/** Kennzeichen vergleichbar machen: "THW  99020", "THW-99020" und "thw99020" sind gleich */
function dv_norm_plate(?string $wert): string
{
    return strtoupper((string)preg_replace('/[^A-Za-z0-9ÄÖÜäöü]/u', '', (string)$wert));
}

/** ISSI-Felder können mehrere Kennungen enthalten: "7120028, 7120029" */
function dv_issi_set(?string $wert): array
{
    preg_match_all('/\d{5,}/', (string)$wert, $m);
    return array_values(array_unique($m[0]));
}

function dv_norm_name(?string $wert): string
{
    return mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', (string)$wert)));
}

/**
 * Welches unserer Fahrzeuge ist das Divera-Fahrzeug? Gibt die id zurück
 * oder null, wenn keins oder mehrere passen.
 * $dv: id, name, number, issi (je nach Schnittstelle nur ein Teil davon)
 */
function dv_match(array $dv, array $fahrzeuge): ?int
{
    $dvId = (int)($dv['id'] ?? 0);

    // 1. bereits verknüpft
    foreach ($fahrzeuge as $f) {
        if ($dvId > 0 && (int)($f['divera_vehicle_id'] ?? 0) === $dvId) {
            return (int)$f['id'];
        }
    }

    // Bereits mit einem anderen Divera-Fahrzeug verknüpfte scheiden aus
    $frei = array_values(array_filter($fahrzeuge, static fn($f) => empty($f['divera_vehicle_id'])));

    $eindeutig = static function (array $treffer): ?int {
        $ids = array_values(array_unique(array_map(static fn($f) => (int)$f['id'], $treffer)));
        return count($ids) === 1 ? $ids[0] : null;
    };

    // 2. ISSI – eine gemeinsame Kennung genügt
    $issi = dv_issi_set($dv['issi'] ?? '');
    if ($issi) {
        $treffer = array_filter($frei, static fn($f) => array_intersect($issi, dv_issi_set($f['issi'] ?? '')) !== []);
        if ($id = $eindeutig($treffer)) {
            return $id;
        }
    }

    // 3. Kennzeichen
    $kz = dv_norm_plate($dv['number'] ?? '');
    if ($kz !== '') {
        $treffer = array_filter($frei, static fn($f) => dv_norm_plate($f['kennzeichen'] ?? '') === $kz);
        if ($id = $eindeutig($treffer)) {
            return $id;
        }
    }

    // 4. Funkrufname, ganz gleich
    $name = dv_norm_name($dv['name'] ?? '');
    if ($name !== '') {
        $treffer = array_filter($frei, static fn($f) => dv_norm_name($f['funkrufname'] ?? '') === $name);
        if ($id = $eindeutig($treffer)) {
            return $id;
        }
    }
    return null;
}

/** Namen der Besatzung aus den (nicht näher beschriebenen) Divera-Objekten */
function dv_crew_names(mixed $crew): array
{
    if (!is_array($crew)) {
        return [];
    }
    $namen = [];
    foreach ($crew as $p) {
        if (is_string($p) && trim($p) !== '') {
            $namen[] = trim($p);
            continue;
        }
        if (!is_array($p)) {
            continue;
        }
        $name = trim(trim((string)($p['firstname'] ?? '')) . ' ' . trim((string)($p['lastname'] ?? '')));
        if ($name === '') {
            $name = trim((string)($p['name'] ?? $p['fullname'] ?? $p['displayname'] ?? ''));
        }
        if ($name !== '') {
            $namen[] = $name;
        }
    }
    return array_values(array_unique($namen));
}

/**
 * Aus einem Eintrag von /pull/vehicle-status die Änderungen am Fahrzeug
 * ableiten – ohne Datenbank.
 * Rückgabe: ['daten' => Spalten, 'statuswechsel' => null|[alt, neu, zeit, notiz]]
 */
function dv_status_update(array $st, array $fahrzeug, bool $mitPosition, int $jetzt): array
{
    $daten = ['divera_sync_at' => date('Y-m-d H:i:s', $jetzt)];
    $wechsel = null;

    $neu = isset($st['fmsstatus']) && $st['fmsstatus'] !== '' && $st['fmsstatus'] !== null
        ? (int)$st['fmsstatus'] : null;
    $ts = (int)($st['fmsstatus_ts'] ?? 0);
    $zeit = $ts > 0 ? date('Y-m-d H:i:s', $ts) : null;
    $notiz = trim((string)($st['fmsstatus_note'] ?? ''));

    $alt = $fahrzeug['fms_status'] !== null && $fahrzeug['fms_status'] !== '' ? (int)$fahrzeug['fms_status'] : null;
    $altZeit = $fahrzeug['fms_at'] ?? null;

    if ($neu !== null) {
        // Ein neuer Zeitstempel zählt auch bei gleichem Status: 2 → 3 → 2 zwischen zwei Abrufen
        if ($neu !== $alt || ($zeit !== null && $zeit !== $altZeit)) {
            $wechsel = ['alt' => $alt, 'neu' => $neu, 'zeit' => $zeit, 'notiz' => $notiz];
        }
        $daten['fms_status'] = $neu;
        $daten['fms_at'] = $zeit;
        $daten['fms_note'] = mb_substr($notiz, 0, 255);
    }

    if ($mitPosition) {
        $lat = $st['lat'] ?? null;
        $lng = $st['lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng) && ((float)$lat !== 0.0 || (float)$lng !== 0.0)
            && abs((float)$lat) <= 90 && abs((float)$lng) <= 180) {
            $daten['geo_lat'] = round((float)$lat, 6);
            $daten['geo_lng'] = round((float)$lng, 6);
            $daten['geo_at'] = date('Y-m-d H:i:s', $jetzt);
        }
    }

    $daten['divera_besatzung'] = json_encode(dv_crew_names($st['crew'] ?? []), JSON_UNESCAPED_UNICODE);
    return ['daten' => $daten, 'statuswechsel' => $wechsel];
}

/**
 * Stammdaten aus /api/v3/vehicles übernehmen – ohne Datenbank.
 * OPTA und RIC führt Divera; Kennzeichen, ISSI und Funkrufname nur, wenn
 * bei uns noch nichts steht (die kommen sonst aus der Stein.APP oder von Hand).
 */
function dv_master_update(array $dv, array $fahrzeug): array
{
    $daten = [];
    foreach (['opta' => 60, 'ric' => 30] as $feld => $laenge) {
        $wert = trim((string)($dv[$feld] ?? ''));
        if ($wert !== '' && $wert !== trim((string)($fahrzeug[$feld] ?? ''))) {
            $daten[$feld] = mb_substr($wert, 0, $laenge);
        }
    }
    foreach (['number' => ['kennzeichen', 20], 'issi' => ['issi', 80], 'name' => ['funkrufname', 80]] as $von => [$nach, $laenge]) {
        $wert = trim((string)($dv[$von] ?? ''));
        if ($wert !== '' && trim((string)($fahrzeug[$nach] ?? '')) === '') {
            $daten[$nach] = mb_substr($wert, 0, $laenge);
        }
    }
    return $daten;
}

/* ==================================================================== *
 * Abgleich
 * ==================================================================== */

function dv_due(string $schluessel, int $minuten, int $jetzt): bool
{
    $letzter = (int)setting($schluessel, '0');
    return $letzter === 0 || $jetzt - $letzter >= max(1, $minuten) * 60;
}

/** Wie dv_due, aber mit dem Stand direkt aus der Datenbank */
function dv_due_fresh(string $schluessel, int $minuten, int $jetzt): bool
{
    $letzter = (int)state_get($schluessel, '0');
    return $letzter === 0 || $jetzt - $letzter >= max(1, $minuten) * 60;
}

/** Alle Fahrzeuge mit den Feldern, die die Zuordnung braucht */
function dv_vehicles_for_matching(): array
{
    return db_all(
        'SELECT id, bezeichnung, funkrufname, kennzeichen, issi, opta, ric, divera_vehicle_id,
                fms_status, fms_at, geo_lat, geo_lng, stein_asset_id
         FROM vehicles WHERE is_active = 1'
    );
}

/** Divera-Fahrzeug fest mit unserem verbinden (merkt sich die Divera-id) */
function dv_link(int $vehicleId, int $diveraId, string $wie, ?array $user = null): void
{
    db_update('vehicles', ['divera_vehicle_id' => $diveraId], 'id = ?', [$vehicleId]);
    journal_add($vehicleId, [
        'art'      => 'divera',
        'titel'    => 'Mit Divera verknüpft',
        'text'     => $wie,
        'neu_wert' => (string)$diveraId,
        'quelle'   => $user ? 'mensch' : 'divera',
        'autor'    => $user ? null : 'Divera',
    ], $user);
}

/**
 * Funkstatus, Position und Besatzung abrufen. Ein Aufruf.
 * Rückgabe: ['status', 'fahrzeuge', 'wechsel', 'offen', 'message']
 */
function divera_vehicles_sync_status(bool $erzwingen = false): array
{
    $jetzt = time();
    if (!divera_vehicles_enabled()) {
        return ['status' => 'aus', 'fahrzeuge' => 0, 'wechsel' => 0, 'offen' => 0,
                'message' => 'Der Fahrzeugabgleich mit Divera ist nicht eingeschaltet.'];
    }
    if (!$erzwingen && !dv_due('divera_status_letzter_abruf', setting_int('divera_status_intervall_minuten', 2), $jetzt)) {
        return ['status' => 'wartet', 'fahrzeuge' => 0, 'wechsel' => 0, 'offen' => 0, 'message' => 'Noch nicht an der Reihe.'];
    }
    if (!db_lock('ovb_divera_status', 0)) {
        return ['status' => 'wartet', 'fahrzeuge' => 0, 'wechsel' => 0, 'offen' => 0, 'message' => 'Ein Abruf läuft gerade.'];
    }
    try {
        if (!$erzwingen && !dv_due_fresh('divera_status_letzter_abruf', setting_int('divera_status_intervall_minuten', 2), $jetzt)) {
            return ['status' => 'wartet', 'fahrzeuge' => 0, 'wechsel' => 0, 'offen' => 0, 'message' => 'Eben erst abgerufen.'];
        }
        return dv_sync_status_locked($jetzt);
    } finally {
        db_unlock('ovb_divera_status');
    }
}

/** Funkstatus abrufen – nur unter der Sperre aufrufen */
function dv_sync_status_locked(int $jetzt): array
{
    state_save('divera_status_letzter_abruf', (string)$jetzt);

    try {
        $antwort = divera_request('/v2/pull/vehicle-status');
    } catch (Throwable $ex) {
        dv_log('fehler', 'Funkstatus: ' . $ex->getMessage());
        return ['status' => 'fehler', 'fahrzeuge' => 0, 'wechsel' => 0, 'offen' => 0, 'message' => $ex->getMessage()];
    }
    $liste = isset($antwort['data']) && is_array($antwort['data']) ? $antwort['data'] : divera_extract_rows($antwort);

    $unsere = dv_vehicles_for_matching();
    $mitPosition = setting_bool('divera_position_speichern', true);
    $insJournal = setting_bool('divera_status_journal', true);
    $wechselAnzahl = 0;
    $offen = [];

    foreach ($liste as $st) {
        if (!is_array($st) || empty($st['id'])) {
            continue;
        }
        $id = dv_match(['id' => $st['id'], 'name' => $st['name'] ?? ''], $unsere);
        if ($id === null) {
            $offen[(string)$st['id']] = ['id' => (int)$st['id'], 'name' => (string)($st['name'] ?? ''),
                                         'typ' => (string)($st['fullname'] ?? $st['shortname'] ?? '')];
            continue;
        }
        $fz = null;
        foreach ($unsere as &$u) {
            if ((int)$u['id'] === $id) {
                if (empty($u['divera_vehicle_id'])) {
                    dv_link($id, (int)$st['id'], 'Über den Funkrufnamen „' . ($st['name'] ?? '') . '" erkannt.');
                    $u['divera_vehicle_id'] = (int)$st['id'];
                }
                $fz = $u;
                break;
            }
        }
        unset($u);
        if ($fz === null) {
            continue;
        }

        $ergebnis = dv_status_update($st, $fz, $mitPosition, $jetzt);
        $ergebnis['daten']['divera_daten'] = json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        db_update('vehicles', $ergebnis['daten'], 'id = ?', [$id]);

        // Standort: beim Statuswechsel mit vermerken, sonst nur bei größerer Bewegung
        $pos = dv_position_text($ergebnis['daten']['geo_lat'] ?? null, $ergebnis['daten']['geo_lng'] ?? null);
        $bewegung = dv_movement_note(
            $fz['geo_lat'] ?? null, $fz['geo_lng'] ?? null,
            $ergebnis['daten']['geo_lat'] ?? null, $ergebnis['daten']['geo_lng'] ?? null,
            setting_int('divera_position_journal_meter', 0)
        );

        $w = $ergebnis['statuswechsel'];
        if ($w !== null && $insJournal) {
            journal_add($id, [
                'art'      => 'fms',
                'titel'    => fms_label($w['neu']) . ($w['zeit'] ? ' um ' . date('H:i', (int)strtotime($w['zeit'])) . ' Uhr' : ''),
                'text'     => trim($w['notiz'] . ($pos !== '' ? "\nStandort: " . $pos : '')),
                'feld'     => 'fms_status',
                'alt_wert' => $w['alt'] !== null ? fms_label($w['alt']) : '',
                'neu_wert' => fms_label($w['neu']),
                'quelle'   => 'divera',
                'autor'    => 'Divera',
            ], null);
            $wechselAnzahl++;
        } elseif ($bewegung !== null && $pos !== '' && $insJournal) {
            // Eigener Eintrag nur, wenn die Bewegung nicht ohnehin oben steht
            journal_add($id, [
                'art'      => 'divera',
                'titel'    => 'Standort ' . ($bewegung > 0
                    ? 'geändert (' . ($bewegung >= 1000
                        ? number_format($bewegung / 1000, 1, ',', '.') . ' km'
                        : round($bewegung) . ' m') . ')'
                    : 'erstmals bekannt'),
                'text'     => 'Standort: ' . $pos,
                'feld'     => 'geo',
                'alt_wert' => dv_position_text($fz['geo_lat'] ?? null, $fz['geo_lng'] ?? null),
                'neu_wert' => $pos,
                'quelle'   => 'divera',
                'autor'    => 'Divera',
            ], null);
        }
    }

    state_save('divera_offene_fahrzeuge', json_encode(array_values($offen), JSON_UNESCAPED_UNICODE));
    $meldung = sprintf('Funkstatus: %d Fahrzeug(e), %d Wechsel%s.', count($liste), $wechselAnzahl,
        $offen ? ', ' . count($offen) . ' ohne Zuordnung' : '');
    dv_log('ok', $meldung);
    return ['status' => 'ok', 'fahrzeuge' => count($liste), 'wechsel' => $wechselAnzahl,
            'offen' => count($offen), 'message' => $meldung];
}

/**
 * Stammdaten aus /api/v3/vehicles. Ein Aufruf (plus einer für die Typen).
 */
function divera_vehicles_sync_master(bool $erzwingen = false): array
{
    $jetzt = time();
    if (!divera_vehicles_enabled()) {
        return ['status' => 'aus', 'geaendert' => 0, 'message' => 'Der Fahrzeugabgleich mit Divera ist nicht eingeschaltet.'];
    }
    if (!$erzwingen && !dv_due('divera_stamm_letzter_abruf', setting_int('divera_stamm_intervall_minuten', 60), $jetzt)) {
        return ['status' => 'wartet', 'geaendert' => 0, 'message' => 'Noch nicht an der Reihe.'];
    }
    if (!db_lock('ovb_divera_stamm', 0)) {
        return ['status' => 'wartet', 'geaendert' => 0, 'message' => 'Ein Abruf läuft gerade.'];
    }
    try {
        if (!$erzwingen && !dv_due_fresh('divera_stamm_letzter_abruf', setting_int('divera_stamm_intervall_minuten', 60), $jetzt)) {
            return ['status' => 'wartet', 'geaendert' => 0, 'message' => 'Eben erst abgerufen.'];
        }
        return dv_sync_master_locked($jetzt);
    } finally {
        db_unlock('ovb_divera_stamm');
    }
}

/** Stammdaten abrufen – nur unter der Sperre aufrufen */
function dv_sync_master_locked(int $jetzt): array
{
    state_save('divera_stamm_letzter_abruf', (string)$jetzt);

    $schluessel = trim((string)setting('divera_personal_key', '')) ?: null;
    try {
        $liste = divera_request('/v3/vehicles', [], $schluessel);
    } catch (Throwable $ex) {
        $hinweis = str_contains($ex->getMessage(), '401')
            ? ' Die v3-Schnittstelle verlangt einen persönlichen Accesskey mit Verwaltungsrechten, eventuell mit Zwei-Faktor-Anmeldung.'
            : '';
        dv_log('fehler', 'Stammdaten: ' . $ex->getMessage() . $hinweis);
        return ['status' => 'fehler', 'geaendert' => 0, 'message' => $ex->getMessage() . $hinweis];
    }
    $liste = array_is_list($liste) ? $liste : divera_extract_rows($liste);

    $unsere = dv_vehicles_for_matching();
    $geaendert = 0;
    foreach ($liste as $dv) {
        if (!is_array($dv) || empty($dv['id'])) {
            continue;
        }
        $id = dv_match($dv, $unsere);
        if ($id === null) {
            continue;
        }
        foreach ($unsere as &$u) {
            if ((int)$u['id'] !== $id) {
                continue;
            }
            if (empty($u['divera_vehicle_id'])) {
                $wie = dv_issi_set($dv['issi'] ?? '') ? 'Über ISSI oder Kennzeichen erkannt.' : 'Über den Funkrufnamen erkannt.';
                dv_link($id, (int)$dv['id'], $wie);
                $u['divera_vehicle_id'] = (int)$dv['id'];
            }
            $daten = dv_master_update($dv, $u);
            if ($daten) {
                foreach ($daten as $feld => $wert) {
                    journal_add($id, [
                        'art'      => 'divera',
                        'titel'    => (FZ_JOURNAL_FELDER[$feld] ?? $feld) . ' aus Divera übernommen',
                        'feld'     => $feld,
                        'alt_wert' => (string)($u[$feld] ?? ''),
                        'neu_wert' => (string)$wert,
                        'quelle'   => 'divera',
                        'autor'    => 'Divera',
                    ], null);
                }
                db_update('vehicles', $daten, 'id = ?', [$id]);
                $u = array_merge($u, $daten);
                $geaendert++;
            }
            break;
        }
        unset($u);
    }
    $meldung = sprintf('Stammdaten: %d Fahrzeug(e) aus Divera, %d aktualisiert.', count($liste), $geaendert);
    dv_log('ok', $meldung);
    return ['status' => 'ok', 'geaendert' => $geaendert, 'message' => $meldung];
}

/** Beides abrufen, wenn fällig – still, Fehler stehen im Protokoll */
function divera_vehicles_sync_if_due(): void
{
    if (!divera_vehicles_enabled()) {
        return;
    }
    try {
        divera_vehicles_sync_master();
        divera_vehicles_sync_status();
    } catch (Throwable) {
        // Der Abruf darf die Seite nie aufhalten
    }
}

function dv_log(string $status, string $meldung): void
{
    db_insert('divera_log', [
        'form_id' => 'fahrzeuge',
        'status'  => $status,
        'message' => mb_substr($meldung, 0, 500),
    ]);
}

/** Divera-Fahrzeuge ohne Zuordnung aus dem letzten Abruf */
function dv_pending(): array
{
    $liste = json_decode((string)setting('divera_offene_fahrzeuge', '[]'), true);
    if (!is_array($liste)) {
        return [];
    }
    $verknuepft = array_map('intval', array_column(
        db_all('SELECT divera_vehicle_id FROM vehicles WHERE divera_vehicle_id IS NOT NULL'), 'divera_vehicle_id'));
    return array_values(array_filter($liste, static fn($d) => is_array($d)
        && !in_array((int)($d['id'] ?? 0), $verknuepft, true)));
}

/** Besatzung eines Fahrzeugs aus dem gespeicherten Stand */
function dv_crew_of(array $vehicle): array
{
    $namen = json_decode((string)($vehicle['divera_besatzung'] ?? ''), true);
    return is_array($namen) ? array_values(array_filter($namen, 'is_string')) : [];
}

/** Link auf die Karte (OpenStreetMap) */
/** Koordinaten als Text für das Journal, leer ohne Angabe. Reine Funktion. */
function dv_position_text(mixed $lat, mixed $lng): string
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return '';
    }
    return sprintf('%.5f, %.5f', (float)$lat, (float)$lng);
}

/**
 * Entfernung zweier Punkte in Metern (Haversine). Reine Funktion.
 * Genau genug, um zu entscheiden, ob sich ein Fahrzeug bewegt hat.
 */
function dv_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Soll die Bewegung ins Journal? Nur wenn eingeschaltet und weit genug
 * vom zuletzt vermerkten Punkt entfernt. Reine Funktion.
 * Rückgabe: Entfernung in Metern oder null.
 */
function dv_movement_note(mixed $altLat, mixed $altLng, mixed $neuLat, mixed $neuLng, int $schwelleM): ?float
{
    if ($schwelleM <= 0 || !is_numeric($neuLat) || !is_numeric($neuLng)) {
        return null;
    }
    if (!is_numeric($altLat) || !is_numeric($altLng)) {
        return 0.0;   // erste bekannte Position
    }
    $m = dv_distance_m((float)$altLat, (float)$altLng, (float)$neuLat, (float)$neuLng);
    return $m >= $schwelleM ? $m : null;
}

function dv_map_url(float $lat, float $lng): string
{
    return sprintf('https://www.openstreetmap.org/?mlat=%1$.6f&mlon=%2$.6f#map=16/%1$.6f/%2$.6f', $lat, $lng);
}
