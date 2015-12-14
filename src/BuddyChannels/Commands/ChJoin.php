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

class ChJoin extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}

	public function execute(\pocketmine\command\CommandSender $sender, $commandLabel, array $args) {
		if( ! $sender->hasPermission("buddychannels.commands.join")) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
		    return;
		}
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&cYou can only perform this command as a player"));
		    return;
		}
		if( ! isset($args[0])) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX  . "&cUsage: /sch join <channel number>"));
		    return;
		}
		if( ! is_numeric($args[0])) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX  . "&cChannel must be a number"));
		    return;
		}
		$newTask = new \BuddyChannels\Tasks\JoinChannelTask($this->plugin, $sender, $args[0], $this->plugin->website);
	}
	
}
?>
