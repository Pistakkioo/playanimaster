<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_static_data.php';

dev_admin_require_auth();

$token = dev_admin_token();
$tables = dev_static_discover_tables($conn);
$tableInfo = dev_static_table_info($conn, $tables);
$flash = '';
$flash_ok = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'export')
    {
        $selected = dev_static_filter_selected_tables(
            isset($_POST['tables']) ? (array) $_POST['tables'] : [],
            $conn
        );
        $sql = dev_static_export_sql($conn, $selected);

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="animaster_static_'
            . date('Y-m-d_His') . '.sql"');
        echo $sql;
        exit;
    }

    if ($action === 'sync_repo')
    {
        $result = dev_static_sync_to_repo($conn, null);
        $flash_ok = $result['ok'];
        $flash = $result['message'];

        header('Location: ' . dev_admin_page_url('dev_static_data.php', [
            'msg' => $flash,
            'ok' => $flash_ok ? '1' : '0'
        ]));
        exit;
    }

    if ($action === 'import')
    {
        $sql = '';
        if (!empty($_FILES['sql_file']['tmp_name']) && is_uploaded_file($_FILES['sql_file']['tmp_name']))
        {
            $sql = (string) file_get_contents($_FILES['sql_file']['tmp_name']);
        }
        elseif (isset($_POST['sql_text']))
        {
            $sql = (string) $_POST['sql_text'];
        }

        $truncate = !empty($_POST['truncate_first']);
        $result = dev_static_import_sql($conn, $sql, $truncate);
        $flash_ok = $result['ok'];
        $flash = $result['message'];

        header('Location: ' . dev_admin_page_url('dev_static_data.php', [
            'msg' => $flash,
            'ok' => $flash_ok ? '1' : '0'
        ]));
        exit;
    }
}

if (isset($_GET['msg']))
{
    $flash = (string) $_GET['msg'];
    $flash_ok = !isset($_GET['ok']) || $_GET['ok'] === '1';
}

$pageUrl = dev_admin_page_url('dev_static_data.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — Static Data Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .table-dark-ish { --bs-table-bg: #121a24; --bs-table-color: #e7ecf1; --bs-table-border-color: #2d3a4d; }
        textarea.sql-box { font-family: ui-monospace, monospace; font-size: .8rem; min-height: 220px; }
        .form-check-input { background-color: #0f1419; border-color: #495057; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Static data export / import</h1>
            <p class="meta mb-0">Tables without <code>id_user</code> or <code>id_user_ig</code> (game content, not player state).</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npcs.php')); ?>">NPC editor</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php')); ?>">Shops editor</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h($pageUrl); ?>">Refresh</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
    <div class="alert alert-<?php echo $flash_ok ? 'success' : 'danger'; ?>"><?php echo dev_admin_h($flash); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card dev-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Export to SQL</strong>
                    <span class="badge bg-secondary"><?php echo count($tableInfo); ?> tables</span>
                </div>
                <div class="card-body">
                    <p class="meta">One bulk <code>INSERT … ON DUPLICATE KEY UPDATE</code> per table — same format for download, repo write, and CLI sync.</p>
                    <p class="meta mb-3">Excluded at runtime: <?php echo dev_admin_h(implode(', ', dev_static_runtime_excludes())); ?></p>

                    <form method="post" action="<?php echo dev_admin_h($pageUrl); ?>">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="action" value="export">

                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-outline-light btn-sm" id="btn-all">All</button>
                            <button type="button" class="btn btn-outline-light btn-sm" id="btn-none">None</button>
                        </div>

                        <div class="table-responsive mb-3" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-sm table-dark-ish">
                                <thead>
                                    <tr>
                                        <th style="width:2.5rem"></th>
                                        <th>Table</th>
                                        <th class="text-end">Rows</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableInfo as $t): ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input tbl-cb" type="checkbox" name="tables[]"
                                                value="<?php echo dev_admin_h($t['name']); ?>" checked>
                                        </td>
                                        <td><code><?php echo dev_admin_h($t['name']); ?></code></td>
                                        <td class="text-end"><?php echo (int) $t['rows']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-primary" type="submit">Download .sql</button>
                    </form>

                    <hr class="border-secondary my-4">

                    <p class="meta mb-2">
                        Overwrite <code>private_functions/SQL/02_insert_static_data.sql</code>
                        with the current DB (same SQL as download).
                        Run this after editing NPCs/species in dev so IDs in git match your local DB.
                    </p>

                    <form method="post" action="<?php echo dev_admin_h($pageUrl); ?>"
                          onsubmit="return confirm('Overwrite 02_insert_static_data.sql with ALL static tables from this database?');">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="action" value="sync_repo">
                        <button class="btn btn-success" type="submit">Write to SQL/02_insert_static_data.sql</button>
                    </form>

                    <p class="meta small mt-2 mb-0">CLI: <code>php scripts/sync_static_data.php</code> from repo root.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Import from SQL</strong></div>
                <div class="card-body">
                    <p class="meta">Only <code>INSERT</code> and <code>SET</code> statements on static tables are executed. Use exports from this page or hand-written inserts.</p>

                    <form method="post" action="<?php echo dev_admin_h($pageUrl); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="action" value="import">

                        <div class="mb-3">
                            <label class="form-label">SQL file</label>
                            <input class="form-control form-control-sm" type="file" name="sql_file" accept=".sql,text/plain">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Or paste SQL</label>
                            <textarea class="form-control sql-box" name="sql_text" placeholder="INSERT INTO ..."></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="truncate_first" value="1" id="truncate_first">
                            <label class="form-check-label" for="truncate_first">
                                TRUNCATE each table before its first INSERT (destructive — replaces content)
                            </label>
                        </div>

                        <button class="btn btn-warning" type="submit" onclick="return confirm('Run import on this database?');">Import SQL</button>
                    </form>
                </div>
            </div>

            <div class="card dev-card">
                <div class="card-header"><strong>Rules</strong></div>
                <div class="card-body meta">
                    <ul class="mb-0">
                        <li>Auto-includes tables with no <code>id_user</code> / <code>id_user_ig</code> column.</li>
                        <li>Export order respects FK dependencies (species before npcs, etc.).</li>
                        <li>Player tables (<code>users</code>, <code>animals</code>, <code>items</code>, chat, trades…) are never included.</li>
                        <li>After editing NPCs on dev, <strong>sync to repo</strong> (button above or CLI) → commit <code>02_insert_static_data.sql</code>.</li>
                        <li>Download and repo sync produce identical SQL (bulk upsert, one statement per table).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function ()
{
    var boxes = document.querySelectorAll('.tbl-cb');
    document.getElementById('btn-all').addEventListener('click', function ()
    {
        boxes.forEach(function (cb) { cb.checked = true; });
    });
    document.getElementById('btn-none').addEventListener('click', function ()
    {
        boxes.forEach(function (cb) { cb.checked = false; });
    });
})();
</script>
</body>
</html>
