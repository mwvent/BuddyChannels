<?php

namespace BuddyChannels\Commands;
use BuddyChannels\Main;
// pocketmine\command\Command
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class BuddyChannels extends Command implements CommandExecutor {
	public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		$this->execute($sender, $cmd, $args);
	}
	
	public function execute(\pocketmine\command\CommandSender $sender, $cmd, array $args) {
		// no args help shortcut
		if(!isset($args[0])) {
			$args[0]="help";
		}
		
		// ch join shortcut i.e. /ch 1 is = to /ch join 1
		if(is_numeric($args[0])) {
			$args[1] = $args[0];
			$args[0] = "join";
		}
		
		switch($args[0]) {
			case "block" :
				$newcmd = new \BuddyChannels\Commands\Block($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "unblock" :
				$newcmd = new \BuddyChannels\Commands\Unblock($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "mute" :
				$newcmd = new \BuddyChannels\Commands\Mute($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "unmute" :
				$newcmd = new \BuddyChannels\Commands\Unmute($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "shout" :
				$newcmd = new \BuddyChannels\Commands\Shout($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "sh" :
				$newcmd = new \BuddyChannels\Commands\Shout($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "help" : 
				$newcmd = new \BuddyChannels\Commands\Help($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "list" :
				$newcmd = new \BuddyChannels\Commands\ChList($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;
			case "join" :
				$newcmd = new \BuddyChannels\Commands\ChJoin($this->plugin);
				$newcmd->execute($sender, $cmd, array_slice($args, 1));
				break;	
		}
    }
}
?>
