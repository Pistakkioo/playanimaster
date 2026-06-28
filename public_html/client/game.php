<?php
require __DIR__ . '/includes/session_auth.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/language_texts.php';

animaster_require_character();

if (!animaster_refresh_character_session())
{
    header('Location: character_select.php');
    exit;
}

$profile = animaster_get_profile();
$battle = animaster_get_battle_resume();

if (!$profile || empty($profile['id_user_ig']))
{
    header('Location: character_select.php');
    exit;
}

$profile = animaster_normalize_profile($profile);

$lvl_up_constant_animal = 40;
$conn = animaster_get_conn();
$costante_row = $conn->query("SELECT valore FROM costanti WHERE costante = 'lvl_up_constant_animal' LIMIT 1");

if ($costante_row)
{
    $costante_data = $costante_row->fetch();

    if ($costante_data && isset($costante_data['valore']))
    {
        $lvl_up_constant_animal = (int) $costante_data['valore'];
    }
}

$langApi = animaster_get_client_lang_api();

$chat_since_row = $conn->query('SELECT NOW() AS chat_since')->fetch(PDO::FETCH_ASSOC);
$chat_since = $chat_since_row && !empty($chat_since_row['chat_since'])
    ? $chat_since_row['chat_since']
    : date('Y-m-d H:i:s');

