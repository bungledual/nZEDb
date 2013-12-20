<?php

require_once dirname(__FILE__) . '/../../../../www/config.php';
require_once nZEDb_LIB . 'framework/db.php';
require_once nZEDb_LIB . 'tmux.php';
require_once nZEDb_LIB . 'site.php';
require_once nZEDb_LIB . 'ColorCLI.php';

$db = new DB();
$DIR = nZEDb_MISC;
$c= new ColorCLI();

passthru('clear');

$s = new Sites();
$site = $s->get();
$patch = (isset($site->sqlpatch)) ? $site->sqlpatch : 0;
$hashcheck = (isset($site->hashcheck)) ? $site->hashcheck : 0;

// Check collections version
if ($hashcheck != 1)
	exit($c->error("\nWe have updated the way collections are created, the collection table has to be updated to use the new changes.\nphp ${DIR}testing/DB_scripts/reset_Collections.php true\n"));

// Check database patch version
if ($patch < 159)
	exit($c->error("\nYour database is not up to date. Please update.\nphp ${DIR}testing/DB_scripts/patchDB.php\n"));

// Search for NNTPProxy session that might be running froma userthreaded.php run. Setup a clean environment to run in.
exec("tmux list-session | grep NNTPProxy", $nntpkill);
if (count($nntpkill) === 0)
{
	exec("tmux list-session", $sessions);
	if (count($sessions) === 0)
		echo $c->notice("The above is a notice generated by tmux. \nWhen starting this script, we first check that nntpproxy is not already running in a tmux session. If it is not, the above notice is generated by tmux.\nIt is not an error.");
}
else
{
	echo $c->notice("Found NNTPProxy tmux session and killing it.");
	exec("tmux kill-session -t NNTPProxy");
}

$t = new Tmux();
$tmux = $t->get();
$tmux_session = (isset($tmux->tmux_session)) ? $tmux->tmux_session : 0;
$seq = (isset($tmux->sequential)) ? $tmux->sequential : 0;
$powerline = (isset($tmux->powerline)) ? $tmux->powerline : 0;
$colors = (isset($tmux->colors)) ? $tmux->colors : 0;
$import = (isset($tmux->import)) ? $tmux->import : 0;
$tablepergroup = (isset($site->tablepergroup)) ? $site->tablepergroup : 0;

//check if session exists
$session = exec("echo `tmux list-sessions | grep $tmux_session | wc -l`");
if ($session != 0)
	exit($c->error("tmux session:".$tmux_session." is already running, aborting.\n"));
else
	echo $c->notice("The above is a notice generated by tmux. \nWhen starting this script, we first check that you do not have a tmux session currently running. If you do not, the above notice is generated by tmux.\nIt is not an error.");

function writelog($pane)
{
	$path = dirname(__FILE__)."/logs";
	$getdate = gmDate("Ymd");
	$tmux = new Tmux();
	$logs = $tmux->get()->write_logs;
	if ($logs == 1)
		return "2>&1 | tee -a $path/$pane-$getdate.log";
	else
		return "";
}

//remove folders from tmpunrar
$tmpunrar = $site->tmpunrarpath;
if ((count(glob("$tmpunrar/*",GLOB_ONLYDIR))) > 0)
{
	echo $c->info("Removing dead folders from ".$tmpunrar);
	exec("rm -r ".$tmpunrar."/*");
}

function command_exist($cmd) {
	$returnVal = exec("which $cmd 2>/dev/null");
	return (empty($returnVal) ? false : true);
}

//check for apps
$apps = array("time", "tmux", "nice", "python", "tee");
foreach ($apps as &$value)
{
	if (!command_exist($value))
		exit($c->error("Tmux scripts require ".$value." but it's not installed. Aborting.\n"));
}

function python_module_exist($module)
{
	exec("python -c \"import $module\"", $output, $returnCode);
	return ($returnCode == 0 ? true : false);
}

$nntpproxy = $site->nntpproxy;
if ($nntpproxy == '1')
{
	$modules = array("nntp", "socketpool");
	foreach ($modules as &$value)
	{
		if (!python_module_exist($value))
			exit($c->error("\nNNTP Proxy requires ".$value." python module but it's not installed. Aborting.\n"));
	}
}

//reset collections dateadded to now
echo $c->header("Resetting expired collections and nzbs dateadded to now. This could take a minute or two. Really.");
if ($tablepergroup == 1)
{
	$sql = "SHOW table status";
	$tables = $db->queryDirect($sql);
	$ran = 0;
	foreach($tables as $row)
	{
		$tbl = $row['name'];
		if (preg_match('/collections_\d+/',$tbl))
		{
			$run = $db->queryDirect('UPDATE '.$tbl.' SET dateadded = now()');
			$ran += $run->rowCount();
		}
	}
	echo $c->primary($ran." collections reset.");
}
else
{
	$run = $db->queryDirect("update collections set dateadded = now()");
	echo $c->primary($run->rowCount()." collections reset.");
}

