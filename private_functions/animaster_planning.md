# Animaster — Roadmap Prototipo

> **Tesi:** un MMORPG con backend PHP 7.3 + MariaDB 10.3 è assolutamente fattibile.  
> PHP non è il collo di bottiglia: lo è l’**architettura** (server autoritativo, API stateless, sync a intervalli, combattimento event-driven). Unity, browser 2D e futuri client consumano lo **stesso contratto API**.

---

## 1. Obiettivo del prototipo (Definition of Done)

Un giocatore può:

1. Registrarsi / loggarsi
2. Entrare in una mappa open-world 2D (vista top-down nel browser)
3. Muoversi con WASD; posizione `(x, z)`, direzione e stato inviati al server ogni **100–200 ms**
4. Vedere altri giocatori, NPC fissi e animali selvatici nelle vicinanze (poll ogni **200–500 ms**)
5. Avvicinarsi a un animale selvatico → entrare in combattimento PvE (player marcato `busy`, scena combattimento separata)
6. Combattere a turni: ordine per **speed**; mosse risolte lato server
7. Vincere → animale liberato, drop (shard elementali, frammenti DNA, trinket), EXP a player e squadra
8. Accumulare DNA → evolvere un animale (almeno 1 albero: es. cane → lupo)
9. Parlare con un NPC (dialogo semplice + 1 quest con requisito livello/item)

**Fuori scope prototipo:** Unity 3D, party/raid, PvP condiviso completo, mondo persistente multi-mappa, anti-cheat avanzato.

---

## 2. Architettura consigliata

```
┌─────────────────┐     ┌─────────────────┐     (futuro)
│ Browser 2D      │     │ Unity Client    │
│ (Canvas/Phaser) │     │                 │
└────────┬────────┘     └────────┬────────┘
         │  HTTPS JSON           │
         └──────────┬────────────┘
                    ▼
         ┌──────────────────────┐
         │  Animaster API       │  PHP vanilla, Slim o router leggero
         │  /animaster/api/v1/  │  PDO prepared statements
         └──────────┬───────────┘
                    │
    ┌───────────────┼───────────────┐
    ▼               ▼               ▼
 MariaDB      Cron tick         (fase 2)
 (stato game)  spawn/combat      WebSocket
               cleanup           Ratchet/Swoole
```

### Principi non negoziabili

| Principio | Perché |
|-----------|--------|
| **Server autoritativo** | Posizione, combattimento, drop, evoluzione: tutto validato e risolto in PHP |
| **API-first** | Nessuna logica di gioco nel frontend; solo rendering + input |
| **Stato in DB** | MariaDB = source of truth; facilita debug, replay, multi-client |
| **Zone + interest management** | Il client chiede solo entità entro raggio R da `(x,z)` |
| **Combattimento = macchina a stati** | Tabella `am_combat_sessions` con turno corrente, coda azioni, log |

### Sync open-world (prototipo)

- **Write:** `POST /world/presence` ogni 100–200 ms → `{ x, z, dir, state, map_id }`
- **Read:** `GET /world/nearby?map_id=&x=&z=&radius=` ogni 200–500 ms
- Il server aggiorna `am_player_presence` (ultimo heartbeat); entità stale > 5 s = offline
- **Fase 2:** WebSocket per push; il contratto JSON resta identico

### Combattimento (entrance / exit)

```
OPEN WORLD                    COMBAT SCENE
state: exploring              state: busy
POST /combat/start            combat_session_id
  → crea sessione PvE         POST /combat/action
  → player.state = busy       GET  /combat/state
  → wild despawn temporaneo   POST /combat/flee | resolve
Vittoria → drop + EXP         → player.state = exploring
```

PvP condiviso (fase successiva): stessa `combat_session`, più `participant_id`.

---

## 3. Struttura directory proposta

```
playanimaster/
├── api/v1/
│   ├── index.php              # router
│   ├── auth.php
│   ├── world.php
│   ├── combat.php
│   ├── creatures.php
│   ├── inventory.php
│   └── npc.php
├── funzioni/
│   ├── AmDb.php               # PDO wrapper
│   ├── AmAuth.php
│   ├── AmWorld.php            # presence, nearby, maps
│   ├── AmSpawn.php            # spawn spheres, NPC placement
│   ├── AmCombat.php           # turn engine
│   ├── AmCreature.php         # stats, moves, evolution
│   ├── AmDrop.php
│   ├── AmDialog.php
│   └── AmExp.php
├── cron/
│   ├── tick_spawn.php         # ogni 30–60 s: riempie spawn spheres
│   └── tick_presence_cleanup.php
├── client/
│   ├── index.html
│   ├── js/game.js             # canvas top-down
│   └── css/game.css
├── sql/
│   ├── 001_schema.sql
│   └── 002_seed.sql           # specie, mosse, mappa test, NPC
└── animaster.md               # questo file
```

