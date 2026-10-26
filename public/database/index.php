<?php
require_once dirname(__DIR__, 2) . '/framework/functions/essential.php';

$dbName = (string)($_GET['db'] ?? '');
schema_database($dbName);
$pageTitle = 'Tables: ' . $dbName;
$items = tables($dbName);

load_partial('html_begin');
load_partial('header');
?>
<main>
    <h2>Tables in <?= h($dbName) ?></h2>
    <ul class="w3-ul w3-card">
        <?php foreach ($items as $name): ?>
            <li><a href="<?= h(app_url('table/index.php', ['db' => $dbName, 'table' => $name])) ?>">
                <i class="fa-solid fa-table" aria-hidden="true"></i> <?= h($name) ?>
            </a></li>
        <?php endforeach; ?>
    </ul>
</main>
<?php load_partial('footer'); load_partial('html_end'); ?>
