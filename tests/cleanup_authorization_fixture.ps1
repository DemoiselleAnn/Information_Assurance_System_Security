$ErrorActionPreference='Stop'
try{
 $r=[Console]::In.ReadToEnd() | ConvertFrom-Json
 $base=[IO.Path]::GetFullPath([string]$r.base)
 $temp=[IO.Path]::GetTempPath().TrimEnd('\')
 if(-not $base.StartsWith($temp+'\',[StringComparison]::OrdinalIgnoreCase) -or (Split-Path $base -Leaf) -notmatch '^frostline-authorization-[a-f0-9]{16}$'){throw 'Unsafe fixture root'}
 $sid=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value
 function WriteAccessOnly($path,$acl){
  $item=Get-Item -LiteralPath $path -Force
  $clean=if($item.PSIsContainer){[Security.AccessControl.DirectorySecurity]::new()}else{[Security.AccessControl.FileSecurity]::new()}
  $clean.SetSecurityDescriptorSddlForm($acl.GetSecurityDescriptorSddlForm([Security.AccessControl.AccessControlSections]::Access),[Security.AccessControl.AccessControlSections]::Access)
  $item.SetAccessControl($clean)
 }
 function SetFixtureAcl($path,$rights,$broad=$false,$inherit=$false){
  $acl=Get-Acl -LiteralPath $path;$acl.SetAccessRuleProtection($true,$false)
  foreach($existing in @($acl.Access)){$acl.RemoveAccessRuleSpecific($existing)}
  $trustedFlags=if((Get-Item -LiteralPath $path).PSIsContainer){'ContainerInherit,ObjectInherit'}else{'None'}
  foreach($id in @('S-1-5-18','S-1-5-32-544')){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($id),'FullControl',$trustedFlags,'None','Allow'))}
  $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($sid),$rights,'None','None','Allow'))
  if($broad){$flags=if($inherit){'ObjectInherit'}else{'None'};$prop=if($inherit){'InheritOnly'}else{'None'};$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new('S-1-1-0'),'Write',$flags,$prop,'Allow'))}
  WriteAccessOnly $path $acl
 }
 if($r.action -eq 'remove'){
  $roots=@($base)
  $item=Get-Item -LiteralPath $base -Force -ErrorAction SilentlyContinue
  if($item -and ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)){
   [IO.Directory]::Delete($base,$false);$roots=@($base+'-target')
  }
  foreach($dir in $roots){
   if(Test-Path -LiteralPath $dir){SetFixtureAcl $dir 'FullControl';foreach($file in Get-ChildItem -LiteralPath $dir -File -Force){if($file.Name -notin @('enabled.json','cleanup.jsonl','real-enabled.json')){throw 'Unexpected fixture file'};SetFixtureAcl $file.FullName 'FullControl';[IO.File]::Delete($file.FullName)};[IO.Directory]::Delete($dir,$false)}
  }
  @{ok=$true} | ConvertTo-Json -Compress;exit
 }
 if(Test-Path -LiteralPath $base){throw 'Fixture exists'}
 $actual=$base
 if($r.case -eq 'redirect'){$actual=$base+'-target'}
 New-Item -ItemType Directory -Path $actual | Out-Null
 $file=Join-Path $actual 'enabled.json';$log=Join-Path $actual 'cleanup.jsonl'
 if($r.case -ne 'missing'){
  $content=if($r.case -eq 'malformed'){'{invalid'}elseif($r.case -eq 'disabled'){'{"enabled":false,"database":"'+$r.database+'"}'}elseif($r.case -eq 'database_mismatch'){'{"enabled":true,"database":"cakey_db"}'}else{'{"enabled":true,"database":"'+$r.database+'"}'}
  [IO.File]::WriteAllText($file,$content,[Text.UTF8Encoding]::new($false))
  if($r.case -eq 'hardlink'){New-Item -ItemType HardLink -Path (Join-Path $actual 'real-enabled.json') -Target $file | Out-Null}
  SetFixtureAcl $file 'ReadAndExecute' ($r.case -eq 'file_write')
 }
 [IO.File]::WriteAllText($log,'',[Text.UTF8Encoding]::new($false));SetFixtureAcl $log 'Modify'
 SetFixtureAcl $actual 'ReadAndExecute' ($r.case -in @('parent_write','inherited_write')) ($r.case -eq 'inherited_write')
 if($r.case -eq 'inherited_write'){$acl=Get-Acl -LiteralPath $file;$acl.SetAccessRuleProtection($false,$false);WriteAccessOnly $file $acl; $confirmed=@((Get-Acl -LiteralPath $file).Access | Where-Object {$_.IsInherited -and $_.IdentityReference -eq 'Everyone'});if($confirmed.Count -eq 0){throw 'Inherited ACE fixture was not persisted'}}
 if($r.case -eq 'redirect'){New-Item -ItemType Junction -Path $base -Target $actual | Out-Null}
 @{ok=$true;path=(Join-Path $base 'enabled.json');log=(Join-Path $base 'cleanup.jsonl')} | ConvertTo-Json -Compress
}catch{[Console]::Error.WriteLine('Isolated fixture operation failed: '+$_.Exception.Message);exit 1}
