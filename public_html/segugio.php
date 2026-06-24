<?php
/*
-------------------
Segugio.php
v1.0 2020-09-26 Aggiunta la documentazione se senza parametri
v1.1 2022-09-17 Possibilità di cercare in una sottocartella
v2.0 2026-01-14 Distinct sul nome file nel risultato 
v2.1 2026-01-21 Aggiunta la security
v2.3 2026-06-08 Ricerca sempre in public_html e private_functions
-------------------
*/

// --- SICUREZZA ---
// Definisci qui il tuo token segreto. CAMBIALO con uno tuo!
define('SECURITY_TOKEN', 'WleFrittate2026!');

// Controlla se il token è stato passato e se è corretto.
if (!isset($_GET['T']) || $_GET['T'] !== SECURITY_TOKEN) {
    // Se il token non è valido, blocca l'esecuzione.
    http_response_code(403); // Forbidden
    die('Accesso non autorizzato.');
}


//config
$nome_log='error.log';   // su macchine Seeweb 

// --- INPUT HANDLING ---
// Recupera i parametri in modo sicuro, con valori predefiniti
$find = isset($_GET['find']) ? trim($_GET['find']) : '';
$find_mod = isset($_GET['mod']) ? $_GET['mod'] : 'S'; // 'S' è il default
$distinct = isset($_GET['distinct']) && $_GET['distinct'] === '1';

// --- TESTATA WEB ---
echo "Segugio by Diablitos &copy; v2.3 2026-06-08";
echo "</br>";
echo "<img src=\"https://img.gruppomol.it/responsive/seg/images/segugio-it-logo.svg\" style=\"max-height: 200px;\" >";
// --------------------

// Se è stato fornito un termine da cercare
if ($find !== '') {
    // --- COSTRUZIONE SICURA DEL COMANDO ---

    // 1. Sanifica l'input per prevenire la Command Injection
    $safe_find = escapeshellarg($find);

    // 2. Valida il modificatore di ricerca (whitelist)
    switch ($find_mod) {
        case 'CS': // Case-Sensitive
            $grep_command = 'grep';
            break;
        case 'S':  // Case-Insensitive (Default)
        default:
            $grep_command = 'grep -i';
            break;
    }
    
    // 3. Directory di ricerca: public_html + private_functions (sorella)
    $search_dirs = [__DIR__];
    $private_functions = realpath(__DIR__ . '/../private_functions');
    $old_cs_files = realpath(__DIR__ . '/../old_cs_files');

    if ($private_functions !== false)
    {
        $search_dirs[] = $private_functions;
    }
    if ($old_cs_files !== false)
    {
        $search_dirs[] = $old_cs_files;
    }

    $find_cmds = [];

    foreach ($search_dirs as $dir)
    {
        $find_cmds[] = 'find ' . escapeshellarg($dir)
            . ' ! -name ' . escapeshellarg($nome_log) 
            . ' -type f -exec ' . $grep_command . ' ' . $safe_find . ' /dev/null {} +';
    }

    $comando_find = '{ ' . implode('; ', $find_cmds) . '; }';

    // 4. Aggiungi la parte per il distinct se richiesta
    if ($distinct) {
        $comando_find .= " | cut -d':' -f1 | sort -u";
    }

    // --- ESECUZIONE E OUTPUT ---
    // Esegui il comando
    $output_find = shell_exec($comando_find);

    // 5. Sanifica l'output per prevenire XSS
    echo "<br/><br/>";
    echo "Cosa sto cercando: [<b>" . htmlspecialchars($find) . "</b>]";
    echo "</br></br>Dove l'ho trovato:";
    echo "<pre>" . htmlspecialchars($output_find) . "</pre>";

} else {
    // --- ISTRUZIONI SE NESSUN PARAMETRO ---
    $segugiophp = htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", ENT_QUOTES, 'UTF-8');
    
    echo "<br/><br/>";
    echo "Per effettuare una ricerca generica (case-insensitive) -> <a href=\"" . $segugiophp . "?find=cercami\" target=\"_blank\">" . $segugiophp . "?find=cercami</a>";
    echo "<br/><br/>";
    echo "Per effettuare una ricerca Case Sensitive -> <a href=\"" . $segugiophp . "?mod=CS&find=cercami\" target=\"_blank\">" . $segugiophp . "?mod=CS&find=cercami</a>";
    echo "<br/><br/>";
    echo "Per effettuare una ricerca con risultato distinto (mostra solo i nomi dei file) -> <a href=\"" . $segugiophp . "?distinct=1&find=cercami\" target=\"_blank\">" . $segugiophp . "?distinct=1&find=cercami</a>";
}
?>
