<?php
/** @var array $rows @var array $stats @var array $filters @var array $votes @var array $budgets */
$modul = (string)setting('wunsch_modul_name', 'Wünsch dir was');
$alle = get_str('alle') === '1';
$mine = get_str('mine') === '1';
?>
<div class="pagehead">
  <div>
    <h1><?= e($modul) ?></h1>
    <p><?= nl2br(e((string)setting('wunsch_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('wish_edit')) ?>">+ Neuer Wunsch</a>
    <a class="btn btn--sec" href="<?= e(url('wishes_export', array_diff_key($_GET, ['p' => 1]))) ?>">CSV</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Wünsche</div><div class="stat__value"><?= (int)$stats['anzahl'] ?></div></div>
  <div class="stat"><div class="stat__label">Summe netto</div><div class="stat__value"><?= e(money($stats['netto'], false)) ?></div></div>
  <div class="stat"><div class="stat__label">Noch offen</div><div class="stat__value"><?= e(money($stats['netto_offen'], false)) ?></div></div>
  <div class="stat"><div class="stat__label">Nice to have</div><div class="stat__value"><?= e(money($stats['nice'], false)) ?></div></div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="wishes">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Bezeichnung, Lieferant, Artikelnummer">
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('wunsch_status', $filters['status_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', $filters['fachgruppe_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="dringlichkeit_id">Dringlichkeit</label>
      <select id="dringlichkeit_id" name="dringlichkeit_id"><?= list_options('dringlichkeit', $filters['dringlichkeit_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach ([
            'prio' => 'Priorität', 'stimmen' => 'Stimmen', 'betrag' => 'Betrag',
            'frist' => 'Frist', 'neu' => 'Neueste', 'name' => 'Bezeichnung',
        ] as $k => $lbl): ?>
          <option value="<?= e($k) ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
  <div class="chips mt">
    <a class="chip<?= $alle ? '' : ' is-active' ?>" href="<?= e(url('wishes', array_diff_key($_GET, ['alle' => 1, 'p' => 1]))) ?>">nur offene</a>
    <a class="chip<?= $alle ? ' is-active' : '' ?>" href="<?= e(url('wishes', array_merge(array_diff_key($_GET, ['p' => 1]), ['alle' => '1']))) ?>">alle</a>
    <a class="chip<?= $mine ? ' is-active' : '' ?>" href="<?= e(url('wishes', array_merge(array_diff_key($_GET, ['p' => 1]), ['mine' => $mine ? '' : '1']))) ?>">meine</a>
    <?php if ($filters['q'] || $filters['status_id'] || $filters['fachgruppe_id'] || $filters['dringlichkeit_id']): ?>
      <a class="chip" href="<?= e(url('wishes')) ?>">Filter zurücksetzen</a>
    <?php endif; ?>
  </div>
</form>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Keine Wünsche gefunden.</div></div>
<?php else: ?>
  <div class="itemlist">
    <?php foreach ($rows as $w): ?>
      <div style="position:relative">
        <?= render_partial('partials/wish_item', ['w' => $w]) ?>
        <?php if (can('vote')): ?>
          <form method="post" action="<?= e(url('wish_action')) ?>" class="inline-form"
                style="position:absolute;right:.7rem;bottom:.7rem">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="vote">
            <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
            <input type="hidden" name="back" value="<?= e(current_url()) ?>">
            <button class="vote<?= isset($votes[(int)$w['id']]) ? ' is-on' : '' ?>" type="submit"
                    title="Diesen Wunsch unterstützen">👍 <?= (int)$w['votes'] ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
