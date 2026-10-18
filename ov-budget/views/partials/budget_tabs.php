<?php
/** Unternavigation des Budgetbereichs. @var int $jahr */
$cur = $_GET['p'] ?? 'budget';
$curArt = $_GET['art'] ?? 'ausgabe';
$listeAktiv = in_array($cur, ['expenses', 'expense_edit'], true);
?>
<div class="tabs">
  <a class="tab<?= in_array($cur, ['budget', 'budget_year_edit'], true) ? ' is-active' : '' ?>"
     href="<?= e(url('budget', ['jahr' => $jahr])) ?>">Übersicht</a>
  <?php if (can('manage_budget')): ?>
    <a class="tab<?= in_array($cur, ['budget_pots', 'budget_edit'], true) ? ' is-active' : '' ?>"
       href="<?= e(url('budget_pots', ['jahr' => $jahr])) ?>">Töpfe</a>
  <?php endif; ?>
  <?php if (can('view_expenses')): ?>
    <a class="tab<?= in_array($cur, ['budget_objektkonten', 'objektkonten', 'objektkonto_edit'], true) ? ' is-active' : '' ?>"
       href="<?= e(url('budget_objektkonten', ['jahr' => $jahr])) ?>">Objektkonten</a>
    <a class="tab<?= $cur === 'budget_prognose' ? ' is-active' : '' ?>"
       href="<?= e(url('budget_prognose', ['jahr' => $jahr])) ?>">Hochrechnung</a>
  <?php endif; ?>
  <a class="tab<?= in_array($cur, ['bestellungen', 'bestellung', 'bestellung_edit'], true) ? ' is-active' : '' ?>"
     href="<?= e(url('bestellungen')) ?>">Bestellungen</a>
  <?php if (can('view_expenses')): ?>
    <a class="tab<?= ($listeAktiv && $curArt !== 'einnahme') ? ' is-active' : '' ?>"
       href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'ausgabe'])) ?>">Ausgaben</a>
    <a class="tab<?= ($listeAktiv && $curArt === 'einnahme') ? ' is-active' : '' ?>"
       href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>">Einnahmen</a>
  <?php endif; ?>
</div>
