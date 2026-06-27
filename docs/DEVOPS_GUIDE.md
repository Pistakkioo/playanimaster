# ANIMASTER — GitHub + Docker + Deploy (beginner guide)

This guide moves ANIMASTER from **edit-on-server / SFTP** to:

1. **GitHub** — version history, branches per feature  
2. **Docker on your PC** — PHP + MariaDB locally  
3. **Controlled deploy** — push finished modules to production via SSH (not the buggy SFTP extension)

**Deploy checklist (step-by-step):** [DEPLOY_GUIDE.md](DEPLOY_GUIDE.md)

---

## What you have today

| Piece | Value |
|-------|--------|
| Local folder | `ANIMASTER/` |
| Production path | `/var/www/playanimaster/` on `5.250.188.110:21709` |
| SSH user | `dev` |
| Web root | `public_html/` (game at `public_html/client/`) |
| PHP includes | `private_functions/` (sibling of `public_html`, not inside it) |
| Database | MariaDB, schema name `playanimaster_db` |

The SFTP extension error (`isDate is not a function`) is a known extension bug — **Git + SSH deploy avoids it**.

---

## Big picture (read once)

```
┌─────────────┐     git push      ┌──────────────┐
│ Your PC     │ ───────────────►  │ GitHub       │
│ Docker      │                   │ (backup +    │
│ localhost   │                   │  history)    │
└──────┬──────┘                   └──────────────┘
       │
       │  deploy.ps1 (SSH/rsync) when module is done
       ▼
┌─────────────┐
│ Production  │
│ server      │
└─────────────┘
```

**Rule:** develop on a branch → test in Docker → merge to `main` → deploy → run SQL on server.

---

## Phase 0 — One-time tools (Windows)

Install in this order:

1. **Git** — https://git-scm.com/download/win  
2. **GitHub account** — https://github.com  
3. **Docker Desktop** — https://www.docker.com/products/docker-desktop/  
4. **GitHub CLI** (optional but easy) — `winget install GitHub.cli`

Verify:

```powershell
git --version
docker --version
docker compose version
```

---

## Phase 1 — Create the GitHub repository

### 1.1 Use only the ANIMASTER folder

The repo should contain **only** `ANIMASTER/`, not `OLD_BISUITES/` or the whole `BISUITE_CURSOR` workspace.

```powershell
cd D:\_GITHUB_PROJECTS_\playanimaster
git init
git branch -M main
```

### 1.2 First commit

```powershell
copy .env.example .env
# Edit .env with local passwords

copy docker\i.php.example private_functions\i.php
# Edit private_functions\i.php if needed (Docker uses env vars from compose)

git add .
git status
git commit -m "Initial ANIMASTER project structure with Docker scaffold"
```

**Important:** `private_functions/i.php` is in `.gitignore` — production credentials stay on the server only.

### 1.3 Create repo on GitHub and push

With GitHub CLI:

```powershell
gh auth login
gh repo create animaster --private --source=. --remote=origin --push
```

Or create an empty repo on github.com, then:

```powershell
git remote add origin https://github.com/YOUR_USER/animaster.git
git push -u origin main
```

---

## Phase 2 — Local Docker (daily development)

### 2.1 Start the stack

```powershell
cd D:\_GITHUB_PROJECTS_\playanimaster
copy .env.example .env
docker compose up -d --build
```

| Service | URL |
|---------|-----|
| Game | http://localhost:8080/client/login.php |
| Adminer (DB UI) | http://localhost:8081 |
| MariaDB from host | `localhost:3307` |

### 2.2 Database setup (first time)

Docker does **not** auto-import your full production DB (too risky). Choose one:

**Option A — Fresh local DB (recommended for learning)**  
Run SQL files in order (see `docs/MODULES.md`) via Adminer or:

```powershell
docker compose exec db mariadb -u animaster -p playanimaster_db < private_functions/SQL/chat_system.sql
```

**Option B — Copy production dump (real data)**  
On server (ask someone with access):

```bash
mysqldump -u USER -p playanimaster_db > animaster_dump.sql
```

Download dump, then:

```powershell
Get-Content animaster_dump.sql | docker compose exec -T db mariadb -u root -p playanimaster_db
```

### 2.3 Stop / reset

```powershell
docker compose down          # stop
docker compose down -v         # wipe DB volume (careful!)
```

### 2.4 Sync code from server (if local files are empty/outdated)

Your workspace may have 0-byte placeholders. Pull production once:

```powershell
# With rsync (Git Bash or WSL):
rsync -avz -e "ssh -p 21709 -i ~/.ssh/id_ed25519" `
  dev@5.250.188.110:/var/www/playanimaster/ `
  ./ --exclude LOG --exclude .git
```

