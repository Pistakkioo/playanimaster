# Deploy to production — step-by-step guide

Use this checklist every time you push code from your PC to the live server.

**Script:** `scripts/deploy.ps1`  
**Server:** `dev@5.250.188.110:21709` → `/var/www/playanimaster/`  
**Related:** [DEVOPS_GUIDE.md](DEVOPS_GUIDE.md) (Git + Docker setup), [MODULES.md](MODULES.md) (SQL order)

---

## What deploy does

```
Your PC (D:\_GITHUB_PROJECTS_\playanimaster)
        │
        │  SSH + rsync  (or tar+scp if rsync missing)
        ▼
Production (/var/www/playanimaster/)
```

- **Full sync:** `.\scripts\deploy.ps1` — uploads the whole project (with excludes below).
- **Partial sync:** `.\scripts\deploy.ps1 -Files @('path/to/file.php')` — uploads only listed files via `scp`.
- **Dry run:** `.\scripts\deploy.ps1 -DryRun` — shows what would happen without uploading.

You do **not** need rsync installed. If it is missing, the script uses **tar + scp** (built into Windows).

Optional: install rsync for faster sync and `--delete` (removes orphan files on server):

```powershell
scoop install cwrsync    # package name is cwrsync, not "rsync"
```

---

## One-time setup (or after reboot)

Run in **PowerShell on your PC** (Windows Terminal — not necessarily inside Cursor).

### 1. Enable SSH agent (once, as Administrator)

```powershell
Get-Service ssh-agent | Set-Service -StartupType Automatic
Start-Service ssh-agent
```

### 2. Load your SSH key (each session)

```powershell
ssh-add C:\Users\sergi\.ssh\id_ed25519
```

Enter your key passphrase if prompted.

Verify:

```powershell
ssh-add -l
```

You should see your key listed.

### 3. Test SSH to the server

```powershell
ssh -p 21709 -i C:\Users\sergi\.ssh\id_ed25519 dev@5.250.188.110 "echo ok"
```

Expected output: `ok`

