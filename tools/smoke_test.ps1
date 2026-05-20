$ErrorActionPreference = "Stop"

$BaseUrl = "http://localhost/PowertechCenter"
$ApiUrl = "$BaseUrl/api.php"

function Read-DotEnvValue($Path, $Key) {
  if (-not (Test-Path -LiteralPath $Path)) { return $null }
  $lines = Get-Content -LiteralPath $Path -ErrorAction Stop
  foreach ($line in $lines) {
    if ($line -eq $null) { $trim = "" } else { $trim = $line.Trim() }
    if ($trim.Length -eq 0) { continue }
    if ($trim.StartsWith("#")) { continue }
    $eq = $trim.IndexOf("=")
    if ($eq -lt 1) { continue }
    $k = $trim.Substring(0, $eq).Trim()
    if ($k -ne $Key) { continue }
    $v = $trim.Substring($eq + 1).Trim()
    if (($v.StartsWith('"') -and $v.EndsWith('"')) -or ($v.StartsWith("'") -and $v.EndsWith("'"))) {
      $v = $v.Substring(1, $v.Length - 2)
    }
    return $v
  }
  return $null
}

function New-RandomPassword() {
  $bytes = New-Object byte[] 32
  $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
  try { $rng.GetBytes($bytes) } finally { if ($rng) { $rng.Dispose() } }
  return ([Convert]::ToBase64String($bytes)).TrimEnd("=")
}

function Get-PhpExe() {
  $candidate = "C:\xampp\php\php.exe"
  if (Test-Path -LiteralPath $candidate) { return $candidate }
  return "php"
}

function Ensure-TestUsers($Password) {
  $php = Get-PhpExe
  $seedScript = Join-Path $PSScriptRoot "seed_test_users.php"
  if (-not (Test-Path -LiteralPath $seedScript)) { throw "seed_test_users.php not found" }

  $prevAllow = $env:PTC_ALLOW_TEST_SEED
  $prevPw = $env:PTC_TEST_PASSWORD
  try {
    $env:PTC_ALLOW_TEST_SEED = "1"
    $env:PTC_TEST_PASSWORD = $Password
    & $php $seedScript | Out-Host
  } finally {
    $env:PTC_ALLOW_TEST_SEED = $prevAllow
    $env:PTC_TEST_PASSWORD = $prevPw
  }
}

function Write-Section($title) {
  Write-Host ""
  Write-Host "=== $title ==="
}

function Assert-True($cond, $msg) {
  if (-not $cond) { throw $msg }
}

function Get-Json($Url, $Session) {
  $res = Invoke-WebRequest -UseBasicParsing -Uri $Url -WebSession $Session -TimeoutSec 20
  return ($res.Content | ConvertFrom-Json)
}

function Post-Json($Url, $BodyObj, $Session) {
  $json = ($BodyObj | ConvertTo-Json -Depth 10)
  $res = Invoke-WebRequest -UseBasicParsing -Uri $Url -Method Post -ContentType "application/json" -Body $json -WebSession $Session -TimeoutSec 20
  return ($res.Content | ConvertFrom-Json)
}

function Post-Form($Url, $Form, $Session) {
  $res = Invoke-WebRequest -UseBasicParsing -Uri $Url -Method Post -Body $Form -WebSession $Session -TimeoutSec 30
  return ($res.Content | ConvertFrom-Json)
}

function Login($Username, $Password, $Company) {
  $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $data = Post-Json "$($ApiUrl)?action=login" @{ username = $Username; password = $Password; company = $Company } $s
  Assert-True ($data.status -eq "success") ("Login failed for $Username ($Company): " + ($data.message | Out-String))
  return $s
}

function Logout($Session) {
  try { Get-Json "$($ApiUrl)?action=logout" $Session | Out-Null } catch {}
}

Write-Section "HTTP Smoke (Public Pages)"
$pages = @(
  "index.html", "login.html", "request.html", "log.html", "directory.html",
  "admin.html", "my_tickets.html", "borrow_management.html",
  "loan.html", "job_ticket.html", "kb.html", "dashboard.html", "smart_booking.html"
)
$pageFailures = @()
foreach ($p in $pages) {
  $url = "$BaseUrl/$p"
  try {
    $code = (Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 20).StatusCode
    if ($code -eq 200) {
      Write-Host "OK $p ($code)"
    } else {
      $pageFailures += "${p}:$code"
      Write-Host "WARN $p ($code)"
    }
  } catch {
    $pageFailures += "${p}:ERROR"
    Write-Host "WARN $p (ERROR)"
  }
}
if ($pageFailures.Count -gt 0) {
  Write-Host ("Pages with warnings: " + ($pageFailures -join ", "))
}

