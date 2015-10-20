<?php
namespace BuddyChannels\Tasks;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Message;
use BuddyChannels\MessageFormatter;
use BuddyChannels\Database;
use pocketmine\Player;
use pocketmine\Server;

class SendMessageTask extends Task {
    private $plugin;
    private $message;
    private $messageFormatter;
    private $database;
    
    public function __construct(
	\BuddyChannels\Message $message,
	\BuddyChannels\Main $plugin) {
	$this->plugin = $plugin;
	$this->message = $message;
	$this->messageFormatter = $plugin->messageFormatter;
	$this->database = $plugin->database;
	$plugin->getServer()->getScheduler()->scheduleTask($this);
    }
    
    public function onRun($currenttick) {
	$message = $this->message;
	
	// messages from local need formatting and formatting for channels 
	// messages from remote only need channel formatting
	if( is_null( $message->serverid ) ) {
	    $this->messageFormatter->formatUserMessage($message);
	    $this->messageFormatter->formatForChannels($message);
	} else {
	    $this->messageFormatter->formatForChannels($message);
	}
	
	$send_to_players = $this->database->getPlayersWhoWillReceiveMessage($message);
	foreach($message->msgs_server_info as $logmsg) {
	    $this->plugin->getServer()->getLogger()->info(Main::translateColors("&", Main::PREFIX . $logmsg));
	}
	
	foreach($send_to_players["echo_users"] as $currentPlayer) {
	    foreach($message->msgs_info as $currentInfoMessage) {
		$currentPlayer->sendMessage(Main::translateColors("&", $currentInfoMessage));
	    }
	    $currentPlayer->sendMessage(Main::translateColors("&", $message->msg_echo));
	}
	foreach($send_to_players["samechannel_users"]  as $currentPlayer) {
	    $currentPlayer->sendMessage(Main::translateColors("&", $message->msg_samegroup));
	}
	foreach($send_to_players["shoutto_users"]  as $currentPlayer) {
	    $currentPlayer->sendMessage(Main::translateColors("&", $message->msg_shouting));
	}
	
	// messages from local get saved 
	if( is_null( $message->serverid ) ) {
	    $this->database->db_saveChatMessage($message);
	}
    }
}