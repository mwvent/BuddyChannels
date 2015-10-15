<?php

namespace BuddyChannels;

use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Mysqli;

class Main extends PluginBase {
	const PRODUCER = "mwvent";
	const VERSION = "1.0";
	const MAIN_WEBSITE = "https://wattz.org.uk/mcpe";
	const PREFIX = "&b[&aBuddy&eChannels&b] ";
	const BABY_TIMEOUT_SECONDS = 60;
	
	public $cfg;
	public $users;
	private $users_hasmutedpublic;
	private $users_babyendtime;
	public $channelnames;
	private $db;
	private $tables;
	public $website;
	public $lastmessages; // array of arrays key is player name sub array is recent messages with timestamp
	private $badWordList;
	
	
    public function translateColors($symbol, $message) {
	    $message = str_replace($symbol."0", TextFormat::BLACK, $message);
	    $message = str_replace($symbol."1", TextFormat::DARK_BLUE, $message);
	    $message = str_replace($symbol."2", TextFormat::DARK_GREEN, $message);
	    $message = str_replace($symbol."3", TextFormat::DARK_AQUA, $message);
	    $message = str_replace($symbol."4", TextFormat::DARK_RED, $message);
	    $message = str_replace($symbol."5", TextFormat::DARK_PURPLE, $message);
	    $message = str_replace($symbol."6", TextFormat::GOLD, $message);
	    $message = str_replace($symbol."7", TextFormat::GRAY, $message);
	    $message = str_replace($symbol."8", TextFormat::DARK_GRAY, $message);
	    $message = str_replace($symbol."9", TextFormat::BLUE, $message);
	    $message = str_replace($symbol."a", TextFormat::GREEN, $message);
	    $message = str_replace($symbol."b", TextFormat::AQUA, $message);
	    $message = str_replace($symbol."c", TextFormat::RED, $message);
	    $message = str_replace($symbol."d", TextFormat::LIGHT_PURPLE, $message);
	    $message = str_replace($symbol."e", TextFormat::YELLOW, $message);
	    $message = str_replace($symbol."f", TextFormat::WHITE, $message);
	    $message = str_replace($symbol."k", TextFormat::OBFUSCATED, $message);
	    $message = str_replace($symbol."l", TextFormat::BOLD, $message);
	    $message = str_replace($symbol."m", TextFormat::STRIKETHROUGH, $message);
	    $message = str_replace($symbol."n", TextFormat::UNDERLINE, $message);
	    $message = str_replace($symbol."o", TextFormat::ITALIC, $message);
	    $message = str_replace($symbol."r", TextFormat::RESET, $message);
	    // $message = str_replace(" ", "ᱹ", $message);
	    return $message;
    }
    
    public function wordlist_censor($msg) {
	if( ! $this->read_cfg("use-wordlist-censor", false) ) {
	    return $msg;
	}
	return str_ireplace($this->badWordList,"*",$msg);
	print_r($this->badWordList);
    }
    
