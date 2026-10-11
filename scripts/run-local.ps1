param(
    [ValidateRange(1, 65535)][int]$Port = 8000,
    [switch]$SetupOnly
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$env:PSModulePath = (Join-Path $PSHOME 'Modules') + [IO.Path]::PathSeparator + $env:PSModulePath
$projectRoot = Split-Path -Parent $PSScriptRoot
$dependencyRoot = Join-Path $projectRoot 'dependencies'
$cacheRoot = Join-Path $dependencyRoot 'cache'

function Get-VerifiedDownload {
    param([string]$Url, [string]$Destination, [string]$ExpectedHash)
    if ((Test-Path -LiteralPath $Destination) -and
        ((Get-FileHash -LiteralPath $Destination -Algorithm SHA256).Hash.ToLowerInvariant() -eq $ExpectedHash)) {
        return
    }
    New-Item -ItemType Directory -Path (Split-Path -Parent $Destination) -Force | Out-Null
    Invoke-WebRequest -UseBasicParsing -Uri $Url -OutFile $Destination
    if ((Get-FileHash -LiteralPath $Destination -Algorithm SHA256).Hash.ToLowerInvariant() -ne $ExpectedHash) {
        throw ('Download checksum verification failed: ' + $Url)
    }
}

function Invoke-Checked {
    param([string]$Executable, [string[]]$Arguments)
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw ('Command failed with exit code ' + $LASTEXITCODE + ': ' + $Executable)
    }
}

function Find-LocalPort {
    param([int]$RequestedPort)
    for ($candidate = $RequestedPort; $candidate -le [Math]::Min($RequestedPort + 9, 65535); $candidate++) {
        $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, $candidate)
        try {
            $listener.Server.ExclusiveAddressUse = $true
            $listener.Start()
            return $candidate
        }
        catch [Net.Sockets.SocketException] { }
        finally { $listener.Stop() }
    }
    throw 'No free local port was found. Run run-local.bat with another port number.'
}

