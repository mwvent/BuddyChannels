<?php
namespace BuddyChannels\Tasks;
use BuddyChannels\Tasks\SendMessageTask;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\AsyncTask;
use BuddyChannels\Main;
use BuddyChannels\Message;
use BuddyChannels\ForeignMessage;
use pocketmine\Server;

class ReadRSSTask_ASyncPuller extends AsyncTask {
	private $rssurl;
	private $xml;
	
	public function __construct($rssurl) {
		$this->rssurl = $rssurl;
	}
	
	public function onRun() {
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $this->rssurl);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		$this->xml = curl_exec ($curl);
		curl_close ($curl);
	}
	
	public function onCompletion(Server $server){
		$plugin = $server->getPluginManager()->getPlugin("BuddyChannels");
		$plugin->readRSSTask->readComplete($this->xml);
	}
}