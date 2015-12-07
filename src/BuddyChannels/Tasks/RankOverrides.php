<?php
namespace BuddyChannels\Tasks;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use pocketmine\Server;

class RankOverrides extends Task {
    private $plugin;
    private $database;
    public $data;
	
    public function __construct(\BuddyChannels\Main $plugin) {
		$this->plugin = $plugin;
		$this->database = $plugin->database;
		$this->data = [];
    }
    
    public function onRun($currenttick) {
		$this->data = $this->database->db_readRankOverrides();
    }
}