$bootstrap = [
    'profile' => $profile,
    'battle' => $battle,
    'costanti' => [
        'lvl_up_constant_animal' => $lvl_up_constant_animal
    ],
    'langApi' => $langApi,
    'texts' => animaster_load_language_texts($conn, $langApi),
    'chat_since' => $chat_since
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animaster — <?php echo animaster_h($profile['display_name']); ?></title>
    <link rel="stylesheet" href="<?php echo animaster_h(animaster_asset_url('css/game.css')); ?>">
    <link rel="stylesheet" href="<?php echo animaster_h(animaster_asset_url('css/chat.css')); ?>">
</head>
<body>
    <div id="world-screen" class="screen">
        <div class="game-layout">
            <div class="canvas-wrap">
                <canvas id="game-canvas" width="960" height="640" tabindex="-1"></canvas>
                <div id="target-panel" class="target-panel" hidden aria-hidden="true">
                    <header id="target-drag-handle" class="target-header">
                        <div class="target-info">
                            <div id="target-name" class="target-name"></div>
                            <div id="target-type" class="target-type"></div>
                        </div>
                        <div class="target-actions">
                            <button type="button" id="target-trade-btn" class="target-trade-btn" hidden title="Trade" data-i18n-title="trade.request_tooltip" aria-label="Trade">&#x1F91D;</button>
                            <button type="button" id="target-duel-btn" class="target-duel-btn" hidden title="Duel" data-i18n-title="duel.request_tooltip" aria-label="Duel">&#x2694;</button>
                        </div>
                        <button type="button" id="target-close" class="target-close" title="Close" data-i18n-title="ui.close" aria-label="Close target">&times;</button>
                    </header>
                </div>
                <div id="npc-talk-bubble" class="npc-talk-bubble" hidden>
                    <button type="button" id="npc-talk-btn" data-i18n="dialog.talk_button">Talk [Space]</button>
                </div>
                <div id="player-chat-bubbles" class="player-chat-bubbles" aria-hidden="true"></div>

                <aside id="inventory-panel" class="inventory-panel side-panel" hidden aria-hidden="true">
                    <header class="inventory-header side-panel-header">
                        <h2 class="inventory-title" data-i18n="inventory.title">Inventory</h2>
                        <button type="button" id="inventory-close" class="inventory-close" title="Close" data-i18n-title="ui.close">&times;</button>
                    </header>
                    <div class="inventory-body">
                        <div id="inventory-list" class="inventory-list" role="list"></div>
                        <div class="inventory-detail">
                            <div id="inventory-detail-name" class="inventory-detail-name">—</div>
                            <div id="inventory-detail-desc" class="inventory-detail-desc"></div>
                            <div id="inventory-detail-meta" class="inventory-detail-meta"></div>
                            <button type="button" id="inventory-use-btn" class="inventory-use-btn" hidden data-i18n="ui.use">Use</button>
                        </div>
                    </div>
                    <p id="inventory-message" class="inventory-message"></p>

                    <div id="inventory-team-overlay" class="inventory-team-overlay" hidden>
                        <div class="inventory-team-panel">
                            <h3 data-i18n="inventory.team_picker_title">Use on which animal?</h3>
                            <div id="inventory-team-list" class="inventory-team-list"></div>
                            <button type="button" id="inventory-team-cancel" class="inventory-team-cancel" data-i18n="ui.cancel">Cancel</button>
                        </div>
                    </div>
                </aside>

                <aside id="team-panel" class="team-panel side-panel" hidden aria-hidden="true">
                    <header class="team-header side-panel-header">
                        <h2 class="team-title" data-i18n="team.title">Team</h2>
                        <div class="team-header-actions">
                            <button type="button" id="team-reorder-toggle" class="team-icon-btn" title="Reorder team" data-i18n-title="team.reorder_toggle" aria-pressed="false" aria-label="Reorder team" disabled>&harr;</button>
                            <button type="button" id="team-close" class="team-close" title="Close" data-i18n-title="ui.close">&times;</button>
                        </div>
                    </header>
                    <div id="team-reorder-bar" class="team-reorder-bar" hidden>
                        <span class="team-reorder-hint" data-i18n="team.reorder_hint">Drag animals to reorder your team.</span>
                        <div class="team-reorder-actions">
                            <button type="button" id="team-reorder-save" class="team-reorder-save" data-i18n="team.reorder_save">Save order</button>
                            <button type="button" id="team-reorder-cancel" class="team-reorder-cancel" data-i18n="ui.cancel">Cancel</button>
                        </div>
                    </div>
                    <div class="team-body">
                        <div id="team-list" class="team-list" role="list"></div>
                        <div class="team-detail">
                            <div class="team-detail-header">
                                <div id="team-detail-species" class="team-detail-species">—</div>
                                <button type="button" id="team-detail-nickname-display" class="team-detail-nickname-display" hidden>""</button>
                                <div id="team-nickname-edit" class="team-nickname-edit" hidden>
                                    <label class="team-nickname-label" for="team-detail-nickname" data-i18n="team.nickname_label">Nickname</label>
                                    <div class="team-nickname-row">
                                        <input type="text" id="team-detail-nickname" class="team-detail-nickname" maxlength="32" autocomplete="off">
                                        <button type="button" id="team-nickname-save" class="team-nickname-save" data-i18n="ui.save">Save</button>
                                    </div>
                                </div>
                                <div id="team-detail-level" class="team-detail-level"></div>
                                <div id="team-detail-element" class="team-detail-element"></div>
                            </div>
                            <div id="team-detail-tabs" class="team-detail-tabs" role="tablist"></div>
                            <div class="team-detail-panels">
                                <div id="team-tab-overview" class="team-detail-panel is-active" role="tabpanel">
                                    <div id="team-detail-buffs" class="team-detail-buffs" hidden>
                                        <div class="team-detail-buffs-title" data-i18n="team.buffs_title">Active effects</div>
                                        <ul id="team-detail-buffs-list" class="team-detail-buffs-list"></ul>
                                    </div>
                                    <div class="team-detail-bar-group">
                                        <span class="team-detail-bar-label">HP</span>
                                        <div class="team-detail-bar team-detail-hp-bar">
                                            <div id="team-detail-hp-fill" class="team-detail-bar-fill"></div>
                                        </div>
                                        <span id="team-detail-hp-text" class="team-detail-bar-text"></span>
                                    </div>
                                    <div class="team-detail-bar-group">
                                        <span class="team-detail-bar-label">XP</span>
                                        <div class="team-detail-bar team-detail-xp-bar">
                                            <div id="team-detail-xp-fill" class="team-detail-bar-fill xp-fill"></div>
                                        </div>
                                        <span id="team-detail-xp-text" class="team-detail-bar-text"></span>
                                    </div>
                                </div>
                                <div id="team-tab-base" class="team-detail-panel" role="tabpanel" hidden></div>
                                <div id="team-tab-dna" class="team-detail-panel" role="tabpanel" hidden></div>
                                <div id="team-tab-exp" class="team-detail-panel" role="tabpanel" hidden></div>
                                <div id="team-tab-points" class="team-detail-panel" role="tabpanel" hidden></div>
                                <div id="team-tab-abilities" class="team-detail-panel" role="tabpanel" hidden></div>
                            </div>
                        </div>
                    </div>
                    <p id="team-message" class="team-message"></p>
                </aside>
            </div>
        </div>
    </div>

    <div class="hud-bottom-dock">
        <button type="button" id="hud-fab-toggle" class="hud-fab-toggle" title="Show HUD" data-i18n-title="hud.toggle_show" aria-label="Show HUD" aria-pressed="false">&#x2139;</button>
        <div id="hud" class="hud-overlay" hidden aria-hidden="true">
            <div id="hud-player"></div>
            <div id="hud-status"></div>
            <div id="hud-help" data-i18n="hud.help">WASD move · Walk into wilds to battle · Talk to NPCs · I bag · T team</div>
            <button type="button" id="team-toggle" class="hud-team-toggle" aria-expanded="false" data-i18n="hud.team">Team</button>
            <button type="button" id="inventory-toggle" class="hud-inventory-toggle" aria-expanded="false" data-i18n="hud.bag">Bag</button>
            <a class="hud-logout" id="hud-characters" href="character_select.php?switch=1" data-i18n="hud.characters">Characters</a>
            <a class="hud-logout" href="logout.php" data-i18n="hud.logout">Logout</a>
        </div>
    </div>

    <div id="notification-overlay" class="notification-overlay" hidden aria-hidden="true">
        <div class="notification-panel" role="alertdialog" aria-labelledby="notification-text">
            <p id="notification-text" class="notification-text"></p>
            <p id="notification-meta" class="notification-meta"></p>
            <button type="button" id="notification-dismiss" class="notification-dismiss" data-i18n="ui.ok">OK</button>
        </div>
    </div>

    <div id="dialog-overlay" class="dialog-overlay" hidden aria-hidden="true">
        <div class="dialog-panel">
            <header class="dialog-header">
                <div>
                    <div id="dialog-npc-name" class="dialog-npc-name"></div>
                    <div id="dialog-title" class="dialog-title"></div>
                </div>
                <button type="button" id="dialog-close" class="dialog-close" title="Close" data-i18n-title="ui.close">&times;</button>
            </header>
            <div id="dialog-text" class="dialog-text"></div>
            <div id="dialog-options" class="dialog-options"></div>
            <footer class="dialog-footer">
                <button type="button" id="dialog-next" class="dialog-next" title="Next" data-i18n-title="ui.next">&rsaquo;</button>
            </footer>
        </div>
    </div>

    <div id="combat-overlay" hidden aria-hidden="true">
        <div class="combat-panel">
            <header class="combat-header">
                <h2 data-i18n="combat.title">Combat</h2>
                <button type="button" id="combat-close" title="Only after battle ends" data-i18n-title="ui.combat_close_title">×</button>
            </header>
            <div id="combat-units" class="combat-units"></div>
            <div id="combat-log" class="combat-log"></div>
            <p id="combat-pvp-status" class="combat-pvp-status" hidden aria-live="polite"></p>
            <div id="combat-abilities" class="combat-abilities"></div>
            <div class="combat-actions">
                <div class="combat-presentation-options">
                    <label class="combat-auto-advance-label">
                        <input type="checkbox" id="combat-auto-advance">
                        <span data-i18n="combat.auto_advance">Auto-advance</span>
                    </label>
                    <label class="combat-skip-animations-label" id="combat-skip-animations-label">
                        <input type="checkbox" id="combat-skip-animations">
                        <span data-i18n="combat.skip_animations">Skip animations</span>
                    </label>
                </div>
                <button type="button" id="combat-flee" data-i18n="combat.flee">Flee</button>
            </div>
            <p id="combat-message" class="combat-message"></p>
        </div>
    </div>

    </div>

    <div id="duel-request-overlay" class="duel-request-overlay" hidden aria-hidden="true">
        <div class="duel-request-panel" role="dialog" aria-labelledby="duel-request-text">
            <p id="duel-request-text" class="duel-request-text"></p>
            <div class="duel-request-timer" aria-hidden="true">
                <div id="duel-request-timer-fill" class="duel-request-timer-fill"></div>
            </div>
            <div class="duel-request-actions">
                <button type="button" id="duel-request-accept" class="duel-request-accept" data-i18n="duel.accept">Accept</button>
                <button type="button" id="duel-request-decline" class="duel-request-decline" data-i18n="duel.decline">Decline</button>
            </div>
        </div>
    </div>

    <div id="trade-request-overlay" class="trade-request-overlay" hidden aria-hidden="true">
        <div class="trade-request-panel" role="dialog" aria-labelledby="trade-request-text">
            <p id="trade-request-text" class="trade-request-text"></p>
            <div class="trade-request-timer" aria-hidden="true">
                <div id="trade-request-timer-fill" class="trade-request-timer-fill"></div>
            </div>
            <div class="trade-request-actions">
                <button type="button" id="trade-request-accept" class="trade-request-accept" data-i18n="trade.accept">Accept</button>
                <button type="button" id="trade-request-decline" class="trade-request-decline" data-i18n="trade.decline">Decline</button>
            </div>
        </div>
    </div>

    <div id="trade-overlay" class="trade-overlay" hidden aria-hidden="true">
        <div class="trade-panel" role="dialog" aria-labelledby="trade-title">
            <header class="trade-header">
                <h2 id="trade-title" class="trade-title"></h2>
                <button type="button" id="trade-close" class="trade-close" title="Close" data-i18n-title="ui.close">&times;</button>
            </header>
            <div class="trade-columns">
                <section class="trade-side trade-side-mine">
                    <h3 id="trade-my-label" class="trade-side-label"></h3>
                    <div class="trade-gold-row">
                        <span class="trade-gold-icon" aria-hidden="true">&#x1FA99;</span>
                        <span id="trade-my-gold-balance" class="trade-gold-balance"></span>
                        <input type="number" id="trade-my-gold-input" class="trade-gold-input" min="0" step="1" value="0">
                    </div>
                    <div id="trade-my-items" class="trade-items"></div>
                    <button type="button" id="trade-add-item" class="trade-add-item-btn" data-i18n="trade.add_item">Add item</button>
                </section>
                <section class="trade-side trade-side-theirs">
                    <h3 id="trade-their-label" class="trade-side-label"></h3>
                    <div class="trade-gold-row trade-gold-readonly">
                        <span class="trade-gold-icon" aria-hidden="true">&#x1FA99;</span>
                        <span id="trade-their-gold-offer" class="trade-gold-offer">0</span>
                    </div>
                    <div id="trade-their-items" class="trade-items trade-items-readonly"></div>
                </section>
            </div>
            <p id="trade-status" class="trade-status"></p>
            <footer class="trade-footer">
                <button type="button" id="trade-confirm-btn" class="trade-confirm-btn" data-i18n="trade.confirm">Confirm trade</button>
                <button type="button" id="trade-cancel-btn" class="trade-cancel-btn" data-i18n="trade.cancel">Cancel trade</button>
            </footer>
        </div>
    </div>

    <div id="trade-pick-overlay" class="trade-pick-overlay" hidden aria-hidden="true">
        <div class="trade-pick-panel">
            <h3 class="trade-pick-title" data-i18n="trade.pick_item_title">Add to trade</h3>
            <div id="trade-pick-list" class="trade-pick-list"></div>
            <div id="trade-pick-qty" class="trade-pick-qty" hidden>
                <p id="trade-pick-qty-name" class="trade-pick-qty-name"></p>
                <label class="trade-pick-qty-label" for="trade-pick-qty-input" data-i18n="trade.pick_quantity_label">Quantity</label>
                <input type="number" id="trade-pick-qty-input" class="trade-pick-qty-input" min="1" step="1" value="1">
                <p id="trade-pick-qty-hint" class="trade-pick-qty-hint"></p>
                <div class="trade-pick-qty-actions">
                    <button type="button" id="trade-pick-qty-confirm" class="trade-pick-qty-confirm" data-i18n="trade.add_item">Add item</button>
                    <button type="button" id="trade-pick-qty-back" class="trade-pick-qty-back" data-i18n="ui.back">Back</button>
                </div>
            </div>
            <button type="button" id="trade-pick-close" class="trade-pick-close" data-i18n="ui.cancel">Cancel</button>
        </div>
    </div>

    <aside id="chat-panel" class="chat-panel chat-pos-left" aria-label="Chat">
        <button type="button" id="chat-fab-restore" class="chat-fab-restore" hidden title="Open chat" data-i18n-title="chat.restore_from_icon" aria-label="Open chat">&#x1F4AC;</button>
        <header class="chat-header">
            <span class="chat-title" data-i18n="chat.title">Chat</span>
            <div class="chat-header-actions">
                <button type="button" id="chat-position-toggle" class="chat-icon-btn" title="Move chat" data-i18n-title="chat.position_toggle" aria-label="Toggle chat position">&#x21C4;</button>
                <button type="button" id="chat-settings-open" class="chat-icon-btn" title="Channel filters" data-i18n-title="chat.settings_open" aria-label="Channel filters">&#x2699;</button>
                <button type="button" id="chat-icon-minimize" class="chat-icon-btn chat-icon-minimize-btn" title="Minimize to icon" data-i18n-title="chat.minimize_icon" aria-label="Minimize chat to icon">&#x1F4AC;</button>
                <button type="button" id="chat-collapse-toggle" class="chat-icon-btn" title="Collapse" data-i18n-title="chat.collapse" aria-label="Collapse chat">&#x2212;</button>
            </div>
        </header>
        <div id="chat-messages" class="chat-messages" role="log" aria-live="polite"></div>
        <form id="chat-form" class="chat-form">
            <div class="chat-input-wrap">
                <input type="text" id="chat-input" class="chat-input" maxlength="500" autocomplete="off" data-i18n-placeholder="chat.input_placeholder" placeholder="Message… @name · !zone · $clan · #party · %alliance · *global">
                <div id="chat-whisper-suggest" class="chat-whisper-suggest" hidden role="listbox" aria-label="Whisper targets"></div>
            </div>
            <button type="submit" class="chat-send-btn" data-i18n="chat.send">Send</button>
        </form>
        <nav id="chat-tabs" class="chat-tabs" aria-label="Chat tabs"></nav>
        <div id="chat-settings" class="chat-settings" hidden>
            <p id="chat-settings-title" class="chat-settings-title"></p>
            <div id="chat-settings-checks" class="chat-settings-checks"></div>
            <button type="button" id="chat-settings-close" class="chat-settings-close" data-i18n="chat.settings_close">Done</button>
        </div>
    </aside>

    <script>
        window.ANIMASTER_BOOTSTRAP = <?php echo json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/lang.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/api.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/wild_sprites_core.js')); ?>"></script>
    <?php
    $wild_sprites_arch = animaster_wild_sprites_arch_script();

    if ($wild_sprites_arch !== null)
    {
        echo '    <script src="' . animaster_h(animaster_asset_url($wild_sprites_arch)) . '"></script>' . "\n";
    }
    ?>
    <script src="<?php echo animaster_h(animaster_asset_url(animaster_wild_sprites_script())); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/world.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/spawn.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/dialog.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/panel-drag.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/element-icons.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/inventory.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/team.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/notifications.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/combat.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/player_chat_bubbles.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/target.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/trade.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/duel.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/chat.js')); ?>"></script>
    <script src="<?php echo animaster_h(animaster_asset_url('js/game.js')); ?>"></script>
</body>
</html>
