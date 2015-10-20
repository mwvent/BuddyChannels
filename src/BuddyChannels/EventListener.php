<?php

namespace BuddyChannels;
use BuddyChannels\Tasks\JoinChannelTask;
use BuddyChannels\Tasks\SendMessageTask;
use BuddyChannels\Message;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\Server;

class EventListener extends PluginBase implements Listener {

	public function __construct(Main $plugin) {
	    $this->plugin = $plugin;
	}
	
	public function onPlayerChat(PlayerChatEvent $event) {
	    $event->setCancelled(true);
	    $player = $event->getPlayer();
	    $username = strtolower($player->getName());
	    $userchannel_number = $this->plugin->database->read_cached_user_channels($username);
	    $userchannel_name = $this->plugin->database->read_cached_channelNames($userchannel_number);
	    $username_lcase = strtolower($player->getName());
	    $message = new \BuddyChannels\Message(
		$player,
		$userchannel_number, // channel number
		$userchannel_name, // channel name
		$this->plugin->getPlayerRank($player),
		$event->getMessage(),
		false // shouting
	    );
	    $messageTask = new \BuddyChannels\Tasks\SendMessageTask($message, $this->plugin);
	}
	
	public function onPlayerJoin(PlayerJoinEvent $event) {
	    $player = $event->getPlayer();
	    $playerchannel = $this->plugin->database->getPlayersChannel($player);
	    // ensure db row is created if not exists and join msg sent
	    $newTask = new \BuddyChannels\Tasks\JoinChannelTask(
		$this->plugin,
		$player,
		$playerchannel,
		$this->plugin->website
	    );
	}
	
	public function onPlayerQuit(PlayerQuitEvent $event) {
	    // TODO call a cleanup function
	}

}
?>
