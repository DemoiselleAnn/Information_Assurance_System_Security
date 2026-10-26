$ErrorActionPreference='Stop'
$base='C:\Users\PC\Documents\FROSTLINECleanup'
$path=Join-Path $base 'enabled.json'
$admin='S-1-5-32-544'; $system='S-1-5-18'; $runtime='S-1-5-21-504805412-1878065803-3817356670-1001'
$principal=[Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
if(-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){throw 'Normal UAC administrator elevation required.'}
$part=Get-Item -LiteralPath $base
while($part){if($part.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Redirected directory.'};$part=$part.Parent}
$parent=Get-Acl -LiteralPath $base
if($parent.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $admin -or -not $parent.AreAccessRulesProtected){throw 'Unexpected directory ownership/inheritance.'}
$rules=@($parent.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier]))
if($rules.Count -ne 3){throw 'Unexpected directory rules.'}
foreach($rule in $rules){
 $sid=$rule.IdentityReference.Value
 $expected=if($sid -eq $runtime){[Security.AccessControl.FileSystemRights]'ReadAndExecute,Synchronize'}elseif($sid -in @($admin,$system)){[Security.AccessControl.FileSystemRights]::FullControl}else{throw 'Unexpected directory principal.'}
 if($rule.IsInherited -or $rule.AccessControlType.ToString() -ne 'Allow' -or $rule.FileSystemRights -ne $expected){throw 'Directory permissions differ from review.'}
}
$task=Get-ScheduledTask -TaskName 'FROSTLINE Expired Provisional Administrators'
if($task.Actions.Count -ne 1 -or $task.Actions[0].Arguments -ne '"C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --dry-run' -or $task.Principal.RunLevel.ToString() -ne 'Limited' -or $task.Principal.LogonType.ToString() -ne 'S4U'){throw 'Task is not the approved dry-run configuration.'}
if(Test-Path -LiteralPath $path){throw 'Authorization file already exists; no overwrite permitted.'}
$acl=[Security.AccessControl.FileSecurity]::new()
$acl.SetAccessRuleProtection($true,$false)
$acl.SetOwner([Security.Principal.SecurityIdentifier]::new($admin))
foreach($sid in @($admin,$system)){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($sid),'FullControl','Allow'))}
$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($runtime),'ReadAndExecute','Allow'))
$stream=[IO.File]::Open($path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
try{
 $bytes=[Text.Encoding]::UTF8.GetBytes('{"enabled":false,"database":"cakey_db"}'+[Environment]::NewLine)
 $stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)
}finally{$stream.Dispose()}
Set-Acl -LiteralPath $path -AclObject $acl
$actual=Get-Acl -LiteralPath $path
if($actual.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $admin -or -not $actual.AreAccessRulesProtected -or $actual.GetSecurityDescriptorSddlForm([Security.AccessControl.AccessControlSections]::Access) -ne $acl.GetSecurityDescriptorSddlForm([Security.AccessControl.AccessControlSections]::Access)){throw 'Authorization ACL verification failed; disabled file requires administrator review.'}
$config=Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
if($config.enabled -ne $false -or $config.database -ne 'cakey_db'){throw 'Disabled content verification failed.'}
Write-Output 'PASS: disabled production authorization file created and protected. Task unchanged.'
