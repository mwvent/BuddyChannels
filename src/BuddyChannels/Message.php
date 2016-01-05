<?php

namespace BuddyChannels;
use pocketmine\Player;

class Message {
    public $serverid = null; // only set when coming from another server
    public $server_name = "";
    /**
     * @var Player
     */
    public $sender;
    public $username;
    public $username_lower;
    public $is_baby = false;
    public $is_shouting = false;
    public $message_blocked = false;
    public $userrank = "";
    public $originalMessage;
    public $msg; // working copy
    public $msgs_info = array(); // any messages to return to sender (command results etc..)
    public $msgs_server_info = array(); // any messages to log on console
    public $msg_echo; // msg to send back to self
    public $msg_samegroup; // msg to send if same non-public group
    public $msg_shouting; // msg to send if shouting from another channel
    public $msg_private; // msg to send if private
    public $senderChannel_number;
    public $senderChannel_name;
    public $message_receivers_lcase_usernames = null;
    public $readyToSend = false;
    
    public function __construct(Player $sender, $senderChannel_number, $senderChannel_name, $userrank, $msg, $shouting) {
	$this->sender = $sender;
	$this->username = $sender->getName();
	$this->username_lower = strtolower($this->username);
	$this->senderChannel_number = $senderChannel_number;
	$this->senderChannel_name = $senderChannel_name;
	$this->originalMessage = $msg;
	$this->msg = $msg;
	$this->is_shouting = $shouting;
	$this->userrank = $userrank;
    }
}
