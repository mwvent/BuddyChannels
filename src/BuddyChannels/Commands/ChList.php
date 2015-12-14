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

class ChList extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}

	public function execute(\pocketmine\command\CommandSender $sender, $commandLabel, array $args) {
		if( ! $sender->hasPermission("buddychannels.commands.list")) {
			$sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
			return;
		}
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cThis command can only be used in-game"));
		    return;
		}
		if( ! isset($args[0])) {
		    $page = 1;
		} else {
		    $page = $args[0];
		}
		if( ! is_numeric($page)) {
		    $sender->sendMessage(Main::translateColors("&", Main::PREFIX  . "&cPage must be a number - showing page 1"));
		    $page = 1;
		}
		$newTask = new \BuddyChannels\Tasks\ListChannelsTask($this->plugin, $sender, $this->plugin->website, $page);
	}
	
}
?>
