<?php

/**
 * Buff / debuff system.
 *
 * - Time-based buffs: entity_buffs (UTC timestamps, layered by dt_applied_utc).
 * - Turn-based buffs: battle_turn_buffs (battle-scoped, layered by turn + order).
 * - Battle start: time-based buffs on animal + owner user_ig are merged and applied to turn-0 stats.
 */
class BUFFS
{
    const STAT_KEYS = ['atk', 'def', 'matk', 'mdef', 'spd', 'acc', 'eva', 'cr', 'hp', 'max_hp'];

    public static function serverTimezone()
    {
        if (defined('ANIMASTER_SERVER_TIMEZONE'))
        {
            return ANIMASTER_SERVER_TIMEZONE;
        }

        return 'UTC';
    }

    public static function nowUtc()
    {
        $tz = new DateTimeZone(self::serverTimezone());
        $dt = new DateTime('now', $tz);

        return $dt->format('Y-m-d H:i:s');
    }

    public static function secondsUntilUtc($dt_expires_utc)
    {
        if ($dt_expires_utc === null || $dt_expires_utc === '')
        {
            return 0;
        }

        $tz = new DateTimeZone(self::serverTimezone());
        $expires = DateTime::createFromFormat('Y-m-d H:i:s', (string) $dt_expires_utc, $tz);

        if (!$expires)
        {
            return 0;
        }

        $now = new DateTime('now', $tz);

        return max(0, (int) $expires->getTimestamp() - (int) $now->getTimestamp());
    }

