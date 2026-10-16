$ErrorActionPreference = 'Stop'
$reportRoot = Split-Path -Parent $PSScriptRoot
$environment = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'environment.json') -Raw | ConvertFrom-Json
$attempts = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'browser-attempts.json') -Raw | ConvertFrom-Json
$afterFive = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'after-five-failures.json') -Raw | ConvertFrom-Json
$audit = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'final-audit-events.json') -Raw | ConvertFrom-Json
$lockout = $attempts | Where-Object attempt -eq 6
if ($attempts.Count -ne 7 -or $audit.failed_login_count -ne 5 -or $audit.locked_login_count -ne 1 -or $audit.successful_login_count -ne 1 -or -not $afterFive.is_limited) {
    throw 'The captured evidence does not match the required five-failure sequence.'
}

function Save-Text([string]$RelativePath, [string]$Content) {
    [IO.File]::WriteAllText((Join-Path $reportRoot $RelativePath), $Content, (New-Object Text.UTF8Encoding($false)))
}
function Html([object]$Value) { [Net.WebUtility]::HtmlEncode([string]$Value) }
function Source-Excerpt([string]$File, [int]$Start, [int]$End) {
    $source = @(Get-Content -LiteralPath (Join-Path $PSScriptRoot ('source/' + $File + '.txt')))
    $lines = for ($index = $Start; $index -le $End; $index++) { '{0,3}  {1}' -f $index, $source[$index - 1] }
    '<h2>' + (Html $File) + ':' + $Start + '</h2><pre>' + (Html ($lines -join "`n")) + '</pre>'
}

$results = @(
    [ordered]@{ id='LOCK-01'; check='Five incorrect passwords'; expected='Reject all five and remain signed out.'; actual='Five credential errors; five auth.login.failed events (IDs 14-18).'; result='PASS'; evidence='screenshots/01-failed-attempt-1.jpg through screenshots/05-failed-attempt-5.jpg; raw/after-five-failures.json' },
    [ordered]@{ id='LOCK-02'; check='Limit active after failure five'; expected='Five failures for the same email/IP activate throttling.'; actual=('Counter = ' + $afterFive.attempts + '; is_limited = true. Fifth response still shows the credential error.'); result='PASS'; evidence='raw/after-five-failures.json; screenshots/05-failed-attempt-5.jpg' },
    [ordered]@{ id='LOCK-03'; check='Correct password during lockout'; expected='Reject submission six, even with the correct password.'; actual=$lockout.visible_message; result='PASS'; evidence='screenshots/06-correct-password-blocked-by-lockout.jpg; raw/after-lockout.json' },
    [ordered]@{ id='LOCK-04'; check='Recovery after expiry'; expected='Allow the correct password after the window expires; clear the counter.'; actual='Dashboard reached after natural expiry. Counter = 0; auth.login.succeeded recorded.'; result='PASS'; evidence='screenshots/07-after-expiry-password-only-dashboard.jpg; raw/final-audit-events.json' },
    [ordered]@{ id='MFA-01'; check='Second authentication factor'; expected='If MFA were implemented, require a separate factor before granting dashboard access.'; actual='Email and password alone granted dashboard access. No MFA enrollment, challenge, secret, or recovery-code implementation found.'; result='NOT IMPLEMENTED / FAIL against an MFA requirement'; evidence='screenshots/07-after-expiry-password-only-dashboard.jpg; raw/mfa-source-search.txt; raw/routes.json; raw/source/' },
    [ordered]@{ id='AUDIT-01'; check='Audit events for this run'; expected='Record five failures, a blocked attempt, and the successful recovery.'; actual='Exactly 5 failed + 1 locked + 1 succeeded, IDs 14-20.'; result='PASS'; evidence='raw/final-audit-events.json' }
)
Save-Text 'test-results.json' (ConvertTo-Json -InputObject $results -Depth 6)
$results | ForEach-Object { [pscustomobject]$_ } | Export-Csv -LiteralPath (Join-Path $reportRoot 'test-results.csv') -NoTypeInformation -Encoding utf8