    public function spamTest($player, $msg) {
	if( ! $this->read_cfg("use-spamfilter", false) ) {
	    return false;
	}
	$origmsg = $msg;
	$msg = strtolower(str_replace(" ", "", $msg)); // remove spaces and caps
	$playername = $player->getName();
	// if no history store msg and return
	if( ! isset($this->lastmessages[$playername]) ) {
	    $playername = strtolower($playername);
	    $this->lastmessages[$playername] = array();
	    $this->lastmessages[$playername][time()] = $msg;
	    return false;
	}
	// save message in array
	$thismsgtime = time();
	$this->lastmessages[$playername][$thismsgtime] = strtolower($msg);
	// has history - clear any items older than spamcheck_message_age config value
	foreach(  $this->lastmessages[$playername] as $msgtime => $prevmsg  ) {
	    if( ( time() - $msgtime ) > $this->read_cfg("spamfilter-time-interval", 60) ) {
		unset( $this->lastmessages[$playername][$msgtime] );
	    }
	}
	// now return true if player has posted more than two of the same in the remaining interval
	$counted_prevMsgs = array_count_values( $this->lastmessages[$playername] );
	if( isset( $counted_prevMsgs[$msg] ) ) {
	    if( $counted_prevMsgs[$msg] > $this->read_cfg("spamfilter-max-duplicates-per-interval", 2) ) {
		$sendmsg = "&cYour message has failed spam detection and has been blocked.";
		$player->sendMessage($this->translateColors("&", $sendmsg));
		$sendmsg = $playername . "'s message was block by spam filter - message was - " . $origmsg;
		Server::getInstance()->getLogger()->info($this->translateColors("&" , Main::PREFIX . $sendmsg));
		unset( $this->lastmessages[$playername][$thismsgtime] );
		return true;
	    }
	}
	
	// now return true if rate limit exceeded ( msgs in the  history interval )
	$count_of_recent = count( $this->lastmessages[$playername] );
	if ( $count_of_recent >= $this->read_cfg("spamfilter-max-messages-per-interval", 6) ) {
	    $sendmsg = "&cYou have exceeded the chat rate limit of "
		       . $this->read_cfg("spamfilter-max-messages-per-interval", 6)
		       . " messages per " . $this->read_cfg("spamfilter-time-interval", 60) . "s";
	    $player->sendMessage($this->translateColors("&", $sendmsg));
	    $sendmsg = $playername . "'s message was block by rate filter - message was - " . $origmsg;
	    Server::getInstance()->getLogger()->info($this->translateColors("&" , Main::PREFIX . $sendmsg));
	    unset( $this->lastmessages[$playername][$thismsgtime] );
	    return true;
	}
	
	// otherwise all is ok
	return false;
    }
    
    public function hasVeryBadLanguage($msg) {
	if( ! $this->read_cfg("swearing-is-immature", false) ) {
	    return false;
	}
	$reallybadwords = array(
	    "fuck",
	    "fucking",
	    "fuking",
	    "bitch",
	    "bastard",
	    "fucker"
	);
	$permittedVariations = array(
	    "itch",
	    "uck"
	);
	
	$colourCodes = str_split("1234567890abcdefghijklmnopqrstuvwxyz");
	foreach($colourCodes as $key => $code) {
	    $colourCodes[$key] = "&" . $code;
	}

	// make 1 letter missing variations
	$badwordlist = array();
	foreach($reallybadwords as $badword) {
	    $badwordlist[] = $badword;
	    $letters = str_split($badword);
	    for($i = 0; $i<count($letters); $i++) {
		$variation = "";
		for($x = 0; $x<count($letters); $x++) {
		    if($x <> $i) $variation .= $letters[$x];
		}
		if( ! in_array($variation, $permittedVariations) ) $badwordlist[] = $variation;
	    }
	}
	
	// manual (non fudged) additions
	// $badwordlist[] = "shit";
	$badwordlist[] = "cunt";
	$badwordlist[] = "nigger";
	$badwordlist[] = "nigga";
	$badwordlist[] = "f***";
	$badwordlist[] = "s***";
	
	
	// replace caps
	$msg = strtolower($msg);
	// replace l33t chars
	$msg = str_replace("1", "i", $msg);
	$msg = str_replace("3", "e", $msg);
	$msg = str_replace("4", "a", $msg);
	$msg = str_replace("5", "s", $msg);
	$msg = str_replace("7", "t", $msg);
	$msg = str_replace("8", "ate", $msg);
	// replace codes
	$msg = str_replace($colourCodes, "", $msg);
	// replace some pronouns
	$msg = str_replace("you", "", $msg);
	// take out non alphanumeric chars
	$msg = preg_replace('/[^a-z\d ]/i', '', $msg);	
	echo $msg . PHP_EOL;
	$msg_words = explode(" ", $msg);
	// add an entry with the spaces taken out
	$msg_words[] =  str_replace(" ", "", $msg);
	// add entry with i's replaced by 1s
	$msg_words[] =  str_replace( "i", "1", str_replace(" ", "", $msg));
	
	foreach($badwordlist as $badword) {
		$badword = strtolower($badword);
		if(in_array($badword, $msg_words)) {
		    return true;
		}
		foreach($msg_words as $msg_word) {
		    if( ! stripos($msg_word, $badword) === false ) {
			return true;
		    }
		}
	}
    }
    
