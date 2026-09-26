<#
.SYNOPSIS
    Pre-flight check for the Smart-Church cPanel auto-deploy pipeline.

.DESCRIPTION
    Verifies the exact hostname / username / password / protocol / port / remote
    directory you are about to store as GitHub Actions secrets -- without waiting
    for a workflow run. It resolves the host, opens the port, logs in, lists the
    remote directory, uploads a small test file and deletes it again.

    Run this from your PC BEFORE adding the GitHub secrets.

.PARAMETER Server
    FTP/SFTP hostname, e.g. server123.webhost.com or ftp.yourdomain.com.

.PARAMETER Username
    cPanel username (e.g. tcnikoro) or a dedicated FTP account name.

.PARAMETER Password
    Omit to be prompted securely (recommended -- a password passed on the command
    line is written to your PowerShell history).

.PARAMETER Protocol
    ftps (default, port 21, encrypted), ftp (port 21, unencrypted) or sftp (port 22).

.PARAMETER RemoteDir
    Directory the workflow will publish into. Must end with a slash.

.EXAMPLE
    .\scripts\test-cpanel-connection.ps1 -Server server123.webhost.com -Username tcnikoro

.EXAMPLE
    .\scripts\test-cpanel-connection.ps1 -Server server123.webhost.com -Username tcnikoro `
        -Protocol sftp -Port 22 -RemoteDir public_html/
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$Server,
    [Parameter(Mandatory = $true)][string]$Username,
    [string]$Password,
    [ValidateSet('ftps', 'ftp', 'sftp')][string]$Protocol = 'ftps',
    [int]$Port = 0,
    [string]$RemoteDir = 'public_html/'
)

$ErrorActionPreference = 'Stop'

if ($Port -eq 0) { $Port = if ($Protocol -eq 'sftp') { 22 } else { 21 } }
if (-not $RemoteDir.EndsWith('/')) { $RemoteDir = "$RemoteDir/" }

# Shared-hosting certificates are frequently self-signed. This mirrors the
# `security: loose` setting already configured in the deploy workflow.
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

function Write-Step { param([string]$Text) Write-Host "`n== $Text" -ForegroundColor Cyan }
function Write-Ok   { param([string]$Text) Write-Host "   [PASS] $Text" -ForegroundColor Green }
function Write-Bad  { param([string]$Text) Write-Host "   [FAIL] $Text" -ForegroundColor Red }
function Write-Info { param([string]$Text) Write-Host "   $Text" -ForegroundColor Gray }