    public static function purgeExpired($conn)
    {
        $stmt = $conn->prepare('
            DELETE FROM entity_buffs
            WHERE dt_expires_utc <= :now_utc
        ');
        $stmt->execute([':now_utc' => self::nowUtc()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchActiveTimeLayers($conn, $entity_type, $id_entity, $include_definition = true)
    {
        self::purgeExpired($conn);

        $entity_type = (string) $entity_type;
        $id_entity = (int) $id_entity;

        if ($id_entity <= 0)
        {
            return [];
        }

        $sql = '
            SELECT EB.id_entity_buff, EB.entity_type, EB.id_entity,
                   EB.dt_applied_utc, EB.dt_expires_utc, EB.source_type, EB.source_id,
                   BD.id_buff_definition, BD.buff_code, BD.stat_key, BD.modifier_kind,
                   BD.modifier_value, BD.is_debuff, BD.target_entity
        ';

        if ($include_definition)
        {
            $sql .= ',
                   BD.name, BD.name_it, BD.name_pt,
                   BD.description, BD.description_it, BD.description_pt
            ';
        }

        $sql .= '
            FROM entity_buffs EB
            INNER JOIN buff_definitions BD ON BD.id_buff_definition = EB.id_buff_definition
            WHERE EB.entity_type = :entity_type
              AND EB.id_entity = :id_entity
              AND EB.dt_expires_utc > :now_utc
              AND BD.flg_active = \'S\'
            ORDER BY EB.dt_applied_utc ASC, EB.id_entity_buff ASC
        ';

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':entity_type' => $entity_type,
            ':id_entity' => $id_entity,
            ':now_utc' => self::nowUtc(),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Layers for battle start: animal buffs + party (user_ig) buffs, ordered by start time.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fetchBattleStartTimeLayers($conn, $id_animal, $id_user_ig)
    {
        $layers = [];

        foreach (self::fetchActiveTimeLayers($conn, 'animal', $id_animal) as $row)
        {
            $row['scope'] = 'animal';
            $layers[] = $row;
        }

        foreach (self::fetchActiveTimeLayers($conn, 'user_ig', $id_user_ig) as $row)
        {
            $row['scope'] = 'party';
            $layers[] = $row;
        }

        usort($layers, function ($a, $b)
        {
            $cmp = strcmp((string) $a['dt_applied_utc'], (string) $b['dt_applied_utc']);

            if ($cmp !== 0)
            {
                return $cmp;
            }

            return (int) $a['id_entity_buff'] <=> (int) $b['id_entity_buff'];
        });

        return $layers;
    }

    /**
     * @param array<string, int|float> $stats
     * @param array<int, array<string, mixed>> $layers
     * @return array<string, int|float>
     */
    public static function applyLayersToStats(array $stats, array $layers)
    {
        foreach ($layers as $layer)
        {
            $stat_key = (string) ($layer['stat_key'] ?? '');

            if (!in_array($stat_key, self::STAT_KEYS, true) || !array_key_exists($stat_key, $stats))
            {
                continue;
            }

            $value = (float) ($layer['modifier_value'] ?? 0);
            $is_debuff = (string) ($layer['is_debuff'] ?? 'N') === 'S';
            $magnitude = abs($value);
            $signed = $is_debuff ? -$magnitude : $magnitude;
            $kind = (string) ($layer['modifier_kind'] ?? 'percent');

            if ($kind === 'flat')
            {
                $stats[$stat_key] = (int) round((float) $stats[$stat_key] + $signed);
            }
            else
            {
                $stats[$stat_key] = (int) round((float) $stats[$stat_key] * (1 + ($signed / 100)));
            }

            if ($stat_key === 'hp' || $stat_key === 'max_hp')
            {
                $stats[$stat_key] = max(1, (int) $stats[$stat_key]);
            }
            elseif (in_array($stat_key, ['atk', 'def', 'matk', 'mdef', 'spd', 'acc', 'eva', 'cr'], true))
            {
                $stats[$stat_key] = max(0, (int) $stats[$stat_key]);
            }
        }

        if (isset($stats['max_hp'], $stats['hp']) && (int) $stats['hp'] > (int) $stats['max_hp'])
        {
            $stats['hp'] = (int) $stats['max_hp'];
        }

        return $stats;
    }

    /**
     * Apply active time-based buffs before battle turn 0 is written.
     *
     * @param array<string, int|float> $stats
     * @return array<string, int|float>
     */
    public static function applyAtBattleStart($conn, $id_animal, $id_user_ig, array $stats)
    {
        $layers = self::fetchBattleStartTimeLayers($conn, (int) $id_animal, (int) $id_user_ig);

        return self::applyLayersToStats($stats, $layers);
    }

    /**
     * Apply time-based and battle-scoped turn buffs when an animal enters active combat
     * (battle start or mid-battle switch).
     *
     * @param array<string, int|float> $stats
     * @return array<string, int|float>
     */
    public static function applyForActiveBattleAnimal($conn, $battle_type, $id_battle, $id_animal, $id_user_ig, array $stats)
    {
        $stats = self::applyAtBattleStart($conn, (int) $id_animal, (int) $id_user_ig, $stats);

        $id_battle = (int) $id_battle;

        if ($id_battle <= 0 || $battle_type === '')
        {
            return $stats;
        }

        $stats = self::applyBattleTurnLayersToStats($conn, $battle_type, $id_battle, 'animal', (int) $id_animal, $stats);
        $stats = self::applyBattleTurnLayersToStats($conn, $battle_type, $id_battle, 'user_ig', (int) $id_user_ig, $stats);

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchDisplayForAnimal($conn, $id_animal, $id_user_ig, $lang = '')
    {
        $lang_suffix = self::normalizeLangSuffix($lang);
        $list = [];

        foreach (self::fetchBattleStartTimeLayers($conn, (int) $id_animal, (int) $id_user_ig) as $layer)
        {
            $name = (string) ($layer['name' . $lang_suffix] ?? $layer['name'] ?? $layer['buff_code']);
            $description = (string) ($layer['description' . $lang_suffix] ?? $layer['description'] ?? '');
            $expires = (string) $layer['dt_expires_utc'];

            $list[] = [
                'id_entity_buff' => (int) $layer['id_entity_buff'],
                'buff_code' => (string) $layer['buff_code'],
                'name' => $name,
                'description' => $description,
                'is_debuff' => (string) ($layer['is_debuff'] ?? 'N'),
                'stat_key' => (string) $layer['stat_key'],
                'modifier_kind' => (string) $layer['modifier_kind'],
                'modifier_value' => (float) $layer['modifier_value'],
                'duration_type' => 'time',
                'scope' => (string) ($layer['scope'] ?? 'animal'),
                'expires_utc' => $expires,
                'seconds_remaining' => self::secondsUntilUtc($expires),
                'server_timezone' => self::serverTimezone(),
            ];
        }

        return $list;
    }

    public static function grantTimeBuff($conn, $id_buff_definition, $entity_type, $id_entity, $duration_seconds, $source_type = null, $source_id = null)
    {
        $id_buff_definition = (int) $id_buff_definition;
        $id_entity = (int) $id_entity;
        $duration_seconds = max(1, (int) $duration_seconds);

        if ($id_buff_definition <= 0 || $id_entity <= 0)
        {
            return false;
        }

        $now = self::nowUtc();
        $tz = new DateTimeZone(self::serverTimezone());
        $expires_dt = DateTime::createFromFormat('Y-m-d H:i:s', $now, $tz);
        $expires_dt->modify('+' . $duration_seconds . ' seconds');
        $expires = $expires_dt->format('Y-m-d H:i:s');

        $stmt = $conn->prepare('
            INSERT INTO entity_buffs
                (id_buff_definition, entity_type, id_entity, dt_applied_utc, dt_expires_utc, source_type, source_id)
            VALUES
                (:id_buff_definition, :entity_type, :id_entity, :dt_applied_utc, :dt_expires_utc, :source_type, :source_id)
        ');

        return $stmt->execute([
            ':id_buff_definition' => $id_buff_definition,
            ':entity_type' => (string) $entity_type,
            ':id_entity' => $id_entity,
            ':dt_applied_utc' => $now,
            ':dt_expires_utc' => $expires,
            ':source_type' => $source_type !== null ? (string) $source_type : null,
            ':source_id' => $source_id !== null ? (int) $source_id : null,
        ]);
    }

    /**
     * Turn-based buff for the current battle only.
     */
    public static function grantBattleTurnBuff($conn, $battle_type, $id_battle, $entity_type, $id_entity, $id_buff_definition, $turns, $applied_at_turn = 0, $applied_order = 0)
    {
        $turns = max(1, (int) $turns);

        $stmt = $conn->prepare('
            INSERT INTO battle_turn_buffs
            (
                battle_type, id_battle, entity_type, id_entity, id_buff_definition,
                applied_at_turn, applied_order, turns_total, turns_remaining, dt_applied_utc
            )
            VALUES
            (
                :battle_type, :id_battle, :entity_type, :id_entity, :id_buff_definition,
                :applied_at_turn, :applied_order, :turns_total, :turns_remaining, :dt_applied_utc
            )
        ');

        return $stmt->execute([
            ':battle_type' => (string) $battle_type,
            ':id_battle' => (int) $id_battle,
            ':entity_type' => (string) $entity_type,
            ':id_entity' => (int) $id_entity,
            ':id_buff_definition' => (int) $id_buff_definition,
            ':applied_at_turn' => (int) $applied_at_turn,
            ':applied_order' => (int) $applied_order,
            ':turns_total' => $turns,
            ':turns_remaining' => $turns,
            ':dt_applied_utc' => self::nowUtc(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchActiveBattleTurnLayers($conn, $battle_type, $id_battle, $entity_type, $id_entity)
    {
        $stmt = $conn->prepare('
            SELECT BTB.*, BD.stat_key, BD.modifier_kind, BD.modifier_value, BD.is_debuff, BD.buff_code
            FROM battle_turn_buffs BTB
            INNER JOIN buff_definitions BD ON BD.id_buff_definition = BTB.id_buff_definition
            WHERE BTB.battle_type = :battle_type
              AND BTB.id_battle = :id_battle
              AND BTB.entity_type = :entity_type
              AND BTB.id_entity = :id_entity
              AND BTB.turns_remaining > 0
              AND BD.flg_active = \'S\'
            ORDER BY BTB.applied_at_turn ASC, BTB.applied_order ASC, BTB.id_battle_turn_buff ASC
        ');
        $stmt->execute([
            ':battle_type' => (string) $battle_type,
            ':id_battle' => (int) $id_battle,
            ':entity_type' => (string) $entity_type,
            ':id_entity' => (int) $id_entity,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function applyBattleTurnLayersToStats($conn, $battle_type, $id_battle, $entity_type, $id_entity, array $stats)
    {
        $layers = self::fetchActiveBattleTurnLayers($conn, $battle_type, $id_battle, $entity_type, $id_entity);

        return self::applyLayersToStats($stats, $layers);
    }

    public static function onSoloPveBattleEnd($conn, $id_battle)
    {
        self::clearBattleTurnBuffs($conn, 'solo_pve', (int) $id_battle);
    }

    public static function tickBattleTurnBuffs($conn, $battle_type, $id_battle)
    {
        $stmt = $conn->prepare('
            UPDATE battle_turn_buffs
            SET turns_remaining = GREATEST(0, turns_remaining - 1)
            WHERE battle_type = :battle_type
              AND id_battle = :id_battle
              AND turns_remaining > 0
        ');
        $stmt->execute([
            ':battle_type' => (string) $battle_type,
            ':id_battle' => (int) $id_battle,
        ]);

        self::clearExpiredBattleTurnBuffs($conn, $battle_type, $id_battle);
    }

    public static function clearBattleTurnBuffs($conn, $battle_type, $id_battle)
    {
        $stmt = $conn->prepare('
            DELETE FROM battle_turn_buffs
            WHERE battle_type = :battle_type
              AND id_battle = :id_battle
        ');
        $stmt->execute([
            ':battle_type' => (string) $battle_type,
            ':id_battle' => (int) $id_battle,
        ]);
    }

    public static function clearExpiredBattleTurnBuffs($conn, $battle_type, $id_battle)
    {
        $stmt = $conn->prepare('
            DELETE FROM battle_turn_buffs
            WHERE battle_type = :battle_type
              AND id_battle = :id_battle
              AND turns_remaining <= 0
        ');
        $stmt->execute([
            ':battle_type' => (string) $battle_type,
            ':id_battle' => (int) $id_battle,
        ]);
    }

    private static function normalizeLangSuffix($lang)
    {
        if ($lang === '_it' || $lang === '_pt')
        {
            return $lang;
        }

        if ($lang === 'it' || $lang === 'pt')
        {
            return '_' . $lang;
        }

        return '';
    }
}