If you get **Permission denied (publickey)**, go to [Troubleshooting](#troubleshooting).

---

## Every deploy — full checklist

### Phase A — Before deploy (local)

| Step | Action |
|------|--------|
| A1 | Start Docker and test locally |
| A2 | Bump asset version if JS/CSS changed |
| A3 | Run new SQL on **local** DB if schema changed |
| A4 | Commit and push to GitHub (recommended) |

**A1 — Test in Docker**

```powershell
cd D:\_GITHUB_PROJECTS_\playanimaster
docker compose up -d
```

Open http://localhost:8080 and verify your changes.

**A2 — Cache bust (JS/CSS only)**

If you changed client assets, bump `ANIMASTER_ASSET_VERSION` in `private_functions/character_config.php`.

**A3 — Local SQL**

If the module adds or alters tables, run the SQL file on your **local** MariaDB first (Adminer at http://localhost:8081 or `docker compose exec`). Note the filename — you will run the same file on production after deploy.

See [MODULES.md](MODULES.md) for SQL file order.

**A4 — Git**

```powershell
git add ...
git commit -m "Describe the change"
git push
```

---

### Phase B — Deploy code to server

| Step | Action |
|------|--------|
| B1 | Open PowerShell, go to project, confirm SSH key loaded |
| B2 | Optional dry-run |
| B3 | Run deploy (partial or full) |
| B4 | Confirm `Deploy complete.` |

**B1 — Session check**

```powershell
cd D:\_GITHUB_PROJECTS_\playanimaster
ssh-add -l
```

If empty, run `ssh-add C:\Users\sergi\.ssh\id_ed25519` again.

**B2 — Dry run (recommended for full sync)**

```powershell
.\scripts\deploy.ps1 -DryRun
```

With rsync: lists files that would be sent.  
Without rsync (tar mode): prints excludes and a note that tar mode does not delete removed files on the server.

**B3 — Deploy**

**Few files changed** (typical for a small fix):

```powershell
.\scripts\deploy.ps1 -Files @(
  'public_html/client/js/dialog.js',
  'private_functions/f.php'
)
```

**Many files / first deploy / big module:**

```powershell
.\scripts\deploy.ps1
```

**B4 — Success message**

Wait for: **`Deploy complete.`**

If you see **`Deploy failed.`**, check [Troubleshooting](#troubleshooting).

---

### Phase C — After deploy

| Step | Action |
|------|--------|
| C1 | Run new SQL on **production** (if any) |
| C2 | Smoke test live site |
| C3 | Check logs if something breaks |

**C1 — Production SQL**

Run **only** SQL that is not already applied on production. Examples:

- Schema alters: `docs/01_alters_structure.sql` (relevant statements only)
- Module files: `private_functions/SQL/*.sql`

Via SSH + `mysql` / `mariadb`, or phpMyAdmin on the server:

```bash
mysql -u USER -p playanimaster_db < /var/www/playanimaster/private_functions/SQL/trade_system.sql
```

Keep a simple log (file name + date) so you do not run the same migration twice.

**C2 — Smoke test**

- Hard refresh the live site (Ctrl+F5)
- Test the feature you changed (two accounts if needed for multiplayer features)
- Dev admin pages (token in server `.env` / config):
  - `https://YOUR-DOMAIN/dev_npcs.php?T=TOKEN`
  - `https://YOUR-DOMAIN/dev_static_data.php?T=TOKEN`

**C3 — Logs**

If something breaks, check on the server:

```
/var/www/playanimaster/LOG/error.log
```

---

## What never gets uploaded

The deploy script **excludes** these paths (server copies are kept):

| Excluded | Reason |
|----------|--------|
| `.git` | Version history stays local / on GitHub |
| `.env` | Local secrets |
| `LOG/` | Runtime logs |
| `docker/data/` | Local MariaDB volume |
| `old_cs_files/` | Reference only |
| `private_functions/d.php` | **Production DB credentials** |

Production keeps its own `d.php`. Do not add production credentials to the repo.

---

## rsync vs tar+scp

| Mode | When | Deletes removed files on server? |
|------|------|----------------------------------|
| **rsync** | `cwrsync` installed | Yes (`--delete`) |
| **tar+scp** | Default fallback on Windows | No — only adds/updates |

If you removed files from the repo and need them gone on production, either install `cwrsync` and run a full rsync deploy, or delete those files manually on the server.

---

## Quick reference — copy/paste

```powershell
# --- Session start (after reboot) ---
Start-Service ssh-agent
ssh-add C:\Users\sergi\.ssh\id_ed25519

# --- Deploy ---
cd D:\_GITHUB_PROJECTS_\playanimaster
.\scripts\deploy.ps1 -DryRun          # optional
.\scripts\deploy.ps1                  # full sync
# OR
.\scripts\deploy.ps1 -Files @('public_html/client/js/chat.js')

# --- Then ---
# 1. Run any new SQL on production DB
# 2. Smoke test live site (Ctrl+F5)
```

---

## Pre-deploy checklist (print this)

- [ ] Tested on Docker locally (http://localhost:8080)
- [ ] SQL migration tested locally (if any)
- [ ] `ANIMASTER_ASSET_VERSION` bumped (if JS/CSS changed)
- [ ] No secrets in commit (`git diff` — no passwords)
- [ ] `ssh-add -l` shows your key
- [ ] `.\scripts\deploy.ps1` or `-Files` completed successfully
- [ ] New SQL run on production (if any)
- [ ] Live smoke test passed

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Permission denied (publickey)` | `ssh-add C:\Users\sergi\.ssh\id_ed25519` |
| `Error connecting to agent` | `Start-Service ssh-agent` then `ssh-add` again |
| `SSH failed. Unlock your key` | Same as above — agent not running or key not loaded |
| `rsync not found` | Normal — tar+scp fallback runs automatically |
| PowerShell parse error on `-DryRun` | Update `deploy.ps1` from repo (known fix applied) |
| Site shows old JS/CSS | Bump `ANIMASTER_ASSET_VERSION`, redeploy, Ctrl+F5 |
| PHP errors / missing columns after deploy | Run pending SQL on production |
| Deploy from Cursor agent fails SSH | Run deploy in **your own** PowerShell — agent may not have ssh-agent |

**Manual SSH test:**

```powershell
ssh -p 21709 -i C:\Users\sergi\.ssh\id_ed25519 dev@5.250.188.110
```

---

## Rule of thumb

```
Docker test → commit → ssh-add → deploy.ps1 → SQL on server (if any) → smoke test
```

Do not use the VS Code SFTP extension `uploadOnSave` as your primary deploy path — use this script instead.
