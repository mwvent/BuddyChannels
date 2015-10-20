<?php
namespace BuddyChannels;
use BuddyChannels\Message;

class ForeignMessage extends \BuddyChannels\Message {
    public function __construct($serverid, $server_name, $username, $userrank, $senderChannel_number, $senderChannel_name, $msg, $shouting) {
	$this->serverid = $serverid;
	$this->server_name = $server_name;
	$this->username = $username;
	$this->username_lower = strtolower($this->username);
	$this->senderChannel_number = $senderChannel_number;
	$this->senderChannel_name = $senderChannel_name;
	$this->originalMessage = $msg;
	$this->msg = $msg;
	$this->is_shouting = $shouting;
	$this->userrank = $userrank;
    }
}