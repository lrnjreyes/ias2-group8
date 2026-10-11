param([string]$DependencyRoot = (Join-Path (Split-Path -Parent $PSScriptRoot) 'dependencies'))

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$env:PSModulePath = (Join-Path $PSHOME 'Modules') + [IO.Path]::PathSeparator + $env:PSModulePath

$phpRoot = Join-Path $DependencyRoot 'php'
$archivePath = Join-Path $DependencyRoot 'cache\php-8.5.11.zip'
$expectedHash = '0ea96e0d2b9b737a6036f05cf4e95c49313faa6d0f27bd97edb2742503f0c043'

try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    New-Item -ItemType Directory -Path $phpRoot -Force | Out-Null
    New-Item -ItemType Directory -Path (Split-Path -Parent $archivePath) -Force | Out-Null

    if (-not (Test-Path -LiteralPath (Join-Path $phpRoot 'php.exe'))) {
        $archiveVerified = (Test-Path -LiteralPath $archivePath) -and
            ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant() -eq $expectedHash)

        if (-not $archiveVerified) {
            Write-Host 'Downloading PHP 8.5.11 from php.net for this project...'
            Invoke-WebRequest -UseBasicParsing `
                -Uri 'https://downloads.php.net/~windows/releases/archives/php-8.5.11-nts-Win32-vs17-x64.zip' `
                -OutFile $archivePath
        }

        if ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash) {
            throw 'The downloaded PHP archive failed its checksum verification.'
        }
        Expand-Archive -LiteralPath $archivePath -DestinationPath $phpRoot -Force
    }

    $certificatePath = Join-Path $phpRoot 'cacert.pem'
    if (-not (Test-Path -LiteralPath $certificatePath)) {
        Invoke-WebRequest -UseBasicParsing -Uri 'https://curl.se/ca/cacert.pem' -OutFile $certificatePath
    }

    $extensionPath = (Join-Path $phpRoot 'ext').Replace('\', '/')
    $certificatePath = $certificatePath.Replace('\', '/')
    $phpSettings = @"
extension_dir="$extensionPath"
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=intl
extension=sodium
memory_limit=512M
opcache.enable=0
opcache.enable_cli=0
date.timezone=Asia/Manila
curl.cainfo="$certificatePath"
openssl.cafile="$certificatePath"
"@
    [IO.File]::WriteAllText((Join-Path $phpRoot 'php.ini'), $phpSettings, (New-Object Text.UTF8Encoding($false)))

    $phpExe = Join-Path $phpRoot 'php.exe'
    & $phpExe -r 'exit(PHP_VERSION_ID >= 80300 && extension_loaded(''pdo_sqlite'') && extension_loaded(''mbstring'') && extension_loaded(''openssl'') && extension_loaded(''fileinfo'') ? 0 : 1);'
    if ($LASTEXITCODE -ne 0) {
        throw 'PHP could not start with the required extensions. If the Microsoft Visual C++ x64 runtime is missing, install it from https://aka.ms/vs/17/release/vc_redist.x64.exe and run the launcher again.'
    }

    Write-Host 'Project-local PHP is ready.'
    exit 0
}
catch {
    Write-Host ('PHP setup failed: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
}
