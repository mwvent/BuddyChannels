<?php

namespace BuddyChannels\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use BuddyChannels\Main;

class Commands extends PluginBase implements CommandExecutor{
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
	if(!strtolower($cmd->getName()) == "buddychannels" && !strtolower($cmd->getName()) == "shout") {
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
	
	// no args help shortcut
	if(!isset($args[0])) {
		$args[0]="help";
	}
	
	// ch join shortcut
	if(is_numeric($args[0])) {
		$args[1] = $args[0];
		$args[0] = "join";
	}
	
	switch($args[0]) {
	    case "mute" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		$sender->sendMessage($this->plugin->translateColors("&", "&cMuting &dpublic channel"));
		$this->plugin->setUserPublicMute($sender->getName(), 1);
		break;

	    case "unmute" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		$sender->sendMessage($this->plugin->translateColors("&", "&aUnmuting &dpublic channel"));
		$this->plugin->setUserPublicMute($sender->getName(), 0);
		break;
	    
	    case "shout" :
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		$this->plugin->SendChannelMessage($sender, $args[1], true);
		break;
		
	    case "help" : 
		if( ! $sender->hasPermission("buddychannels.commands.help")) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
		    return;
		}
		$messages = array(
		    "&b>> &aAvailable Commands &b<<",
		    "&a/ch info &b>>&e Show info about this plugin",
		    "&a/ch help &b>>&e Show help about this plugin",
		    "&a/ch list &b>>&e Show the list of all channels",
		    "&a/ch &c<number>&b>>&e Switch channel numbers",
		    "&a/shout &b>>&e send a message to public chan",
		    "&a/sh &b>>&e shortcut for shout",
		    "&a/ch mute &b>>&e Mutes chat from public channel",
		    "&a/ch unmute &b>>&e Unmutes chat from public channel"
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
		$sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&eWebsite &b" . Main::MAIN_WEBSITE));
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
		$list = $this->plugin->getAllChannels($sender);
		$sender->sendMessage($this->plugin->translateColors("&", "&b>> &aAvailable Channels &b<<"));
		foreach($list as $channum => $channame) {
		    $sender->sendMessage($this->plugin->translateColors("&", "/ch &b&c" . $channum . " &a" . $channame));
		}
		if(count($list) <2) {
		    $sender->sendMessage($this->plugin->translateColors("&", 
			"&dVisit " . $this->plugin->website ." and join social groups to add more."));
		}
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
		$this->plugin->joinChannel($sender, $args[1]);
		break;	
	}
    }
}
?>
