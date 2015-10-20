<?php
namespace BuddyChannels\Tasks;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use pocketmine\Player;

class SetMutePublicTask extends Task {
    private $plugin;
    private $database;
    private $player;
    private $username_lcase;
    private $newMuteStatus;
    
    public function __construct(\BuddyChannels\Main $plugin, \pocketmine\Player $player, $newMuteStatus) {
	$this->plugin = $plugin;
	$this->player = $player;
	$this->database = $plugin->database;
	$this->username_lcase = strtolower($player->getName());
	$this->newMuteStatus = $newMuteStatus;
	$plugin->getServer()->getScheduler()->scheduleTask($this);
    }
    
    public function onRun($currenttick) {
	$this->database->db_setUserPublicMute($this->username_lcase, $this->newMuteStatus);
	if($this->newMuteStatus) {
	    $this->player->sendMessage(Main::translateColors("&", "&cMuted &dpublic channel"));
	} else {
	    $this->player->sendMessage(Main::translateColors("&", "&aUnuted &dpublic channel"));
	}
    }
}