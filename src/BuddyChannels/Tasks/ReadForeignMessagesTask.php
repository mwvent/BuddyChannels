<?php
namespace BuddyChannels\Tasks;
use BuddyChannels\Tasks\SendMessageTask;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use BuddyChannels\Message;
use BuddyChannels\ForeignMessage;
use pocketmine\Server;

class ReadForeignMessagesTask extends Task {
    private $plugin;
    private $database;
    
    public function __construct(\BuddyChannels\Main $plugin) {
	$this->plugin = $plugin;
	$this->database = $plugin->database;
    }
    
    public function onRun($currenttick) {
	$newMessages = $this->database->db_readNewForeignChat();
	foreach($newMessages as $currentMessage) {
	    $newSMTask = new \BuddyChannels\Tasks\SendMessageTask($currentMessage, $this->plugin);
	}
    }
}