Prefisso tabelle: `am_` (Animaster), database dedicato o schema separato consigliato.

---

## 4. Schema database (MVP)

### Core

```sql
-- Giocatori
am_players (id, username, password_hash, level, exp, map_id, state ENUM('exploring','busy','offline'),
            created_at)

am_player_presence (player_id PK, map_id, pos_x, pos_z, direction, anim_state, updated_at)

-- Mappe e spawn
am_maps (id, name, width, height, spawn_x, spawn_z)

am_spawn_spheres (id, map_id, center_x, center_z, radius, species_id, max_count, respawn_sec)

am_world_entities (id, map_id, entity_type ENUM('wild','npc'), template_id, pos_x, pos_z,
                   state ENUM('idle','patrol','in_combat','despawned'), spawn_sphere_id NULL, updated_at)

am_npc_templates (id, name, dialog_tree_id, pos_x, pos_z, map_id)  -- posizione fissa
```

### Creature

```sql
am_species (id, code, name, element, base_hp, base_atk, base_def, base_speed, evolution_group)

am_evolution_rules (id, from_species_id, to_species_id, min_dna, rule_json)
-- rule_json es.: {"branch":"stat_ratio","prefer":"speed","thresholds":{"lion":0.4,"tiger":0.35}}

am_moves (id, code, name, element, power, accuracy, target_type, effect_json)

am_species_moves (species_id, move_id, learn_level)

am_player_creatures (id, player_id, species_id, nickname, level, exp, hp, atk, def, speed,
                     dna_count, is_wild_caught, created_at)

am_player_creature_moves (creature_id, move_id, slot)
```

### Combattimento

```sql
am_combat_sessions (id, type ENUM('pve','pvp'), map_id, state ENUM('active','finished','fled'),
                    turn_number, created_at)

am_combat_participants (id, session_id, side ENUM('player','enemy'), ref_type ENUM('player','creature','wild'),
                        ref_id, current_hp, speed, status_json, sort_order)

am_combat_turn_queue (session_id, participant_id, speed, resolved TINYINT)

am_combat_log (id, session_id, turn, message, payload_json)
```

### Inventario & progressione

```sql
am_items (id, code, name, type ENUM('shard','dna','trinket','quest','consumable'), element NULL, meta_json)

am_player_inventory (player_id, item_id, qty)

am_drop_tables (species_id, item_id, min_qty, max_qty, chance_pct)
```

### Dialoghi

```sql
am_dialog_trees (id, code, name)

am_dialog_nodes (id, tree_id, node_key, text, type ENUM('talk','request','reward'), next_default)

am_dialog_options (id, node_id, label, next_node_id, requirement_json)
-- requirement_json: {"min_level":5,"items":[{"item_id":3,"qty":1}]}
```

---

## 5. Roadmap per fasi

### Fase 0 — Fondamenta (settimana 1)

**Obiettivo:** API vuota che risponde, DB creato, auth funzionante.

| Task | Output |
|------|--------|
| Creare schema `am_*` + seed minimo (1 mappa, 3 specie, 5 mosse) | SQL eseguito |
| Router API v1 + middleware auth (session token o JWT semplice) | `POST /auth/register`, `/auth/login` |
| `AmDb` con PDO prepared statements | Pattern uguale a `funzioni/db.php` esistente |
| Pagina client vuota che fa login | `client/index.html` |

**Seed minimo specie prototipo:**
- Cane (Terra) → Lupo → Fenrir (DNA + livello)
- Gatto (Ombra) → branch stat-ratio (Leone/Tigre/Leopardo…)
- Coniglio selvatico (Natura) — solo wild, no evoluzione in MVP

---

### Fase 1 — Open world 2D (settimane 2–3)

**Obiettivo:** Muoversi e vedere entità vicine.

