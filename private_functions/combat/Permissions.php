<?php

/**
 * Planning-phase permissions: who may act, when a round is ready to resolve.
 */
class Permissions
{
    const MODE_SOLO = CombatSession::TYPE_SOLO;
    const MODE_PVP = CombatSession::TYPE_PVP;
    const MODE_PARTY = CombatSession::TYPE_PARTY;

    /**
     * Party PvE uses an explicit confirm step after staging.
     */
    public static function requiresConfirmStep($mode)
    {
        return (string) $mode === self::MODE_PARTY;
    }

    /**
     * Changing an already-staged action invalidates teammates' confirmations.
     */
    public static function stagedChangeInvalidatesOthers($mode, $hadPreviousChoice, $choiceChanged)
    {
        return (string) $mode === self::MODE_PARTY
            && $hadPreviousChoice
            && $choiceChanged;
    }

    /**
     * PvP: resolve when both players submitted, or either submitted flee.
     *
     * @param array<int, array<string, mixed>> $choices
     */
    public static function pvpShouldResolveTurn($choiceCount, array $choices)
    {
        if ($choiceCount < 1)
        {
            return false;
        }

        foreach ($choices as $choice)
        {
            if ((string) ($choice['action_type'] ?? '') === 'action'
                && (int) ($choice['action_id'] ?? 0) === 4)
            {
                return true;
            }
        }

        return $choiceCount >= 2;
    }

    /**
     * Party PvE: resolve when every alive+active member confirmed, or leader confirmed flee.
     *
     * @param array<string, mixed>|null $leaderChoice
     */
    public static function partyPveShouldResolveRound($confirmedCount, $alivePartyCount, $leaderChoice)
    {
        if ($alivePartyCount <= 0)
        {
            return false;
        }

        if ($confirmedCount >= $alivePartyCount)
        {
            return true;
        }

        return self::partyPveLeaderFleeConfirmed($leaderChoice);
    }

    /**
     * @param array<string, mixed>|null $leaderChoice
     */
    public static function partyPveLeaderFleeConfirmed($leaderChoice)
    {
        return $leaderChoice
            && (string) ($leaderChoice['action_type'] ?? '') === 'flee'
            && trim((string) ($leaderChoice['flg_confirmed'] ?? 'N')) === 'Y';
    }
}
