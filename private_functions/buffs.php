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

    /**
     * Parse comma-separated stat_key / modifier_value pairs from a buff definition.
     *
     * @return array<int, array{stat_key:string, modifier_value:float}>
     */
    public static function parseStatModifiers($stat_key, $modifier_value)
    {
        $keys = array_map('trim', explode(',', (string) $stat_key));
        $values = array_map('trim', explode(',', (string) $modifier_value));
        $pairs = [];

        foreach ($keys as $index => $key)
        {
            $key = strtolower($key);

            if ($key === '' || !in_array($key, self::STAT_KEYS, true))
            {
                continue;
            }

            $value_raw = $values[$index] ?? '';

            if ($value_raw === '')
            {
                $value_raw = $values[0] ?? '0';
            }

            $pairs[] = [
                'stat_key' => $key,
                'modifier_value' => (float) $value_raw,
            ];
        }

        return $pairs;
    }

    /**
     * Whether a buff definition touches a combat stat (supports multi-stat CSV keys).
     */
    public static function buffAffectsStat($stat_key, $target_stat)
    {
        $target_stat = strtolower((string) $target_stat);

        foreach (self::parseStatModifiers($stat_key, '0') as $pair)
        {
            if ($pair['stat_key'] === $target_stat)
            {
                return true;
            }
        }

        return false;
    }

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
                   BD.modifier_value, BD.is_debuff, BD.target_entity, BD.icon, BD.tier
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
            $is_debuff = (string) ($layer['is_debuff'] ?? 'N') === 'S';
            $kind = (string) ($layer['modifier_kind'] ?? 'percent');

            foreach (self::parseStatModifiers($layer['stat_key'] ?? '', $layer['modifier_value'] ?? '') as $pair)
            {
                $stat_key = $pair['stat_key'];

                if (!array_key_exists($stat_key, $stats))
                {
                    continue;
                }

                $value = (float) $pair['modifier_value'];
                $magnitude = abs($value);
                $signed = $is_debuff ? -$magnitude : $magnitude;

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

            $effectRow = [
                'stat_key' => (string) $layer['stat_key'],
                'modifier_value' => (string) $layer['modifier_value'],
                'is_debuff' => (string) ($layer['is_debuff'] ?? 'N'),
                'modifier_kind' => (string) ($layer['modifier_kind'] ?? 'percent'),
            ];

            $list[] = [
                'id_entity_buff' => (int) $layer['id_entity_buff'],
                'buff_code' => (string) $layer['buff_code'],
                'name' => $name,
                'description' => $description,
                'is_debuff' => (string) ($layer['is_debuff'] ?? 'N'),
                'stat_key' => (string) $layer['stat_key'],
                'modifier_kind' => (string) $layer['modifier_kind'],
                'modifier_value' => (string) $layer['modifier_value'],
                'icon' => (string) ($layer['icon'] ?? ''),
                'tier' => self::normalizeBuffTier($layer['tier'] ?? 0),
                'total_effect_label' => self::computeGroupedEffectLabel([$effectRow]),
                'duration_type' => 'time',
                'scope' => (string) ($layer['scope'] ?? 'animal'),
                'expires_utc' => $expires,
                'seconds_remaining' => self::secondsUntilUtc($expires),
                'server_timezone' => self::serverTimezone(),
            ];
        }

        return $list;
    }

    /**
     * Level-scaled stats from base / DNA / stat pts / EXP pts (no buffs).
     *
     * @param array<string, mixed> $row animals row joined with species base_* columns
     * @return array<string, int>
     */
    public static function computeAnimalLevelStats(array $row)
    {
        $lvl = max(1, (int) ($row['lvl'] ?? 1));

        $scaled = function ($suffix, $bonus = 5) use ($row, $lvl)
        {
            $base = (float) ($row['base_' . $suffix] ?? 0);
            $dna = (float) ($row['dna_' . $suffix] ?? 0);
            $pt = floor(0.25 * (float) ($row['pt_' . $suffix] ?? 0));
            $xp = floor(0.25 * (float) ($row['xp_' . $suffix] ?? 0));

            return (int) floor(0.01 * (2 * $base + $dna + $pt + $xp) * $lvl) + $bonus;
        };

        $max_hp = (int) floor(0.01 * (
            2 * (float) ($row['base_hp'] ?? 0)
            + (float) ($row['dna_hp'] ?? 0)
            + floor(0.25 * (float) ($row['pt_hp'] ?? 0))
            + floor(0.25 * (float) ($row['xp_hp'] ?? 0))
        ) * $lvl) + $lvl + 10;

        $current_hp = (int) ($row['current_hp'] ?? $max_hp);

        if ($current_hp < 0)
        {
            $current_hp = 0;
        }

        return [
            'hp' => $current_hp,
            'max_hp' => max(1, $max_hp),
            'atk' => $scaled('atk'),
            'def' => $scaled('def'),
            'matk' => $scaled('matk'),
            'mdef' => $scaled('mdef'),
            'spd' => $scaled('spd'),
            'acc' => (int) ($row['base_acc'] ?? 0),
            'eva' => (int) ($row['base_eva'] ?? 0),
            'cr' => (int) ($row['base_cr'] ?? 0),
        ];
    }

    /**
     * Team panel stat sheet: level stats as base, time buffs applied as effective.
     *
     * @param array<string, mixed> $animalRow
     * @return array<int, array<string, mixed>>
     */
    public static function fetchTeamCurrentStatSheet($conn, array $animalRow, $lang = '')
    {
        $id_animal = (int) ($animalRow['id_animal'] ?? 0);
        $id_user_ig = (int) ($animalRow['id_user_ig'] ?? 0);

        if ($id_animal <= 0)
        {
            return [];
        }

        $base_stats = self::computeAnimalLevelStats($animalRow);

        return self::fetchCombatStatSheet(
            $conn,
            '',
            0,
            'animal',
            $id_animal,
            $id_user_ig > 0 ? $id_user_ig : null,
            $base_stats,
            $lang
        );
    }

    /**
     * Animal row joined with species base stats (for level HP recompute).
     *
     * @return array<string, mixed>|null
     */
    public static function fetchAnimalLevelRow($conn, $id_animal)
    {
        $id_animal = (int) $id_animal;

        if ($id_animal <= 0)
        {
            return null;
        }

        $stmt = $conn->prepare('
            SELECT A.*,
                   L.base_hp, L.base_atk, L.base_def, L.base_matk, L.base_mdef, L.base_spd,
                   L.base_acc, L.base_eva, L.base_cr
            FROM animals A
            INNER JOIN species L ON L.id_species = A.id_species
            WHERE A.id_animal = :id_animal
            LIMIT 1
        ');
        $stmt->execute([':id_animal' => $id_animal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{base_max_hp:int,effective_max_hp:int}
     */
    public static function computeAnimalHpCaps($conn, array $row)
    {
        $level_stats = self::computeAnimalLevelStats($row);
        $id_animal = (int) ($row['id_animal'] ?? 0);
        $id_user_ig = (int) ($row['id_user_ig'] ?? 0);
        $effective = self::computeEffectiveStats(
            $conn,
            '',
            0,
            'animal',
            $id_animal,
            $id_user_ig > 0 ? $id_user_ig : null,
            $level_stats
        );

        return [
            'base_max_hp' => (int) $level_stats['max_hp'],
            'effective_max_hp' => (int) ($effective['max_hp'] ?? $level_stats['max_hp']),
        ];
    }

    /**
     * Persist canonical base max_hp; optionally sync current_hp.
     */
    public static function persistAnimalHp($conn, $id_animal, $base_max_hp, $current_hp = null)
    {
        $id_animal = (int) $id_animal;
        $base_max_hp = max(1, (int) $base_max_hp);

        if ($id_animal <= 0)
        {
            return;
        }

        if ($current_hp === null)
        {
            $stmt = $conn->prepare('
                UPDATE animals
                SET max_hp = :max_hp
                WHERE id_animal = :id_animal
            ');
            $stmt->execute([
                ':max_hp' => $base_max_hp,
                ':id_animal' => $id_animal,
            ]);

            return;
        }

        $stmt = $conn->prepare('
            UPDATE animals
            SET max_hp = :max_hp,
                current_hp = :current_hp
            WHERE id_animal = :id_animal
        ');
        $stmt->execute([
            ':max_hp' => $base_max_hp,
            ':current_hp' => max(0, (int) $current_hp),
            ':id_animal' => $id_animal,
        ]);
    }

    /**
     * After combat or overworld sync: store battle current_hp, never buffed max_hp.
     */
    public static function persistAnimalHpAfterBattle($conn, $id_animal, $current_hp)
    {
        $row = self::fetchAnimalLevelRow($conn, (int) $id_animal);

        if (!$row)
        {
            return;
        }

        $caps = self::computeAnimalHpCaps($conn, $row);
        $normalized_current = max(0, min((int) $current_hp, $caps['effective_max_hp']));

        self::persistAnimalHp(
            $conn,
            (int) $id_animal,
            $caps['base_max_hp'],
            $normalized_current
        );
    }

    /**
     * API/read path: expose base max_hp, effective cap, clamp current; repair stale DB rows.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeTeamAnimalHpRow($conn, array $row, $repair_db = true)
    {
        $id_animal = (int) ($row['id_animal'] ?? 0);
        $stored_max = (int) ($row['max_hp'] ?? 0);
        $stored_current = (int) ($row['current_hp'] ?? 0);
        $caps = self::computeAnimalHpCaps($conn, $row);
        $base_max = $caps['base_max_hp'];
        $effective_max = $caps['effective_max_hp'];
        $normalized_current = max(0, min($stored_current, $effective_max));

        $row['max_hp'] = $base_max;
        $row['effective_max_hp'] = $effective_max;
        $row['current_hp'] = $normalized_current;

        if ($repair_db && $id_animal > 0 && ($stored_max !== $base_max || $stored_current !== $normalized_current))
        {
            self::persistAnimalHp($conn, $id_animal, $base_max, $normalized_current);
        }

        return $row;
    }

    /**
     * Grant a time-based buff to the player's team according to buff_definitions.target_entity.
     * user_ig buffs apply party-wide at battle start; animal buffs are granted to each team slot.
     *
     * @return array{granted_count:int,buff_name:string,buff_code:string,target_entity:string}|false
     */
    public static function grantTeamTimeBuff($conn, $id_user_ig, $id_buff_definition, $duration_seconds, $source_type = null, $source_id = null, $alive_only = false)
    {
        $id_user_ig = (int) $id_user_ig;
        $id_buff_definition = (int) $id_buff_definition;
        $duration_seconds = max(1, (int) $duration_seconds);

        if ($id_user_ig <= 0 || $id_buff_definition <= 0)
        {
            return false;
        }

        $stmt = $conn->prepare('
            SELECT id_buff_definition, target_entity, buff_code, name, name_it, name_pt, flg_active
            FROM buff_definitions
            WHERE id_buff_definition = :id_buff_definition
            LIMIT 1
        ');
        $stmt->execute([':id_buff_definition' => $id_buff_definition]);
        $def = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$def || (string) ($def['flg_active'] ?? '') !== 'S')
        {
            return false;
        }

        $target_entity = strtolower(trim((string) ($def['target_entity'] ?? 'animal')));
        $granted_count = 0;

        if ($target_entity === 'user_ig')
        {
            if (self::grantTimeBuff($conn, $id_buff_definition, 'user_ig', $id_user_ig, $duration_seconds, $source_type, $source_id))
            {
                $granted_count = 1;
            }
        }
        else
        {
            $sql = '
                SELECT id_animal
                FROM animals
                WHERE id_user_ig = :id_user_ig
                  AND team_position > 0
                  AND team_position < 6
            ';

            if ($alive_only)
            {
                $sql .= ' AND current_hp > 0';
            }

            $sql .= ' ORDER BY team_position ASC';

            $stmt_team = $conn->prepare($sql);
            $stmt_team->execute([':id_user_ig' => $id_user_ig]);

            while ($animal = $stmt_team->fetch(PDO::FETCH_ASSOC))
            {
                $id_animal = (int) ($animal['id_animal'] ?? 0);

                if ($id_animal <= 0)
                {
                    continue;
                }

                if (self::grantTimeBuff($conn, $id_buff_definition, 'animal', $id_animal, $duration_seconds, $source_type, $source_id))
                {
                    $granted_count++;
                }
            }
        }

        if ($granted_count <= 0)
        {
            return false;
        }

        return [
            'granted_count' => $granted_count,
            'buff_name' => (string) ($def['name'] ?? $def['buff_code']),
            'buff_name_it' => (string) ($def['name_it'] ?? $def['name'] ?? $def['buff_code']),
            'buff_name_pt' => (string) ($def['name_pt'] ?? $def['name'] ?? $def['buff_code']),
            'buff_code' => (string) ($def['buff_code'] ?? ''),
            'target_entity' => $target_entity,
        ];
    }

    /**
     * Time-based buffs don't stack per stat_key: an active buff sharing the
     * new one's stat_key is replaced when the new buff's tier is >= the old
     * one's tier; otherwise the (stronger) old buff is kept and the new one
     * is rejected. Buffs on different stat_keys are unaffected (still layer).
     * Battle-scoped turn buffs (grantBattleTurnBuff) are untouched by this and
     * keep stacking freely.
     *
     * @param array<string, mixed> $new_def stat_key, tier (from buff_definitions)
     * @return bool true if the caller may proceed to insert the new buff
     */
    private static function reserveTimeBuffSlot($conn, $entity_type, $id_entity, array $new_def)
    {
        $new_stat_key = self::statKeySignature((string) ($new_def['stat_key'] ?? ''));

        if ($new_stat_key === '')
        {
            return true;
        }

        $new_tier = self::normalizeBuffTier($new_def['tier'] ?? 0);

        self::purgeExpired($conn);

        $stmt = $conn->prepare('
            SELECT EB.id_entity_buff, BD.stat_key, BD.tier
            FROM entity_buffs EB
            INNER JOIN buff_definitions BD ON BD.id_buff_definition = EB.id_buff_definition
            WHERE EB.entity_type = :entity_type
              AND EB.id_entity = :id_entity
              AND EB.dt_expires_utc > :now_utc
              AND BD.flg_active = \'S\'
        ');
        $stmt->execute([
            ':entity_type' => (string) $entity_type,
            ':id_entity' => (int) $id_entity,
            ':now_utc' => self::nowUtc(),
        ]);

        $superseded_ids = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row)
        {
            if (self::statKeySignature((string) ($row['stat_key'] ?? '')) !== $new_stat_key)
            {
                continue;
            }

            if (self::normalizeBuffTier($row['tier'] ?? 0) > $new_tier)
            {
                return false;
            }

            $superseded_ids[] = (int) $row['id_entity_buff'];
        }

        if ($superseded_ids)
        {
            $placeholders = implode(',', array_fill(0, count($superseded_ids), '?'));
            $delete_stmt = $conn->prepare('DELETE FROM entity_buffs WHERE id_entity_buff IN (' . $placeholders . ')');
            $delete_stmt->execute($superseded_ids);
        }

        return true;
    }

    /**
     * Order/case/whitespace-insensitive signature for a (possibly CSV) stat_key,
     * so "atk,def" and "def, ATK" are treated as the same stat group.
     */
    private static function statKeySignature($stat_key)
    {
        $keys = array_filter(array_map('trim', explode(',', strtolower((string) $stat_key))));
        sort($keys);

        return implode(',', $keys);
    }

    public static function grantTimeBuff($conn, $id_buff_definition, $entity_type, $id_entity, $duration_seconds, $source_type = null, $source_id = null)
    {
        $id_buff_definition = (int) $id_buff_definition;
        $entity_type = (string) $entity_type;
        $id_entity = (int) $id_entity;
        $duration_seconds = max(1, (int) $duration_seconds);

        if ($id_buff_definition <= 0 || $id_entity <= 0)
        {
            return false;
        }

        $stmt = $conn->prepare('
            SELECT stat_key, tier
            FROM buff_definitions
            WHERE id_buff_definition = :id_buff_definition
            LIMIT 1
        ');
        $stmt->execute([':id_buff_definition' => $id_buff_definition]);
        $new_def = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$new_def)
        {
            return false;
        }

        if (!self::reserveTimeBuffSlot($conn, $entity_type, $id_entity, $new_def))
        {
            // An active buff with the same stat_key and a strictly higher tier
            // already covers this entity; the new (weaker) buff doesn't stick.
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
    public static function grantBattleTurnBuff(
        $conn,
        $battle_type,
        $id_battle,
        $entity_type,
        $id_entity,
        $id_buff_definition,
        $turns,
        $applied_at_turn = 0,
        $applied_order = 0,
        $id_ability_effect = null
    )
    {
        $turns = max(1, (int) $turns);

        $stmt = $conn->prepare('
            INSERT INTO battle_turn_buffs
            (
                battle_type, id_battle, entity_type, id_entity, id_buff_definition,
                id_ability_effect, applied_at_turn, applied_order, turns_total, turns_remaining, dt_applied_utc
            )
            VALUES
            (
                :battle_type, :id_battle, :entity_type, :id_entity, :id_buff_definition,
                :id_ability_effect, :applied_at_turn, :applied_order, :turns_total, :turns_remaining, :dt_applied_utc
            )
        ');

        return $stmt->execute([
            ':battle_type' => (string) $battle_type,
            ':id_battle' => (int) $id_battle,
            ':entity_type' => (string) $entity_type,
            ':id_entity' => (int) $id_entity,
            ':id_buff_definition' => (int) $id_buff_definition,
            ':id_ability_effect' => $id_ability_effect !== null ? (int) $id_ability_effect : null,
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
            SELECT BTB.*, BD.stat_key, BD.modifier_kind, BD.modifier_value, BD.is_debuff, BD.buff_code, BD.icon, BD.tier,
                   BD.name, BD.name_it, BD.name_pt
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rollAbilityEffects($conn, $id_ability)
    {
        $id_ability = (int) $id_ability;

        if ($id_ability <= 0)
        {
            return [];
        }

        $stmt = $conn->prepare('
            SELECT AE.id_ability_effect, AE.id_ability, AE.id_buff_definition, AE.effect_target,
                   AE.effect_chance, AE.duration_turns, AE.sort_order,
                   BD.buff_code, BD.stat_key, BD.modifier_kind, BD.modifier_value, BD.is_debuff, BD.icon,
                   BD.name, BD.name_it, BD.name_pt
            FROM ability_effects AE
            INNER JOIN buff_definitions BD ON BD.id_buff_definition = AE.id_buff_definition
            WHERE AE.id_ability = :id_ability
              AND BD.flg_active = \'S\'
            ORDER BY AE.sort_order ASC, AE.id_ability_effect ASC
        ');
        $stmt->execute([':id_ability' => $id_ability]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $ability_effect_row
     * @param array<string, mixed> $caster_entity entity_type, id_entity
     * @param array<string, mixed> $target_entity entity_type, id_entity
     */
    public static function grantAbilityEffect(
        $conn,
        $battle_type,
        $id_battle,
        array $ability_effect_row,
        array $caster_entity,
        array $target_entity,
        $applied_at_turn = 0
    )
    {
        $chance = (int) ($ability_effect_row['effect_chance'] ?? 100);

        if ($chance <= 0 || rand(1, 100) > $chance)
        {
            return false;
        }

        $recipient = (string) ($ability_effect_row['effect_target'] ?? 'target') === 'self'
            ? $caster_entity
            : $target_entity;

        return self::grantBattleTurnBuff(
            $conn,
            $battle_type,
            (int) $id_battle,
            (string) ($recipient['entity_type'] ?? 'animal'),
            (int) ($recipient['id_entity'] ?? 0),
            (int) ($ability_effect_row['id_buff_definition'] ?? 0),
            (int) ($ability_effect_row['duration_turns'] ?? 3),
            (int) $applied_at_turn,
            0,
            (int) ($ability_effect_row['id_ability_effect'] ?? 0)
        );
    }

    /**
     * Live effective combat stats: time layers (animal + user_ig) + battle turn layers.
     *
     * @param array<string, int|float> $base_stats
     * @return array<string, int|float>
     */
    public static function computeEffectiveStats(
        $conn,
        $battle_type,
        $id_battle,
        $entity_type,
        $id_entity,
        $id_user_ig_or_null,
        array $base_stats
    )
    {
        $entity_type = (string) $entity_type;
        $id_entity = (int) $id_entity;
        $id_battle = (int) $id_battle;
        $stats = $base_stats;

        if ($entity_type === 'animal' && $id_user_ig_or_null !== null)
        {
            $stats = self::applyAtBattleStart($conn, $id_entity, (int) $id_user_ig_or_null, $stats);
        }

        if ($id_battle > 0 && $battle_type !== '' && $id_entity > 0)
        {
            $stats = self::applyBattleTurnLayersToStats($conn, $battle_type, $id_battle, $entity_type, $id_entity, $stats);
        }

        if ($id_battle > 0 && $battle_type !== '' && $id_user_ig_or_null !== null && (int) $id_user_ig_or_null > 0)
        {
            $stats = self::applyBattleTurnLayersToStats(
                $conn,
                $battle_type,
                $id_battle,
                'user_ig',
                (int) $id_user_ig_or_null,
                $stats
            );
        }

        return $stats;
    }

    /**
     * Grouped buff list for combat UI (time + turn layers merged).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fetchCombatDisplay(
        $conn,
        $battle_type,
        $id_battle,
        $entity_type,
        $id_entity,
        $id_user_ig_or_null,
        $lang = ''
    )
    {
        $raw = [];

        if ((string) $entity_type === 'animal' && $id_user_ig_or_null !== null)
        {
            foreach (self::fetchBattleStartTimeLayers($conn, (int) $id_entity, (int) $id_user_ig_or_null) as $layer)
            {
                $raw[] = self::combatDisplayRowFromTimeLayer($layer, $lang);
            }
        }

        if ((int) $id_battle > 0 && (int) $id_entity > 0)
        {
            foreach (self::fetchActiveBattleTurnLayers($conn, $battle_type, (int) $id_battle, (string) $entity_type, (int) $id_entity) as $layer)
            {
                $raw[] = self::combatDisplayRowFromTurnLayer($layer, $lang);
            }
        }

        if ((int) $id_battle > 0 && $id_user_ig_or_null !== null && (int) $id_user_ig_or_null > 0)
        {
            foreach (self::fetchActiveBattleTurnLayers($conn, $battle_type, (int) $id_battle, 'user_ig', (int) $id_user_ig_or_null) as $layer)
            {
                $raw[] = self::combatDisplayRowFromTurnLayer($layer, $lang);
            }
        }

        return self::groupCombatDisplayBuffs($raw);
    }

    /**
     * @param array<string, int|float> $base_stats
     * @return array<int, array<string, mixed>>
     */
    public static function fetchCombatStatSheet(
        $conn,
        $battle_type,
        $id_battle,
        $entity_type,
        $id_entity,
        $id_user_ig_or_null,
        array $base_stats,
        $lang = ''
    )
    {
        $effective = self::computeEffectiveStats(
            $conn,
            $battle_type,
            $id_battle,
            $entity_type,
            $id_entity,
            $id_user_ig_or_null,
            $base_stats
        );
        $displayBuffs = self::fetchCombatDisplay(
            $conn,
            $battle_type,
            $id_battle,
            $entity_type,
            $id_entity,
            $id_user_ig_or_null,
            $lang
        );
        $integerKeys = ['hp', 'max_hp', 'acc', 'eva', 'cr'];
        $sheet = [];

        foreach (['hp', 'max_hp', 'atk', 'def', 'matk', 'mdef', 'acc', 'eva', 'cr', 'spd'] as $statKey)
        {
            $base = $base_stats[$statKey] ?? 0;
            $eff = $effective[$statKey] ?? $base;
            $castBase = in_array($statKey, $integerKeys, true) ? (int) $base : (float) $base;
            $castEffective = in_array($statKey, $integerKeys, true) ? (int) $eff : (float) $eff;
            $buffs = [];

            foreach ($displayBuffs as $buff)
            {
                if (self::buffAffectsStat($buff['stat_key'] ?? '', $statKey))
                {
                    $buffs[] = $buff;
                }
            }

            $sheet[] = [
                'stat_key' => $statKey,
                'base' => $castBase,
                'effective' => $castEffective,
                'is_modified' => !empty($buffs) || $castBase !== $castEffective,
                'buffs' => $buffs,
            ];
        }

        return $sheet;
    }

    /**
     * @param array<int, array<string, mixed>> $buffs
     * @return array<int, array<string, mixed>>
     */
    public static function groupCombatDisplayBuffs(array $buffs)
    {
        $groups = [];

        foreach ($buffs as $buff)
        {
            $key = self::combatBuffStackKey($buff);

            if (!isset($groups[$key]))
            {
                $groups[$key] = [];
            }

            $groups[$key][] = $buff;
        }

        $display = [];

        foreach ($groups as $stacks)
        {
            $first = $stacks[0];
            $display[] = [
                'buff_code' => (string) ($first['buff_code'] ?? ''),
                'name' => (string) ($first['name'] ?? $first['buff_code'] ?? ''),
                'icon' => (string) ($first['icon'] ?? ''),
                'tier' => self::normalizeBuffTier($first['tier'] ?? 0),
                'stat_key' => (string) ($first['stat_key'] ?? ''),
                'is_debuff' => (string) ($first['is_debuff'] ?? 'N') === 'S',
                'modifier_kind' => (string) ($first['modifier_kind'] ?? 'percent'),
                'stack_count' => count($stacks),
                'total_effect_label' => self::computeGroupedEffectLabel($stacks),
                'turns_remaining' => self::minTurnsRemaining($stacks),
                'scope' => (string) ($first['scope'] ?? 'turn'),
                'seconds_remaining' => self::minSecondsRemaining($stacks),
            ];
        }

        return $display;
    }

    /**
     * @param array<string, mixed> $layer
     * @return array<string, mixed>
     */
    private static function combatDisplayRowFromTimeLayer(array $layer, $lang)
    {
        $lang_suffix = self::normalizeLangSuffix($lang);

        return [
            'buff_code' => (string) ($layer['buff_code'] ?? ''),
            'name' => (string) ($layer['name' . $lang_suffix] ?? $layer['name'] ?? $layer['buff_code'] ?? ''),
            'icon' => (string) ($layer['icon'] ?? ''),
            'tier' => self::normalizeBuffTier($layer['tier'] ?? 0),
            'stat_key' => (string) ($layer['stat_key'] ?? ''),
            'is_debuff' => (string) ($layer['is_debuff'] ?? 'N'),
            'modifier_kind' => (string) ($layer['modifier_kind'] ?? 'percent'),
            'modifier_value' => (string) ($layer['modifier_value'] ?? '0'),
            'scope' => 'time',
            'turns_remaining' => null,
            'seconds_remaining' => self::secondsUntilUtc((string) ($layer['dt_expires_utc'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $layer
     * @return array<string, mixed>
     */
    private static function combatDisplayRowFromTurnLayer(array $layer, $lang)
    {
        $lang_suffix = self::normalizeLangSuffix($lang);

        return [
            'buff_code' => (string) ($layer['buff_code'] ?? ''),
            'name' => (string) ($layer['name' . $lang_suffix] ?? $layer['name'] ?? $layer['buff_code'] ?? ''),
            'icon' => (string) ($layer['icon'] ?? ''),
            'tier' => self::normalizeBuffTier($layer['tier'] ?? 0),
            'stat_key' => (string) ($layer['stat_key'] ?? ''),
            'is_debuff' => (string) ($layer['is_debuff'] ?? 'N'),
            'modifier_kind' => (string) ($layer['modifier_kind'] ?? 'percent'),
            'modifier_value' => (string) ($layer['modifier_value'] ?? '0'),
            'scope' => 'turn',
            'turns_remaining' => (int) ($layer['turns_remaining'] ?? 0),
            'seconds_remaining' => null,
        ];
    }

    /**
     * @param array<string, mixed> $buff
     */
    private static function combatBuffStackKey(array $buff)
    {
        $buff_code = (string) ($buff['buff_code'] ?? '');

        if ($buff_code !== '')
        {
            return implode('|', [
                (string) ($buff['scope'] ?? 'turn'),
                $buff_code,
            ]);
        }

        return implode('|', [
            (string) ($buff['scope'] ?? 'turn'),
            (string) ($buff['is_debuff'] ?? 'N'),
            (string) ($buff['stat_key'] ?? ''),
            (string) ($buff['modifier_kind'] ?? 'percent'),
            (string) ($buff['modifier_value'] ?? '0'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $stacks
     */
    private static function computeGroupedEffectLabel(array $stacks)
    {
        if (!$stacks)
        {
            return '';
        }

        $first = $stacks[0];
        $isDebuff = (string) ($first['is_debuff'] ?? 'N') === 'S' || ($first['is_debuff'] ?? false) === true;
        $kind = (string) ($first['modifier_kind'] ?? 'percent');
        $templatePairs = self::parseStatModifiers($first['stat_key'] ?? '', $first['modifier_value'] ?? '');

        if (!$templatePairs)
        {
            return '';
        }

        $parts = [];

        foreach ($templatePairs as $pair)
        {
            $statLabel = strtoupper($pair['stat_key']);

            if ($kind === 'flat')
            {
                $flatTotal = 0.0;

                foreach ($stacks as $buff)
                {
                    foreach (self::parseStatModifiers($buff['stat_key'] ?? '', $buff['modifier_value'] ?? '') as $stackPair)
                    {
                        if ($stackPair['stat_key'] !== $pair['stat_key'])
                        {
                            continue;
                        }

                        $magnitude = abs((float) $stackPair['modifier_value']);
                        $flatTotal += $isDebuff ? -$magnitude : $magnitude;
                        break;
                    }
                }

                $parts[] = ($flatTotal >= 0 ? '+' : '')
                    . rtrim(rtrim(number_format($flatTotal, 1, '.', ''), '0'), '.')
                    . ' '
                    . $statLabel;
            }
            else
            {
                $multiplier = 1.0;

                foreach ($stacks as $buff)
                {
                    foreach (self::parseStatModifiers($buff['stat_key'] ?? '', $buff['modifier_value'] ?? '') as $stackPair)
                    {
                        if ($stackPair['stat_key'] !== $pair['stat_key'])
                        {
                            continue;
                        }

                        $magnitude = abs((float) $stackPair['modifier_value']);
                        $signed = $isDebuff ? -$magnitude : $magnitude;
                        $multiplier *= (1 + ($signed / 100));
                        break;
                    }
                }

                $percentTotal = (int) round(($multiplier - 1) * 100);
                $parts[] = ($percentTotal >= 0 ? '+' : '') . $percentTotal . '% ' . $statLabel;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $stacks
     */
    private static function minTurnsRemaining(array $stacks)
    {
        $min = null;

        foreach ($stacks as $buff)
        {
            if ((string) ($buff['scope'] ?? '') !== 'turn')
            {
                continue;
            }

            $remaining = (int) ($buff['turns_remaining'] ?? 0);

            if ($min === null || $remaining < $min)
            {
                $min = $remaining;
            }
        }

        return $min;
    }

    /**
     * @param array<int, array<string, mixed>> $stacks
     */
    private static function minSecondsRemaining(array $stacks)
    {
        $min = null;

        foreach ($stacks as $buff)
        {
            if ((string) ($buff['scope'] ?? '') !== 'time')
            {
                continue;
            }

            $remaining = (int) ($buff['seconds_remaining'] ?? 0);

            if ($min === null || $remaining < $min)
            {
                $min = $remaining;
            }
        }

        return $min;
    }

    private static function normalizeBuffTier($tier)
    {
        $tier = (int) $tier;

        if ($tier < 1)
        {
            return 0;
        }

        if ($tier > 5)
        {
            return 5;
        }

        return $tier;
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
