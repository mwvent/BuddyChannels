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

class Tell extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        	$this->plugin = $plugin;
		parent::__construct("Tell", "Private Message");
    	}
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}

	public function execute(\pocketmine\command\CommandSender $sender, $commandLabel, array $args) {
		if( ! $sender instanceof Player) {
			$sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
			return;
		}
		
		if( ! isset($args[0]) ) {
			$sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /tell <player> <msg>"));
			return;
		}
		
		$player = $this->plugin->getPlayer($args[0]);
		if(!$player){
		    $sender->sendMessage(TextFormat::RED . "That player is not on this server and cannot be PM'd");
		    return false;
		} else {
		    $player_name = strtolower($player->getName());
		}
		
		if(strtolower($player_name) === strtolower($sender->getName())){
		    $sender->sendMessage(TextFormat::RED . "[Error] You can't PM yourself silly :-)");
		    return false;
		}
		
		if( ! isset($args[1]) ) {
			$sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /tell <player> <msg>"));
			return;
		}
		
		$player = $sender;
		$username = strtolower($player->getName());
		$username_lcase = strtolower($player->getName());
		$message = new \BuddyChannels\Message(
			$player,
			-1, // channel number
			"PM to " . $player_name, // channel name
			$this->plugin->getPlayerRank($player),
			implode(" ", array_slice($args,1)),
			true // shouting
		);
		$message->message_receivers_lcase_usernames = [$player_name => $player_name];
		$messageTask = new \BuddyChannels\Tasks\SendMessageTask($message, $this->plugin);
	}
	
}
?>