| Task | Dettaglio |
|------|-----------|
| `POST /world/presence` | Upsert posizione; rifiuta se `state=busy` |
| `GET /world/nearby` | Query entità entro raggio (formula distanza euclidea su x,z) |
| Spawn NPC fissi | Insert da `am_npc_templates` → `am_world_entities` |
| Cron `tick_spawn.php` | Per ogni sphere: `COUNT < max_count` → spawn wild a posizione random nella sphere |
| Client Canvas | Griglia, player dot, altri player, NPC (blu), wild (arancio) |
| Input WASD | Invio presence throttled; interpolazione lato client per smoothness |

**Parametri iniziali:** presence ogni **150 ms**, nearby ogni **300 ms**, raggio **30 unità**.

**Demo presentabile:** 2 browser logged come utenti diversi si vedono muovere.

---

### Fase 2 — Combattimento a turni PvE (settimane 3–5)

**Obiettivo:** Incontrare wild → combattere → liberare animale.

| Task | Dettaglio |
|------|-----------|
| `POST /combat/start` | `{ wild_entity_id }` — valida distanza, crea sessione, `player.state=busy`, wild `in_combat` |
| Turn order | All’inizio turno: ordina partecipanti per `speed` DESC; pari → random server-side |
| `POST /combat/action` | `{ move_id, target_id }` — valida turno, risolvi danno/status, avanza coda |
| `GET /combat/state` | HP, turno corrente, mosse disponibili, log ultimi N eventi |
| Fine combattimento | HP enemy ≤ 0 → vittoria; HP squadra = 0 → sconfitta/flee |
| Liberazione wild | Wild → `despawned`; messaggio “corre verso la libertà” |
| UI combattimento | Pannello laterale nel browser (lista mosse, log turni) |

**Motore turni (pseudologica):**

```
1. Carica partecipanti attivi
2. Genera coda turno per questo round (speed order)
3. Per ogni entry non risolta:
     - Se player: attendi action (timeout 30s → pass)
     - Se AI wild: scegli move random ponderato
     - Applica move, scrivi log
     - Check KO
4. Se round finito e combattimento attivo → nuovo round (turn_number++)
```

---

### Fase 3 — Drop, EXP, DNA (settimana 5–6)

| Task | Dettaglio |
|------|-----------|
| `AmDrop.roll()` | Da `am_drop_tables` per specie: shard (elemento specie), DNA fragment, trinket raro |
| `POST /combat/claim` o auto on victory | Aggiungi a `am_player_inventory`, incrementa `dna_count` su specie correlata |
| EXP player + creature | Formula semplice: `base_exp * wild_level`; level-up soglie in tabella o JSON config |
| `GET /creatures`, `GET /inventory` | API per UI |

---

### Fase 4 — Evoluzione (settimana 6–7)

| Task | Dettaglio |
|------|-----------|
| `POST /creatures/{id}/evolve` | Verifica DNA ≥ soglia, applica `am_evolution_rules` |
| Branch stat-ratio (gatto) | Calcola ratio atk/def/speed vs total; match regola con highest margin |
| Aggiorna specie, reset parziale stats | Mantieni mosse compatibili nuova specie |
| UI | Bottone “Evolve” quando DNA sufficiente |

**Esempio regola Fenrir:** `from=wolf, min_dna=50, to=fenrir, min_level=25`

---

### Fase 5 — Dialoghi NPC (settimana 7–8)

| Task | Dettaglio |
|------|-----------|
| `GET /npc/{entity_id}/dialog` | Nodo corrente + opzioni filtrate per `requirement_json` |
| `POST /npc/{entity_id}/dialog/choose` | `{ option_id }` — avanza albero, eroga reward se tipo `reward` |
| Tipi | `talk` (solo testo), `request` (consuma item da inventario), `reward` (grant item/exp) |
| Prossimità | Solo se distanza player–NPC < soglia |

**Quest MVP:** NPC chiede 3 shard Fuoco → ricompensa 100 EXP.

---

### Fase 6 — Polish demo (settimana 8–9)

| Task | Dettaglio |
|------|-----------|
| Schermata HUD | Livello, EXP bar, inventario compatto |
| Messaggi combattimento | “Il coniglio selvaggio è stato liberato!” |
| Documentazione API | OpenAPI minimale o README endpoint |
| Stress test leggero | 10 player fittizi con script PHP che muovono presence |
| Prova multi-client | Stesso API da curl / secondo tab → prova che Unity potrà usare lo stesso JSON |

---

## 6. Contratto API (endpoint MVP)

