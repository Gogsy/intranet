# =============================================================================
# Build a clean, production-ready release of the app into a SEPARATE folder.
#
# The output folder is self-contained: full production vendor/, built assets,
# empty storage skeleton, NO .env, NO dev junk. WinSCP the whole output folder
# to /var/www/sites/intranet.overseas.hr — no per-file thinking, no exclusions.
#
# Your working copy (D:\Projects\applikacije) is NOT modified.
#
# Usage (from the project root):
#   powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
#   powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1 -Zip
#
# Requires Herd's php/composer on PATH and Node (npm) for the asset build.
# =============================================================================

param(
    [string]$Dest = "D:\Projects\applikacije-release",
    [switch]$Zip
)

$ErrorActionPreference = "Stop"
$src = (Resolve-Path "$PSScriptRoot\..").Path
$herd = "C:\Users\g.jevtovic\.config\herd\bin"
$env:Path = "$herd;$env:Path"

Write-Host "== Building release ==" -ForegroundColor Cyan
Write-Host "  source: $src"
Write-Host "  dest:   $Dest"

# 1) Build front-end assets in the source (lands in public/build, which we copy).
Write-Host "`n[1/5] Building assets (npm run build)..." -ForegroundColor Cyan
Push-Location $src
npm run build
Pop-Location

# 2) Mirror the project into the release folder, excluding dev-only / secret /
#    regenerated paths. robocopy /MIR makes the dest an exact mirror.
Write-Host "`n[2/5] Copying project (excluding dev/secret paths)..." -ForegroundColor Cyan
$excludeDirs = @(
    "$src\vendor",                          # regenerated below (prod, no dev deps)
    "$src\node_modules",                    # not shipped
    "$src\.git",
    "$src\storage\logs",
    "$src\storage\framework\cache",
    "$src\storage\framework\sessions",
    "$src\storage\framework\views"
)
$excludeFiles = @(".env", "*.log")
# robocopy returns 0-7 on success (8+ = real error); swallow the non-zero "ok" codes.
robocopy $src $Dest /MIR /XD $excludeDirs /XF $excludeFiles /NFL /NDL /NJH /NJS /NP
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with code $LASTEXITCODE" }
$global:LASTEXITCODE = 0

# 3) Install PRODUCTION dependencies into the release vendor/.
Write-Host "`n[3/5] composer install --no-dev --optimize-autoloader..." -ForegroundColor Cyan
composer.bat install --no-dev --optimize-autoloader --working-dir="$Dest"

# 4) Recreate the empty storage skeleton + clear bootstrap cache in the release.
Write-Host "`n[4/5] Resetting storage skeleton + caches..." -ForegroundColor Cyan
$skeleton = @(
    "storage\app\public",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\logs"
)
foreach ($d in $skeleton) {
    $p = Join-Path $Dest $d
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Force -Path $p | Out-Null }
    if (-not (Test-Path "$p\.gitignore")) { "*`n!.gitignore" | Out-File -Encoding ascii "$p\.gitignore" }
}
# Drop any copied compiled config/routes and the public/storage symlink artifact.
Get-ChildItem -Path (Join-Path $Dest "bootstrap\cache") -Filter *.php -ErrorAction SilentlyContinue | Remove-Item -Force
Remove-Item -Force -ErrorAction SilentlyContinue (Join-Path $Dest "public\storage")
# Never ship a real .env; leave the example so the server copies it.
Remove-Item -Force -ErrorAction SilentlyContinue (Join-Path $Dest ".env")

# 5) Optional zip for a single-file upload.
if ($Zip) {
    Write-Host "`n[5/5] Zipping..." -ForegroundColor Cyan
    $zipPath = "$Dest.zip"
    if (Test-Path $zipPath) { Remove-Item -Force $zipPath }
    Compress-Archive -Path "$Dest\*" -DestinationPath $zipPath
    Write-Host "  -> $zipPath"
} else {
    Write-Host "`n[5/5] Skipping zip (pass -Zip to create one)." -ForegroundColor DarkGray
}

Write-Host "`n== Release ready ==" -ForegroundColor Green
Write-Host "Upload the WHOLE folder to /var/www/sites/intranet.overseas.hr :"
Write-Host "  $Dest"
Write-Host "Then on the server: create .env, then run the SETUP.md sec.7.4 commands"
Write-Host "(key:generate, migrate --force, shield:generate, storage:link, optimize, chown)."
