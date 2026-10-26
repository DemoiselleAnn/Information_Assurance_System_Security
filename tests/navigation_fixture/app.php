<?php
// Synthetic navigation fixture only. No database, authentication, or application records.
$member = ($_GET['layout'] ?? '') === 'member';
$page = $_GET['page'] ?? 'security_logs';
if ($page === 'login') { echo '<h1>Fixture authentication redirect</h1>'; exit; }
if (isset($_GET['expired'])) { header('Location: app.php?page=login'); exit; }
if (isset($_GET['deny'])) { http_response_code(403); exit('Fixture forbidden'); }
function fixtureLink(array $params): string { global $member, $page; return 'app.php?'.htmlspecialchars(http_build_query(array_merge(['page'=>$page,'layout'=>$member?'member':'management'], $params)), ENT_QUOTES); }
function fixtureTable(string $id, string $heading, string $parameter): void {
    $number=max(1,min(4,(int)($_GET[$parameter]??1)));$rows=$number===4?2:12;
    echo '<section id="'.$id.'" class="sl-card management-panel"><h2>'.$heading.'</h2><p class="range">Page '.$number.' — synthetic records</p><table><tbody>';
    for($i=1;$i<=$rows;$i++) echo '<tr><td style="height:68px">Fixture record '.(($number-1)*12+$i).'</td></tr>';
    echo '</tbody></table><nav class="pagination" aria-label="'.$heading.' pages">';
    foreach(['Previous'=>max(1,$number-1),'1'=>1,'2'=>2,'3'=>3,'4'=>4,'Next'=>min(4,$number+1)] as $label=>$n) {
        $params=array_merge($_GET,[$parameter=>$n]);
        echo '<a '.($n===$number?'aria-current="page" ':'').'href="'.fixtureLink($params).'">'.$label.'</a> ';
    }
    echo '</nav></section>';
}
?><!doctype html><html><head><meta charset="utf-8"><title>Isolated navigation fixture</title>
<link rel="stylesheet" href="../../css/<?= $member?'customer-dashboard':'superadmin' ?>.css">
<style>section{margin:24px;padding:24px;border:1px solid #b5cddd;background:#fff}nav a{margin:8px}.fixture-note{padding:16px;background:#eef6fc}table{width:100%}h2{margin:0 0 12px}</style>
<?php if(!isset($_GET['nojs'])): ?><script src="../../js/sidebar_scroll.js?v=<?=filemtime(__DIR__.'/../../js/sidebar_scroll.js')?>" defer></script><?php endif; ?>
</head><body class="<?= $member?'dashboard-page':'superadmin-body' ?>">
<?php if($member): ?><header class="dashboard-header">Synthetic User fixture</header><div class="dashboard-shell"><aside class="sidebar"><nav class="sidebar-nav"><?php else: ?><div class="dashboard-container"><aside class="sidebar"><nav class="sidebar-nav"><?php endif; ?>
<?php foreach(['security_logs'=>'Security Logs','account_management'=>'Account Management'] as $key=>$label): ?><a class="<?= $member?'sidebar-link':'nav-item' ?> <?= $page===$key?'active':'' ?>" href="<?=fixtureLink(['page'=>$key])?>"><?=$label?></a><?php endforeach; ?>
</nav></aside><?php if($member): ?><main class="dashboard-main"><div class="content-wrap"><?php else: ?><div class="main-content"><header class="top-header">Synthetic management fixture</header><div class="dashboard-content"><?php endif; ?>
<p class="fixture-note">Isolated navigation fixture. Synthetic records only; no application accounts or permissions.</p><h1><?= $page==='security_logs'?'Security Logs':'Account Management' ?></h1>
<section class="management-panel" id="fixture-filters"><h2>Fixture filters</h2><form method="get" action="app.php"><input type="hidden" name="page" value="<?=htmlspecialchars($page)?>"><input type="hidden" name="layout" value="<?=$member?'member':'management'?>"><label>Search fixture<input name="q" value="<?=htmlspecialchars($_GET['q']??'')?>"></label><button type="submit">Apply fixture filter</button><a href="<?=fixtureLink([])?>">Clear fixture filter</a></form></section>
<?php if($_SERVER['REQUEST_METHOD']==='POST'): ?><p role="status">Fixture submission received: <?=htmlspecialchars($_POST['action']??'missing submitter')?></p><?php endif; ?>
<?php fixtureTable('login-sessions','Login Sessions','sessions_page');fixtureTable('security-events','Security Events','page_no'); ?>
<section class="management-panel" id="fixture-save"><h2>Fixture secure form</h2><form method="post" onsubmit="return confirm('Submit the synthetic fixture form?')"><input type="hidden" name="csrf_token" value="fixture-only"><button type="submit" name="action" value="save_fixture">Save synthetic fixture</button></form><a href="<?=fixtureLink(['deny'=>1])?>">Fixture denied request</a></section>
<?php if($member): ?></div></main></div><?php else: ?></div></div></div><?php endif; ?>
<script>document.documentElement.dataset.fixtureDocument=String(Math.random());</script>
</body></html>
