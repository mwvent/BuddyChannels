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

class Me extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        	$this->plugin = $plugin;
		parent::__construct("Me", "Emote");
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
			$sender->sendMessage($this->plugin->translateColors("&", "&cUsage: /me <text>"));
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
			"#" . implode(" ",$args),
			false // shouting
		);
		$messageTask = new \BuddyChannels\Tasks\SendMessageTask($message, $this->plugin);
	}
	
}
?>
