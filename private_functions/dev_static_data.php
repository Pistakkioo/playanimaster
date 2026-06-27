<?php

/**
 * Discover / export / import static game data (tables without id_user / id_user_ig).
 */
function dev_static_runtime_excludes()
{
    return [
        'log',
        'wild_animals',
        'battles_solo_pve_moves',
        'storico_battles_solo_pve_moves',
        'alliances',
        'clans'
    ];
}

function dev_static_preferred_order()
{
    return [
        'elements',
        'classes',
        'subclasses',
        'abilities',
        'species',
        'species_abilities',
        'item_types',
        'costanti',
        'requirements',
        'consequences',
        'zones',
        'npcs',
        'conversations',
        'dialogues',
        'dialogues_options',
        'conversation_requirements',
        'conversation_consequences',
        'npc_requirements',
        'quests',
        'quest_requirements',
        'spawn_points',
        'zone_animals',
        'wild_animal_drop_types',
        'language_texts',
        'chat_word_replacements',
        'chat_global_item_config'
    ];
}

function dev_static_db_name(PDO $conn)
{
    $name = $conn->query('SELECT DATABASE()')->fetchColumn();

    return $name ? (string) $name : 'playanimaster_db';
}

function dev_static_discover_tables(PDO $conn)
{
    $db = dev_static_db_name($conn);
    $runtime = dev_static_runtime_excludes();

    $stmt = $conn->prepare('
        SELECT t.TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES t
        WHERE t.TABLE_SCHEMA = :db
          AND t.TABLE_TYPE = \'BASE TABLE\'
          AND NOT EXISTS (
              SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS c
              WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA
                AND c.TABLE_NAME = t.TABLE_NAME
                AND c.COLUMN_NAME IN (\'id_user\', \'id_user_ig\')
          )
        ORDER BY t.TABLE_NAME
    ');
    $stmt->execute([':db' => $db]);

    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $name = $row['TABLE_NAME'];
        if (in_array($name, $runtime, true))
        {
            continue;
        }
        $tables[] = $name;
    }

    return dev_static_sort_tables($tables);
}

function dev_static_sort_tables(array $tables)
{
    $order = dev_static_preferred_order();
    $rank = [];
    foreach ($order as $i => $name)
    {
        $rank[$name] = $i;
    }

    usort($tables, function ($a, $b) use ($rank)
    {
        $ra = isset($rank[$a]) ? $rank[$a] : 1000;
        $rb = isset($rank[$b]) ? $rank[$b] : 1000;
        if ($ra === $rb)
        {
            return strcmp($a, $b);
        }
        return $ra - $rb;
    });

    return $tables;
}

function dev_static_table_info(PDO $conn, array $tables)
{
    $info = [];
    foreach ($tables as $table)
    {
        if (!dev_static_valid_table_name($table))
        {
            continue;
        }
        $quoted = dev_static_quote_ident($table);
        $count = (int) $conn->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn();
        $info[] = [
            'name' => $table,
            'rows' => $count
        ];
    }
    return $info;
}

function dev_static_valid_table_name($table)
{
    return (bool) preg_match('/^[a-zA-Z0-9_]+$/', $table);
}

function dev_static_quote_ident($ident)
{
    return '`' . str_replace('`', '``', $ident) . '`';
}

function dev_static_sql_literal(PDO $conn, $value)
{
    if ($value === null)
    {
        return 'NULL';
    }

    if (is_bool($value))
    {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value))
    {
        return (string) $value;
    }

    return $conn->quote((string) $value);
}

function dev_static_export_table(PDO $conn, $table, $dbName)
{
    if (!dev_static_valid_table_name($table))
    {
        return '';
    }

    $quoted = dev_static_quote_ident($table);
    $stmt = $conn->query('SELECT * FROM ' . $quoted);
    if (!$stmt)
    {
        return '';
    }

    $lines = [];
    $columns = null;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        if ($columns === null)
        {
            $columns = array_keys($row);
        }

        $colList = implode(', ', array_map('dev_static_quote_ident', $columns));
        $values = [];
        foreach ($columns as $col)
        {
            $values[] = dev_static_sql_literal($conn, $row[$col]);
        }

        $lines[] = 'INSERT INTO ' . dev_static_quote_ident($dbName) . '.' . $quoted
            . ' (' . $colList . ') VALUES(' . implode(', ', $values) . ');';
    }

    if (!$lines)
    {
        return '-- ' . $table . ': (empty)' . "\n";
    }

    $out = '-- ' . $table . ' (' . count($lines) . " rows)\n";
    $out .= implode("\n", $lines) . "\n";

    return $out;
}

