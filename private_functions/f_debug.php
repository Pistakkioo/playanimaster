<?php

/**
 * Insert a debug row into table `log`.
 *
 * @param string|array|object $note     Message or data to store (max 1000 chars).
 * @param string|null       $nome_proc  Caller label; defaults to current script basename.
 * @param PDO|null          $conn       Optional PDO handle; uses global $conn when omitted.
 * @return int|false                    New id_log, or false on failure.
 */
function debug_log($note, $nome_proc = null, $conn = null)
{
    if ($conn === null)
    {
        global $conn;
    }

    if (!isset($conn) || !($conn instanceof PDO))
    {
        error_log('[debug_log] PDO connection not available');
        return false;
    }

    if (is_array($note) || is_object($note))
    {
        $note = json_encode($note, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $note = mb_substr((string) $note, 0, 1000);

    if ($nome_proc === null || $nome_proc === '')
    {
        $nome_proc = basename($_SERVER['SCRIPT_NAME'] ?? 'unknown');
    }

    $nome_proc = mb_substr((string) $nome_proc, 0, 100);

    try
    {
        $stmt = $conn->prepare('
            INSERT INTO log (nome_proc, dt_creazione, note)
            VALUES (:nome_proc, NOW(), :note)
        ');
        $stmt->execute([
            ':nome_proc' => $nome_proc,
            ':note' => $note
        ]);

        return (int) $conn->lastInsertId();
    }
    catch (PDOException $e)
    {
        error_log('[debug_log] ' . $e->getMessage());
        return false;
    }
}
