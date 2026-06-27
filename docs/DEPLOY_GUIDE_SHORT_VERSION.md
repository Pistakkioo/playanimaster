ON powershell:

cd D:\_GITHUB_PROJECTS_\playanimaster
.\scripts\deploy.ps1 -DryRun
You should see something like:

ok
rsync not found - using tar+scp fallback.
[dry-run] tar+scp full sync -> /var/www/playanimaster/
  Excludes: .git, .env, LOG, ...
Deploy complete.
Then deploy for real:


.\scripts\deploy.ps1