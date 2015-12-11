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
		if($this->page < 1) {
			$this->page = 1;
		}
		
		$list = $this->database->db_getAvailibleChannelsForUser($this->username_lcase);
		$list[0] = "Public";
		ksort ( $list );
		
		$pages = [];
		$cur_list_item = 0;
		foreach($list as $chanNum => $chanName) {
			$cur_list_item ++;
			$cur_page = floor( $cur_list_item / $this->channels_per_page );
			$pages[$cur_page][$chanNum] = $chanName;
		}
		
		if($this->page > count($pages)) {
			$this->page = count($pages);
		}
		
		$returnmsgs = array();
		$returnmsgs[] = "&b>> &aAvailable Channels &b<<";
		
		foreach($pages[$this->page - 1] as $channum => $channame) {
			$returnmsgs[] = "/ch &b&c" . $channum . " &a" . $channame;
		}
		if(count($pages) < 2) {
			$returnmsgs[] = "Visit &a" .$this->website_url . " and join in social groups to add more channels.";
		} else {
			 $returnmsgs[] = "Page " . $this->page . " of " . count($pages);
		}
		
		foreach($returnmsgs as $curmsg) {
			$this->player->sendMessage(Main::translateColors("&", $curmsg));
		}
    }
}