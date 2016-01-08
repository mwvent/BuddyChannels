<?php

namespace BuddyChannels\Commands;
use BuddyChannels\Main;
use BuddyChannels\Tasks\SetMultiworldMuteTask;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;


class MultiworldMute extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}
        
        public function getUsage() {
            return "&cUsage /multiworldmute <on|off>";
        }

	public function execute(\pocketmine\command\CommandSender $sender, $commandLabel, array $args) {
            if( ! isset($args[0]) ) {
                $sender->sendMessage($this->plugin->translateColors("&", $this->getUsage()));
                return;
            }
            if(strtolower($args[0]) !== "on" and strtolower($args[0]) !== "off") {
                $sender->sendMessage($this->plugin->translateColors("&", $this->getUsage()));
                return;
            }
            $newmutestatus = (strtolower($args[0]) == "on");
            if( ! $sender instanceof Player) {
                $msg = "&cThis command can only be used in-game";
                $sender->sendMessage($this->plugin->translateColors("&", $msg));
                return;
            }
            $newTask = new SetMultiworldMuteTask($this->plugin, $sender, $newmutestatus);
	}
	
}
?>
