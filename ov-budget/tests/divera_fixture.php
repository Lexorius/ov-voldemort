<?php
declare(strict_types=1);
/*
 * Antworten der Divera-Formular-Schnittstelle, gebaut nach
 * https://api.divera247.com/docs/api_v2_reporttype.yaml
 *   GET /api/v2/reporttypes              – Formulare (data.items: Objekt nach id)
 *   GET /api/v2/reporttypes/{id}/reports – Einträge (data.items: Liste, 50 je Seite)
 * Werte von radio/selectbox/checkbox sind die ids der Optionen, nicht deren Text.
 */

function fixture_felder(): array
{
    return [
        'bez'   => ['id' => 'f-bez', 'name' => 'Bezeichnung', 'type' => 'textinput', 'options' => [], 'required' => '1'],
        'anz'   => ['id' => 'f-anz', 'name' => 'Anzahl', 'type' => 'number', 'options' => [], 'required' => '1'],
        'preis' => ['id' => 'f-preis', 'name' => 'Nettobetrag', 'type' => 'float', 'options' => [], 'required' => '0'],
        'dring' => ['id' => 'f-dring', 'name' => 'Dringlichkeit', 'type' => 'radio', 'required' => '1', 'options' => [
            ['id' => 'o-hoch', 'name' => 'hoch'], ['id' => 'o-mittel', 'name' => 'mittel'], ['id' => 'o-niedrig', 'name' => 'niedrig']]],
        'fg'    => ['id' => 'f-fg', 'name' => 'Fachgruppe', 'type' => 'selectbox', 'required' => '0', 'options' => [
            ['id' => 'o-b', 'name' => 'Bergungsgruppe'], ['id' => 'o-n', 'name' => 'Notinstandsetzung']]],
        'nice'  => ['id' => 'f-nice', 'name' => 'Nice to have', 'type' => 'checkbox', 'required' => '0', 'options' => [
            ['id' => 'o-ja', 'name' => 'ja']]],
        'zweck' => ['id' => 'f-zweck', 'name' => 'Einsatzzweck', 'type' => 'checkbox', 'required' => '0', 'options' => [
            ['id' => 'o-einsatz', 'name' => 'Einsatz'], ['id' => 'o-ausb', 'name' => 'Ausbildung'], ['id' => 'o-jugend', 'name' => 'Jugend']]],
        'bis'   => ['id' => 'f-bis', 'name' => 'Benötigt bis', 'type' => 'date', 'options' => [], 'required' => '0'],
        'grund' => ['id' => 'f-grund', 'name' => 'Begründung', 'type' => 'textarea', 'options' => [], 'required' => '0'],
        'kopf'  => ['id' => 'f-kopf', 'name' => 'Angaben zum Wunsch', 'type' => 'headline', 'options' => [], 'required' => '0'],
    ];
}

function fixture_formulare(): array
{
    $f = fixture_felder();
    return ['success' => true, 'data' => [
        'items' => [
            '654' => ['id' => 654, 'name' => 'Wünsch dir was', 'description' => 'Bedarfe der Fachgruppen', 'version' => 3,
                      'uploads' => true, 'anonym' => false, 'location' => false, 'vehicle' => false,
                      'fields' => array_values($f)],
            '655' => ['id' => 655, 'name' => 'Schadensmeldung', 'description' => '', 'version' => 1,
                      'uploads' => true, 'anonym' => true, 'location' => true, 'vehicle' => false, 'fields' => []],
        ],
        'sorting' => [654, 655],
    ], 'ucr' => 101];
}

/** Ein Eintrag, wie Divera ihn liefert */
function fixture_eintrag(string $id, array $werte, array $extra = []): array
{
    $f = fixture_felder();
    $felder = [];
    foreach ($werte as $schluessel => $wert) {
        $felder[] = ['field' => $f[$schluessel], 'value' => $wert];
    }
    return $extra + [
        'id' => $id, 'cluster_id' => 70, 'user_cluster_relation_id' => 123, 'reporttype_id' => 654,
        'cluster_use_vehicle_id' => null, 'status' => 0, 'attachment_count' => 0, 'attachment' => [],
        'lat' => null, 'lng' => null, 'address' => '', 'fields' => $felder, 'ts_create' => 1758200000,
    ];
}

function fixture_eintraege(): array
{
    return [
        fixture_eintrag('9001', [
            'kopf' => '', 'bez' => 'Tauchpumpe TP 8/1', 'anz' => '2', 'preis' => '1249,90', 'dring' => 'o-hoch',
            'fg' => 'o-b', 'nice' => '', 'zweck' => 'o-einsatz,o-ausb', 'bis' => '2026-11-30',
            'grund' => 'Alte Pumpe ist defekt.',
        ], ['attachment_count' => 1, 'attachment' => [['id' => 5, 'file_id' => 77, 'name' => 'Angebot.pdf']]]),
        fixture_eintrag('9002', [
            'bez' => 'Stirnlampen', 'anz' => '12', 'preis' => '39.50', 'dring' => 'o-niedrig', 'fg' => 'o-n',
            'nice' => 'o-ja', 'zweck' => '["o-jugend"]', 'bis' => '', 'grund' => '',
        ]),
    ];
}

/** Seite mit Einträgen: 50 je Seite, wie Divera blättert */
function fixture_seite(array $alle, int $offset, int $proSeite = 50): array
{
    return ['success' => true, 'data' => [
        'items' => array_slice($alle, $offset, $proSeite),
        'itemcount' => count($alle),
    ], 'ucr' => 101];
}
