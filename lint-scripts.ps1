<#
  lint-scripts.ps1
  ---------------------------------------------------------------------------
  Pemeriksa statis untuk skrip PowerShell di proyek ini. Dibuat setelah
  beberapa bug halus sempat lolos dan baru ketahuan saat runtime:

    - fungsi dipakai tapi tidak terdefinisi di file itu  (kasus nyata: Enc)
    - parameter tertimpa variabel lokal karena PowerShell TIDAK membedakan
      huruf besar/kecil                                   (kasus nyata: $Headers vs $headers)
    - assignment ke variabel otomatis/reserved            (kasus nyata: $pid)
    - karakter non-ASCII di dalam string pada file TANPA BOM: PowerShell 5.1
      membaca .ps1 sebagai ANSI sehingga string putus dan parser kacau
      (kasus nyata: tanda pisah panjang di dalam Write-Host)

  Jalankan sebelum commit perubahan skrip:
      powershell -ExecutionPolicy Bypass -File lint-scripts.ps1

  Exit code 0 = bersih, 1 = ada temuan.
#>

$root = $PSScriptRoot
$files = Get-ChildItem -Path $root -Filter *.ps1 -File |
         Where-Object { $_.Name -ne 'lint-scripts.ps1' } |
         Sort-Object Name

$reserved = @('PID','HOST','ERROR','INPUT','MATCHES','ARGS','HOME','PWD','PSItem','_',
              'THIS','MyInvocation','PSScriptRoot','PSCommandPath','ExecutionContext',
              'PSBoundParameters','PSCmdlet','LASTEXITCODE','PSVersionTable','StackTrace')

$total = 0

foreach ($file in $files) {
    $path   = $file.FullName
    $issues = @()

    $tokens = $null; $errors = $null
    $ast = [System.Management.Automation.Language.Parser]::ParseFile($path, [ref]$tokens, [ref]$errors)

    # --- 1. Parse error ---
    foreach ($e in $errors) {
        $issues += "PARSE    baris $($e.Extent.StartLineNumber): $($e.Message)"
    }

    # --- 2. Perintah/fungsi tidak terdefinisi ---
    $defined = @($ast.FindAll({ param($n) $n -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true) |
                 ForEach-Object { $_.Name })
    $seen = @{}
    foreach ($c in $ast.FindAll({ param($n) $n -is [System.Management.Automation.Language.CommandAst] }, $true)) {
        $name = $c.GetCommandName()
        if (-not $name -or $seen.ContainsKey($name)) { continue }
        $seen[$name] = $true
        if ($defined -contains $name) { continue }
        if (Get-Command $name -ErrorAction SilentlyContinue) { continue }
        $issues += "UNDEF    baris $($c.Extent.StartLineNumber): '$name' tidak terdefinisi di file ini & bukan cmdlet"
    }

    # --- 3. Parameter tertimpa variabel lokal (beda huruf besar/kecil saja) ---
    foreach ($fn in $ast.FindAll({ param($n) $n -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true)) {
        if (-not $fn.Body.ParamBlock) { continue }
        $params = @($fn.Body.ParamBlock.Parameters | ForEach-Object { $_.Name.VariablePath.UserPath })
        if ($params.Count -eq 0) { continue }
        foreach ($asg in $fn.FindAll({ param($n) $n -is [System.Management.Automation.Language.AssignmentStatementAst] }, $true)) {
            if ($asg.Left -isnot [System.Management.Automation.Language.VariableExpressionAst]) { continue }
            $vn = $asg.Left.VariablePath.UserPath
            foreach ($p in $params) {
                if ($vn -cne $p -and $vn -ieq $p) {
                    $issues += "COLLIDE  baris $($asg.Extent.StartLineNumber): fungsi '$($fn.Name)': variabel `$$vn menimpa parameter `$$p"
                }
            }
        }
    }

    # --- 4. Assignment ke variabel otomatis/reserved ---
    foreach ($asg in $ast.FindAll({ param($n) $n -is [System.Management.Automation.Language.AssignmentStatementAst] }, $true)) {
        if ($asg.Left -isnot [System.Management.Automation.Language.VariableExpressionAst]) { continue }
        $vn = $asg.Left.VariablePath.UserPath
        if ($reserved | Where-Object { $_ -ieq $vn }) {
            $issues += "RESERVED baris $($asg.Extent.StartLineNumber): assignment ke variabel otomatis `$$vn"
        }
    }

    # --- 5. Encoding: non-ASCII di dalam string pada file tanpa BOM ---
    $bytes  = [System.IO.File]::ReadAllBytes($path)
    $hasBom = ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF)
    if (-not $hasBom) {
        foreach ($t in $tokens) {
            if ($t.Kind -ne 'StringLiteral' -and $t.Kind -ne 'StringExpandable') { continue }
            if ($t.Text.ToCharArray() | Where-Object { [int]$_ -gt 127 }) {
                $issues += "ENCODING baris $($t.Extent.StartLineNumber): non-ASCII di dalam string pada file TANPA BOM (simpan sebagai UTF-8 with BOM, atau pakai ASCII)"
            }
        }
    }

    if ($issues.Count -eq 0) {
        Write-Host ("[BERSIH] " + $file.Name) -ForegroundColor Green
    } else {
        Write-Host ("[" + $issues.Count + " TEMUAN] " + $file.Name) -ForegroundColor Yellow
        $issues | Sort-Object -Unique | ForEach-Object { Write-Host "    $_" }
        $total += $issues.Count
    }
}

Write-Host ""
if ($total -eq 0) {
    Write-Host "Semua skrip bersih." -ForegroundColor Green
    exit 0
} else {
    Write-Host "Total temuan: $total" -ForegroundColor Red
    exit 1
}