| Metodo | Path | Scopo |
|--------|------|-------|
| POST | `/auth/register` | Crea account |
| POST | `/auth/login` | Token sessione |
| POST | `/world/presence` | Aggiorna posizione/stato |
| GET | `/world/nearby` | Entità vicine |
| GET | `/world/map/{id}` | Metadati mappa (size, spawn) |
| POST | `/combat/start` | Inizia PvE |
| GET | `/combat/{id}/state` | Stato combattimento |
| POST | `/combat/{id}/action` | Esegui mossa |
| POST | `/combat/{id}/flee` | Fuga (opzionale) |
| GET | `/creatures` | Squadra player |
| POST | `/creatures/{id}/evolve` | Evoluzione |
| GET | `/inventory` | Inventario |
| GET | `/npc/{entity_id}/dialog` | Albero dialogo |
| POST | `/npc/{entity_id}/dialog/choose` | Scelta opzione |

Tutte le risposte: `{ "ok": true, "data": {...} }` o `{ "ok": false, "error": "..." }`.

---

## 7. Client browser 2D (MVP)

**Stack:** HTML5 Canvas + vanilla JS (niente framework obbligatorio; Phaser opzionale se serve).

**Rendering:**
- Mappa: griglia o tile semplice colorata
- Player: cerchio verde + freccia direzione
- Altri player: cerchio blu
- NPC: quadrato giallo
- Wild: triangolo arancio

**Loop:**
```
setInterval(sendPresence, 150)
setInterval(fetchNearby, 300)
requestAnimationFrame(render)
```

**Stati UI:** `LOGIN` → `WORLD` → `COMBAT` (overlay/panel)

---

## 8. Risposte alla sfida “PHP non può fare MMORPG”

| Obiezione | Risposta |
|-----------|----------|
| “PHP è lento” | Il prototipo gestisce decine–centinaia di player con poll 2–5 req/s ciascuno; bottleneck è DB indexing, non linguaggio |
| “Serve TCP realtime” | Molti MMO usano UDP/TCP per posizione; **poll HTTP** basta per demo; WebSocket in PHP (Ratchet) è fase 2 senza cambiare logica |
| “Stato in memoria (Redis)” | MariaDB con `presence.updated_at` e query spatiali semplici è sufficiente per MVP; Redis opzionale dopo |
| “Combattimento realtime” | Il tuo design è **turn-based** → perfetto per request/response PHP |
| “Unity vuole C# server” | Unity è solo view; **tutti** i client AAA parlano con API. PHP = stesso ruolo di Node/Go per logic server |

**Cosa NON promettere nel prototipo:** 1000 player stessa mappa, combattimento action realtime, physics server-side complessa.

**Cosa promettere:** architettura corretta, gameplay loop completo, backend swappable (PHP oggi, altro domani) senza riscrivere il client.

---

## 9. Ordine di implementazione consigliato (checklist)

```
[ ] Fase 0: SQL + auth + struttura cartelle
[ ] Fase 1: presence + nearby + spawn cron + canvas movement
[ ] Fase 2: combat session + turn engine + UI combat
[ ] Fase 3: drops + exp + inventory API
[ ] Fase 4: evolution (dog→wolf + cat branch semplificato)
[ ] Fase 5: 1 NPC con dialogo quest
[ ] Fase 6: demo polish + doc API
```

**Primo milestone “wow”:** due account che camminano insieme e uno inizia un combattimento mentre l’altro lo vede `busy` (opzionale: ghost icon).

---

## 10. Evoluzioni post-prototipo

| Feature | Nota tecnica |
|---------|--------------|
| Unity 3D | `UnityWebRequest` → stessi endpoint; sostituisci solo renderer |
| WebSocket presence | Push nearby delta; riduce latenza percepite |
| Party / raid | `am_parties`, combat session multi-player side |
| PvP condiviso | Stessa sessione, due lato `player` |
| Anti-cheat | Validazione velocità max, path server-side, rate limit |
| Sharding mappe | `map_id` + server cron per zona |

---

## 11. Prossimo passo immediato

1. Creare `playanimaster/sql/001_schema.sql` con le tabelle sopra
2. Creare `002_seed.sql` con mappa test 100×100, 2 spawn spheres, 1 NPC, specie cane/gatto/coniglio
3. Implementare `api/v1/auth.php` + `world/presence` + client canvas minimale

Quando vuoi passare all’implementazione, si può iniziare dalla Fase 0 nello stesso workspace `playanimaster/`.
