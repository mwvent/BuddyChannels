<?php

namespace BuddyChannels\Commands;
use BuddyChannels\Main;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Commands extends PluginBase implements CommandExecutor{
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
	$valid_commands = array("buddychannels", "shout", "block", "unblock");
	if(!in_array($cmd->getName(), $valid_commands)) {
		return;
	}

	// shout command alias for /ch shout
	if(strtolower($cmd->getName()) == "shout") {
		$newargs = array(
		    0 => "shout",
		    1 => implode(" ", $args)
		);
		$args = $newargs;
	}
	
	// block command alias for /ch block
	if(strtolower($cmd->getName()) == "block") {
		$newargs = array(0 => "block");
		if( isset($args[0]) ) {
		    $newargs[1] = $args[0];
		}
		$args = $newargs;
	}
	
	// unblock command alias for /ch unblock
	if(strtolower($cmd->getName()) == "unblock") {
		$newargs = array(0 => "unblock");
		if( isset($args[0]) ) {
		    $newargs[1] = $args[0];
		}
		$args = $newargs;
	}
	
	// no args help shortcut
	if(!isset($args[0])) {
		$args[0]="help";
	}
	
	// ch join shortcut i.e. /ch 1 is = to /ch join 1
	if(is_numeric($args[0])) {
		$args[1] = $args[0];
		$args[0] = "join";
	}
	
	switch($args[0]) {
	    case "block" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		if( ! isset($args[1]) ) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /ch block <name>"));
		    return;
		}
		$player = $this->plugin->getPlayer($args[1]);
		if(!$player){
		    $player = null;
		    $player_name = $args[1];
		} else {
		    $player_name = $player->getName();
		}
		if(strtolower($player_name) === strtolower($sender->getName())){
		    $sender->sendMessage(TextFormat::RED . "[Error] You can't block yourself silly :-)");
		    return false;
		}
		if( !is_null($player) ) {
		    $newTask = new \BuddyChannels\Tasks\SetUserBlockTask($this->plugin, $sender, $player_name, true, $player);
		} else {
		    $newTask = new \BuddyChannels\Tasks\SetUserBlockTask($this->plugin, $sender, $player_name, true);
		}
		break;
	    case "unblock" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		if( ! isset($args[1]) ) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /ch unblock <name>"));
		    return;
		}
		$player = $this->plugin->getPlayer($args[1]);
		if(!$player){
		    $player = null;
		    $player_name = $args[1];
		} else {
		    $player_name = $player->getName();
		}
		if(strtolower($player_name) === strtolower($sender->getName())){
		    $sender->sendMessage(TextFormat::RED . "[Error] You can't unblock yourself silly :-)");
		    return false;
		}
		if( !is_null($player) ) {
		    $newTask = new \BuddyChannels\Tasks\SetUserBlockTask($this->plugin, $sender, $player_name, false, $player);
		} else {
		    $newTask = new \BuddyChannels\Tasks\SetUserBlockTask($this->plugin, $sender, $player_name, false);
		}
		break;
	    case "mute" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		$newTask = new \BuddyChannels\Tasks\SetMutePublicTask($this->plugin, $sender, true);
		break;

	    case "unmute" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		$newTask = new \BuddyChannels\Tasks\SetMutePublicTask($this->plugin, $sender, false);
		break;
	    
	    case "shout" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		if( ! isset($args[1]) ) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /sh <text>"));
		    return;
		}
		$player = $sender;
		$username = strtolower($player->getName());
		$userchannel_number = $this->plugin->database->read_cached_user_channels($username);
		$userchannel_name = $this->plugin->database->read_cached_channelNames($userchannel_number);
		$username_lcase = strtolower($player->getName());
		$message = new \BuddyChannels\Message(
		    $player,
		    $userchannel_number, // channel number
		    $userchannel_name, // channel name
		    $this->plugin->getPlayerRank($player),
		    implode(" ",array_slice($args, 1)),
		    true // shouting
		);
		$messageTask = new \BuddyChannels\Tasks\SendMessageTask($message, $this->plugin);
		break;
		
	    case "help" : 
		$messages = array(
		    "&b>> &aAvailable Commands &b<<",
		    "&a/ch info &b>>&e Show info about this plugin",
		    "&a/ch help &b>>&e Show help about this plugin",
		    "&a/ch list &b>>&e Show the list of all channels",
		    "&a/ch &c<number>&b>>&e Switch channel numbers",
		    "&a/shout &b>>&e send a message to public chan",
		    "&a/sh &b>>&e shortcut for shout",
		    "&a/ch mute &b>>&e Mutes chat from public channel",
		    "&a/ch unmute &b>>&e Unmutes chat from public channel",
		    "&a/ch block/unblock &c<username> &b>>&e Blocks or unblocks a user from your chat"
		    
		);
		foreach($messages as $message) {
		    $sender->sendMessage($this->plugin->translateColors("&", $message));
		}
		break;
	    
	    case "info" :
		if( ! $sender->hasPermission("buddychannels.commands.info")) {
			$sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
			return;
		}
		$sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&eBuddyChannels &bv" . Main::VERSION . " &edeveloped by&b " . Main::PRODUCER));
		$sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&eWebsite &b" . $this->plugin->website));
		break;
	    
	    case "list" :
		if( ! $sender->hasPermission("buddychannels.commands.list")) {
			$sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
			return;
		}
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		if( ! isset($args[1])) {
		    $page = 1;
		} else {
		    $page = $args[1];
		}
		if( ! is_numeric($page)) {
		    $sender->sendMessage(Main::translateColors("&", Main::PREFIX  . "&cPage must be a number - showing page 1"));
		    $page = 1;
		}
		$newTask = new \BuddyChannels\Tasks\ListChannelsTask($this->plugin, $sender, $this->plugin->website, $page);
		break;
	    
	    case "join" :
		if( ! $sender->hasPermission("buddychannels.commands.join")) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
		    return;
		}
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&cYou can only perform this command as a player"));
		    return;
		}
		if( ! isset($args[1])) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX  . "&cUsage: /sch join <channel number>"));
		    return;
		}
		if( ! is_numeric($args[1])) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX  . "&cChannel must be a number"));
		    return;
		}
		$newTask = new \BuddyChannels\Tasks\JoinChannelTask($this->plugin, $sender, $args[1], $this->plugin->website);
		break;	
	}
    }
}
?>
