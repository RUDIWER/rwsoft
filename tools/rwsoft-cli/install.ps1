$ErrorActionPreference = 'Stop'

$BaseUrl = $env:RWSOFT_CLI_BASE_URL
if (-not $BaseUrl) {
    $BaseUrl = 'https://github.com/RUDIWER/rwsoft/releases/latest/download'
}

$arch = if ([System.Runtime.InteropServices.RuntimeInformation]::ProcessArchitecture -eq 'Arm64') { 'arm64' } else { 'amd64' }
$asset = "rwsoft-windows-$arch.exe"
$url = "$BaseUrl/$asset"
$checksumsUrl = "$BaseUrl/checksums.txt"
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) ('rwsoft-installer-' + [System.Guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $tmp | Out-Null
$exe = Join-Path $tmp 'rwsoft.exe'
$checksums = Join-Path $tmp 'checksums.txt'

try {
    Write-Host "Downloading $url"
    Invoke-WebRequest -Uri $url -OutFile $exe
    Invoke-WebRequest -Uri $checksumsUrl -OutFile $checksums

    $line = Get-Content $checksums | Where-Object { $_ -match "\s+$([Regex]::Escape($asset))$" } | Select-Object -First 1
    if (-not $line) {
        throw "Checksum entry not found for $asset"
    }

    $expected = ($line -split '\s+')[0].ToLowerInvariant()
    $actual = (Get-FileHash -Path $exe -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($expected -ne $actual) {
        throw "Checksum verification failed for $asset"
    }

    $installArgs = @($args)
    $hasRefArg = $false
    foreach ($arg in $installArgs) {
        if ($arg -eq '--branch' -or $arg -like '--branch=*' -or $arg -eq '--source' -or $arg -like '--source=*') {
            $hasRefArg = $true
            break
        }
    }

    if (-not $hasRefArg) {
        $installArgs += '--branch=latest'
    }

    & $exe install @installArgs
    exit $LASTEXITCODE
}
finally {
    Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue
}
