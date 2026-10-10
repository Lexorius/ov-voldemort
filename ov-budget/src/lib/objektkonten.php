<?php
declare(strict_types=1);

/*
 * Objektkonten: eine zweite Sicht auf die Buchungen. Mehrere Kategorien
 * (etwa „Getränke" und „Lebensmittel") hängen an einem Konto („Verpflegung");
 * die Statistik nach Kategorien bleibt, die Auswertung nach Objektkonten
 * kommt dazu. Jede Kategorie gehört zu höchstens einem Konto.
 */

/** Listen, deren Einträge ein Objektkonto bekommen können */
const OBJEKTKONTO_LISTEN = ['ausgabe_kategorie' => 'Ausgaben', 'einnahme_kategorie' => 'Einnahmen'];

function objektkonto_all(bool $nurAktive = false): array
{
    return db_all('SELECT * FROM objektkonten' . ($nurAktive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, nummer, name, id');
}

function objektkonto_find(?int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM objektkonten WHERE id = ?', [$id]) : null;
}

/** „4210 Verpflegung" bzw. nur der Name. Reine Funktion. */
function objektkonto_titel(array $k): string
{
    $nummer = trim((string)($k['nummer'] ?? ''));
    return $nummer !== '' ? $nummer . ' ' . (string)$k['name'] : (string)$k['name'];
}

/** Alle Ausgaben- und Einnahmekategorien mit ihrem Objektkonto */
function objektkonto_kategorien(bool $nurAktive = false): array
{
    return db_all("SELECT id, list_key, label, color, sort_order, is_active, objektkonto_id FROM list_items
                   WHERE list_key IN ('ausgabe_kategorie', 'einnahme_kategorie')" . ($nurAktive ? ' AND is_active = 1' : '') . '
                   ORDER BY list_key, sort_order, label');
}

/** Kategorien nach Konto gruppieren: [konto_id => [Kategorien]], 0 = ohne Konto. Reine Funktion. */
function objektkonto_kategorien_je_konto(array $kategorien): array
{
    $out = [];
    foreach ($kategorien as $k) {
        $out[(int)($k['objektkonto_id'] ?? 0)][] = $k;
    }
    return $out;
}

/** Konto speichern, Kategorien zuordnen. Gibt [id, fehler[]] zurück. */
function objektkonto_save_from_post(?array $existing, array $user): array
{
    $name = mb_substr(trim(post_str('name')), 0, 150);
    $fehler = [];
    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben – etwa „Verpflegung" oder „Liegenschaft".';
    }
    $color = post_str('color', '#64748b');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#64748b';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'nummer'       => mb_substr(trim(post_str('nummer')), 0, 20),
        'name'         => $name,
        'beschreibung' => mb_substr(trim(post_str('beschreibung')), 0, 255),
        'color'        => $color,
        'sort_order'   => post_int('sort_order', 0) ?? 0,
        'is_active'    => post_bool('is_active'),
    ];
    if ($existing) {
        db_update('objektkonten', $daten, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('objektkonto.bearbeitet', 'objektkonto', $id, objektkonto_titel($daten));
    } else {
        $id = db_insert('objektkonten', $daten);
        audit('objektkonto.angelegt', 'objektkonto', $id, objektkonto_titel($daten));
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['kategorien'] ?? [])))));
    objektkonto_kategorien_setzen($id, $ids);
    return [$id, []];
}

/** Genau diese Kategorien gehören zum Konto – alle anderen Zuordnungen des Kontos fallen weg */
function objektkonto_kategorien_setzen(int $kontoId, array $ids): void
{
    db_exec('UPDATE list_items SET objektkonto_id = NULL WHERE objektkonto_id = ?', [$kontoId]);
    if ($ids) {
        $platz = implode(',', array_fill(0, count($ids), '?'));
        db_exec("UPDATE list_items SET objektkonto_id = ? WHERE id IN ($platz) AND list_key IN ('ausgabe_kategorie', 'einnahme_kategorie')",
            array_merge([$kontoId], $ids));
    }
}

function objektkonto_delete(array $k): void
{
    // Die Kategorien verlieren nur die Zuordnung (ON DELETE SET NULL)
    db_exec('DELETE FROM objektkonten WHERE id = ?', [(int)$k['id']]);
    audit('objektkonto.geloescht', 'objektkonto', (int)$k['id'], objektkonto_titel($k));
}