Then commit the real files to GitHub.

---

## Phase 3 — Workflow per module (repeat every feature)

Example: “trade quantity selector” module.

### Step 1 — Branch

```powershell
git checkout main
git pull
git checkout -b feature/trade-quantity
```

### Step 2 — Develop locally

- Edit PHP/JS/CSS  
- Bump `ANIMASTER_ASSET_VERSION` in `private_functions/character_config.php` when client assets change  
- Test at http://localhost:8080  

### Step 3 — SQL (if any)

- Add file under `private_functions/SQL/`  
- Run it on **local** DB first  
- Document it in `docs/MODULES.md`

### Step 4 — Commit

```powershell
git add public_html/client/js/trade.js private_functions/character_config.php
git commit -m "Trade: quantity selector for stackable items"
git push -u origin feature/trade-quantity
```

### Step 5 — Merge

On GitHub: open Pull Request → review diff → Merge to `main`.

```powershell
git checkout main
git pull
```

### Step 6 — Deploy to production

Follow **[DEPLOY_GUIDE.md](DEPLOY_GUIDE.md)** (SSH key, dry-run, full or partial deploy, production SQL, smoke test).

### Step 7 — Tag release (optional)

```powershell
git tag -a v1.0-trade -m "Trade system live"
git push origin v1.0-trade
```

---

## Phase 4 — Module roadmap (suggested order)

Deploy after each block is tested locally:

| # | Module | Deploy includes |
|---|--------|-----------------|
| 1 | Repo + Docker scaffold | `docker-compose.yml`, `.gitignore`, this guide |
| 2 | Core game client | `public_html/client/*`, `character_config.php` |
| 3 | Chat | `chat.php`, `chat.js`, SQL chat_* |
| 4 | Chat word filter | `chat_word_filter.php`, SQL replacements |
| 5 | Target panel | `target.js`, `world.js`, CSS |
| 6 | Trade | `trade.php`, `trade.js`, SQL trade_* |

See `docs/MODULES.md` for SQL file order.

---

## Phase 5 — Automation (when comfortable)

### 5.1 GitHub Actions — deploy on tag (optional)

When you tag `v*`, CI can SSH and rsync. **Do not store production SSH keys in GitHub until you understand secrets.**

Skeleton (add later as `.github/workflows/deploy.yml`):

- Trigger: `push` tags `v*`
- Steps: checkout → rsync over SSH using `DEPLOY_KEY` secret

### 5.2 Pre-deploy checklist

See the printable checklist in **[DEPLOY_GUIDE.md](DEPLOY_GUIDE.md)**.

---

## Troubleshooting

### Local: “DB connection failed”

- Is `docker compose ps` showing `db` healthy?  
- Does `private_functions/i.php` exist (copy from `docker/i.php.example`)?  
- Do `.env` passwords match `docker-compose.yml`?

### Local: blank page / 500

```powershell
docker compose logs web
```

### Deploy: Permission denied (publickey)

```powershell
ssh-add C:\Users\sergi\.ssh\id_ed25519
ssh -p 21709 -i C:\Users\sergi\.ssh\id_ed25519 dev@5.250.188.110
```

### Production: old JS still loads

- Bump `ANIMASTER_ASSET_VERSION`  
- Hard refresh / clear cache  

### SFTP extension errors

Stop relying on `uploadOnSave`. Use:

```powershell
.\scripts\deploy.ps1 -Files @('path/to/file.php')
```

---

## Security reminders

- Never commit `private_functions/i.php`, `.env`, or SSH private keys  
- Use **private** GitHub repo for a live game  
- Production DB credentials only on the server  
- Run SQL migrations manually until you have a proper migration tracker  

---

## Quick reference commands

```powershell
# Daily start
cd D:\_GITHUB_PROJECTS_\playanimaster
docker compose up -d
git pull

# New feature
git checkout -b feature/my-feature

# Deploy one file
.\scripts\deploy.ps1 -Files @('public_html/client/js/chat.js')

# Stop Docker
docker compose down
```

---

## Next steps for you

1. Fix local files if many are 0 bytes — rsync from server (Phase 2.4)  
2. `git init` + push to GitHub (Phase 1)  
3. `docker compose up` and confirm login page loads (Phase 2)  
4. Deploy one small change with `deploy.ps1` to verify SSH path (Phase 3)  
5. Retire SFTP `uploadOnSave` as primary workflow  

When you finish module 1 on GitHub, you can ask for a ready-made GitHub Actions workflow tailored to your server.
