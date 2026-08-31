<?php
/** Unternavigation des Budgetbereichs. @var int $jahr */
$cur = $_GET['p'] ?? 'budget';
$curArt = $_GET['art'] ?? 'ausgabe';
$listeAktiv = in_array($cur, ['expenses', 'expense_edit'], true);
?>
<div class="tabs">
  <a class="tab<?= in_array($cur, ['budget', 'budget_edit', 'budget_year_edit'], true) ? ' is-active' : '' ?>"
     href="<?= e(url('budget', ['jahr' => $jahr])) ?>">Übersicht</a>
  <?php if (can('view_expenses')): ?>
    <a class="tab<?= ($listeAktiv && $curArt !== 'einnahme') ? ' is-active' : '' ?>"
       href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'ausgabe'])) ?>">Ausgaben</a>
    <a class="tab<?= ($listeAktiv && $curArt === 'einnahme') ? ' is-active' : '' ?>"
       href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>">Einnahmen</a>
  <?php endif; ?>
</div>