function dev_static_export_sql(PDO $conn, array $tables)
{
    $dbName = dev_static_db_name($conn);
    $allowed = dev_static_discover_tables($conn);
    $selected = [];

    foreach ($tables as $table)
    {
        if (in_array($table, $allowed, true))
        {
            $selected[] = $table;
        }
    }

    $selected = dev_static_sort_tables($selected);

    $header = "-- ANIMASTER static data export\n";
    $header .= '-- Database: ' . $dbName . "\n";
    $header .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n";
    $header .= "-- Tables: " . implode(', ', $selected) . "\n\n";
    $header .= "SET NAMES utf8mb4;\n";
    $header .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $body = '';
    foreach ($selected as $table)
    {
        $body .= dev_static_export_table($conn, $table, $dbName) . "\n";
    }

    $footer = "SET FOREIGN_KEY_CHECKS=1;\n";

    return $header . $body . $footer;
}

function dev_static_split_sql_statements($sql)
{
    $statements = [];
    $current = '';
    $inString = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++)
    {
        $ch = $sql[$i];
        $current .= $ch;

        if ($inString)
        {
            if ($ch === '\\' && $i + 1 < $len)
            {
                $current .= $sql[$i + 1];
                $i++;
                continue;
            }
            if ($ch === "'")
            {
                if ($i + 1 < $len && $sql[$i + 1] === "'")
                {
                    $current .= "'";
                    $i++;
                    continue;
                }
                $inString = false;
            }
            continue;
        }

        if ($ch === "'")
        {
            $inString = true;
            continue;
        }

        if ($ch === ';')
        {
            $stmt = trim($current);
            if ($stmt !== '' && $stmt !== ';')
            {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    $tail = trim($current);
    if ($tail !== '')
    {
        $statements[] = $tail;
    }

    return $statements;
}

function dev_static_is_allowed_import_statement($stmt)
{
    $upper = strtoupper(ltrim($stmt));

    if ($upper === '' || strpos($upper, '--') === 0)
    {
        return false;
    }

    $allowed = [
        'INSERT INTO',
        'SET NAMES',
        'SET FOREIGN_KEY_CHECKS=0',
        'SET FOREIGN_KEY_CHECKS=1',
        'SET FOREIGN_KEY_CHECKS = 0',
        'SET FOREIGN_KEY_CHECKS = 1'
    ];

    foreach ($allowed as $prefix)
    {
        if (strpos($upper, $prefix) === 0)
        {
            return true;
        }
    }

    return false;
}

function dev_static_extract_insert_table($stmt)
{
    if (!preg_match('/^INSERT\s+INTO\s+(?:`?([a-zA-Z0-9_]+)`?\.)?`?([a-zA-Z0-9_]+)`?/i', $stmt, $m))
    {
        return null;
    }

    return $m[2];
}

function dev_static_import_sql(PDO $conn, $sql, $truncate = false)
{
    $statements = dev_static_split_sql_statements($sql);
    $allowedTables = dev_static_discover_tables($conn);
    $truncated = [];
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    try
    {
        $conn->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($statements as $stmt)
        {
            if (!dev_static_is_allowed_import_statement($stmt))
            {
                $skipped++;
                continue;
            }

            $table = dev_static_extract_insert_table($stmt);
            if ($table !== null)
            {
                if (!in_array($table, $allowedTables, true))
                {
                    $skipped++;
                    continue;
                }

                if ($truncate && !isset($truncated[$table]))
                {
                    $conn->exec('TRUNCATE TABLE ' . dev_static_quote_ident($table));
                    $truncated[$table] = true;
                }
            }

            $conn->exec($stmt);

            if ($table !== null)
            {
                $inserted++;
            }
        }

        $conn->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    catch (PDOException $e)
    {
        try
        {
            $conn->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        catch (PDOException $e2)
        {
        }

        return [
            'ok' => false,
            'message' => 'Import failed: ' . $e->getMessage(),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'truncated' => array_keys($truncated)
        ];
    }

    return [
        'ok' => true,
        'message' => 'Import complete. INSERT statements run: ' . $inserted
            . ', skipped: ' . $skipped
            . ($truncate ? ', truncated tables: ' . count($truncated) : '') . '.',
        'inserted' => $inserted,
        'skipped' => $skipped,
        'truncated' => array_keys($truncated)
    ];
}

function dev_static_filter_selected_tables(array $posted, PDO $conn)
{
    $allowed = dev_static_discover_tables($conn);
    $selected = [];

    foreach ($posted as $table)
    {
        $table = (string) $table;
        if (in_array($table, $allowed, true))
        {
            $selected[] = $table;
        }
    }

    if (!$selected)
    {
        return $allowed;
    }

    return dev_static_sort_tables($selected);
}
