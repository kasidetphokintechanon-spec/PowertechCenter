$ErrorActionPreference = "Stop"

$BaseUrl = "http://localhost/PowertechCenter"

$targets = @(
  "$BaseUrl/api.php?action=check_session",
  "$BaseUrl/api.php?action=get_pending_counts",
  "$BaseUrl/api.php?action=get&file=employees&status_filter=active",
  "$BaseUrl/api.php?action=get&file=it_logs"
)

$TotalRequestsPerTarget = 50
$Concurrency = 10

foreach ($t in $targets) {
  Write-Host ""
  Write-Host "=== Load test: $t ==="

  $runs = @()
  $i = 0
  while ($i -lt $TotalRequestsPerTarget) {
    $batch = @()
    for ($j = 0; $j -lt $Concurrency -and $i -lt $TotalRequestsPerTarget; $j++) {
      $batch += Start-Job -ScriptBlock {
        param($url)
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        try {
          $r = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 20
          $ok = ($r.StatusCode -eq 200)
        } catch {
          $ok = $false
        }
        $sw.Stop()
        [PSCustomObject]@{ url = $url; ok = $ok; ms = $sw.ElapsedMilliseconds }
      } -ArgumentList $t
      $i++
    }

    foreach ($job in $batch) {
      try {
        $runs += Receive-Job -Job $job -Wait -ErrorAction SilentlyContinue
      } finally {
        Remove-Job -Job $job -Force | Out-Null
      }
    }
  }

  $runs = $runs | Where-Object { $_ -ne $null }
  $okCount = ($runs | Where-Object ok).Count
  $avg = [Math]::Round((($runs | Measure-Object -Property ms -Average).Average), 2)
  $p95 = ($runs | Sort-Object ms | Select-Object -Index ([Math]::Floor($TotalRequestsPerTarget * 0.95) - 1)).ms
  $max = ($runs | Measure-Object -Property ms -Maximum).Maximum
  Write-Host "OK: $okCount/$TotalRequestsPerTarget  Avg(ms): $avg  P95(ms): $p95  Max(ms): $max"
}