$markdownRows = ($results | ForEach-Object { '| ' + $_.id + ' | ' + $_.expected + ' | ' + $_.actual + ' | ' + $_.result + ' |' }) -join "`n"
$captureLinks = ($attempts | ForEach-Object { '- Submission ' + $_.attempt + ': [' + $_.kind + '](' + $_.screenshot + ')' }) -join "`n"
$readme = @'
# MFA + Account Lockout: verified results

**Lockout: working as email/IP login throttling. MFA: NOT IMPLEMENTED.**

Audited on 16 October 2026, Asia/Manila. Source commit: `SOURCE_HEAD`.
Target: `http://127.0.0.1:8000`; demonstration account: `employee@example.test`.
Runtime: PHP PHP_VERSION; Laravel LARAVEL_VERSION; database cache store.

Open [report.html](report.html) for the complete visual evidence. All images in
`screenshots/01` through `screenshots/07` are actual browser screenshots of the
unmodified application. `08` and later images are explicitly labeled report or
source-listing screenshots, not extra application screens.

## Expected versus actual

| Check | Expected | Actual | Result |
|---|---|---|---|
RESULT_ROWS

The fifth incorrect password returns the normal credentials error while bringing
the counter to five. The guard runs before authentication on each request, so
the lockout message is displayed on submission six. The captured sixth submission
used the known-correct demonstration password and was denied.

The default decay is **60 seconds from the first counted failure**, not 60 seconds
after the fifth failure. The screenshot displayed the remaining wait, rather than
a fresh full minute. No counter was cleared between the fifth failure and the
successful recovery; the browser recovery used natural expiration.

This is a temporary rate limit keyed by lowercased email plus IP address. It is
not a persistent account-disabled flag and does not establish a global lockout
across different IP addresses. Multi-IP bypass testing was outside this run.

## Exact code locations

- `app/Http/Requests/Auth/LoginRequest.php:42-56`: check the limit, try credentials,
  increment the failure counter, log failure, and clear the counter on success.
- `app/Http/Requests/Auth/LoginRequest.php:64-82`: threshold of five, lockout event,
  audit record, remaining time, and the throttle validation message.
