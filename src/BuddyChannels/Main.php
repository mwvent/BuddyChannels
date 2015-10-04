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
	
	public $cfg;
	public $users;
	private $users_hasmutedpublic;
	public $channelnames;
	private $db;
	private $tables;
	public $website;
	
	
	public function translateColors($symbol, $message){
		
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
		
		return $message;
	}
	
    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->cfg = $this->getConfig()->getAll();
        $this->getCommand("buddychannels")->setExecutor(new Commands\Commands($this));
        $this->getCommand("shout")->setExecutor(new Commands\Commands($this));
	$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	$this->channelnames = array("0" => "Public");
	$this->users_hasmutedpublic = array();
	// TODO default values if not in array
	$this->tables = array(
	    "bpgroupmembers" => $this->cfg["buddypress-bp_groups_members-tablename"],
	    "bpgroups" => $this->cfg["buddypress-bp_groups-tablename"],
	    "wpusers" => $this->cfg["wordpress-users-tablename"],
	    "bcusers" => $this->cfg["buddychannels-users-table"],
	    "chatlog" => $this->cfg["buddychannels-chatlog-table"]
	);
	
	$this->website = $this->cfg["website"];
	
	$this->db = new \mysqli(
	    $this->cfg["mysql-server"],
	    $this->cfg["mysql-user"], 
	    $this->cfg["mysql-pass"], 
	    $this->cfg["database"]
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
		`message` TEXT
	    )
	    ;
	";
	if( ! $this->db->query($sql) ) {
	    Server::getInstance()->getLogger()->critical(
		$this->translateColors("&", Main::PREFIX  . "DB Error " . $this->db->error));
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
	    $this->channelnames[$row['channelnumber']] = $row['groupname'];
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
	    $groups[$row['group_id']] = $row['name'];
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
	$message_elements[] = "&a> &r" . $message;
	return implode("&r&f ", $message_elements);
    }
    
    public function SendChannelMessage(Player $player, $message, $shouting = false) {
        // (re) init users channel
        $channelnumber = $this->getPlayersChannel($player);
        $channelname = $this->channelnames[$channelnumber];
	
	// array to be filled with the names of players who can receive msg
	// player can always receive their own echo
	$names_of_players_receiving_message = array(strtolower($player->getName()));
	
	// format the message	
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
	$sqlvars[] = "'1'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $player->getName() ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $channelnumber ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $channelname ) . "'";
	$sqlvars[] = "'" . $this->db->real_escape_string( $message ) . "'";
	$sql = "INSERT INTO `" . $this->tables["chatlog"] . "`
		(`serverid`, `username`, `channel`, `channelname`, `message`)
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
