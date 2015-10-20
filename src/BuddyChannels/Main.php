<?php

namespace BuddyChannels;
use BuddyChannels\Database;
use BuddyChannels\Message;
use BuddyChannels\MessageFormatter;
use BuddyChannels\Tasks\SendMessageTask;
use BuddyChannels\Tasks\ReadForeignMessagesTask;

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
    const VERSION = "1.1";
    const PREFIX = "&b[&aBuddy&eChannels&b] ";

    public $cfg;
    public $database;
    public $website;
    public $messageFormatter;
    public $readForeignMessagesTask;
	
    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->getCommand("buddychannels")->setExecutor(new Commands\Commands($this));
        $this->getCommand("shout")->setExecutor(new Commands\Commands($this));
	$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	$this->website = $this->read_cfg("website");
	$this->database = new \BuddyChannels\Database($this);
	
	if( $this->read_cfg("use-wordlist-censor", false) ) {
	    $badWordList = file($this->getDataFolder() . "/badwords.txt", FILE_IGNORE_NEW_LINES);
	} else {
	    $badWordList = array();
	}
	
	$this->messageFormatter = new \BuddyChannels\MessageFormatter(
	    $this->read_cfg("use-spamfilter", false),
	    $this->read_cfg("spamfilter-time-interval", 60),
	    $this->read_cfg("spamfilter-max-duplicates-per-interval", 2),
	    $this->read_cfg("spamfilter-max-messages-per-interval", 6),
	    $this->read_cfg("swearing-is-immature", false),
	    $this->read_cfg("swearing-is-immature-punishment-period", 60),
	    $this->read_cfg("use-wordlist-censor", false),
	    $badWordList
	);
	
	$this->readForeignMessagesTask = new \BuddyChannels\Tasks\ReadForeignMessagesTask($this);
	$rate_aprox_seconds = 5; // TODO assuming second is rougthly 20 ticks need to add this to the config file too
	$this->getServer()->getScheduler()->scheduleRepeatingTask($this->readForeignMessagesTask, $rate_aprox_seconds * 20);
    }

    public static function translateColors($symbol, $message) {
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
    
    public static function removeColors($symbol, $message) {
	$colourCodes = str_split("1234567890abcdefghijklmnopqrstuvwxyz");
	foreach($colourCodes as $key => $code) {
	    $message = str_replace($symbol.$code, "", $message);
	}
	return $message;
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
    
    public function getPlayerRank(Player $player) {
	$playername = strtolower($player->getName());
	return $this->getUserRank($playername);
    }
    
    public function getUserRank($playername) {
	// TODO very hacky! - should get data from pureperms plugin, should have a range of classes selectable
	// by config for supporting other plugins
	if($this->read_cfg("read-ranks-from", "none") != "pureperms") {
	    return "";
	}
	$playername = strtolower($playername);
	$purePermsPlayersFolder = $this->getDataFolder() . "/../PurePerms/players";
	
	$rank_yml_cmd = "cat \"$purePermsPlayersFolder/$playername.yml\" | grep \"group: \" | cut -d\" \" -f2 | head -n 1 | xargs printf";
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
 
    public function processNewMessage(Player $player, $message, $shouting = false) {
	$username_lcase = strtolower($player->getName());
	$message = new \BuddyChannels\Message(
	    $player,
	    $this->users[$username_lcase], // channel number
	    $this->channelnames[$this->users[$username_lcase]], // channel name
	    $this->getPlayerRank($player),
	    $message,
	    $shouting
	);
	$messageTask = new \BuddyChannels\Tasks\SendMessageTask($message, $this->messageFormatter, $this->database);
	$this->getServer()->getScheduler()->scheduleAsyncTask($messageTask);
    }

}


/*

*/
?>