    public function read_cfg($key, $defaultvalue = null) {
	// if not loaded config load and continue
	if( ! isset($this->cfg) ) {
	    $this->cfg = $this->getConfig()->getAll();
	}
	// if key not in config but a default value is allowed return default
	if( ( ! isset($this->cfg[$key]) ) && ( ! is_null( $defaultvalue ) ) ) {
	    return $defaultvalue;
	}
	// if key not in config but is required
	if( ( ! isset($this->cfg[$key]) ) && ( ! is_null( $defaultvalue ) ) ) {
	    $sendmsg = "Cannot load " . Main::PREFIX . " required config key " . $key . " not found in config file";
	    Server::getInstance()->getLogger()->critical($this->translateColors("&", Main::PREFIX . $sendmsg));
	    die();
	}
	// otherwise return config file value
	return $this->cfg[$key];
    }
	
    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->getCommand("buddychannels")->setExecutor(new Commands\Commands($this));
        $this->getCommand("shout")->setExecutor(new Commands\Commands($this));
	$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	$this->channelnames = array("0" => "Public");
	$this->users_hasmutedpublic = array();
	$this->users_babyendtime = array();
	$this->lastmessages = array();
	if( $this->read_cfg("use-wordlist-censor", false) ) {
	    $this->badWordList = file($this->getDataFolder() . "/badwords.txt", FILE_IGNORE_NEW_LINES);
	}
	    
	$this->tables = array(
	    "bpgroupmembers" => $this->read_cfg("buddypress-bp_groups_members-tablename"),
	    "bpgroups" => $this->read_cfg("buddypress-bp_groups-tablename"),
	    "wpusers" => $this->read_cfg("wordpress-users-tablename"),
	    "bcusers" => $this->read_cfg("buddychannels-users-table"),
	    "chatlog" => $this->read_cfg("buddychannels-chatlog-table"),
	    "servers" => $this->read_cfg("buddychannels-servers-table")
	);
	
	$this->website = $this->read_cfg("website");
	
