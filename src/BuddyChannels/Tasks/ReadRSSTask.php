<?php
namespace BuddyChannels\Tasks;
use BuddyChannels\Tasks\SendMessageTask;
use BuddyChannels\Tasks\ReadRSSTask_ASyncPuller;
use pocketmine\scheduler\Task;
use BuddyChannels\Main;
use BuddyChannels\Message;
use BuddyChannels\ForeignMessage;
use pocketmine\Server;

class ReadRSSTask extends Task {
    private $plugin;
	private $rssurl;
	private $website;
	private $last_known_timestamp;
	private $waiting_for_puller;
	private $lastData;
	private $firstRun;
    
    public function __construct(\BuddyChannels\Main $plugin, $rssurl, $website) {
		$this->plugin = $plugin;
		$this->website = $website;
		$this->rssurl = $rssurl;
		$this->last_known_timestamp;
		$this->waiting_for_puller = false;
		$this->lastData = null;
		$this->firstRun = true;
    }
	
	function getFeedData($fromTimeStamp = 0) {
		$retArr = [];
		$xml = $this->lastData;
		foreach( $xml->channel->item as $curpost ) {
			$title = $curpost->title;
			$pubDate = $curpost->pubDate;
			$title =  preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $title);
			$pubDate =  preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $pubDate);
			$pubDate = DateTime::createFromFormat('D, d M Y H:i:s O', $pubDate);
			$posttime = date_timestamp_get($pubDate);
			if( $posttime < $fromTimeStamp ) {
				return $retArr;
			}
			$retArr[$posttime] = $title;
		}
		return $retArr;
	}
	
	public function readComplete($data) {
		// no simplexml extension availible with pocketmines php - use a dirty method 
		$this->lastData = [];
		$lastpos = 0;
		$finished = false;
		while( ! $finished ) {
			$lastpos = strpos($data, "<item>", $lastpos);
			if($lastpos === false) { $finished = true;	break; }
			$titlepos = strpos($data, "<title>", $lastpos);
			if($titlepos === false) { $finished = true;	break; }
			$titlepos += strlen("<title>");
			$titleendpos = strpos($data, "</title>", $lastpos);
			if($titleendpos === false) { $finished = true;	break; }
			$pubdatepos = strpos($data, "<pubDate>", $lastpos);
			if($pubdatepos === false) { $finished = true;	break; }
			$pubdatepos += strlen("<pubDate>");
			$pubdateendpos = strpos($data, "</pubDate>", $lastpos);
			if($pubdateendpos === false) { $finished = true;	break; }
			
			$lastpos = ($pubdateendpos > $titleendpos) ? $pubdateendpos : $titleendpos;
			$lastpos ++;
			
			$title = substr ($data, $titlepos, $titleendpos - $titlepos);
			$pubdate = substr ($data, $pubdatepos, $pubdateendpos - $pubdatepos);
			$pubdate = \DateTime::createFromFormat('D, d M Y H:i:s O', $pubdate);
			$pubdate = date_timestamp_get($pubdate);
			
			if( $pubdate > $this->last_known_timestamp ) {
				$this->lastData[$pubdate] = $title;
			}
		}

		$this->waiting_for_puller = false;
	}
    
    public function onRun($currenttick) {
		// waiting for pulled data
		if($this->waiting_for_puller) {
			return;
		}
		// needs to request new data
		if( is_null($this->lastData) ) {
			$newPullTask = new \BuddyChannels\Tasks\ReadRSSTask_ASyncPuller($this->rssurl);
			$this->plugin->getServer()->getScheduler()->scheduleAsyncTask($newPullTask);
			$this->waiting_for_puller = true;
			return;
		}
		// read complete
		foreach($this->lastData  as $cur_timestamp => $cur_msg ) {
			if($cur_timestamp > $this->last_known_timestamp) {
				$this->last_known_timestamp = $cur_timestamp;
			}
			if( ! $this->firstRun ) {
				$currentMessage = new \BuddyChannels\ForeignMessage(
					-1, // serverid
					$this->website, // servername
					"", // username
					"", // userrank
					0, // channel number
					"Public", // channel name 
					$cur_msg, // msg 
					true // shout
				);
				// echo "DEBUG: Making new BuddyChannels Msg for $cur_msg \n";
				$newSMTask = new \BuddyChannels\Tasks\SendMessageTask($currentMessage, $this->plugin);
			}
		}
		// unset data for next tick
		$this->lastData = null;
		
		if( $this->firstRun ) {
				$this->firstRun = false;
				// echo "DEBUG: BuddyChannels RSS Reader started feed at timestamp $this->last_known_timestamp \n";
		}
    }
}