- `app/Http/Requests/Auth/LoginRequest.php:87-90`: email/IP throttle key.
- `vendor/laravel/framework/src/Illuminate/Cache/RateLimiter.php:148`: `hit()` has
  a default decay of 60 seconds. This is dependency code, not an application edit.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php:31-41`: password
  authentication is followed directly by session creation and dashboard redirect.
- `routes/auth.php`: password login, reset, email-verification, and logout routes;
  no MFA enrollment or challenge route.
- `app/Models/User.php`: no second-factor trait or stored MFA-secret configuration.

See [the code picture](code-locations.html), [numbered source](raw/code-locations-numbered.txt),
and [exact source snapshots](raw/source/). Line references describe the captured build.

## MFA confirmation

**There is no working MFA in this audited build. Do not claim MFA is implemented.**
The successful browser login reached the protected dashboard with only email and
password. Source, route, dependency, and model inspection found no OTP/TOTP,
authenticator enrollment, WebAuthn/passkey, recovery-code, or second-factor flow.
Email verification, password reset, and password confirmation routes do not
establish a second authentication factor in the login flow.

The negative finding is supported by both [source inspection](raw/mfa-source-search.txt)
and the [successful password-only login](screenshots/07-after-expiry-password-only-dashboard.jpg).
The search alone is not treated as proof of absence.

## Browser captures

CAPTURE_LINKS

The server audit baseline was ID 13. IDs 14-18 are the five failed requests,
ID 19 is the lockout, and ID 20 is the successful recovery. Preliminary UI timing
checks happened before that baseline and are excluded. The raw timestamps and
audit entries let a reviewer correlate the screenshots with actual requests.
Passwords and session tokens are not included in the report or screenshots.

## Automated verification

- Existing project lockout test: **1 test, 3 assertions, passed**.
- Additional evidence checks: **3 tests, 41 assertions, passed**.
- Combined: **4 tests, 44 assertions**.

The additional password-only test passes because it characterizes the current
absence of MFA; that pass must not be reported as passing an MFA requirement.
MFA-01 above is explicitly not implemented / failed against that requirement.

Raw PHPUnit output and JUnit XML are in `raw/`. Tests use SQLite in memory and
the array cache/session drivers from `phpunit.xml`; they do not reset the local
application database. The browser checks use the existing demonstration account
and persist normal security audit events. Only that demonstration email/IP
counter was cleared once before the final sequence. No application UI or
authentication implementation was changed.

Re-run the evidence tests from the project root:

```powershell
.\dependencies\php\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml output/mfa-account-lockout-2026-10-16/raw/MfaLockoutEvidenceTest.php --testdox
.\dependencies\php\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --filter=test_login_is_rate_limited_after_five_failures --testdox
```

With system PHP installed on Mac/Linux, replace `.\dependencies\php\php.exe` with
`php`. Browser screenshots verify this local Windows-hosted build; this evidence
does not claim a native macOS execution or a production deployment verification.
'@
$readme = $readme.Replace('SOURCE_HEAD', $environment.source_head).Replace('PHP_VERSION', $environment.php_version).Replace('LARAVEL_VERSION', $environment.laravel_version).Replace('RESULT_ROWS', $markdownRows).Replace('CAPTURE_LINKS', $captureLinks)
Save-Text 'README.md' $readme

$style = @'
body{margin:0;background:#fff;color:#18212b;font:16px/1.5 Arial,sans-serif}main{max-width:1100px;margin:0 auto;padding:32px}h1{font-size:30px;line-height:1.2;margin:0 0 12px}h2{font-size:19px;line-height:1.35;margin:28px 0 10px;overflow-wrap:anywhere}p{margin:10px 0}.meta{font-size:14px;color:#47515c}.status{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:22px 0}.status p{margin:0;padding:16px;border:1px solid #bcc7cf}.pass{color:#116337}.fail{color:#a02121}table{width:100%;border-collapse:collapse;font-size:14px}th,td{border:1px solid #b9c3cb;padding:10px;text-align:left;vertical-align:top}th{background:#eef2f5}code,pre{font-family:Consolas,monospace}code{font-size:13px}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f2f4f6;border:1px solid #c7cfd6;padding:14px;font-size:13px;line-height:1.5}.gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.gallery img{width:100%;height:auto;border:1px solid #b9c3cb}.gallery figure{margin:0}.gallery figcaption{font-size:13px;margin:6px 0 16px}a{color:#1456a0}.note{border-left:4px solid #a02121;padding:10px 16px;background:#fff3f1}.small{font-size:13px}footer{border-top:1px solid #b9c3cb;margin-top:24px;padding-top:14px;font-size:13px}@media(max-width:650px){main{padding:18px}.gallery{grid-template-columns:1fr 1fr}.status{grid-template-columns:1fr}}
'@
$head = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MFA + Account Lockout Evidence</title><style>' + $style + '</style><main>'
$title = '<h1>MFA + Account Lockout</h1><p class="meta">Local verification · 16 October 2026 · Asia/Manila<br>Demo account: employee@example.test · PHP ' + (Html $environment.php_version) + ' · Laravel ' + (Html $environment.laravel_version) + '<br>Source: <code>' + (Html $environment.source_head) + '</code></p>'
$statuses = '<div class="status"><p><strong class="pass">LOCKOUT — PASS</strong><br>Five failures activate the email/IP limit; the next correct password is rejected.</p><p><strong class="fail">MFA — NOT IMPLEMENTED</strong><br>Password alone reaches the dashboard. Do not claim working MFA.</p></div>'
$tableRows = ($results | ForEach-Object { $class=if ($_.id -eq 'MFA-01') {'fail'} else {'pass'}; '<tr><td><strong>' + (Html $_.id) + '</strong><br>' + (Html $_.check) + '</td><td>' + (Html $_.expected) + '</td><td>' + (Html $_.actual) + '</td><td class="' + $class + '"><strong>' + (Html $_.result) + '</strong></td></tr>' }) -join ''
$table = '<table><thead><tr><th>Check</th><th>Expected</th><th>Actual</th><th>Result</th></tr></thead><tbody>' + $tableRows + '</tbody></table>'
$notes = '<p class="small"><strong>Threshold detail:</strong> the fifth failure still displays the credentials error. Submission six displays the lockout message. The 60-second window starts with the first failure. This is per email/IP throttling, not a global account-disabled flag.</p><p class="small"><strong>Automated verification:</strong> 4 tests / 44 assertions passed. One passing characterization test confirms the absence of MFA; it does not establish MFA compliance.</p><p class="small">Audit correlation: IDs 14–18 = five failures; ID 19 = lockout; ID 20 = successful recovery. Existing accounts and passwords were unchanged.</p>'
$footer = '<footer>Evidence report generated from real local browser captures, audit events, and source inspection. This report page is not an application UI screen. <a href="README.md">Full notes</a> · <a href="raw/final-audit-events.json">Audit JSON</a> · <a href="code-locations.html">Code locations</a></footer>'
Save-Text 'summary.html' ($head + $title + $statuses + $table + $notes + $footer + '</main></html>')

$gallery = ($attempts | ForEach-Object { '<figure><a href="' + $_.screenshot + '"><img src="' + $_.screenshot + '" alt="Actual browser capture for submission ' + $_.attempt + '"></a><figcaption><strong>Submission ' + $_.attempt + '</strong> — ' + (Html $_.kind) + '<br>' + (Html $_.visible_message) + '<br>' + (Html $_.timestamp) + '</figcaption></figure>' }) -join ''
$events = ($audit.events | ForEach-Object { '<tr><td>' + $_.id + '</td><td><code>' + (Html $_.event) + '</code></td><td>' + (Html $_.created_at) + '</td><td>' + (Html $_.ip_address) + '</td></tr>' }) -join ''
$mfaNote = '<h2>MFA finding</h2><p class="note"><strong>MFA is not implemented in this audited build.</strong> The protected dashboard was reached with email and password only. Source, model, route, and dependency inspection found no enrollment or challenge flow. Email verification and password reset are not MFA login challenges.</p>'
Save-Text 'report.html' ($head + $title + $statuses + $table + $notes + $mfaNote + '<h2>Actual application screenshots</h2><p>Five separate completed failed requests, one correct-password lockout, and one successful password-only login after natural expiration. Original captures are linked below.</p><div class="gallery">' + $gallery + '</div><h2>Server audit correlation</h2><table><tr><th>ID</th><th>Event</th><th>Time (UTC)</th><th>IP</th></tr>' + $events + '</table><h2>Actual code location</h2><p><code>app/Http/Requests/Auth/LoginRequest.php:42–90</code> implements the failure counter, threshold, wait message, and email/IP key. The dependency default timeout is in <code>vendor/laravel/framework/src/Illuminate/Cache/RateLimiter.php:148</code>. <a href="code-locations.html">Open the numbered code evidence</a>.</p>' + $footer + '</main></html>')

$code = '<h1>Actual code locations</h1><p class="meta">Source listing from the audited local build. Original file paths and line numbers are retained.<br>Commit: <code>' + (Html $environment.source_head) + '</code></p>'
$code += Source-Excerpt 'app/Http/Requests/Auth/LoginRequest.php' 42 56
$code += Source-Excerpt 'app/Http/Requests/Auth/LoginRequest.php' 64 82
$code += Source-Excerpt 'app/Http/Requests/Auth/LoginRequest.php' 87 90
$code += Source-Excerpt 'vendor/laravel/framework/src/Illuminate/Cache/RateLimiter.php' 148 151
$code += '<h2>MFA: password-only session creation</h2><p class="small">Current flow authenticates the password, creates the session, and redirects to the dashboard. No second-factor challenge is inserted here.</p>'
$code += Source-Excerpt 'app/Http/Controllers/Auth/AuthenticatedSessionController.php' 31 41
Save-Text 'code-locations.html' ($head + $code + $footer + '</main></html>')
Write-Output 'Created README, visual report, summary, code listing, and expected/actual result files.'
