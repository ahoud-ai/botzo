param(
    [switch]$Apply,
    [string]$BuildDir = "public/build",
    [ValidateSet("text", "json")]
    [string]$Format = "text"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$command = @("php", "scripts/prune-build-assets.php", "--build-dir=$BuildDir", "--format=$Format")
if ($Apply) {
    $command += "--apply"
}

& $command[0] $command[1..($command.Count - 1)]
exit $LASTEXITCODE
