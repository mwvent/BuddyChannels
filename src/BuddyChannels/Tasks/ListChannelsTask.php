<?php
namespace BuddyChannels\Tasks;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Database;
use pocketmine\Player;

class ListChannelsTask extends Task {
    private $plugin;
    private $database;
    private $player;
    private $username_lcase;
    private $page;
    private $website_url;
    private $channels_per_page = 5;
    
    public function __construct(\BuddyChannels\Main $plugin, \pocketmine\Player $player, $website_url, $page=1) {
	$this->plugin = $plugin;
	$this->database = $plugin->database;
	$this->player = $player;
	$this->username_lcase = strtolower($player->getName());
	$this->page = $page;
	$this->website_url = $website_url;
	$plugin->getServer()->getScheduler()->scheduleTask($this);
    }
    
    public function onRun($currenttick) {
	$list = $this->database->db_getAvailibleChannelsForUser($this->username_lcase);
	$list[0] = "Public";
	ksort ( $list );
	$pages = ceil(count($list) / $this->channels_per_page);
	if($this->page > $pages) {
	    $this->page = $pages;
	}
	$page_start = ($this->page - 1) * $this->channels_per_page;
	
	if(count($list) > $this->channels_per_page) {
	    $list = array_slice( $list, $page_start, $page_start + $this->channels_per_page );
	}
	
	$returnmsgs = array();
	$returnmsgs[] = "&b>> &aAvailable Channels &b<<";
	foreach($list as $channum => $channame) {
	    $returnmsgs[] = "/ch &b&c" . $channum . " &a" . $channame;
	}
	if($pages < 2) {
	    $returnmsgs[] = "Visit &a" .$this->website_url . " and join in social groups to add more channels.";
	} else {
	     $returnmsgs[] = "Page " . $this->page . " of " . $pages;
	}
	
	foreach($returnmsgs as $curmsg) {
	    $this->player->sendMessage(Main::translateColors("&", $curmsg));
	}
    }
}