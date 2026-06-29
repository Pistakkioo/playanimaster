#!/usr/bin/env php
<?php

/**
 * Export static catalog tables from the local DB into:
 *   private_functions/SQL/02_insert_static_data.sql
 *
 * Run after creating/editing content in dev_npcs.php, dev_species.php, etc.
 * so repo SQL stays aligned with real auto-increment IDs.
 *
 * Usage (from repo root):
 *   php scripts/sync_static_data.php
 *   php scripts/sync_static_data.php conversations dialogues npcs
 */

if (php_sapi_name() !== 'cli')
{
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/private_functions/i.php';
require_once $root . '/private_functions/dev_static_data.php';

if (!isset($conn) || !($conn instanceof PDO))
{
    fwrite(STDERR, "Database connection not available. Check private_functions/d.php.\n");
    exit(1);
}

$table_args = array_slice($argv, 1);
$tables = $table_args ? $table_args : null;

$result = dev_static_sync_to_repo($conn, $tables);

if ($result['ok'])
{
    fwrite(STDOUT, $result['message'] . ' (' . number_format($result['bytes']) . " bytes)\n");
    exit(0);
}

fwrite(STDERR, $result['message'] . "\n");
exit(1);
