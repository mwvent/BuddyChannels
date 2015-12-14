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

class Unblock extends Command  implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
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
		    $sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /ch unblock <name>"));
		    return;
		}
		$player = $this->plugin->getPlayer($args[0]);
		if(!$player){
		    $player = null;
		    $player_name = $args[0];
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
	}
	
}
?>