	$this->db = new \mysqli(
	    $this->read_cfg("mysql-server"),
	    $this->read_cfg("mysql-user"), 
	    $this->read_cfg("mysql-pass"), 
	    $this->read_cfg("database")
	);
	if ($this->db->connect_errno) {
	    trigger_error("DB Error");
	}
	$sql = "
	    CREATE TABLE IF NOT EXISTS `" . $this->tables["bcusers"] . "` (
		`username` VARCHAR(50),
		`channel` INT,
		`hasmutedpublic` INT,
		PRIMARY KEY (`username`)
	    )
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    Server::getInstance()->getLogger()->critical(
		$this->translateColors("&", Main::PREFIX . "DB Error " . $this->db->error));
	}
	$sql = "
	    CREATE TABLE IF NOT EXISTS `" . $this->tables["chatlog"] . "` (
		`messagetime` DATETIME DEFAULT CURRENT_TIMESTAMP,
		`serverid` INT,
		`username` VARCHAR(50),
		`channel` INT,
		`channelname` TEXT,
		`message` TEXT,
		`unfiltered_message` TEXT,
		`was_baby` TINYINT,
		`was_shouting` TINYINT
	    )
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    Server::getInstance()->getLogger()->critical(
		$this->translateColors("&", Main::PREFIX  . "DB Error " . $this->db->error));
	}
	$sql = "
	    CREATE TABLE IF NOT EXISTS `" . $this->tables["servers"] . "` (
		`serverid` INT,
		`servername` VARCHAR(50),
		`connect_chat` TINYINT,
		PRIMARY KEY (`serverid`)
	    )
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    Server::getInstance()->getLogger()->critical(
		$this->translateColors("&", Main::PREFIX  . "DB Error " . $this->db->error));
	}
	$sql = "
	    INSERT INTO `" . $this->tables["servers"] . "` 
		(`serverid`, `servername`, `connect_chat`)
	    VALUES
		( '" . $this->read_cfg("server-id", 0) .  "',
		'" . $this->read_cfg("server-name", "untitled") . "' ,
		'" . $this->read_cfg("connect-server-chat", false) . "')
	    ON DUPLICATE KEY UPDATE 
		`serverid` = '" . $this->read_cfg("server-id", 0) . "',
		`servername` = '" . $this->read_cfg("server-name", "untitled") . "',
		`connect_chat` = '" . $this->read_cfg("connect-server-chat", false) . "'
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    trigger_error("DB Error" . $this->db->error);
	}
	Server::getInstance()->getLogger()->info(
	    $this->translateColors("&", Main::PREFIX  . "Ready"));
    }
    
    function setUserChannel($username, $channel) {
	$username_sql = $this->db->real_escape_string( strtolower($username) );
	$channel_sql = $this->db->real_escape_string( strtolower(strval($channel)) );
	$sql = "
	    INSERT INTO `" . $this->tables["bcusers"] . "` 
		(`username`, `channel`, `hasmutedpublic`)
	    VALUES
		( '" . $username_sql .  "', '" . $channel_sql . "' , 0)
	    ON DUPLICATE KEY UPDATE `channel` = '" . $channel_sql . "' 
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    trigger_error("DB Error" . $this->db->error);
	}
    }
    
    function setUserPublicMute($username, $hasmutedpublic) {
	if($hasmutedpublic != "1") {
	    $hasmutedpublic != "0";
	}
	$username_sql = $this->db->real_escape_string( strtolower($username) );
	$sql = "
	    INSERT INTO `" . $this->tables["bcusers"] . "` 
		(`username`, `channel`, `hasmutedpublic`)
	    VALUES
		( '" . $username_sql .  "', 0 , " . $hasmutedpublic . ")
	    ON DUPLICATE KEY UPDATE `hasmutedpublic` = " . $hasmutedpublic . "
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    trigger_error("DB Error" . $this->db->error);
	}
	$this->users_hasmutedpublic[strtolower($username)] = $hasmutedpublic;
    }
    
    public function getLogOnConsole(){
    	$tmp = $this->getConfig()->getAll();
    	return $tmp["log-on-console"];
    }
    
    public function getPlayersChannel($player) {
	$username_sql = $this->db->real_escape_string( strtolower( $player->getName() ) );
	$sql = "
	SELECT
	    `" . $this->tables["bpgroupmembers"] . "`.`group_id` AS `channelnumber`,
	    `" . $this->tables["bpgroups"] . "`.`name` AS `groupname`,
	    `" . $this->tables["bcusers"] . "`.`username` AS `username`,
	    `" . $this->tables["bcusers"] . "`.`hasmutedpublic` AS `hasmutedpublic`
	FROM `" . $this->tables["wpusers"] . "`
	    INNER JOIN `" . $this->tables["bpgroupmembers"] . "`
		ON `" . $this->tables["bpgroupmembers"] . "`.`user_id`=`" . $this->tables["wpusers"] . "`.`ID`  
	    INNER JOIN `" . $this->tables["bpgroups"] . "`
		ON `" . $this->tables["bpgroupmembers"] . "`.`group_id`=`" . $this->tables["bpgroups"] . "`.`id`
	    INNER JOIN `" . $this->tables["bcusers"] . "`
		ON `" . $this->tables["bcusers"] . "`.`channel`= `" . $this->tables["bpgroupmembers"] . "`.`group_id`
	WHERE
	    LCASE(`" . $this->tables["bcusers"] . "`.`username`)='" . $username_sql . "'
	    AND is_confirmed=1
	    AND is_banned=0
	    LIMIT 1
	;
	";
	if( ! $result = $this->db->query($sql) ) {
	    trigger_error("DB Error" . $this->db->error);
	    return 0;
	}
	$this->users_hasmutedpublic[strtolower( $player->getName() )] = 0;
	if( $row = $result->fetch_assoc() ) {
	    $this->channelnames[$row['channelnumber']] = stripcslashes($row['groupname']);
	    $this->users_hasmutedpublic[strtolower( $player->getName() )] = $row['hasmutedpublic'];
	    return $row['channelnumber'];
	}
	return 0;
    }
    
    // TODO FAILSAFE WRAPPER TO REPLACE direct reading of $this->channelnames array
    
    public function joinChannel(Player $player, $channelnumber) {
	$this->setUserChannel($player->getName(), $channelnumber);
	// verify (and ensure channel name is in channelnames array by calling getPlayersChannel)
	$newchannelnumber = $this->getPlayersChannel($player);
	if($newchannelnumber != $channelnumber) {
	    $msg = "&cCould not join channel &6" . $channelnumber . "&c use /ch list or join channels via the website";
	    $player->sendMessage($this->translateColors("&", $msg));
	    $msg = "&b" . $this->website;
	    $player->sendMessage($this->translateColors("&", $msg));
	}
	$msg = "&dYou are currentley in channel &c" . $newchannelnumber . " &o&n&6{" . 
		$this->channelnames["$newchannelnumber"] . "}";
	$player->sendMessage($this->translateColors("&", $msg));
	if($newchannelnumber != 0) {
	    if($this->users_hasmutedpublic[strtolower( $player->getName() )] == 1) {
		$muteStatusTxt = "&cmuted";
	    } else {
		$muteStatusTxt = "&aunmuted";
	    }
	    $msg = "&dThe public channel is " . $muteStatusTxt;
	    $player->sendMessage($this->translateColors("&", $msg));
	}
	$this->users[strtolower($player->getName())] = $channelnumber;
    }
    
    public function getAllChannels(Player $player) {
	$user = $player->getName();
    	$user = strtolower($user);

	$groups = array();
	$groups[0] = "Public"; // there will always be a public channel 0

	$user_sql = $this->db->real_escape_string($user);
	
	$sql = "
		SELECT `" . $this->tables["bpgroupmembers"] . "`.`group_id`, `" . $this->tables["bpgroups"] . "`.`name` 
		    FROM `" . $this->tables["wpusers"] . "`
			INNER JOIN `" . $this->tables["bpgroupmembers"] . "`
			    ON `" . $this->tables["bpgroupmembers"] . "`.`user_id`=`" . $this->tables["wpusers"] . "`.`ID`  
			INNER JOIN " . $this->tables["bpgroups"] . "
			    ON `" . $this->tables["bpgroupmembers"] . "`.`group_id`=`" . $this->tables["bpgroups"] . "`.`id`
		WHERE
		  LCASE(`" . $this->tables["wpusers"] . "`.`user_nicename`)=\"" . $user_sql . "\"
		  AND is_confirmed=1
		  AND is_banned=0
		;
	";

	if( ! $res = $this->db->query($sql) ) {
	    Server::getInstance()->getLogger()->critical(
		$this->translateColors("&", Main::PREFIX  . "&cDB Error " . $this->db->error));
	    return $groups;
	}

	// TODO PAGIGNATION
	while ($row = $res->fetch_assoc()) {
	    $groups[$row['group_id']] = stripcslashes($row['name']);
	}

	return $groups;
    }
    
    
    
    public function getPlayerRank(Player $player) {
	$playername = strtolower($player->getName());
	// TODO very hacky! - should be file get contents read etc at least or direct plugin comms at best
	$rank_yml_cmd = "cat /home/minecraft/pocketmine/plugins/PurePerms/players/" .$playername . ".yml | grep \"group: \" | cut -d\" \" -f2 | head -n 1 | xargs printf";
	$rank = exec($rank_yml_cmd);
	if($rank == "") {
		    return "";
	}
	switch(strtolower($rank)) {
	    case "newbie":
		$rank_formatted = "&6[Newbie]";
		break;
	    case "junior":
	        $rank_formatted = "&b[Junior]";
		break;
	    case "senior":
		$rank_formatted = "&1[Senior]";
		break;
	    case "elder":
		$rank_formatted = "&c[Elder]";
		break;
	    case "owner":
		$rank_formatted = "&c[&6E&7n&8g&9i&an&be&ce&dr&c]";
		break;
	    default:
		$rank_formatted = "&c[" . $rank . "]";
		break;
	}
	return $rank_formatted;
    }
    
    public function getChannelFormat($channelnumber, $channelname, Player $player, $message, $samechan = false) {	
	$channel_formatted =  "&o&n&6{" . $channelname . "}";
	if($channelnumber == 0) {
	    $channel_formatted =  "&o&n&1{Public}";
	}
	
	$message_elements = array();
	if( ! $samechan) { 
	    $message_elements[] = $channel_formatted; 
	}
	$message_elements[] = $this->getPlayerRank($player);
	$message_elements[] = $player->getName();
	$message_elements[] = "&a‣&r" . $message;
	return implode("&r&f ", $message_elements);
    }
    
    public function babyFilter(Player $player, $message) {
	// check for extreme bad language (words that have higher occurance of false positive can be handled
	// by a general swear filter
	$is_baby = false;
	$msg_has_profanity = $this->hasVeryBadLanguage($message);
	// already on a swearing punishment?
	if( isset($this->users_babyendtime[$player->getName()]) ) {
	    $bendtime = $this->users_babyendtime[$player->getName()];
	    $timeleft = ( $bendtime - time() );
	    if( $timeleft > 0 ) {
		$is_baby = true;
		// now we will tell the user they are still a baby - unless they have swore again, that will be handled below
		if( ! $msg_has_profanity ) {
		    $sendmsg = $this->translateColors("&" , "&3You are still a baby for the next &c" . $timeleft . "&3 seconds.");
		    $player->sendMessage($sendmsg);
		}
	    } else {
		$is_baby = false;
		unset($this->users_babyendtime[$player->getName()]);
		$sendmsg = $this->translateColors("&" , "&3You are no longer a baby - may we please remind you that swearing can result in a permanent ban.");
		$player->sendMessage($sendmsg);
	    }
	}
	// swore (again?)
	if($msg_has_profanity) {
	    $sendmsg = $this->translateColors("&" , "&cWe have detected you are using profanity.");
	    $player->sendMessage($sendmsg);
	    $sendmsg = Main::PREFIX . $player->getName() . " activated baby swear filter with message " . $message;
	    Server::getInstance()->getLogger()->info($this->translateColors("&" , $sendmsg));	
	    // swore while not already a baby
	    if( ! $is_baby ) {
		$sendmsg = "&3Swearing is for babies - You are now a baby for &c" . $this->read_cfg("swearing-is-immature-punishment-period", 60) . "&3 seconds.";
		$player->sendMessage($this->translateColors("&" , $sendmsg));
		$is_baby = true;
		$this->users_babyendtime[$player->getName()] = ( time() + $this->read_cfg("swearing-is-immature-punishment-period", 60) );
	    } else { // swore while already a baby
		$bendtime = $this->users_babyendtime[$player->getName()];
		$timeleft = (( $bendtime - time() ) * 2 ) + $this->read_cfg("swearing-is-immature-punishment-period", 60);
		$this->users_babyendtime[$player->getName()] = ( time() + $timeleft );
		$sendmsg = "&3Your time as a baby has been extended to &c" . $timeleft . "&3 seconds.";
		$player->sendMessage($this->translateColors("&" , $sendmsg));
		$is_baby = true;
	    }
	}
	
	// if a baby - pre-format the message
	if( $is_baby ) {
	    $babyisms = array("goo","ga", "gurgle");
	    $msg_words = explode(" ", $message);
	    $message = "";
	    foreach($msg_words as $i) { $message .= $babyisms[array_rand($babyisms)] . " "; }    
	}
	return $message;
    }
    
    public function SendChannelMessage(Player $player, $message, $shouting = false) {
	$unfiltered_message = $message;
    
	// check for spam
	if( $this->spamTest($player, $message) ) {
	    return;
	}
	
        // (re) init users channel
        $channelnumber = $this->getPlayersChannel($player);
        $channelname = $this->channelnames[$channelnumber];
	
	// array to be filled with the names of players who can receive msg
	// player can always receive their own echo
	$names_of_players_receiving_message = array(strtolower($player->getName()));
	
	// format the message
	$is_baby = false;
	$babyfiltered = $this->babyFilter($player, $message);
	if($babyfiltered != $message) {
	    $is_baby = true;
	    $message = $babyfiltered;
	}
	$message = $this->wordlist_censor($message);
	$message_formatted = $this->translateColors("&", $this->getChannelFormat($channelnumber, $channelname, $player, $message));
	$message_formatted_sc = $this->translateColors("&", $this->getChannelFormat($channelnumber, $channelname, $player, $message, true));
	
	// if broadcasting to public channel or shouting to it we need to see who wants to hear
	if( $channelnumber == 0 || $shouting ) {
	    // generate MySQL WHERE Clause for currentley logged on users who are either
	    // in the public channel or do not have it muted
	    $players_online = array();
	    foreach( $this->getServer()->getOnlinePlayers() as $curplayer ) {
		$curpname = $curplayer->getName();
		$players_online[$curpname] = "`username`='" . $this->db->real_escape_string( strtolower( $curpname ) ) . "'";
	    }
	    $players_online_query_arr = $players_online;
	    $players_online_sql = "(" . implode(" OR ", $players_online_query_arr) . ")";
	    $sql_where_params = implode(" AND ", array("(`hasmutedpublic`= 0 OR `channel`= 0)", $players_online_sql));
	    // create array of the players that will hear a public message3
	    $sql = "SELECT * FROM `" . $this->tables["bcusers"] . "` WHERE " . $sql_where_params . ";";
	    if( ! $result = $this->db->query($sql) ) {
		 Server::getInstance()->getLogger()->critical(
		    $this->translateColors("&", Main::PREFIX  . "DB Error " . $this->db->error));
	    } else {
		  while($row = $result->fetch_assoc()) {
		      $names_of_players_receiving_message[] = strtolower($row['username']);
		  }
	    }
	    // add to array of the players that are friends with the player
	    // TODO
	} else {
	    // otherwise send to players in same channel only
	    $sql = "SELECT * FROM `" . $this->tables["bcusers"] . "`
		    WHERE `channel` = '" . $this->db->real_escape_string($channelnumber) . "';";
	    if( ! $result = $this->db->query($sql) ) {
		Server::getInstance()->getLogger()->critical(
		    $this->translateColors("&", Main::PREFIX  . "DB Error " . $this->db->error));
	    } else {
		while( $row = $result->fetch_assoc() ) {
		    $names_of_players_receiving_message[] = strtolower($row['username']);
		}
	    }
	}
	
	// send message to qualifying players
	foreach( $this->getServer()->getOnlinePlayers() as $curplayer ) {
	    $curpname = $curplayer->getName();
	    if( in_array(strtolower($curpname), $names_of_players_receiving_message) ) {
		  if( $channelnumber == 0 || $shouting ) {
		      $curplayer->sendMessage($message_formatted);
		  } else {
		      $curplayer->sendMessage($message_formatted_sc);
		  }
	    }
	}
	
	if($this->getLogOnConsole()){
		Server::getInstance()->getLogger()->info(
		    $this->translateColors("&", Main::PREFIX) . $message_formatted);
	}
	//TODO Log chat to db
	$sqlvars = array();
	$sqlvars[] = "'" . $this->db->real_escape_string( $this->read_cfg("server-id", 0) ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $player->getName() ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $channelnumber ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $channelname ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $message ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $unfiltered_message ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $is_baby ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $shouting ) . "'";
	$sql = "INSERT INTO `" . $this->tables["chatlog"] . "`
		(`serverid`, `username`, `channel`, `channelname`, `message`, `unfiltered_message`, `was_baby`, `was_shouting`)
		VALUES ( " . implode(",", $sqlvars) . " )
		;
	";
	if( ! $this->db->query($sql) ) {
	    Server::getInstance()->getLogger()->critical(
		$this->translateColors("&", Main::PREFIX  . "DB Error " . $this->db->error));
	}
    }
    
}


/*

*/
?>
