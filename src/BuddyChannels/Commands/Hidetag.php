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

class Hidetag extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}

	public function execute(\pocketmine\command\CommandSender $sender, $commandLabel, array $args) {
		if( ! $sender->hasPermission("buddychannels.commands.hidetag")) {
		    $sender->sendMessage($this->plugin->translateColors("&", "&cYou don't have permissions to use this command"));
		    return;
		}
		if( ! $sender instanceof Player) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX . "&cYou can only perform this command as a player"));
		    return;
		}
		if( ! isset($args[0])) {
		    $sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX  . "&cUsage: /hidetag on/off"));
		    return;
		}
		if( (strtolower($args[0] != "on")) and (strtolower($args[0] != "off")) ) {
			$sender->sendMessage($this->plugin->translateColors("&", Main::PREFIX  . "&cUsage: /hidetag on/off"));
		    return;
		}

		switch(strtolower($args[0])) {
			case "on" :
				// only activte website users get to use command
				$hoursAgoUsedWebsite = 4;
				$activeUsers = $this->plugin->database->db_getActiveWebsiteUsers($hoursAgoUsedWebsite);
				$currentPlayerName = strtolower( $sender->getName() );
				if( ! in_array( $currentPlayerName, $activeUsers ) ) {
					$msg = "&cThis command is only availible to users who have used the website in the";
					$msg .= "past " . $hoursAgoUsedWebsite . " hours. Please login to the website to ";
					$msg .= "continue.";
					$sender->sendMessage($this->plugin->translateColors("&", $msg));
					return;
				}
				$sender->setNameTagVisible(false);
				$msg = "&fYour nametag is now hidden.";
				$sender->sendMessage($this->plugin->translateColors("&", $msg));
				break;
			case "off" :
				$msg = "&fYour nametag is now visible.";
				$sender->setNameTagVisible(true);
				$sender->sendMessage($this->plugin->translateColors("&", $msg));
				break;
		}
	}
	
}
