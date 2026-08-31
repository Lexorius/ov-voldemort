<?php
/** Unternavigation des Budgetbereichs. @var int $jahr */
$cur = $_GET['p'] ?? 'budget';
?>
<div class="tabs">
  <a class="tab<?= in_array($cur, ['budget', 'budget_edit', 'budget_year_edit'], true) ? ' is-active' : '' ?>"
     href="<?= e(url('budget', ['jahr' => $jahr])) ?>">Übersicht</a>
  <?php if (can('view_expenses')): ?>
    <a class="tab<?= in_array($cur, ['expenses', 'expense_edit'], true) ? ' is-active' : '' ?>"
       href="<?= e(url('expenses', ['jahr' => $jahr])) ?>">Ausgaben</a>
  <?php endif; ?>
</div>
