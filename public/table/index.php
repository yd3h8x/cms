<?php
require_once dirname(__DIR__, 2) . '/framework/functions/essential.php';

$dbName = (string)($_GET['db'] ?? '');
$table = (string)($_GET['table'] ?? '');
schema_database($dbName);
schema_table($dbName, $table);

$perPage = current_records_per_page();
$count = record_count($dbName, $table);
$pages = max(1, (int)ceil($count / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$rows = fetch_records($dbName, $table, $perPage, ($page - 1) * $perPage);
$keys = primary_keys($dbName, $table);
$pageTitle = $table;

load_partial('html_begin');
load_partial('header');
?>
<main>
    <div class="w3-row">
        <div class="w3-col s8"><h2><?= h($table) ?></h2></div>
        <div class="w3-col s4 w3-right-align">
            <a class="w3-button w3-green" title="Add record"
               href="<?= h(app_url('form/index.php', ['db'=>$dbName,'table'=>$table])) ?>">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="w3-table-all w3-hoverable">
            <thead><tr>
                <?php foreach (columns($dbName, $table) as $column): ?>
                    <th><?= h($column['COLUMN_NAME']) ?></th>
                <?php endforeach; ?>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $value): ?><td><?= h($value) ?></td><?php endforeach; ?>
                    <td class="record-actions">
                        <?php $keyParams = []; foreach ($keys as $key) $keyParams['key'][$key] = $row[$key]; ?>
                        <a class="w3-button w3-blue icon-button" title="Edit"
                           href="<?= h(app_url('form/index.php', ['db'=>$dbName,'table'=>$table] + $keyParams)) ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form class="inline-form" method="post" action="/form/save.php">
                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_parent">
                            <input type="hidden" name="db" value="<?= h($dbName) ?>">
                            <input type="hidden" name="table" value="<?= h($table) ?>">
                            <?php foreach ($keys as $key): ?>
                                <input type="hidden" name="key[<?= h($key) ?>]" value="<?= h($row[$key]) ?>">
                            <?php endforeach; ?>
                            <button class="w3-button w3-red" title="Delete"
                                    data-confirm="Delete this record? MySQL foreign-key rules apply.">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination" aria-label="Pagination">
        <?php for ($n = 1; $n <= $pages; $n++): ?>
            <a class="w3-button <?= $n === $page ? 'w3-teal' : 'w3-black' ?>"
               href="<?= h(app_url('table/index.php', ['db'=>$dbName,'table'=>$table,'page'=>$n,'per_page'=>$perPage])) ?>"><?= $n ?></a>
        <?php endfor; ?>
    </div>
</main>
<?php load_partial('footer'); load_partial('html_end'); ?>