$run = $db->queryDirect("update nzbs set dateadded = now()");
echo $c->primary($run->rowCount()." nzbs reset.");
sleep(2);

function start_apps($tmux_session)
{
	$t = new tmux();
	$tmux = $t->get();
	$htop = $tmux->htop;
	$vnstat = $tmux->vnstat;
	$vnstat_args = $tmux->vnstat_args;
	$tcptrack = $tmux->tcptrack;
	$tcptrack_args = $tmux->tcptrack_args;
	$nmon = $tmux->nmon;
	$bwmng = $tmux->bwmng;
	$mytop = $tmux->mytop;
	$showprocesslist = $tmux->showprocesslist;
	$processupdate = $tmux->processupdate;
	$console_bash = $tmux->console;

	if ($htop == 1 && command_exist("htop"))
		exec("tmux new-window -t $tmux_session -n htop 'printf \"\033]2;htop\033\" && htop'");

	if ($nmon == 1 && command_exist("nmon"))
		exec("tmux new-window -t $tmux_session -n nmon 'printf \"\033]2;nmon\033\" && nmon -t'");

	if ($vnstat == 1 && command_exist("vnstat"))
		exec("tmux new-window -t $tmux_session -n vnstat 'printf \"\033]2;vnstat\033\" && watch -n10 \"vnstat ${vnstat_args}\"'");

	if ($tcptrack == 1 && command_exist("tcptrack"))
		exec("tmux new-window -t $tmux_session -n tcptrack 'printf \"\033]2;tcptrack\033\" && tcptrack ${tcptrack_args}'");

	if ($bwmng == 1 && command_exist("bwm-ng"))
		exec("tmux new-window -t $tmux_session -n bwm-ng 'printf \"\033]2;bwm-ng\033\" && bwm-ng'");

	if ($mytop == 1 && command_exist("mytop"))
		exec("tmux new-window -t $tmux_session -n mytop 'printf \"\033]2;mytop\033\" && mytop -u'");

	if ($showprocesslist == 1)
		exec("tmux new-window -t $tmux_session -n showprocesslist 'printf \"\033]2;showprocesslist\033\" && watch -n .5 \"mysql -e \\\"SELECT time, state, info FROM information_schema.processlist WHERE command != \\\\\\\"Sleep\\\\\\\" AND time >= $processupdate ORDER BY time DESC \\\G\\\"\"'");
		//exec("tmux new-window -t $tmux_session -n showprocesslist 'printf \"\033]2;showprocesslist\033\" && watch -n .2 \"mysql -e \\\"SELECT time, state, rows_examined, info FROM information_schema.processlist WHERE command != \\\\\\\"Sleep\\\\\\\" AND time >= $processupdate ORDER BY time DESC \\\G\\\"\"'");

	if ($console_bash == 1)
		exec("tmux new-window -t $tmux_session -n bash 'printf \"\033]2;Bash\033\" && bash -i'");
}

function window_proxy($tmux_session, $window)
{
	$s = new Sites();
	$site = $s->get();
	$nntpproxy = $site->nntpproxy;
	if ($nntpproxy == 1)
	{
		$DIR = nZEDb_MISC;
		$nntpproxypy = $DIR."update_scripts/python_scripts/nntpproxy.py";
		if(file_exists($DIR."update_scripts/python_scripts/lib/nntpproxy.conf"))
		{
			$nntpproxyconf = $DIR."update_scripts/python_scripts/lib/nntpproxy.conf";
			exec("tmux new-window -t $tmux_session -n nntpproxy 'printf \"\033]2;NNTPProxy\033\" && python $nntpproxypy $nntpproxyconf'");
		}
	}
	$alternate_nntp = $site->alternate_nntp;
	$grabnzbs = $site->grabnzbs;
	if ($nntpproxy == 1 && ($alternate_nntp == 1 || $grabnzbs == 2))
	{
		$DIR = nZEDb_MISC;
		$nntpproxypy = $DIR."update_scripts/python_scripts/nntpproxy.py";
		if (file_exists($DIR."update_scripts/python_scripts/lib/nntpproxy_a.conf"))
		{
			$nntpproxyconf = $DIR."update_scripts/python_scripts/lib/nntpproxy_a.conf";
			exec("tmux selectp -t 0; tmux splitw -t $tmux_session:$window -h -p 50 'printf \"\033]2;NNTPProxy\033\" && python $nntpproxypy $nntpproxyconf'");
		}
	}

}

