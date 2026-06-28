# SQL conventions (Animaster / playanimaster)

**Audience:** AI agents and developers working on this repository.

All database SQL for this project lives in `private_functions/SQL/`. Do **not** add `.sql` files elsewhere (no module-specific paths under `docs/`, `public_html/`, etc.). Consolidate here.

**Database:** `playanimaster_db` (qualify table names when helpful: `playanimaster_db.table_name`).

**Deploy order (existing / incremental environments):**

1. `00_tables.sql` — **fresh installs only** (see below)
2. `01_alters_structure.sql` — append new changes at the end
3. `02_insert_static_data.sql` — append new changes at the end

---

## `00_tables.sql` — table creation (immutable)

- Contains **only** `CREATE TABLE` (and optionally `CREATE DATABASE` comments).
- Defines the **baseline schema** for a brand-new database.
- **NEVER modify this file after it has been committed.**  
  If the live schema diverges, record the difference in `01_alters_structure.sql`, not by editing `00_tables.sql`.

### New table workflow

1. Add the full `CREATE TABLE` definition to `00_tables.sql` (for new environments).
2. Add the equivalent `ALTER TABLE` / `CREATE TABLE` migration to **`01_alters_structure.sql`** for databases that already exist.
3. Add any seed rows to **`02_insert_static_data.sql`**.

---

## `01_alters_structure.sql` — schema changes

Append **only** at the end of the file. Never reorder or edit past statements.

**Belongs here:**

- `ALTER TABLE` (add/change/drop columns, indexes, constraints, engine, charset)
- `CREATE INDEX` / `DROP INDEX` when not part of initial `CREATE TABLE`
- `CREATE TABLE` for **new** tables on already-deployed databases (when the table is also added to `00_tables.sql` for greenfield installs)
- One-off **data migrations tied to a schema change** (e.g. `UPDATE` to backfill a column immediately after `ADD COLUMN`)
- `RENAME TABLE`, `CHANGE COLUMN`, engine/charset normalization

**Does not belong here:**

- Inserts/updates of static catalog data (abilities, i18n, elements, buff definitions, etc.) → use `02_insert_static_data.sql`

Use short comment blocks before each logical change (what / why). Statements should be safe to re-run where possible, or clearly documented if run-once.

---

## `02_insert_static_data.sql` — static data (inserts & updates)

Append **only** at the end of the file.

**Belongs here:**

- Reference / catalog data: `abilities`, `species`, `elements`, `item_types`, `language_texts`, `buff_definitions`, `costanti`, NPC/dialogue seeds, etc.
- `INSERT` statements
- `UPDATE` statements that change static configuration or copy (not schema backfills — those go in `01` when tied to an `ALTER`)

### Required: `ON DUPLICATE KEY UPDATE`

Every `INSERT` **must** end with `ON DUPLICATE KEY UPDATE`, listing **every non-key column** from the `INSERT`. Do **not** update primary keys or unique key columns.

**Exclude from the update clause:**

- Primary key columns (e.g. `id_ability`, `id_buff_definition`)
- Columns that form the unique key used for upsert (e.g. `tag` on `language_texts`, `buff_code` on `buff_definitions`)

**Include in the update clause:**

- All other inserted columns (`text`, `text_it`, `name`, `modifier_value`, `dt_modifica`, etc.)

Use `VALUES(column_name)` (MariaDB/MySQL) to reference the inserted row.

### Examples

**`language_texts` (unique key: `tag`):**

```sql
INSERT INTO playanimaster_db.language_texts (dt_c, tag, text, text_it, text_pt) VALUES
(NOW(), 'team.title', 'Team', 'Squadra', 'Equipa')
ON DUPLICATE KEY UPDATE
    text = VALUES(text),
    text_it = VALUES(text_it),
    text_pt = VALUES(text_pt);
```

(`dt_c` is typically not updated on duplicate; `tag` is the unique key and must not appear in the update clause.)

**Row with explicit primary key (e.g. `elements`):**

```sql
INSERT INTO playanimaster_db.elements (id_element, `element`, element_it, element_pt, color) VALUES
(1, 'Water', 'Acqua', 'Agua', '#058ef0')
ON DUPLICATE KEY UPDATE
    `element` = VALUES(`element`),
    element_it = VALUES(element_it),
    element_pt = VALUES(element_pt),
    color = VALUES(color);
```

(`id_element` is the primary key — omit from `ON DUPLICATE KEY UPDATE`.)

**Standalone `UPDATE` (no insert):**

Use when changing existing static rows in place. Add a brief comment. Prefer `INSERT ... ON DUPLICATE KEY UPDATE` for new catalog rows so deploys stay idempotent.

---

## Checklist for agents

Before finishing a task that touches the database:

- [ ] All new SQL is in `private_functions/SQL/` (correct file per rules above)
- [ ] `00_tables.sql` was **not** edited (unless the user explicitly asked for a greenfield baseline change)
- [ ] New alters appended to `01_alters_structure.sql`
- [ ] New static data appended to `02_insert_static_data.sql` with full `ON DUPLICATE KEY UPDATE`
- [ ] No secrets or environment-specific credentials in SQL files

---

## Related application code

When SQL adds columns or tables, update PHP/JS that reads or writes them in the same change when required. Module docs under `docs/modules/` may describe behavior but must **not** hold authoritative SQL — point to these files instead.
