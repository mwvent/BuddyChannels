<?php

namespace BuddyChannels;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class EventListener extends PluginBase implements Listener{
	
	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}
	
	public function onPlayerChat(PlayerChatEvent $event){
		$message = $event->getMessage();
		$player = $event->getPlayer();
		$this->plugin->SendChannelMessage($player, $message);
		$event->setCancelled(true);
	}
	
	public function onPlayerJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		$playerchannel = $this->plugin->getPlayersChannel($player);
		$this->plugin->joinChannel($player, $playerchannel);
	}
	
	public function onPlayerQuit(PlayerQuitEvent $event){
		//$player = $event->getPlayer();
		//if($this->plugin->hasJoined($player)){
			// $this->plugin->leaveChannel($player);
		//}
	}

}
?>