function window_utilities($tmux_session)
{
	exec("tmux new-window -t $tmux_session -n utils 'printf \"\033]2;fixReleaseNames\033\"'");
	exec("tmux splitw -t $tmux_session:1 -v -p 50 'printf \"\033]2;updateTVandTheaters\033\"'");
	exec("tmux selectp -t 0; tmux splitw -t $tmux_session:1 -h -p 50 'printf \"\033]2;removeCrapReleases\033\"'");
	exec("tmux selectp -t 2; tmux splitw -t $tmux_session:1 -h -p 50 'printf \"\033]2;decryptHashes\033\"'");
}

function window_colors($tmux_session)
{
	exec("tmux new-window -t $tmux_session -n colors 'printf \"\033]2;tmux_colors\033\"'");
}

function window_stripped_utilities($tmux_session)
{
	exec("tmux new-window -t $tmux_session -n utils 'printf \"\033]2;updateTVandTheaters\033\"'");
	exec("tmux selectp -t 0; tmux splitw -t $tmux_session:1 -h -p 50 'printf \"\033]2;postprocessing_amazon\033\"'");
}

function window_post($tmux_session)
{
	exec("tmux new-window -t $tmux_session -n post 'printf \"\033]2;postprocessing_additional\033\"'");
	exec("tmux splitw -t $tmux_session:2 -v -p 67 'printf \"\033]2;postprocessing_non_amazon\033\"'");
	exec("tmux splitw -t $tmux_session:2 -v -p 50 'printf \"\033]2;postprocessing_amazon\033\"'");
}

function window_optimize($tmux_session)
{
	exec("tmux new-window -t $tmux_session -n optimize 'printf \"\033]2;update_nZEDb\033\"'");
	exec("tmux splitw -t $tmux_session:3 -v -p 50 'printf \"\033]2;optimize\033\"'");
}

function attach($DIR, $tmux_session)
{
	if (command_exist("php5"))
		$PHP = "php5";
	else
		$PHP = "php";

	//get list of panes by name
	$panes_win_1 = exec("echo `tmux list-panes -t $tmux_session:0 -F '#{pane_title}'`");
	$panes0 = str_replace("\n", '', explode(" ", $panes_win_1));
	$log = writelog($panes0[0]);
	exec("tmux respawnp -t $tmux_session:0.0 '$PHP ".$DIR."update_scripts/nix_scripts/tmux/monitor.php $log'");
	exec("tmux select-window -t $tmux_session:0; tmux attach-session -d -t $tmux_session");
}

//create tmux session
if ($powerline == 1)
	$tmuxconfig = $DIR."update_scripts/nix_scripts/tmux/powerline/tmux.conf";
else
	$tmuxconfig = $DIR."update_scripts/nix_scripts/tmux/tmux.conf";

if ($seq == 1)
{
	exec("cd ${DIR}/update_scripts/nix_scripts/tmux; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
	exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;update_releases\033\"'");
	if ($import != 0)
		exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 33 'printf \"\033]2;nzb-import\033\"'");
	else
		exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 5 'printf \"\033]2;nzb-import\033\"'");

	window_utilities($tmux_session);
	window_post($tmux_session);
	window_proxy($tmux_session, 3);
	if ($colors == 1)
		window_colors($tmux_session);
	start_apps($tmux_session);
	attach($DIR, $tmux_session);
}
else if ($seq == 2)
{
	exec("cd ${DIR}/update_scripts/nix_scripts/tmux; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
	exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;sequential\033\"'");
	if ($import != 0)
		exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 33 'printf \"\033]2;nzb-import\033\"'");
	else
		exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 5 'printf \"\033]2;nzb-import\033\"'");

	window_stripped_utilities($tmux_session);
	window_proxy($tmux_session, 2);
	if ($colors == 1)
		window_colors($tmux_session);
	start_apps($tmux_session);
	attach($DIR, $tmux_session);
}
else
{
	exec("cd ${DIR}/update_scripts/nix_scripts/tmux; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;Monitor\033\"'");
	exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;update_binaries\033\"'");
	if ($import != 0)
		exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 33 'printf \"\033]2;nzb-import\033\"'");
	else
		exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 5 'printf \"\033]2;nzb-import\033\"'");
	exec("tmux selectp -t $tmux_session:0.2; tmux splitw -t $tmux_session:0 -v -p 67 'printf \"\033]2;backfill\033\"'");
	exec("tmux splitw -t $tmux_session -v -p 50 'printf \"\033]2;update_releases\033\"'");

	window_utilities($tmux_session);
	window_post($tmux_session);
	window_proxy($tmux_session, 3);

	if ($colors == 1)
		window_colors($tmux_session);
	start_apps($tmux_session);
	attach($DIR, $tmux_session);
}
?>
