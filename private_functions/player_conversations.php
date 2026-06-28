<?php

/**
 * One-time (flg_register = S) conversation progress per character.
 */
class PLAYER_CONVERSATIONS
{
    public static function isFinished($conn, $id_user_ig, $id_conversation)
    {
        $stmt = $conn->prepare('
            SELECT 1
            FROM user_conversations
            WHERE id_user_ig = :id_user_ig
              AND id_conversation = :id_conversation
              AND finished = \'S\'
            LIMIT 1
        ');
        $stmt->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':id_conversation' => (int) $id_conversation,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public static function requiresRegister($conn, $id_conversation)
    {
        $stmt = $conn->prepare('
            SELECT flg_register
            FROM conversations
            WHERE id_conversation = :id_conversation
            LIMIT 1
        ');
        $stmt->execute([':id_conversation' => (int) $id_conversation]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && (string) $row['flg_register'] === 'S';
    }

    public static function optionHasConsequences($conn, $id_conversation, $id_option)
    {
        if ((int) $id_option <= 0)
        {
            return false;
        }

        $stmt = $conn->prepare('
            SELECT 1
            FROM conversation_consequences
            WHERE id_conversation = :id_conversation
              AND id_option = :id_option
            LIMIT 1
        ');
        $stmt->execute([
            ':id_conversation' => (int) $id_conversation,
            ':id_option' => (int) $id_option,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Register when the player actually completed the conversation, not when they decline.
     * Linear dialogs (no final option) use id_option = 0.
     * Branched dialogs register only when the chosen option has linked consequences.
     */
    public static function shouldRegisterOnFinish($conn, $id_conversation, $id_option)
    {
        if (!self::requiresRegister($conn, $id_conversation))
        {
            return false;
        }

        $id_option = (int) $id_option;

        if ($id_option <= 0)
        {
            return true;
        }

        return self::optionHasConsequences($conn, $id_conversation, $id_option);
    }

    public static function registerFinished($conn, $id_user_ig, $id_conversation, $id_option = 0)
    {
        if ((int) $id_user_ig <= 0 || (int) $id_conversation <= 0)
        {
            return false;
        }

        if (self::isFinished($conn, $id_user_ig, $id_conversation))
        {
            return true;
        }

        $stmt = $conn->prepare('
            INSERT INTO user_conversations
                (id_user_ig, id_conversation, dt_c, finished, dt_finished, finish_option)
            VALUES
                (:id_user_ig, :id_conversation, NOW(), \'S\', NOW(), :finish_option)
            ON DUPLICATE KEY UPDATE
                finished = \'S\',
                dt_finished = NOW(),
                finish_option = :finish_option_update,
                dt_m = NOW()
        ');

        return $stmt->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':id_conversation' => (int) $id_conversation,
            ':finish_option' => (int) $id_option,
            ':finish_option_update' => (int) $id_option,
        ]);
    }
}
