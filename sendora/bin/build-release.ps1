# Build WordPress.org-ready sendora.zip (forward-slash paths).
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$out = Join-Path (Split-Path -Parent $root) 'sendora.zip'
$exclude = @(
    '.git', '.gitattributes', '.gitignore', '.distignore', '.github', '.superpowers',
    '.phpunit.cache', 'composer.json', 'composer.lock', 'phpunit.xml.dist',
    'vendor', 'tests', 'docs', 'bin', 'node_modules'
)

if (Test-Path $out) { Remove-Item $out -Force }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$fs = [System.IO.File]::Open($out, [System.IO.FileMode]::Create)
$zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    Get-ChildItem $root -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
        $top = $rel.Split('/')[0]
        if ($exclude -contains $top) { return }
        if ($rel.ToLower().EndsWith('.md')) { return }
        [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip,
            $_.FullName,
            "sendora/$rel"
        )
    }
}
finally {
    $zip.Dispose()
    $fs.Dispose()
}

Write-Host "Built $out ($((Get-Item $out).Length) bytes)"