try {
    if (-not [Environment]::Is64BitOperatingSystem) {
        throw 'This launcher requires 64-bit Windows.'
    }
    Set-Location -LiteralPath $projectRoot
    foreach ($requiredFile in @('artisan', '.env.example', 'composer.lock', 'package-lock.json')) {
        if (-not (Test-Path -LiteralPath $requiredFile)) {
            throw ('Missing ' + $requiredFile + '. Extract or clone the complete project before running this launcher.')
        }
    }
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    New-Item -ItemType Directory -Path $cacheRoot -Force | Out-Null
    & (Join-Path $PSScriptRoot 'setup-local-php.ps1') -DependencyRoot $dependencyRoot
    if ($LASTEXITCODE -ne 0) { throw 'PHP setup did not complete.' }
    $phpExe = Join-Path $dependencyRoot 'php\php.exe'

    $nodeRoot = Join-Path $dependencyRoot 'node-v24.15.0-win-x64'
    $nodeExe = Join-Path $nodeRoot 'node.exe'
    if (-not (Test-Path -LiteralPath $nodeExe)) {
        Write-Host 'Downloading Node.js for this project...'
        $nodeArchive = Join-Path $cacheRoot 'node-v24.15.0-win-x64.zip'
        Get-VerifiedDownload -Url 'https://nodejs.org/dist/v24.15.0/node-v24.15.0-win-x64.zip' `
            -Destination $nodeArchive -ExpectedHash 'cc5149eabd53779ce1e7bdc5401643622d0c7e6800ade18928a767e940bb0e62'
        Expand-Archive -LiteralPath $nodeArchive -DestinationPath $dependencyRoot -Force
    }
    Invoke-Checked -Executable $nodeExe -Arguments @('--version')
    $npmCmd = Join-Path $nodeRoot 'npm.cmd'

    $composerPhar = Join-Path $dependencyRoot 'composer\composer.phar'
    if (-not (Test-Path -LiteralPath $composerPhar)) { Write-Host 'Downloading Composer for this project...' }
    Get-VerifiedDownload -Url 'https://getcomposer.org/download/2.10.3/composer.phar' `
        -Destination $composerPhar -ExpectedHash '7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6'
    $env:Path = (Split-Path -Parent $phpExe) + ';' + $nodeRoot + ';' + $env:Path
    $env:COMPOSER_CACHE_DIR = Join-Path $cacheRoot 'composer'
    $env:npm_config_cache = Join-Path $cacheRoot 'npm'

    foreach ($directory in @('storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'bootstrap\cache')) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }
    if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }

    $statePath = Join-Path $dependencyRoot 'setup-state.json'
    $previousState = $null
    if (Test-Path -LiteralPath $statePath) {
        try { $previousState = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json }
        catch { $previousState = $null }
    }
    $composerHash = (Get-FileHash -LiteralPath 'composer.lock' -Algorithm SHA256).Hash
    $packageHash = (Get-FileHash -LiteralPath 'package-lock.json' -Algorithm SHA256).Hash
    if (-not (Test-Path -LiteralPath 'vendor\autoload.php') -or
        ($previousState -and $previousState.ComposerLock -ne $composerHash)) {
        Write-Host 'Installing the PHP application packages. First setup can take several minutes...'
        Invoke-Checked -Executable $phpExe -Arguments @($composerPhar, 'install', '--no-interaction', '--prefer-dist', '--no-progress')
    }
    Invoke-Checked -Executable $phpExe -Arguments @($composerPhar, 'check-platform-reqs', '--no-interaction')
    Invoke-Checked -Executable $phpExe -Arguments @('artisan', 'config:clear', '--no-ansi')
    if (-not ((Get-Content -LiteralPath '.env' -Raw) -match '(?m)^APP_KEY=\S.+$')) {
        Invoke-Checked -Executable $phpExe -Arguments @('artisan', 'key:generate', '--force', '--no-ansi')
    }
    if (-not (Test-Path -LiteralPath 'database\database.sqlite')) {
        New-Item -ItemType File -Path 'database\database.sqlite' | Out-Null
    }
    Invoke-Checked -Executable $phpExe -Arguments @('artisan', 'migrate', '--force', '--no-ansi')
    $userCheck = 'require ''vendor/autoload.php''; $app = require ''bootstrap/app.php''; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(App\Models\User::exists() ? 0 : 2);'
    & $phpExe -r $userCheck
    if ($LASTEXITCODE -eq 2) {
        Invoke-Checked -Executable $phpExe -Arguments @('artisan', 'db:seed', '--force', '--no-ansi')
    }
    elseif ($LASTEXITCODE -ne 0) { throw 'Could not check the local accounts.' }

    if (-not (Test-Path -LiteralPath 'node_modules\vite\bin\vite.js') -or
        ($previousState -and $previousState.PackageLock -ne $packageHash)) {
        Write-Host 'Installing the frontend packages...'
        Invoke-Checked -Executable $npmCmd -Arguments @('ci', '--no-audit', '--no-fund')
    }
    Write-Host 'Building the app...'
    Invoke-Checked -Executable $npmCmd -Arguments @('run', 'build')
    [ordered]@{ ComposerLock = $composerHash; PackageLock = $packageHash } |
        ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding ascii
    if ($SetupOnly) {
        Write-Host 'Local setup completed.'
        exit 0
    }

    $serverPort = Find-LocalPort -RequestedPort $Port
    Write-Host ''
    Write-Host ('Open http://127.0.0.1:' + $serverPort + '/login')
    Write-Host 'Keep this window open. Press Ctrl+C to stop the server.'
    Invoke-Checked -Executable $phpExe -Arguments @('artisan', 'serve', '--host=127.0.0.1', ('--port=' + $serverPort), '--tries=1', '--no-reload')
    exit 0
}
catch {
    Write-Host ('Local startup failed: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
}