Write-Section "API Smoke (No Auth)"
$s0 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$cs = Get-Json "$($ApiUrl)?action=check_session" $s0
Assert-True ($cs.status -eq "success" -or $cs.loggedin -ne $null) "check_session unexpected response"
Write-Host "OK check_session"

Write-Section "Auth + Company Scoping (PTA/PT4/PTE)"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$dotEnvPath = Join-Path $root ".env.local"
$testPw = $env:PTC_TEST_PASSWORD
if (-not ($testPw -and $testPw.Length -gt 0)) {
  $testPw = Read-DotEnvValue $dotEnvPath "PTC_TEST_PASSWORD"
}
if (-not ($testPw -and $testPw.Length -gt 0)) {
  $testPw = New-RandomPassword
}
Ensure-TestUsers $testPw

$matrix = @(
  @{ company = "PTA"; user = "tst_user_pta"; staff = "tst_staff_pta" },
  @{ company = "PT4"; user = "tst_user_pt4"; staff = "tst_staff_pt4" },
  @{ company = "PTE"; user = "tst_user_pte"; staff = "tst_staff_pte" }
)

foreach ($m in $matrix) {
  Write-Section ("Company " + $m.company + " - Directory/Employees Visibility")
  $sessUser = Login $m.user $testPw $m.company
  $emps = Get-Json "$($ApiUrl)?action=get&file=employees&status_filter=all" $sessUser
  Assert-True ($emps -is [System.Array]) "employees response is not array"
  $bad = $emps | Where-Object { $_.assignments -and (($_.assignments | Where-Object { $_.company -ne $m.company }).Count -gt 0) }
  Assert-True (($bad | Measure-Object).Count -eq 0) "Employee scoping failed for $($m.company)"
  Write-Host "OK employees scoped to $($m.company)"
  Logout $sessUser

  Write-Section ("Company " + $m.company + " - Ticket CRUD (User create, Staff update)")
  $sessUser = Login $m.user $testPw $m.company

  $ticketData = @{
    date = (Get-Date).ToString("yyyy-MM-dd")
    company = "PTE"
    department = "IT"
    requester = "Should be overwritten"
    asset_id = ""
    problem = "SmokeTest ticket ($($m.company))"
    service_type = "IT"
    location_name = ""
    status = "Pending"
    type = "Hardware"
    urgency = "ปกติ"
    appointment_date = $null
  }
  $add = Post-Form $ApiUrl @{ action="add_item"; key="it_logs"; data=($ticketData | ConvertTo-Json -Depth 10); requester_id="WRONG" } $sessUser
  Assert-True ($add.status -eq "success") ("add_item failed: " + ($add.message | Out-String))
  $newId = $add.new_id
  Assert-True ($newId) "No new_id returned"
  Write-Host "OK created ticket $newId"

  $logsUser = Get-Json "$($ApiUrl)?action=get&file=it_logs" $sessUser
  $created = $logsUser | Where-Object { $_.id -eq $newId } | Select-Object -First 1
  Assert-True ($created) "Created ticket not visible to creator"
  Assert-True ($created.company -eq $m.company) "Company enforcement failed (expected $($m.company), got $($created.company))"
  Assert-True ($created.requester_id -eq "TST_USER_$($m.company)") "requester_id enforcement failed"
  Logout $sessUser

  $sessStaff = Login $m.staff $testPw $m.company
  $updPayload = @{ id = $newId; status = "In Progress" }
  $upd = Post-Form $ApiUrl @{ action="update_item"; key="it_logs"; data=($updPayload | ConvertTo-Json -Depth 10) } $sessStaff
  Assert-True ($upd.status -eq "success") ("update_item failed: " + ($upd.message | Out-String))
  Write-Host "OK staff updated ticket status"

  Logout $sessStaff
}

Write-Section "Done"
Write-Host "All smoke tests passed."
