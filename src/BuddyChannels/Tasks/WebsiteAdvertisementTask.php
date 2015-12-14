<?php
namespace BuddyChannels\Tasks;
use BuddyChannels\Tasks\SendMessageTask;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use BuddyChannels\Message;
use BuddyChannels\ForeignMessage;
use pocketmine\Server;

class WebsiteAdvertisementTask extends Task {
    private $plugin;
    private $database;
	private $website;
	private $leaveAlonePeriod;
	private $sendMessages;
	private $lastSentMessage;
    
    public function __construct(\BuddyChannels\Main $plugin, $website, $sendMessages, $leaveAlonePeriod = 12) {
		$this->plugin = $plugin;
		$this->database = $plugin->database;
		$this->website = $website;
		$this->leaveAlonePeriod = $leaveAlonePeriod;
		$this->sendMessages = $sendMessages;
		$this->lastSentMessage = -1;
    }
	
	public function getNextAdMessage() {
		$this->lastSentMessage ++;
		if( $this->lastSentMessage > ( count($this->sendMessages) - 1 ) ) {
			$this->lastSentMessage = 0;
		}
		$adMessage = $this->sendMessages[ $this->lastSentMessage ];
		return str_replace( "<WEBSITE>", $this->website, $adMessage);
	}
    
    public function onRun($currenttick) {
		$consolemsg = "Sent ad Message (to ";
		$activeUsers = $this->database->db_getActiveWebsiteUsers($this->leaveAlonePeriod);
		$userNamesSentTo = [];
		$adMessage = $this->getNextAdMessage();
		foreach($this->plugin->getServer ()->getOnlinePlayers() as $currentPlayer) {
			$currentPlayerName = strtolower($currentPlayer->getName());
			$currentAdMessage = str_replace( "<PLAYER>", $currentPlayerName, $adMessage);
			if( ! in_array( $currentPlayerName, $activeUsers ) ) {
				$currentPlayer->sendMessage(Main::translateColors("&", $currentAdMessage));
				$userNamesSentTo[] = $currentPlayerName;
			}
		}
		$consolemsg .= implode(",", $userNamesSentTo). ") : " . $adMessage;
		$this->plugin->getLogger()->info(Main::translateColors("&",$consolemsg));
    }
}