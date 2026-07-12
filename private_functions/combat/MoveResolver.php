<?php

/**
 * Shared ability resolution: accuracy, damage, crit/STAB, element bonus, ability_effects buffs.
 * Used by solo PvE, party PvE, and PvP so formulas stay in one place.
 */
class MoveResolver
{
    /**
     * @param array<string, mixed> $ability Row from abilities (accuracy, power, m_power, id_element, id_ability, ability or ability_name)
     * @param array<string, mixed> $attacker Fighter snapshot (lvl, acc, cr, atk, def, matk, mdef, eva, spd, id_element, nickname; hp or current_hp)
     * @param array<string, mixed> $defender Same shape as attacker
     * @param array<string, mixed> $options lang_suffix, apply_stat_effects, conn, battle_type, id_battle, applied_at_turn, attacker_entity, defender_entity
     * @return array{
     *   attacker: array<string, mixed>,
     *   defender: array<string, mixed>,
     *   move_hit: string,
     *   damage: int,
     *   move_description: string,
     *   id_ability: int
     * }
     */
    public static function resolveAbility(array $ability, array $attacker, array $defender, array $options = [])
    {
        if (!class_exists('FUNZIONI'))
        {
            require_once __DIR__ . '/../f.php';
        }

        $lang_suffix = isset($options['lang_suffix']) ? (string) $options['lang_suffix'] : '';
        $apply_stat_effects = !array_key_exists('apply_stat_effects', $options) || (bool) $options['apply_stat_effects'];

        $baseAttacker = self::normalizeFighter($attacker);
        $baseDefender = self::normalizeFighter($defender);
        $mathAttacker = self::fighterForMath($baseAttacker, $options, $options['attacker_entity'] ?? null);
        $mathDefender = self::fighterForMath($baseDefender, $options, $options['defender_entity'] ?? null);

        $attacker_acc = (int) $mathAttacker['acc'];
        $defender_eva = (int) $mathDefender['eva'];
        $attacker_lvl = (int) $mathAttacker['lvl'];
        $attacker_atk = (float) $mathAttacker['atk'];
        $attacker_matk = (float) $mathAttacker['matk'];
        $defender_def = (float) $mathDefender['def'];
        $defender_mdef = (float) $mathDefender['mdef'];
        $attacker_cr = (int) $mathAttacker['cr'];
        $attacker_element = (int) $mathAttacker['id_element'];
        $defender_element = (int) $mathDefender['id_element'];

        $acc = $attacker_acc * ((float) $ability['accuracy'] / 100);
        $acc *= (100 - $defender_eva) / 100;

        $move_hit = 'N';
        $damage = 0;
        $crit_mult = 1.0;

        if (rand(1, 100) <= $acc)
        {
            $move_hit = 'S';
            $type_bonus = 1.0;

            if (rand(1, 100) <= $attacker_cr)
            {
                $crit_mult = 1.5;
                $move_hit = 'C';
            }

            if ((int) $ability['id_element'] === $attacker_element)
            {
                $type_bonus = 1.5;
            }

            $dmg = ($attacker_lvl * 0.5 * (int) $ability['power'] * $attacker_atk / max(1.0, $defender_def))
                + ($attacker_lvl * 0.5 * (int) $ability['m_power'] * $attacker_matk / max(1.0, $defender_mdef));
            $dmg /= 40;

            if ((int) $ability['power'] > 0 || (int) $ability['m_power'] > 0)
            {
                $dmg += 3;
            }

            $dmg *= $crit_mult;
            $dmg *= $type_bonus;
            $dmg *= FUNZIONI::element_bonus((int) $ability['id_element'], $defender_element);
            $damage = max(0, (int) $dmg);

            $baseDefender['current_hp'] = max(0, (int) $baseDefender['current_hp'] - $damage);

            if ($apply_stat_effects && (int) $baseDefender['current_hp'] > 0)
            {
                self::grantAbilityEffects(
                    $ability,
                    $options,
                    $options['attacker_entity'] ?? null,
                    $options['defender_entity'] ?? null
                );
            }
        }

        $ability_name = self::abilityDisplayName($ability);
        $actor_name = (string) ($baseAttacker['nickname'] ?? '');

        return [
            'attacker' => $baseAttacker,
            'defender' => $baseDefender,
            'move_hit' => $move_hit,
            'damage' => $damage,
            'move_description' => self::buildAbilityDescription($actor_name, $ability_name, $lang_suffix),
            'id_ability' => (int) ($ability['id_ability'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $fighter
     * @param array<string, mixed> $options
     * @param array<string, mixed>|null $entity
     * @return array<string, mixed>
     */
    private static function fighterForMath(array $fighter, array $options, $entity)
    {
        if (!self::hasBattleContext($options) || !is_array($entity))
        {
            return $fighter;
        }

        if (!class_exists('BUFFS'))
        {
            require_once __DIR__ . '/../buffs.php';
        }

        $stats = self::statsFromFighter($fighter);
        $effective = BUFFS::computeEffectiveStats(
            $options['conn'],
            (string) $options['battle_type'],
            (int) $options['id_battle'],
            (string) ($entity['entity_type'] ?? 'animal'),
            (int) ($entity['id_entity'] ?? 0),
            array_key_exists('id_user_ig', $entity) ? $entity['id_user_ig'] : null,
            $stats
        );

        $math = $fighter;
        foreach (['atk', 'def', 'matk', 'mdef', 'acc', 'eva', 'cr', 'spd', 'hp', 'max_hp'] as $key)
        {
            if (array_key_exists($key, $effective))
            {
                $math[$key] = $effective[$key];
            }
        }

        if (array_key_exists('hp', $effective))
        {
            $math['current_hp'] = (int) $effective['hp'];
        }

        return $math;
    }

    /**
     * @param array<string, mixed> $ability
     * @param array<string, mixed> $options
     * @param array<string, mixed>|null $caster_entity
     * @param array<string, mixed>|null $target_entity
     */
    private static function grantAbilityEffects(array $ability, array $options, $caster_entity, $target_entity)
    {
        if (!self::hasBattleContext($options) || !is_array($caster_entity) || !is_array($target_entity))
        {
            return;
        }

        $id_ability = (int) ($ability['id_ability'] ?? 0);

        if ($id_ability <= 0)
        {
            return;
        }

        if (!class_exists('BUFFS'))
        {
            require_once __DIR__ . '/../buffs.php';
        }

        $effects = BUFFS::rollAbilityEffects($options['conn'], $id_ability);

        foreach ($effects as $effect_row)
        {
            BUFFS::grantAbilityEffect(
                $options['conn'],
                (string) $options['battle_type'],
                (int) $options['id_battle'],
                $effect_row,
                $caster_entity,
                $target_entity,
                (int) ($options['applied_at_turn'] ?? 0)
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function hasBattleContext(array $options)
    {
        return !empty($options['conn'])
            && !empty($options['battle_type'])
            && (int) ($options['id_battle'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $fighter
     * @return array<string, int|float>
     */
    public static function statsFromFighter(array $fighter)
    {
        $hp = array_key_exists('current_hp', $fighter)
            ? (int) $fighter['current_hp']
            : (int) ($fighter['hp'] ?? 0);

        return [
            'hp' => $hp,
            'max_hp' => (int) ($fighter['max_hp'] ?? $hp),
            'atk' => (float) ($fighter['atk'] ?? 0),
            'def' => (float) ($fighter['def'] ?? 0),
            'matk' => (float) ($fighter['matk'] ?? 0),
            'mdef' => (float) ($fighter['mdef'] ?? 0),
            'acc' => (int) ($fighter['acc'] ?? 0),
            'eva' => (int) ($fighter['eva'] ?? 0),
            'cr' => (int) ($fighter['cr'] ?? 0),
            'spd' => (float) ($fighter['spd'] ?? 0),
        ];
    }

    /**
     * Copy battle stat fields into a normalized fighter array (current_hp alias).
     *
     * @param array<string, mixed> $fighter
     * @return array<string, mixed>
     */
    public static function normalizeFighter(array $fighter)
    {
        $normalized = $fighter;

        if (!array_key_exists('current_hp', $normalized) && array_key_exists('hp', $normalized))
        {
            $normalized['current_hp'] = $normalized['hp'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $ability
     */
    private static function abilityDisplayName(array $ability)
    {
        if (!empty($ability['ability']))
        {
            return (string) $ability['ability'];
        }

        if (!empty($ability['ability_name']))
        {
            return (string) $ability['ability_name'];
        }

        return '';
    }

    private static function buildAbilityDescription($actor_name, $ability_name, $lang_suffix)
    {
        $actor_name = (string) $actor_name;
        $ability_name = (string) $ability_name;

        if ($lang_suffix === '_it')
        {
            return $actor_name . ' ha usato ' . $ability_name;
        }

        if ($lang_suffix === '_pt')
        {
            return $actor_name . ' usou ' . $ability_name;
        }

        return $actor_name . ' used ' . $ability_name;
    }
}
