<?php

/**
 * Quest runtime engine: progression (phases), objective progress, and the
 * hooks that feed it from combat/dialog/leveling code.
 *
 * Schema recap (see docs/modules/004_QUESTS.md):
 * - quests / quest_requirements: catalog + accept/turn-in gating (existing).
 * - user_quests: one row per (id_user_ig, id_quest); `phase` advances as
 *   objectives complete. Once phase > MAX(quest_objectives.phase) for that
 *   quest, the quest is "ready to turn in" (flg_completed still 'N').
 * - quest_objectives: N rows per phase; a phase completes only when every
 *   objective row for it is satisfied.
 * - user_quest_objective_progress: persisted counter, used only by
 *   objective types that track a transient event (kill_species). Other
 *   types are checked live against existing state.
 */
class QUESTS
{
    const OBJECTIVE_KILL_SPECIES = 'kill_species';
    const OBJECTIVE_COLLECT_ITEM = 'collect_item';
    const OBJECTIVE_TALK_NPC = 'talk_npc';
    const OBJECTIVE_REACH_LEVEL = 'reach_level';

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchQuest($conn, $id_quest)
    {
        $stmt = $conn->prepare('SELECT * FROM quests WHERE id_quest = :id_quest LIMIT 1');
        $stmt->execute([':id_quest' => (int) $id_quest]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fetchUserQuest($conn, $id_user_ig, $id_quest)
    {
        $stmt = $conn->prepare('
            SELECT * FROM user_quests
            WHERE id_user_ig = :id_user_ig AND id_quest = :id_quest
            LIMIT 1
        ');
        $stmt->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':id_quest' => (int) $id_quest,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function maxPhase($conn, $id_quest)
    {
        $stmt = $conn->prepare('SELECT MAX(phase) AS max_phase FROM quest_objectives WHERE id_quest = :id_quest');
        $stmt->execute([':id_quest' => (int) $id_quest]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && $row['max_phase'] !== null ? (int) $row['max_phase'] : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchPhaseObjectives($conn, $id_quest, $phase)
    {
        $stmt = $conn->prepare('
            SELECT * FROM quest_objectives
            WHERE id_quest = :id_quest AND phase = :phase
            ORDER BY sort_order ASC, id_quest_objective ASC
        ');
        $stmt->execute([
            ':id_quest' => (int) $id_quest,
            ':phase' => (int) $phase,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Requirement type: "quest not started". True when the player has never
     * begun the quest, or finished a repeatable one and can begin it again.
     */
    public static function isNotStarted($conn, $id_user_ig, $id_quest)
    {
        $user_quest = self::fetchUserQuest($conn, $id_user_ig, $id_quest);

        if (!$user_quest)
        {
            return true;
        }

        if ((string) $user_quest['flg_completed'] !== 'S')
        {
            return false;
        }

        $quest = self::fetchQuest($conn, $id_quest);

        return $quest && (string) $quest['repeatable'] === 'S';
    }

    /**
     * Requirement type: "quest started". Exact opposite of "quest not started"
     * — true once the player has an active attempt at the quest (in progress,
     * ready to turn in, or completed-and-not-repeatable so it can't restart).
     */
    public static function isStarted($conn, $id_user_ig, $id_quest)
    {
        return !self::isNotStarted($conn, $id_user_ig, $id_quest);
    }

    /**
     * Requirement type: "quest ready to turn in". True once every objective
     * of the final phase is met but the turn-in consequence has not fired.
     */
    public static function isReadyToTurnIn($conn, $id_user_ig, $id_quest)
    {
        $user_quest = self::fetchUserQuest($conn, $id_user_ig, $id_quest);

        if (!$user_quest || (string) $user_quest['flg_completed'] === 'S')
        {
            return false;
        }

        $max_phase = self::maxPhase($conn, $id_quest);

        return (int) $user_quest['phase'] > $max_phase;
    }

    /**
     * Requirement type: "quest completed".
     */
    public static function isCompleted($conn, $id_user_ig, $id_quest)
    {
        $user_quest = self::fetchUserQuest($conn, $id_user_ig, $id_quest);

        return $user_quest && (string) $user_quest['flg_completed'] === 'S';
    }

    /**
     * Requirement type: "quest phase". True while the quest is active and the
     * player's current phase equals $phase (not ready to turn in, not completed).
     */
    public static function isOnPhase($conn, $id_user_ig, $id_quest, $phase)
    {
        $phase = (int) $phase;

        if ($phase <= 0)
        {
            return false;
        }

        $user_quest = self::fetchUserQuest($conn, $id_user_ig, $id_quest);

        if (!$user_quest || (string) $user_quest['flg_completed'] === 'S')
        {
            return false;
        }

        $max_phase = self::maxPhase($conn, $id_quest);
        $current = (int) $user_quest['phase'];

        if ($current > $max_phase)
        {
            return false;
        }

        return $current === $phase;
    }

    /**
     * Requirement type: "quest phase completed". True once every objective of
     * $phase is done — i.e. user_quests.phase is strictly greater than $phase
     * (includes ready to turn in and completed quests).
     */
    public static function isPhaseCompleted($conn, $id_user_ig, $id_quest, $phase)
    {
        $phase = (int) $phase;

        if ($phase <= 0)
        {
            return false;
        }

        $user_quest = self::fetchUserQuest($conn, $id_user_ig, $id_quest);

        if (!$user_quest)
        {
            return false;
        }

        if ((string) $user_quest['flg_completed'] === 'S')
        {
            return true;
        }

        return (int) $user_quest['phase'] > $phase;
    }

    /**
     * [start quest] consequence. No-ops (returns true) if the quest is
     * already active; refuses to restart a completed non-repeatable quest.
     */
    public static function startQuest($conn, $id_user_ig, $id_quest, $LANG)
    {
        $quest = self::fetchQuest($conn, $id_quest);

        if (!$quest)
        {
            error_log('[QUESTS] startQuest: unknown quest ' . (int) $id_quest);
            return false;
        }

        $user_quest = self::fetchUserQuest($conn, $id_user_ig, $id_quest);

        if ($user_quest)
        {
            if ((string) $user_quest['flg_completed'] !== 'S')
            {
                return true;
            }

            if ((string) $quest['repeatable'] !== 'S')
            {
                return false;
            }

            self::resetObjectiveProgress($conn, $id_user_ig, $id_quest);

            $stmt = $conn->prepare('
                UPDATE user_quests
                SET phase = 1, flg_completed = \'N\', dt_completed = NULL, dt_m = NOW()
                WHERE id_user_quest = :id_user_quest
            ');
            $stmt->execute([':id_user_quest' => (int) $user_quest['id_user_quest']]);
        }
        else
        {
            $stmt = $conn->prepare('
                INSERT INTO user_quests (id_user_ig, id_quest, phase, flg_completed, dt_c, dt_m)
                VALUES (:id_user_ig, :id_quest, 1, \'N\', NOW(), NOW())
            ');
            $stmt->execute([
                ':id_user_ig' => (int) $id_user_ig,
                ':id_quest' => (int) $id_quest,
            ]);
        }

        FUNZIONI::AddNotification($conn, $id_user_ig, self::questStartedText($quest, $LANG), 'quest_start');

        // Covers objectives already satisfied at accept time (e.g. a "reach
        // level 2" objective when the player is already level 5).
        self::refreshProgress($conn, $id_user_ig, $LANG);

        return true;
    }

    /**
     * [complete quest] consequence. Requires the quest to be ready to turn
     * in; reward items/buffs are separate consequences on the same dialog
     * option, per the existing conversation_consequences pattern.
     */
    public static function completeQuest($conn, $id_user_ig, $id_quest, $LANG)
    {
        if (!self::isReadyToTurnIn($conn, $id_user_ig, $id_quest))
        {
            error_log('[QUESTS] completeQuest: quest ' . (int) $id_quest . ' not ready to turn in for user ' . (int) $id_user_ig);
            return false;
        }

        $quest = self::fetchQuest($conn, $id_quest);

        $stmt = $conn->prepare('
            UPDATE user_quests
            SET flg_completed = \'S\', dt_completed = NOW(), dt_m = NOW()
            WHERE id_user_ig = :id_user_ig AND id_quest = :id_quest
        ');
        $stmt->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':id_quest' => (int) $id_quest,
        ]);

        FUNZIONI::AddNotification($conn, $id_user_ig, self::questCompletedText($quest, $LANG), 'quest_complete');

        return true;
    }

    private static function resetObjectiveProgress($conn, $id_user_ig, $id_quest)
    {
        $stmt = $conn->prepare('
            DELETE UQOP FROM user_quest_objective_progress UQOP
            JOIN quest_objectives QO ON QO.id_quest_objective = UQOP.id_quest_objective
            WHERE UQOP.id_user_ig = :id_user_ig AND QO.id_quest = :id_quest
        ');
        $stmt->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':id_quest' => (int) $id_quest,
        ]);
    }

    /**
     * Hook: a wild animal was defeated (solo PvE win, or party PvE win per
     * rewarded member). Bumps any matching kill_species counters for the
     * user's active quests, then re-evaluates progress.
     */
    public static function onWildDefeated($conn, $id_user_ig, $id_species, $LANG)
    {
        $active = self::fetchActiveUserQuests($conn, $id_user_ig);

        foreach ($active as $user_quest)
        {
            $objectives = self::fetchPhaseObjectives($conn, $user_quest['id_quest'], $user_quest['phase']);

            foreach ($objectives as $objective)
            {
                if ($objective['objective_type'] !== self::OBJECTIVE_KILL_SPECIES
                    || (int) $objective['target_ref'] !== (int) $id_species)
                {
                    continue;
                }

                self::incrementObjectiveProgress($conn, $id_user_ig, (int) $objective['id_quest_objective']);
            }
        }

        self::refreshProgress($conn, $id_user_ig, $LANG);
    }

    /**
     * Hook: a (register-able) conversation was just marked finished for
     * this player. talk_npc objectives are live-checked against
     * user_conversations, so this only needs to trigger a re-evaluation.
     */
    public static function onConversationFinished($conn, $id_user_ig, $id_conversation, $LANG)
    {
        self::refreshProgress($conn, $id_user_ig, $LANG);
    }

    /**
     * Hook: the player's level changed (AdjustUserLvlFromExp).
     */
    public static function onLevelChanged($conn, $id_user_ig, $LANG)
    {
        self::refreshProgress($conn, $id_user_ig, $LANG);
    }

    /**
     * Hook: the player's inventory changed (item obtained via drop or
     * dialog reward). collect_item objectives are live-checked, so this
     * only needs to trigger a re-evaluation.
     */
    public static function onInventoryChanged($conn, $id_user_ig, $LANG)
    {
        self::refreshProgress($conn, $id_user_ig, $LANG);
    }

    private static function incrementObjectiveProgress($conn, $id_user_ig, $id_quest_objective)
    {
        $stmt = $conn->prepare('
            INSERT INTO user_quest_objective_progress (id_user_ig, id_quest_objective, progress_count, dt_c, dt_m)
            VALUES (:id_user_ig, :id_quest_objective, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                progress_count = progress_count + 1,
                dt_m = NOW()
        ');
        $stmt->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':id_quest_objective' => (int) $id_quest_objective,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchActiveUserQuests($conn, $id_user_ig)
    {
        $stmt = $conn->prepare('
            SELECT * FROM user_quests
            WHERE id_user_ig = :id_user_ig AND flg_completed = \'N\'
        ');
        $stmt->execute([':id_user_ig' => (int) $id_user_ig]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Re-evaluates every active quest for a user: advances phase whenever
     * every objective of the current phase is met (looping in case the new
     * phase is already satisfied too, e.g. a reach_level objective the
     * player already qualifies for), and notifies on phase advance / ready
     * to turn in.
     */
    public static function refreshProgress($conn, $id_user_ig, $LANG)
    {
        $active = self::fetchActiveUserQuests($conn, $id_user_ig);

        foreach ($active as $user_quest)
        {
            $id_quest = (int) $user_quest['id_quest'];
            $phase = (int) $user_quest['phase'];
            $max_phase = self::maxPhase($conn, $id_quest);

            if ($phase > $max_phase)
            {
                continue;
            }

            $quest = self::fetchQuest($conn, $id_quest);
            $advanced = false;

            while ($phase <= $max_phase)
            {
                $objectives = self::fetchPhaseObjectives($conn, $id_quest, $phase);

                if (empty($objectives))
                {
                    break;
                }

                $all_met = true;

                foreach ($objectives as $objective)
                {
                    $progress = self::getObjectiveProgress($conn, $id_user_ig, $objective);

                    if (!$progress['complete'])
                    {
                        $all_met = false;
                        break;
                    }
                }

                if (!$all_met)
                {
                    break;
                }

                $phase++;
                $advanced = true;
            }

            if (!$advanced)
            {
                continue;
            }

            $stmt = $conn->prepare('
                UPDATE user_quests SET phase = :phase, dt_m = NOW() WHERE id_user_quest = :id_user_quest
            ');
            $stmt->execute([
                ':phase' => $phase,
                ':id_user_quest' => (int) $user_quest['id_user_quest'],
            ]);

            if ($phase > $max_phase)
            {
                FUNZIONI::AddNotification($conn, $id_user_ig, self::questReadyToTurnInText($quest, $LANG), 'quest_progress');
            }
            else
            {
                FUNZIONI::AddNotification($conn, $id_user_ig, self::questPhaseAdvancedText($quest, $LANG), 'quest_progress');
            }
        }
    }

    /**
     * @param array<string, mixed> $objective
     * @return array{count:int, target:int, complete:bool}
     */
    public static function getObjectiveProgress($conn, $id_user_ig, array $objective)
    {
        $target = max(1, (int) $objective['target_count']);
        $count = 0;

        switch ($objective['objective_type'])
        {
            case self::OBJECTIVE_KILL_SPECIES:
                $stmt = $conn->prepare('
                    SELECT progress_count FROM user_quest_objective_progress
                    WHERE id_user_ig = :id_user_ig AND id_quest_objective = :id_quest_objective
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_user_ig' => (int) $id_user_ig,
                    ':id_quest_objective' => (int) $objective['id_quest_objective'],
                ]);
                $count = (int) $stmt->fetchColumn();
                break;

            case self::OBJECTIVE_COLLECT_ITEM:
                $stmt = $conn->prepare('
                    SELECT COUNT(*) FROM items
                    WHERE id_user_ig = :id_user_ig AND id_item_type = :id_item_type AND dt_used IS NULL
                ');
                $stmt->execute([
                    ':id_user_ig' => (int) $id_user_ig,
                    ':id_item_type' => (int) $objective['target_ref'],
                ]);
                $count = (int) $stmt->fetchColumn();
                break;

            case self::OBJECTIVE_TALK_NPC:
                if (!class_exists('PLAYER_CONVERSATIONS'))
                {
                    require_once __DIR__ . '/player_conversations.php';
                }

                $count = PLAYER_CONVERSATIONS::isFinished($conn, $id_user_ig, (int) $objective['target_ref']) ? $target : 0;
                break;

            case self::OBJECTIVE_REACH_LEVEL:
                $stmt = $conn->prepare('SELECT level FROM users_ig WHERE id_user_ig = :id_user_ig LIMIT 1');
                $stmt->execute([':id_user_ig' => (int) $id_user_ig]);
                $count = (int) $stmt->fetchColumn();
                break;

            default:
                error_log('[QUESTS] unknown objective_type: ' . $objective['objective_type']);
                break;
        }

        return [
            'count' => $count,
            'target' => $target,
            'complete' => $count >= $target,
        ];
    }

    /**
     * Full quest log for the tracker UI/panel: every quest the player has
     * ever started (active or completed), each with its current phase's
     * objectives and live progress.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fetchQuestLog($conn, $id_user_ig, $LANG)
    {
        $lang_suffix = self::normalizeLangSuffix($LANG);

        $stmt = $conn->prepare('
            SELECT UQ.*,
                   Q.quest AS quest_name,
                   Q.quest' . $lang_suffix . ' AS quest_name_lang,
                   Q.description AS quest_description,
                   Q.description' . $lang_suffix . ' AS quest_description_lang,
                   Q.repeatable,
                   Q.id_starter_npc
            FROM user_quests UQ
            JOIN quests Q ON Q.id_quest = UQ.id_quest
            WHERE UQ.id_user_ig = :id_user_ig
            ORDER BY UQ.flg_completed ASC, UQ.dt_c DESC
        ');
        $stmt->execute([':id_user_ig' => (int) $id_user_ig]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $log = [];

        foreach ($rows as $row)
        {
            $id_quest = (int) $row['id_quest'];
            $max_phase = self::maxPhase($conn, $id_quest);
            $phase = (int) $row['phase'];
            $flg_completed = (string) $row['flg_completed'] === 'S';
            $ready_to_turn_in = !$flg_completed && $phase > $max_phase;

            $objectives = [];

            if (!$flg_completed && !$ready_to_turn_in)
            {
                foreach (self::fetchPhaseObjectives($conn, $id_quest, $phase) as $objective)
                {
                    $progress = self::getObjectiveProgress($conn, $id_user_ig, $objective);

                    $objectives[] = [
                        'objective_type' => $objective['objective_type'],
                        'target_ref' => $objective['target_ref'] !== null ? (int) $objective['target_ref'] : null,
                        'target_name' => self::resolveTargetName($conn, $objective, $lang_suffix),
                        'description' => self::pickLang($objective, 'description', $lang_suffix),
                        'count' => min($progress['count'], $progress['target']),
                        'target' => $progress['target'],
                        'complete' => $progress['complete'],
                    ];
                }
            }

            $log[] = [
                'id_user_quest' => (int) $row['id_user_quest'],
                'id_quest' => $id_quest,
                'name' => $row['quest_name_lang'] ?: $row['quest_name'],
                'description' => $row['quest_description_lang'] ?: $row['quest_description'],
                'id_starter_npc' => (int) $row['id_starter_npc'],
                'repeatable' => (string) $row['repeatable'] === 'S',
                'phase' => $phase,
                'max_phase' => $max_phase,
                'flg_completed' => $flg_completed,
                'ready_to_turn_in' => $ready_to_turn_in,
                'objectives' => $objectives,
            ];
        }

        return $log;
    }

    /**
     * @param array<string, mixed> $objective
     */
    private static function resolveTargetName($conn, array $objective, $lang_suffix)
    {
        $target_ref = $objective['target_ref'] !== null ? (int) $objective['target_ref'] : 0;

        if ($target_ref <= 0)
        {
            return null;
        }

        if ($objective['objective_type'] === self::OBJECTIVE_KILL_SPECIES)
        {
            $stmt = $conn->prepare('SELECT species' . $lang_suffix . ' AS name FROM species WHERE id_species = :id LIMIT 1');
            $stmt->execute([':id' => $target_ref]);

            return (string) $stmt->fetchColumn();
        }

        if ($objective['objective_type'] === self::OBJECTIVE_COLLECT_ITEM)
        {
            $stmt = $conn->prepare('SELECT nome' . $lang_suffix . ' AS name FROM item_types WHERE id_item_type = :id LIMIT 1');
            $stmt->execute([':id' => $target_ref]);

            return (string) $stmt->fetchColumn();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function pickLang(array $row, $base_field, $lang_suffix)
    {
        if ($lang_suffix !== '' && !empty($row[$base_field . $lang_suffix]))
        {
            return $row[$base_field . $lang_suffix];
        }

        return $row[$base_field] ?? null;
    }

    private static function normalizeLangSuffix($LANG)
    {
        if ($LANG === '_it' || $LANG === '_pt')
        {
            return $LANG;
        }

        return '';
    }

    /**
     * @param array<string, mixed>|null $quest
     */
    private static function questName($quest, $LANG)
    {
        if (!$quest)
        {
            return 'quest';
        }

        $lang_suffix = self::normalizeLangSuffix($LANG);

        if ($lang_suffix !== '' && !empty($quest['quest' . $lang_suffix]))
        {
            return $quest['quest' . $lang_suffix];
        }

        return $quest['quest'] ?: 'quest';
    }

    private static function questStartedText($quest, $LANG)
    {
        $name = self::questName($quest, $LANG);

        if ($LANG === '_it')
        {
            return 'Nuova missione: ' . $name;
        }

        if ($LANG === '_pt')
        {
            return 'Nova missao: ' . $name;
        }

        return 'New quest: ' . $name;
    }

    private static function questPhaseAdvancedText($quest, $LANG)
    {
        $name = self::questName($quest, $LANG);

        if ($LANG === '_it')
        {
            return 'Progressi nella missione: ' . $name;
        }

        if ($LANG === '_pt')
        {
            return 'Progresso na missao: ' . $name;
        }

        return 'Quest progress: ' . $name;
    }

    private static function questReadyToTurnInText($quest, $LANG)
    {
        $name = self::questName($quest, $LANG);

        if ($LANG === '_it')
        {
            return 'Missione pronta per la consegna: ' . $name;
        }

        if ($LANG === '_pt')
        {
            return 'Missao pronta para entregar: ' . $name;
        }

        return 'Quest ready to turn in: ' . $name;
    }

    private static function questCompletedText($quest, $LANG)
    {
        $name = self::questName($quest, $LANG);

        if ($LANG === '_it')
        {
            return 'Missione completata: ' . $name;
        }

        if ($LANG === '_pt')
        {
            return 'Missao concluida: ' . $name;
        }

        return 'Quest completed: ' . $name;
    }
}
