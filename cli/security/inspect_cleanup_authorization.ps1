$ErrorActionPreference='Stop'
$stream=$null
try {
    $request=[Console]::In.ReadToEnd() | ConvertFrom-Json
    $isolated=$request.isolated -eq $true
    if($isolated -and $request.database -notmatch '^frostline_step4b_test_[a-f0-9]{16}$'){throw 'test_scope'}
    if(-not $isolated -and $request.database -ne 'cakey_db'){throw 'database'}
    $runtime='S-1-5-21-504805412-1878065803-3817356670-1001'
    $trusted=@('S-1-5-18','S-1-5-32-544')
    if($isolated){$trusted+= [Security.Principal.WindowsIdentity]::GetCurrent().User.Value}
    $path=[IO.Path]::GetFullPath([string]$request.path)
    $expected=if($isolated){[string]$request.expected}else{'C:\Users\PC\Documents\FROSTLINECleanup\enabled.json'}
    $expected=[IO.Path]::GetFullPath($expected)
    if(-not [string]::Equals($path,$expected,[StringComparison]::OrdinalIgnoreCase)){throw 'path'}
    $item=Get-Item -LiteralPath $path -Force
    if($item.PSIsContainer){throw 'file_type'}
    $part=$item
    while($part){
        if(($part.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'reparse_point'}
        $part=if($part.PSIsContainer){$part.Parent}else{$part.Directory}
    }
    # Hold the file before ACL checks; deny concurrent content writes and deletion.
    $stream=[IO.File]::Open($path,[IO.FileMode]::Open,[IO.FileAccess]::Read,[IO.FileShare]::Read)
    $parent=Split-Path -LiteralPath $path
    $modify=[int64][Security.AccessControl.FileSystemRights]::Write -bor [int64][Security.AccessControl.FileSystemRights]::Delete -bor [int64][Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles -bor [int64][Security.AccessControl.FileSystemRights]::ChangePermissions -bor [int64][Security.AccessControl.FileSystemRights]::TakeOwnership
    foreach($target in @($parent,$path)){
        $acl=Get-Acl -LiteralPath $target
        $owner=$acl.GetOwner([Security.Principal.SecurityIdentifier]).Value
        if($isolated -and $request.test_fault -eq 'owner_unverified'){$owner=''}
        if($owner -notin $trusted){throw 'untrusted_owner'}
        $raw=[Security.AccessControl.RawSecurityDescriptor]::new($acl.Sddl)
        if($null -eq $raw.DiscretionaryAcl -or $raw.DiscretionaryAcl.Count -eq 0){throw 'unverifiable_dacl'}
        $read=$false
        foreach($rule in $acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])){
            if(($rule.PropagationFlags -band [Security.AccessControl.PropagationFlags]::InheritOnly) -ne 0){continue}
            $sid=$rule.IdentityReference.Value
            if($rule.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow){
                if(([int64]$rule.FileSystemRights -band $modify) -ne 0 -and ($sid -notin $trusted -or $sid -eq $runtime)){throw 'untrusted_write'}
                if($sid -eq $runtime -and ([int64]$rule.FileSystemRights -band [int64][Security.AccessControl.FileSystemRights]::ReadAndExecute) -eq [int64][Security.AccessControl.FileSystemRights]::ReadAndExecute){$read=$true}
            }
        }
        if(-not $read){throw 'runtime_read_access'}
    }
    # Read a checked, non-redirected handle with write/delete sharing denied.
    Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;
public static class FrostlineFileIdentity {
 [StructLayout(LayoutKind.Sequential)] public struct Info {public uint a; public System.Runtime.InteropServices.ComTypes.FILETIME c,d,e; public uint v,h,l,n,ih,il;}
 [DllImport("kernel32.dll",SetLastError=true)] static extern bool GetFileInformationByHandle(SafeFileHandle h,out Info i);
 [DllImport("kernel32.dll",CharSet=CharSet.Unicode,SetLastError=true)] static extern uint GetFinalPathNameByHandle(SafeFileHandle h,StringBuilder b,uint n,uint f);
 public static string Check(SafeFileHandle h) {Info i;if(!GetFileInformationByHandle(h,out i)||i.n!=1)throw new Exception("file_identity");var b=new StringBuilder(32768);uint n=GetFinalPathNameByHandle(h,b,32768,0);if(n==0||n>=32768)throw new Exception("final_path");return b.ToString();}
}
'@
    $final=[FrostlineFileIdentity]::Check($stream.SafeFileHandle)
    if($final.StartsWith('\\?\')){$final=$final.Substring(4)}
    if(-not [string]::Equals($final,$expected,[StringComparison]::OrdinalIgnoreCase)){throw 'final_path'}
    if($stream.Length -gt 4096){throw 'oversized_control'}
    $bytes=New-Object byte[] ([int]$stream.Length)
    $offset=0
    while($offset -lt $bytes.Length){$n=$stream.Read($bytes,$offset,$bytes.Length-$offset);if($n -eq 0){throw 'short_read'};$offset+=$n}
    @{ok=$true;contents_base64=[Convert]::ToBase64String($bytes);runtime_sid=$runtime;isolated=$isolated} | ConvertTo-Json -Compress
}catch{
    # No contents, secrets or credentials are emitted on failure.
    @{ok=$false;reason='Windows authorization inspection rejected or unavailable'} | ConvertTo-Json -Compress
    exit 1
}finally{if($stream){$stream.Dispose()}}
