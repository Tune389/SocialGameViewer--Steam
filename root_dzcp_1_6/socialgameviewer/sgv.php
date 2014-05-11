<?php
# SocialGameViewer by Tune389

## OUTPUT BUFFER START ##
include("../inc/buffer.php");

## INCLUDES ##
include(basePath."/inc/debugger.php");
include(basePath."/inc/config.php");
include(basePath."/inc/bbcode.php");

## SETTINGS ##
lang($language);
$dir = "socialgameviewer";

$lang = ($language == deutsch) ? 'german' : 'english';
$output = $cache->get('socialgameviewer_'.$lang);

if($output == null) {
    $prefix = $sql_prefix;
    $settings =  mysqli_fetch_object( db('SELECT * FROM '.$prefix.'socialgameviewer_settings WHERE id = 1 LIMIT 1'));

    $steamUrl = null;

    $count = 0;

    checkSteamDatabase();
    checkDeRankedUsers();

    $output = getListFrom(getPlayersFromCMS());
    $cache->set('socialgameviewer_'.$lang, $output, $settings->cache_delay);
}

disp($output);

//--------------------------------------------------------------------------------------------

function getPlayersFromCMS() {
    global $count, $prefix, $settings, $steamUrl;
    $qry_steam = db ( 'SELECT t1.comid, t1.steamid, t1.userid, t1.id FROM '.$prefix.'socialgameviewer_users t1 INNER JOIN '.$prefix.'users t2 ON (t1.steamid = t2.steamid)' );
    $palyers = array();
    while ( $get = _fetch ( $qry_steam ))	
    {
        if ($get['comid'] != 0) 
        {
            $playerid[(string)$get['comid']] = $get['userid'];
            $players .= $get['comid'].","; $count++;
        }
    }
    $steamUrl = getJson("http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".$settings->steam_api_key."&steamids=".substr($players,0,-1));
    return $playerid;
}

function getListFrom($playerid) {
    global $count, $settings, $steamUrl, $lang;
    
    $list = "";
    $result = $steamUrl;
    $online_member = 0;	
	$add = "";
    if ($settings->view_addfriend) {
		$x_size = $settings->x_size*0.88;
		$add = '<img src="http://steamsignature.com/AddFriend.png" width="'.$width.'"/>'
	} else {
		$x_size = $settings->x_size;
	}
    $width = $settings->x_size*0.12; 

    for ($i=0 ; $i < $count  && $online_member < $settings->max_disp ; $i++)
    {				
        $steamid = $result->response->players[$i]->steamid;
        if ($settings->view_steamlink) $href = 'http://steamcommunity.com/profiles/'.$steamid;						
        else $href = '../user/?action=user&amp;id='.$playerid[(string)$steamid];
        if (!$settings->view_offline && !$result->response->players[$i]->personastate) {}
        else if (!$settings->view_privat && $result->response->players[$i]->communityvisibilitystate < 2) {}
        else if (!empty($result->response->players[$i])) 
        {
            if ($settings->view_newtab) $add2 = 'target="_blank"';
            else $add2 = "";
            $list .= '<a href="'.$href.'" '.$add2.'><img src="http://steamsignature.com/status/'.$lang.'/'.$steamid.'.png" width="'.$x_size.'" height="'. ($x_size/5.33) .'"/></a><a href="steam://friends/add/'.$steamid.'" >'.$add.'</a><br/>' ;
            $online_member++;
        }
    }
    if ($online_member == 0) $list = "Keine Member Online";
    return $list;
}

function getJson($url) {
    return json_decode(file_get_contents($url));
}

function checkDeRankedUsers() {
    global $prefix;
    while (mysqli_fetch_object(db('select id FROM '.$prefix.'socialgameviewer_users WHERE userid = (select id from '.$prefix.'users where level < 3 limit 1)'))->id != null)
    {
        db('delete FROM '.$prefix.'socialgameviewer_users WHERE userid = (select id from '.$prefix.'users where level < 3 limit 1)');
    }
}

function checkSteamDatabase() {
    global $prefix;
    $qry_steam = db ( 'SELECT t1.id,t1.steamid FROM '.$prefix.'users t1 WHERE t1.steamid NOT LIKE "" AND t1.steamid NOT IN (SELECT steamid FROM '.$prefix.'socialgameviewer_users) and level > 2' );
    while ( $get = _fetch ( $qry_steam ))	
    {
        $data="";$ret="";
        $data=strtolower(trim($get['steamid']));
        if ($data!='') 
        {
            if (ereg('7656119', $data))
            {
                $ret = $data;
            }
            else if (substr($data,0,7)=='steam_0') 
            {
                $tmp=explode(':',$data);
                if ((count($tmp)==3) && is_numeric($tmp[1]) && is_numeric($tmp[2]))
                {							
                        $friendid=($tmp[2]*2)+$tmp[1]+1197960265728;
                        $friendid='7656'.$friendid;
                        $ret = $friendid;
                }
            }
            if ($ret!="")
            {
                $comid = $ret;
            }
            else
            {
                $steam_profile = simplexml_load_file("http://steamcommunity.com/id/".str_replace('steam_','ERROR_POFILE_FIXED',$data)."/?xml=1");
                $comid = $steam_profile->steamID64;
            }
        }
        if ($comid != "") {
            db ('INSERT INTO '.$prefix."socialgameviewer_users (`id`, `steamid`, `comid`, `userid`) VALUES (NULL, '".$get['steamid']."', '".$comid."', '".$get['id']."');");
        }
    }
}

function disp ($content) {
    echo $content; 
}
