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

class Help extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}

	public function execute(\pocketmine\command\CommandSender $sender, $commandLabel, array $args) {
		$messages = array(
		    "&b>> &aAvailable Commands &b<<",
		    "&a/ch help &b>>&e Show help about this plugin",
		    "&a/ch list &b>>&e Show the list of all channels",
		    "&a/ch &c<number>&b>>&e Switch channel numbers",
		    "&a/ch shout &b>>&e send a message to public chan",
		    "&a/sh &b>>&e shortcut for /ch shout",
		    "&a/mute &b>>&e Mutes chat from public channel",
		    "&a/unmute &b>>&e Unmutes chat from public channel",
		    "&a/block &c<username> &b>>&e Blocks a user from your chat",
			"&a/unblock &c<username> &b>>&e Unblocks a user from your chat",
		    "&a/me &c<text> &b>>&e Emotes"
		);
		foreach($messages as $message) {
		    $sender->sendMessage($this->plugin->translateColors("&", $message));
		}
	}
	
}
?>
