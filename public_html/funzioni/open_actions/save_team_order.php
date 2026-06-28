<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = '';
$stato = 'KO';
$msg = 'INVALID_REQUEST';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = isset($_POST['lang']) ? $_POST['lang'] : '';
$order_raw = isset($_POST['team_order']) ? trim((string) $_POST['team_order']) : '';

if ($id_user_ig > 0 && $order_raw !== '')
{
    $ids = [];

    foreach (explode(',', $order_raw) as $part)
    {
        $id_animal = (int) trim($part);

        if ($id_animal > 0)
        {
            $ids[] = $id_animal;
        }
    }

    $ids = array_values(array_unique($ids));

    if (!empty($ids))
    {
        $stmt_current = $conn->prepare('
            SELECT id_animal
            FROM animals
            WHERE id_user_ig = :id_user_ig
              AND team_position > 0
              AND team_position < 6
            ORDER BY team_position ASC
        ');
        $stmt_current->execute([':id_user_ig' => $id_user_ig]);
        $current_ids = $stmt_current->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $current_ids = array_map('intval', $current_ids);

        sort($current_ids);
        $sorted_requested = $ids;
        sort($sorted_requested);

        if (count($ids) === count($current_ids) && $sorted_requested === $current_ids)
        {
            try
            {
                $conn->beginTransaction();

                $stmt_update = $conn->prepare('
                    UPDATE animals
                    SET team_position = :team_position,
                        dt_modifica = NOW()
                    WHERE id_animal = :id_animal
                      AND id_user_ig = :id_user_ig
                      AND team_position > 0
                      AND team_position < 6
                ');

                $position = 1;

                foreach ($ids as $id_animal)
                {
                    $stmt_update->execute([
                        ':team_position' => $position,
                        ':id_animal' => (int) $id_animal,
                        ':id_user_ig' => $id_user_ig,
                    ]);
                    $position++;
                }

                $conn->commit();
                $stato = 'OK';
                $msg = 'OK';
            }
            catch (Exception $e)
            {
                if ($conn->inTransaction())
                {
                    $conn->rollBack();
                }

                $stato = 'KO';
                $msg = 'SAVE_FAILED';
            }
        }
        else
        {
            $msg = 'INVALID_TEAM_ORDER';
        }
    }
}

$riga = [
    'stato' => $stato,
    'msg' => $msg,
    'response' => $stringone,
];

echo json_encode($riga);

?>
