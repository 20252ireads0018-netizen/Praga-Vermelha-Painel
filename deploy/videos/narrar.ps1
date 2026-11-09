Add-Type -AssemblyName System.Speech
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$json = Get-Content -Raw -Encoding UTF8 (Join-Path $dir 'roteiros.json') | ConvertFrom-Json
$saida = @{}
# os outros vídeos (ex.: cavaleiro) usam voz neural, de narrar_neural.py: mantém o que já está em linha.json
$antes = Get-Content -Raw -Encoding UTF8 (Join-Path $dir 'linha.json') | ConvertFrom-Json
foreach ($p in $antes.PSObject.Properties) { if ($p.Name -notin @('eletrico', 'fisiomancia')) { $saida[$p.Name] = $p.Value } }
foreach ($video in @('eletrico', 'fisiomancia')) {
    $lista = @()
    $i = 0
    foreach ($c in $json.$video) {
        $s = New-Object System.Speech.Synthesis.SpeechSynthesizer
        $s.SelectVoice('Microsoft Maria Desktop')
        $s.Rate = $(if ($video -eq 'eletrico') { 4 } else { 3 }) # o da Atração Elétrica fala mais rápido para caber em 30 s
        $arq = Join-Path $dir ("{0}-{1:D2}.wav" -f $video, $i)
        $s.SetOutputToWaveFile($arq)
        $s.Speak($c.fala)
        $s.Dispose()
        $bytes = (Get-Item $arq).Length
        # WAV PCM: duração = (bytes - 44) / (taxa * canais * bytes por amostra); lê o cabeçalho
        $fs = [System.IO.File]::OpenRead($arq); $br = New-Object System.IO.BinaryReader($fs)
        $fs.Seek(28, 'Begin') | Out-Null; $bps = $br.ReadInt32(); $br.Close()
        $dur = [math]::Round(($bytes - 44) / $bps, 3)
        $lista += [pscustomobject]@{ cena = $c.cena; fala = $c.fala; audio = (Split-Path -Leaf $arq); dur = $dur }
        $i++
    }
    $saida[$video] = $lista
}
$saida | ConvertTo-Json -Depth 5 | Out-File -Encoding utf8 (Join-Path $dir 'linha.json')
foreach ($v in $saida.Keys) { "{0}: {1} cenas, {2:N1} s" -f $v, $saida[$v].Count, ($saida[$v] | Measure-Object dur -Sum).Sum }
