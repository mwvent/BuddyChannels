<?php
namespace BuddyChannels\Tasks;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use pocketmine\Player;

class SetMultiworldMuteTask extends Task {
    private $plugin;
    /** 
     *
     * @var Database
     */
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
	if($this->newMuteStatus) {
            $this->database->db_addUserMetaData($this->username_lcase, "settings", "mutemultiworld");
	    $this->player->sendMessage(Main::translateColors("&", "&cMuted &dother worlds"));
	} else {
            $this->database->db_removeUserMetaData($this->username_lcase, "settings", "mutemultiworld");
	    $this->player->sendMessage(Main::translateColors("&", "&aUnmuted &dother worlds"));
	}
    }
}