/**
 * Die Auswertung: Beträge je Konto aus den Beträgen je Kategorie.
 * $ausgaben/$einnahmen wie expense_by_category (id|null, label, color, anzahl, betrag).
 * Rückgabe: ['zeilen' => [[konto, ausgaben, einnahmen, anzahl, anteil_ausgaben, anteil_einnahmen, posten[]]],
 *            'ohne' => dieselbe Form für alles ohne Konto, 'summe' => ['ausgaben', 'einnahmen']]. Reine Funktion.
 */
function objektkonto_auswertung(array $konten, array $kategorien, array $ausgaben, array $einnahmen): array
{
    $kontoVon = [];
    foreach ($kategorien as $k) {
        if ((int)($k['objektkonto_id'] ?? 0) > 0) {
            $kontoVon[(int)$k['id']] = (int)$k['objektkonto_id'];
        }
    }
    $leer = static fn(?array $konto): array => ['konto' => $konto, 'ausgaben' => 0.0, 'einnahmen' => 0.0, 'anzahl' => 0,
                                                 'anteil_ausgaben' => null, 'anteil_einnahmen' => null, 'posten' => []];
    $zeilen = [];
    foreach ($konten as $k) {
        $zeilen[(int)$k['id']] = $leer($k);
    }
    $ohne = $leer(null);
    $summe = ['ausgaben' => 0.0, 'einnahmen' => 0.0];
    foreach (['ausgaben' => $ausgaben, 'einnahmen' => $einnahmen] as $art => $liste) {
        foreach ($liste as $p) {
            $betrag = (float)$p['betrag'];
            $kontoId = $p['id'] !== null ? ($kontoVon[(int)$p['id']] ?? 0) : 0;
            $ziel = $kontoId > 0 && isset($zeilen[$kontoId]) ? $zeilen[$kontoId] : $ohne;
            $ziel[$art] += $betrag;
            $ziel['anzahl'] += (int)$p['anzahl'];
            $ziel['posten'][] = ['art' => $art === 'ausgaben' ? 'ausgabe' : 'einnahme', 'label' => (string)($p['label'] ?? 'ohne Kategorie'),
                                 'color' => (string)($p['color'] ?? '#94a3b8'), 'betrag' => $betrag, 'anzahl' => (int)$p['anzahl'], 'ohne_kategorie' => $p['id'] === null];
            if ($kontoId > 0 && isset($zeilen[$kontoId])) {
                $zeilen[$kontoId] = $ziel;
            } else {
                $ohne = $ziel;
            }
            $summe[$art] += $betrag;
        }
    }
    $anteil = static fn(float $teil, float $ganz): ?float => $ganz > 0 ? round($teil / $ganz * 100, 1) : null;
    $fertig = static function (array $z) use ($anteil, $summe): array {
        $z['anteil_ausgaben'] = $z['ausgaben'] > 0 ? $anteil($z['ausgaben'], $summe['ausgaben']) : null;
        $z['anteil_einnahmen'] = $z['einnahmen'] > 0 ? $anteil($z['einnahmen'], $summe['einnahmen']) : null;
        usort($z['posten'], static fn($a, $b) => [$a['art'], $b['betrag']] <=> [$b['art'], $a['betrag']]);
        return $z;
    };
    $aus = [];
    foreach ($zeilen as $z) {
        // Stillgelegte Konten nur zeigen, wenn noch etwas darauf liegt
        if ((int)($z['konto']['is_active'] ?? 1) === 1 || $z['ausgaben'] > 0 || $z['einnahmen'] > 0) {
            $aus[] = $fertig($z);
        }
    }
    return ['zeilen' => $aus, 'ohne' => $fertig($ohne), 'summe' => $summe];
}

/** Die Auswertung eines Haushaltsjahrs aus der Datenbank (Ist: bezahlt und offen) */
function objektkonto_auswertung_jahr(int $jahr): array
{
    return objektkonto_auswertung(objektkonto_all(), objektkonto_kategorien(), expense_by_category($jahr), expense_by_category($jahr, 'einnahme'));
}
