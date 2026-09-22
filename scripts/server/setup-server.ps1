<#
    HygienePro - server setup for notifications
    Run ON 192.168.1.201, in an ELEVATED PowerShell.

    DEFAULT (email): registers the two scheduled tasks this project never had.
      1. No queue:work for this project, so queued mail sits in the `jobs`
         table forever (PendingVerificationDigest, RandomAuditEscalationMail).
      2. No schedule:run for this project, so every Schedule:: entry in
         routes/console.php never fires - including the smart digest and the
         overdue-CAR report.
    Email needs nothing else: SMTP to Office 365 authenticates fine, and its
    STARTTLS handshake succeeds without a configured CA bundle because PHP's
    openssl stream layer falls back to the Windows certificate store.

    -IncludeLineFix (LINE): additionally installs a CA bundle and points
      C:\PHP\php.ini at it. Only cURL - i.e. Guzzle, i.e. the LINE push - needs
      this; it is what makes every LINE call die with "cURL error 60".
      NOTE: the LINE channel is also over its monthly message quota, so this
      switch alone will not make LINE messages arrive.

    Use -WhatIf to see what it would do without changing anything.
#>
[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [string] $PhpDir  = 'C:\PHP',
    [string] $AppDir  = 'C:\Users\azure.ad\Documents\project\hygiene-checklist',
    # Account the scheduled tasks run as. SYSTEM needs no stored password.
    [string] $RunAsUser = 'SYSTEM',
    # Opt in to the cURL/LINE certificate fix as well.
    [switch] $IncludeLineFix
)

$ErrorActionPreference = 'Stop'

function Step($m) { Write-Host "`n=== $m ===" -ForegroundColor Cyan }
function Ok($m)   { Write-Host "  [ok] $m"   -ForegroundColor Green }
function Warn($m) { Write-Host "  [!!] $m"   -ForegroundColor Yellow }

# schtasks writes to stderr when a task does not exist, and with
# $ErrorActionPreference = 'Stop' PowerShell promotes a native command's stderr
# into a terminating error - so "delete a task that was never there" would abort
# the whole run. Use the ScheduledTasks cmdlets for the remove, which report a
# missing task through -ErrorAction instead of stderr.
function Remove-TaskIfPresent([string] $Name) {
    if (Get-ScheduledTask -TaskName $Name -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $Name -Confirm:$false
        Ok "removed existing task '$Name'"
    }
}

$php = Join-Path $PhpDir 'php.exe'

if (-not (Test-Path $php))    { throw "php.exe not found at $php" }
if (-not (Test-Path $AppDir)) { throw "app directory not found at $AppDir" }

# ------------------------------------------------------- 1. Scheduled tasks
Step '1/2  Register the scheduler task (drives every Schedule:: entry)'

$schedTask = 'HygienePro Scheduler'

if ($PSCmdlet.ShouldProcess($schedTask, 'create scheduled task, every 1 minute')) {
    Remove-TaskIfPresent $schedTask
    # Every minute; Laravel's own scheduler decides what is actually due.
    schtasks /create /tn $schedTask `
        /tr "`"$php`" artisan schedule:run" `
        /sc minute /mo 1 /ru $RunAsUser /rl HIGHEST /f | Out-Null
    # schtasks has no "start in" switch; set the working directory via the task API.
    $t = Get-ScheduledTask -TaskName $schedTask
    $t.Actions[0].WorkingDirectory = $AppDir
    Set-ScheduledTask -TaskName $schedTask -Action $t.Actions | Out-Null
    Ok "$schedTask created (every 1 min, working dir $AppDir)"
}

Step '2/2  Register the queue worker task (delivers queued mail)'

$queueTask = 'HygienePro Queue Worker'
$queueBat  = Join-Path $AppDir 'scripts\server\start-queue.bat'

if (-not (Test-Path $queueBat)) { throw "start-queue.bat not found at $queueBat" }

if ($PSCmdlet.ShouldProcess($queueTask, 'create scheduled task, at startup')) {
    Remove-TaskIfPresent $queueTask
    schtasks /create /tn $queueTask `
        /tr "`"$queueBat`"" `
        /sc onstart /ru $RunAsUser /rl HIGHEST /f | Out-Null
    # Start-ScheduledTask rather than `schtasks /run`, for the same stderr reason.
    Start-ScheduledTask -TaskName $queueTask
    Ok "$queueTask created (at startup) and started now"
}

# ------------------------------------------------- optional: LINE cURL fix
if ($IncludeLineFix) {
    Step 'Optional  Install CA bundle for cURL (LINE only)'

    $ini    = Join-Path $PhpDir 'php.ini'
    $cacert = Join-Path $PhpDir 'cacert.pem'
    if (-not (Test-Path $ini)) { throw "php.ini not found at $ini" }

    if ($PSCmdlet.ShouldProcess($cacert, 'download cacert.pem from curl.se')) {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -OutFile $cacert -UseBasicParsing
        Ok "downloaded $cacert ($([math]::Round((Get-Item $cacert).Length / 1KB)) KB)"
    }

    $backup = "$ini.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"
    if ($PSCmdlet.ShouldProcess($ini, "back up to $backup and set curl.cainfo / openssl.cafile")) {
        Copy-Item $ini $backup
        Ok "backed up php.ini -> $backup"

        $lines = Get-Content $ini
        $didCurl = $false; $didSsl = $false
        $lines = $lines | ForEach-Object {
            if ($_ -match '^\s*;?\s*curl\.cainfo\s*=')        { $didCurl = $true; "curl.cainfo = `"$cacert`"" }
            elseif ($_ -match '^\s*;?\s*openssl\.cafile\s*=') { $didSsl  = $true; "openssl.cafile = `"$cacert`"" }
            else { $_ }
        }
        if (-not $didCurl) { $lines += "curl.cainfo = `"$cacert`"" }
        if (-not $didSsl)  { $lines += "openssl.cafile = `"$cacert`"" }

        Set-Content -Path $ini -Value $lines -Encoding ASCII
        Ok 'php.ini updated'
        & $php -r "echo '  curl.cainfo=' . ini_get('curl.cainfo') . PHP_EOL;"
    }

    if ($PSCmdlet.ShouldProcess('hygiene-checklist app pool', 'restart so php-cgi rereads php.ini')) {
        Import-Module WebAdministration -ErrorAction SilentlyContinue
        if (Get-Command Restart-WebAppPool -ErrorAction SilentlyContinue) {
            Restart-WebAppPool -Name 'hygiene-checklist'
            Ok 'app pool hygiene-checklist restarted'
        } else {
            Warn 'WebAdministration module unavailable - run "iisreset" manually'
        }
    }

    Warn 'LINE is still over its monthly message quota - see README.md'
}

Step 'Verify'
Write-Host @"
  cd $AppDir
  $php artisan schedule:list         # car:check-overdue and friends listed
  $php artisan queue:work --once     # drains one queued job by hand
  $php artisan queue:failed          # anything that blew up

  Watch storage\logs\laravel.log after finishing a session. It now says either
    "... queued '<event>' email to N QA supervisor(s)."      -> handed to SMTP
    "... NO email sent - N QA supervisor(s) found, none opted in to '<event>'."
  The second line means the recipients have that event switched off in their
  notification preferences - not that mail is broken.
"@ -ForegroundColor Gray
