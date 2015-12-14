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

class Unmute extends Command implements CommandExecutor {
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
		$newTask = new \BuddyChannels\Tasks\SetMutePublicTask($this->plugin, $sender, false);
	}
	
}
?>