function Test-TcpPort {
    param([string]$TargetHost, [int]$TargetPort, [int]$TimeoutMs = 10000)
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $handle = $client.BeginConnect($TargetHost, $TargetPort, $null, $null)
        if (-not $handle.AsyncWaitHandle.WaitOne($TimeoutMs, $false)) { return $false }
        $client.EndConnect($handle)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function New-FtpRequest {
    param([string]$Path, [string]$Method)
    $uri = '{0}://{1}:{2}/{3}' -f $Protocol, $Server, $Port, $Path.TrimStart('/')
    $request = [System.Net.FtpWebRequest]::Create($uri)
    $request.Method = $Method
    $request.Credentials = New-Object System.Net.NetworkCredential($Username, $Password)
    $request.UsePassive = $true
    $request.UseBinary = $true
    $request.KeepAlive = $false
    $request.Timeout = 30000
    $request.EnableSsl = ($Protocol -eq 'ftps')
    return $request
}

function Invoke-FtpRequest {
    param([System.Net.FtpWebRequest]$Request)
    $response = $Request.GetResponse()
    try {
        $content = ''
        $stream = $response.GetResponseStream()
        if ($null -ne $stream) {
            $reader = New-Object System.IO.StreamReader($stream)
            $content = $reader.ReadToEnd()
            $reader.Close()
        }
        return $content
    } finally {
        $response.Close()
    }
}

function Get-FtpErrorDetail {
    param([System.Exception]$Exception)
    if ($Exception -is [System.Net.WebException] -and $null -ne $Exception.Response) {
        $response = [System.Net.FtpWebResponse]$Exception.Response
        return ('{0} {1}' -f ([int]$response.StatusCode), $response.StatusDescription).Trim()
    }
    return $Exception.Message
}

function Write-ErrorHint {
    param([string]$Message)
    Write-Info $Message
    if ($Message -match '530') {
        Write-Info 'Hint: 530 = wrong username/password. In cPanel > FTP Accounts, reset the'
        Write-Info '      password and re-run. The account must be active, not suspended.'
    } elseif ($Message -match '550') {
        Write-Info 'Hint: 550 = the directory does not exist or is not writable. Check the'
        Write-Info '      spelling of -RemoteDir and that it ends with a slash.'
    } elseif ($Message -match '42[15]|TLS|SSL') {
        Write-Info 'Hint: the server refused the FTPS handshake. Try -Protocol ftp to prove the'
        Write-Info '      credentials, then use sftp (port 22) for the real deployment.'
    }
}

Write-Host ''
Write-Host 'Smart-Church :: cPanel connection pre-flight' -ForegroundColor White
Write-Host ('  Server     : {0}' -f $Server)
Write-Host ('  Username   : {0}' -f $Username)
Write-Host ('  Transport  : {0} on port {1}' -f $Protocol.ToUpper(), $Port)
Write-Host ('  Remote dir : {0}' -f $RemoteDir)

if (-not $Password) {
    $secure = Read-Host -Prompt "  Password for '$Username'" -AsSecureString
    $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { $Password = [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

$failures = 0

Write-Step '1. Resolving the hostname'
try {
    $addresses = [System.Net.Dns]::GetHostAddresses($Server) | ForEach-Object { $_.IPAddressToString }
    Write-Ok ('{0} resolves to {1}' -f $Server, ($addresses -join ', '))
} catch {
    Write-Bad ("DNS lookup failed for '{0}'." -f $Server)
    Write-Info 'Use the exact hostname from cPanel > right sidebar > Server Information.'
    $failures++
}

Write-Step ('2. Connecting to TCP port {0}' -f $Port)
if (Test-TcpPort -TargetHost $Server -TargetPort $Port) {
    Write-Ok ('Port {0} is open' -f $Port)
} else {
    Write-Bad ('Port {0} is closed, filtered, or the host is wrong.' -f $Port)
    Write-Info 'Your host may block FTP from outside. If port 22 also fails, ask the host to'
    Write-Info 'whitelist GitHub Actions, or switch to a git/SSH-based deploy.'
    $failures++
}

if ($Protocol -eq 'sftp') {
    Write-Step '3. SFTP login'
    Write-Info 'SFTP cannot be tested with the built-in Windows FTP client.'
    Write-Info 'Verify manually instead:'
    Write-Info ('    sftp -P {0} {1}@{2}' -f $Port, $Username, $Server)
    Write-Info ('    ls      (then check that "{0}" exists)' -f $RemoteDir.TrimEnd('/'))
} elseif ($failures -gt 0) {
    Write-Step '3. Login skipped'
    Write-Info 'Fix the DNS/port problems above first.'
} else {
    Write-Step '3. Listing the remote directory'
    try {
        $listing = Invoke-FtpRequest (New-FtpRequest -Path $RemoteDir -Method ([System.Net.WebRequestMethods+Ftp]::ListDirectory))
        $entries = @($listing -split "`r?`n" | Where-Object { $_.Trim() -ne '' })
        Write-Ok ('Logged in and listed {0} ({1} entries)' -f $RemoteDir, $entries.Count)
        if ($entries.Count -gt 0) {
            Write-Info ('Contents: {0}' -f (($entries | Select-Object -First 12) -join ', '))
            if ($entries -match '^artisan$') {
                Write-Info 'NOTE: "artisan" already exists here, so an earlier copy of the app is'
                Write-Info '      deployed in this directory -- this deploy will overlay it.'
            }
        } else {
            Write-Info 'Directory is empty -- the first deploy will upload everything.'
        }
    } catch {
        Write-Bad ('Login or directory listing failed: {0}' -f (Get-FtpErrorDetail $_.Exception))
        Write-ErrorHint (Get-FtpErrorDetail $_.Exception)
        $failures++
    }

    if ($failures -eq 0) {
        Write-Step '4. Uploading a test file'
        $testName = '_deploy_connection_test.txt'
        $testBody = 'Smart-Church deploy test ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
        try {
            $request = New-FtpRequest -Path "$RemoteDir$testName" -Method ([System.Net.WebRequestMethods+Ftp]::UploadFile)
            $bytes = [System.Text.Encoding]::ASCII.GetBytes($testBody)
            $request.ContentLength = $bytes.Length
            $stream = $request.GetRequestStream()
            $stream.Write($bytes, 0, $bytes.Length)
            $stream.Close()
            (Invoke-FtpRequest $request) | Out-Null
            Write-Ok ('Wrote {0}{1} -- write permission confirmed' -f $RemoteDir, $testName)

            Write-Step '5. Cleaning up the test file'
            $request = New-FtpRequest -Path "$RemoteDir$testName" -Method ([System.Net.WebRequestMethods+Ftp]::DeleteFile)
            (Invoke-FtpRequest $request) | Out-Null
            Write-Ok 'Test file deleted'
        } catch {
            Write-Bad ('FTP error: {0}' -f (Get-FtpErrorDetail $_.Exception))
            Write-ErrorHint (Get-FtpErrorDetail $_.Exception)
            Write-Info 'Any leftover _deploy_connection_test.txt is harmless; delete it in File Manager.'
            $failures++
        }
    }
}

Write-Step 'Result'
if ($failures -eq 0) {
    Write-Host '   ALL CHECKS PASSED - paste these into GitHub:' -ForegroundColor Green
    Write-Host ''
    Write-Host '   Repository > Settings > Secrets and variables > Actions' -ForegroundColor White
    Write-Host '   Secrets tab:' -ForegroundColor Yellow
    Write-Host ('     CPANEL_HOST       = {0}' -f $Server)
    Write-Host ('     CPANEL_USER       = {0}' -f $Username)
    Write-Host '     CPANEL_PASSWORD   = (the password you just typed)'
    Write-Host '   Variables tab:' -ForegroundColor Yellow
    Write-Host ('     CPANEL_PROTOCOL   = {0}' -f $Protocol)
    Write-Host ('     CPANEL_PORT       = {0}' -f $Port)
    Write-Host ('     CPANEL_REMOTE_DIR = {0}' -f $RemoteDir)
    Write-Host ''
    Write-Host '   Then: Actions > Deploy to cPanel > Run workflow' -ForegroundColor White
    exit 0
}

Write-Host ("   {0} CHECK(S) FAILED - fix the errors above before adding the secrets." -f $failures) -ForegroundColor Red
exit 1


