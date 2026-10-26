$ErrorActionPreference='Stop'
$name='FROSTLINE Expired Provisional Administrators'
$root='C:\xampp\htdocs\Cakedeco'
$private='C:\Users\PC\Documents\FROSTLINECleanup'
$file=Join-Path $private 'enabled.json'
$log=Join-Path $private 'cleanup.jsonl'
$backup='C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b'
$report=Join-Path $backup 'cleanup-final-activation-result.json'
$php='C:\xampp\php\php.exe'
$dry='"C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --dry-run'
$execute='"C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --execute --enable-file="C:\Users\PC\Documents\FROSTLINECleanup\enabled.json" --log-file="C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl" --limit=100'
$principal=[Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if(-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){throw 'Normal UAC administrator elevation required.'}
function Snapshot([bool]$authorize=$false){
 $arguments=@((Join-Path $root 'cli\security\cleanup_deployment_snapshot.php'));if($authorize){$arguments+='--authorize'}
 $output=& $php @arguments
 if($LASTEXITCODE -ne 0){throw 'Database or production authorization verification failed.'}
 return ($output | ConvertFrom-Json)
}
function WriteStatus([bool]$enabled){
 $bytes=[Text.Encoding]::UTF8.GetBytes(('{"enabled":'+$enabled.ToString().ToLowerInvariant()+',"database":"cakey_db"}')+[Environment]::NewLine)
 $stream=[IO.File]::Open($file,'Open','Write','None')
 try{$stream.SetLength(0);$stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)}finally{$stream.Dispose()}
}
function Inspect{
 $env:PSModulePath='C:\Windows\System32\WindowsPowerShell\v1.0\Modules'
 $result='{"path":"C:/Users/PC/Documents/FROSTLINECleanup/enabled.json","database":"cakey_db","isolated":false}' | & 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -NoProfile -NonInteractive -File (Join-Path $root 'cli\security\inspect_cleanup_authorization.ps1')
 if($LASTEXITCODE -ne 0 -or -not ($result | ConvertFrom-Json).ok){throw 'Production NTFS inspector failed.'}
}
$changed=$false
try{
 if(Test-Path -LiteralPath $report){throw 'Activation report already exists; do not overwrite.'}
 $hashes=Get-Content (Join-Path $root 'docs\cleanup-authorization-tested-hashes.json') -Raw | ConvertFrom-Json
 foreach($property in $hashes.PSObject.Properties){if((Get-FileHash -LiteralPath (Join-Path $root $property.Name)).Hash -ne $property.Value){throw 'Tested code changed.'}}
 if((Get-FileHash -LiteralPath (Join-Path $backup 'cakey_db_full_20261012_163936_4d15c289.sql')).Hash -ne 'B9DFBC486AD7244B58D734470E711A7C4EF2E49D93FCDE6FB8E900487CD8C2F4'){throw 'Backup changed.'}
 Inspect
 $config=Get-Content -LiteralPath $file -Raw | ConvertFrom-Json
 if($config.enabled -ne $false -or $config.database -ne 'cakey_db' -or @($config.PSObject.Properties).Count -ne 2){throw 'Unexpected disabled file contents.'}
 $acl=(Get-Acl -LiteralPath $file).Sddl
 $task=Get-ScheduledTask -TaskName $name
 $info=Get-ScheduledTaskInfo -TaskName $name
 if($task.Actions.Count -ne 1 -or $task.Actions[0].Arguments -ne $dry -or $task.Actions[0].Execute -ne $php -or $task.Actions[0].WorkingDirectory -ne $root -or $task.Principal.LogonType.ToString() -ne 'S4U' -or $task.Principal.RunLevel.ToString() -ne 'Limited' -or $task.Settings.MultipleInstances.ToString() -ne 'IgnoreNew' -or $task.Triggers[0].Repetition.Interval -ne 'PT1M' -or $info.LastTaskResult -ne 0 -or -not $task.Settings.Enabled){throw 'Unexpected or unsuccessful dry-run task.'}
 $sid=([Security.Principal.NTAccount]::new($task.Principal.UserId)).Translate([Security.Principal.SecurityIdentifier]).Value
 if($sid -ne 'S-1-5-21-504805412-1878065803-3817356670-1001'){throw 'Unexpected task identity.'}
 $before=Snapshot
 if($before.database -ne 'cakey_db' -or $before.eligible_count -ne 0){throw 'Unexpected eligible accounts or database.'}
 $originalXml=Export-ScheduledTask -TaskName $name
 $originalXml | Set-Content -LiteralPath (Join-Path $backup ('cleanup-task-before-activation_'+(Get-Date -Format 'yyyyMMdd_HHmmss')+'.xml')) -Encoding UTF8
 $changed=$true;WriteStatus $true
 Inspect
 if((Get-Acl -LiteralPath $file).Sddl -ne $acl){throw 'Authorization permissions changed.'}
 $authorized=Snapshot $true
 if($authorized.eligible_count -ne 0){throw 'Unexpected eligible accounts before task activation.'}
 $action=New-ScheduledTaskAction -Execute $php -Argument $execute -WorkingDirectory $root
 Set-ScheduledTask -TaskName $name -Action $action | Out-Null
 $updated=Get-ScheduledTask -TaskName $name
 if($updated.Actions[0].Arguments -ne $execute){throw 'Task action update failed.'}
 $a=[xml]$originalXml;$b=[xml](Export-ScheduledTask -TaskName $name)
 foreach($xml in @($a,$b)){$node=$xml.SelectSingleNode('//*[local-name()="Actions"]');[void]$node.ParentNode.RemoveChild($node)}
 if($a.OuterXml -ne $b.OuterXml){throw 'Task settings other than action changed.'}
 $activated=Get-Date
 $deadline=$activated.AddSeconds(150);$completion=$null;$trigger=$null
 while((Get-Date) -lt $deadline){
  $events=@(Get-WinEvent -FilterHashtable @{LogName='Microsoft-Windows-TaskScheduler/Operational';Id=107,201;StartTime=$activated} -ErrorAction SilentlyContinue | Where-Object {$_.Message -like ('*'+$name+'*')})
  $trigger=$events | Where-Object Id -eq 107 | Select-Object -First 1
  $completion=$events | Where-Object Id -eq 201 | Select-Object -First 1
  if($trigger -and $completion){break}
  Start-Sleep -Seconds 2
 }
 if(-not $trigger -or -not $completion){throw 'No successful time-triggered execution observed.'}
 $eventXml=[xml]$completion.ToXml();$code=($eventXml.Event.EventData.Data | Where-Object Name -eq 'ResultCode').'#text'
 if($code -ne '0'){throw ('Scheduled action failed: '+$code)}
 $records=@(Get-Content -LiteralPath $log | Where-Object {$_} | ForEach-Object {$_ | ConvertFrom-Json})
 $recent=@($records | Where-Object { [DateTimeOffset]::Parse($_.time).UtcDateTime -ge $activated.ToUniversalTime() })
 if(@($recent | Where-Object {$_.event -in @('rolled_back','transaction_failed','commit_outcome_unknown','committed_log_failure')}).Count){throw 'Cleanup logged transaction failure.'}
 $committed=$recent | Where-Object event -eq 'committed' | Select-Object -Last 1
 if(-not $committed -or @($committed.account_ids).Count -ne 0){throw 'Unexpected deletion result or missing committed log.'}
 $after=Snapshot $true
 if($after.users_sha256 -ne $before.users_sha256 -or $after.security_logs_sha256 -ne $before.security_logs_sha256 -or $after.completed_count -ne $before.completed_count){throw 'Account or historical audit integrity changed unexpectedly.'}
 [ordered]@{success=$true;activated_at=$activated.ToString('o');verified_at=(Get-Date).ToString('o');enabled=$true;arguments=$execute;before=$before;after=$after;deleted=0;scheduled_completion=$completion.TimeCreated.ToString('o');scheduled_result=0;timer_trigger_confirmed=$true;task_settings_preserved=$true;authorization_acl_preserved=$true;errors=@()} | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $report -Encoding UTF8
}catch{
 $failure=$_.Exception.Message;$recovery=@()
 if($changed){
  try{Disable-ScheduledTask -TaskName $name | Out-Null}catch{$recovery+='Task disabling failed: '+$_.Exception.Message}
  try{WriteStatus $false}catch{$recovery+='Authorization reset failed: '+$_.Exception.Message}
 }
 [ordered]@{success=$false;error=$failure;changed=$changed;recovery_errors=$recovery;time=(Get-Date).ToString('o')} | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $report -Encoding UTF8
}
