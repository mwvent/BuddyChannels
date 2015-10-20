<?php
namespace BuddyChannels\Tasks;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use pocketmine\Player;
use pocketmine\Server;

class JoinChannelTask extends Task {
    private $plugin;
    private $database;
    private $player;
    private $username_lcase;
    private $website_url;
    private $channel_number;
    
    public function __construct(\BuddyChannels\Main $plugin, \pocketmine\Player $player, $channel_number, $website_url) {
	$this->plugin=$plugin;
	$this->player = $player;
	$this->database = $plugin->database;
	$this->username_lcase = strtolower($player->getName());
	$this->website_url = $website_url;
	$this->channel_number = $channel_number;
	$plugin->getServer()->getScheduler()->scheduleTask($this);
    }
    
    public function onRun($currenttick) {
	$returnmsgs = $this->database->joinChannel($this->player, $this->channel_number);
	
	//foreach($returnmsgs as $curmsg) {
	//    $this->player->sendMessage(Main::translateColors("&", $curmsg);
	//}
	
    }
}