<?php

namespace BuddyChannels;

use BuddyChannels\Database;
use BuddyChannels\Message;
use BuddyChannels\MessageFormatter;
use BuddyChannels\Tasks\SendMessageTask;
use BuddyChannels\Tasks\ReadForeignMessagesTask;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Mysqli;
use SimpleAuth\SimpleAuth;

class Main extends PluginBase {

    const PRODUCER = "mwvent";
    const VERSION = "1.1";
    const PREFIX = "&b[&aBuddy&eChannels&b] ";

    public $cfg;
    public $database;
    public $website;
    public $messageFormatter;
    /**
     * @var MessageFormatter[]
     */
    private $messageFormatterListeners_pre = [];
    private $messageFormatterListeners_post = [];
    public $readForeignMessagesTask;
    public $purePerms;
    public $readRSSTask;
    public $websiteAdvertisementTask;
    public $rankOverrides;
    public $baseRanks = [];
    /**
     * @var SimpleAuth
     */
    private $simpleAuth = null;
    
    
    public function setBaseRank(string $playerName, string $rank) {
        $playerName = strtolower($playerName);
        $this->baseRanks[$playerName] = $rank;
    }
    
    public function registerFormatter(MessageFormatter $callBackClass, $pre = false) {
        if($pre) { 
            $this->messageFormatterListeners_pre[] = $callBackClass;
        } else {
            $this->messageFormatterListeners_post[] = $callBackClass;
        }
    }
    
    public function externalFormatUserMessage(Message $message, $pre = false) {
        if($pre) {
            foreach($this->messageFormatterListeners_pre as $formatter) {
                $formatter->formatUserMessage($message);
            }
        } else {
            foreach($this->messageFormatterListeners_post as $formatter) {
                $formatter->formatUserMessage($message);
            }
        }
    }
    
    public function externalFromatForChannels(Message $message, $pre = false) {
        if($pre) {
            foreach($this->messageFormatterListeners_pre as $formatter) {
                $formatter->formatForChannels($message);
            }
        } else {
            foreach($this->messageFormatterListeners_post as $formatter) {
                $formatter->formatForChannels($message);
            }
        }
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        /* override default commands */
        $commandMap = $this->getServer()->getCommandMap();

        $commandToOverride = $commandMap->getCommand("me");
        $commandToOverride->setLabel("me_disabled"); //This prepares the command for the next step, setting up the Command->nextLabel
        $commandToOverride->unregister($commandMap); //This changes the current label

        $commandToOverride = $commandMap->getCommand("tell");
        $commandToOverride->setLabel("tell_disabled"); //This prepares the command for the next step, setting up the Command->nextLabel
        $commandToOverride->unregister($commandMap); //This changes the current label

        $commandToOverride = $commandMap->getCommand("msg");
        $commandToOverride->setLabel("msg_disabled"); //This prepares the command for the next step, setting up the Command->nextLabel
        $commandToOverride->unregister($commandMap); //This changes the current label
        // register Commands
        $this->getCommand("buddychannels")->setExecutor(new Commands\BuddyChannels($this));
        $this->getCommand("shout")->setExecutor(new Commands\Shout($this));
        $this->getCommand("block")->setExecutor(new Commands\Block($this));
        $this->getCommand("unblock")->setExecutor(new Commands\Unblock($this));
        $this->getCommand("mute")->setExecutor(new Commands\Mute($this));
        $this->getCommand("unmute")->setExecutor(new Commands\Unmute($this));
        $this->getCommand("tell")->setExecutor(new Commands\Tell($this));
        $this->getCommand("multiworldmute")->setExecutor(new Commands\MultiworldMute($this));
		$this->getCommand("hidetag")->setExecutor(new Commands\Hidetag($this));

        // Overrides dont work with setExecutor ( I dont think ) so use commandmap instead
        $commandMap->register("me", new Commands\Me($this));
        $commandMap->register("me", new Commands\Me($this), "me");
        $commandMap->register("me", new Commands\Me($this), "emote");
        $commandMap->register("tell", new Commands\Tell($this));
        $commandMap->register("tell", new Commands\Tell($this), "tell");
        $commandMap->register("tell", new Commands\Tell($this), "msg");

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->website = $this->read_cfg("website");
        $this->database = new \BuddyChannels\Database($this);

        if (strtolower($this->read_cfg("read-ranks-from", "none")) == "pureperms") {
            if (($plugin = $this->getServer()->getPluginManager()->getPlugin("PurePerms")) !== null) {
                $this->purePerms = $plugin;
                $this->getLogger()->info("Successfully loaded with PurePerms");
            } else {
                $this->getLogger()->alert("Dependency PurePerms not found");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            }
        }

        if ($this->read_cfg("use-rank-override", false)) {
            $this->rankOverrides = new \BuddyChannels\Tasks\RankOverrides($this);
            $rate_aprox_seconds = 5; // TODO assuming second is rougthly 20 ticks need to add this to the config file too
            $this->getServer()->getScheduler()->scheduleRepeatingTask($this->rankOverrides, $rate_aprox_seconds * 20);
        }

        if ($this->read_cfg("use-wordlist-censor", false)) {
            $badWordList = file($this->getDataFolder() . "/badwords.txt", FILE_IGNORE_NEW_LINES);
        } else {
            $badWordList = array();
        }

        $this->messageFormatter = new \BuddyChannels\MessageFormatter(
                $this->read_cfg("use-spamfilter", false), $this->read_cfg("spamfilter-time-interval", 60), $this->read_cfg("spamfilter-max-duplicates-per-interval", 2), $this->read_cfg("spamfilter-max-messages-per-interval", 6), $this->read_cfg("swearing-is-immature", false), $this->read_cfg("swearing-is-immature-punishment-period", 60), $this->read_cfg("use-wordlist-censor", false), $badWordList
        );

        if ($this->read_cfg("connect-server-chat", false)) {
            $this->readForeignMessagesTask = new \BuddyChannels\Tasks\ReadForeignMessagesTask($this);
            $rate_aprox_seconds = 5; // TODO assuming second is rougthly 20 ticks need to add this to the config file too
            $this->getServer()->getScheduler()->scheduleRepeatingTask($this->readForeignMessagesTask, $rate_aprox_seconds * 20);
        }

        if ($this->read_cfg("use-rss", false) && ( $this->read_cfg("rss-url", false) !== false )) {
            $this->readRSSTask = new \BuddyChannels\Tasks\ReadRSSTask($this, $this->read_cfg("rss-url"), $this->website);
            $rate_aprox_seconds = 5; // TODO assuming second is rougthly 20 ticks need to add this to the config file too
            $this->getServer()->getScheduler()->scheduleRepeatingTask(
                    $this->readRSSTask, $rate_aprox_seconds * 20
            );
        }

        if ($this->read_cfg("use-website-advertisements", false)) {
            $leavealoneperiod = $this->read_cfg("leave-alone-period");
            $ads = $this->read_cfg("ad-messages");
            $this->websiteAdvertisementTask = new \BuddyChannels\Tasks\WebsiteAdvertisementTask(
                    $this, $this->website, $ads, $leavealoneperiod
            );
            $rate_aprox_seconds = $this->read_cfg("ad-freq");
            $this->getServer()->getScheduler()->scheduleRepeatingTask(
                    $this->websiteAdvertisementTask, $rate_aprox_seconds * 20
            );
        }
        
        $this->simpleAuth = $this->getServer()->getPluginManager()->getPlugin("SimpleAuth");
        if(is_null($this->simpleAuth)) {
            $this->getLogger()->error("Could not attach to simpleauth");
        }
    }
    
    /**
     * @return boolean
     */
    public function simpeAuthAttatched() {
        return ( ! is_null($this->simpleAuth) );
    }
    
    /**
     * 
     * @param Player $player
     * @return boolean
     */
    public function isPlayerAuthenticated(Player $player) {
        if( is_null($this->simpleAuth) ) {
            return true;
        }
        return $this->simpleAuth->isPlayerAuthenticated($player);
    }

    /**
     * @return Player[]
     */
    public function getAuthenticatedPlayers() {
        $players = $this->getServer()->getOnlinePlayers();
        $authenticatedPlayers = [];
        if( is_null($this->simpleAuth) ) {
            return $players;
        }
        foreach ($players as $curKey => $player) {
            if( $this->simpleAuth->isPlayerAuthenticated($player)) {
                $authenticatedPlayers[$curKey] = $player;
            }
        }
        return $authenticatedPlayers;
    }
    
    public static function translateColors($symbol, $message) {
        $message = str_replace($symbol . "0", TextFormat::BLACK, $message);
        $message = str_replace($symbol . "1", TextFormat::DARK_BLUE, $message);
        $message = str_replace($symbol . "2", TextFormat::DARK_GREEN, $message);
        $message = str_replace($symbol . "3", TextFormat::DARK_AQUA, $message);
        $message = str_replace($symbol . "4", TextFormat::DARK_RED, $message);
        $message = str_replace($symbol . "5", TextFormat::DARK_PURPLE, $message);
        $message = str_replace($symbol . "6", TextFormat::GOLD, $message);
        $message = str_replace($symbol . "7", TextFormat::GRAY, $message);
        $message = str_replace($symbol . "8", TextFormat::DARK_GRAY, $message);
        $message = str_replace($symbol . "9", TextFormat::BLUE, $message);
        $message = str_replace($symbol . "a", TextFormat::GREEN, $message);
        $message = str_replace($symbol . "b", TextFormat::AQUA, $message);
        $message = str_replace($symbol . "c", TextFormat::RED, $message);
        $message = str_replace($symbol . "d", TextFormat::LIGHT_PURPLE, $message);
        $message = str_replace($symbol . "e", TextFormat::YELLOW, $message);
        $message = str_replace($symbol . "f", TextFormat::WHITE, $message);
        $message = str_replace($symbol . "k", TextFormat::OBFUSCATED, $message);
        $message = str_replace($symbol . "l", TextFormat::BOLD, $message);
        $message = str_replace($symbol . "m", TextFormat::STRIKETHROUGH, $message);
        $message = str_replace($symbol . "n", TextFormat::UNDERLINE, $message);
        $message = str_replace($symbol . "o", TextFormat::ITALIC, $message);
        $message = str_replace($symbol . "r", TextFormat::RESET, $message);
        // $message = str_replace(" ", "ᱹ", $message);
        return $message;
    }

    public static function removeColors($symbol, $message) {
        $colourCodes = str_split("1234567890abcdefghijklmnopqrstuvwxyz");
        foreach ($colourCodes as $key => $code) {
            $message = str_replace($symbol . $code, "", $message);
        }
        return $message;
    }

    public function read_cfg($key, $defaultvalue = null) {
        // if not loaded config load and continue
        if (!isset($this->cfg)) {
            $this->cfg = $this->getConfig()->getAll();
        }
        // if key not in config but a default value is allowed return default
        if ((!isset($this->cfg[$key]) ) && (!is_null($defaultvalue) )) {
            return $defaultvalue;
        }
        // if key not in config but is required
        if ((!isset($this->cfg[$key]) ) && (!is_null($defaultvalue) )) {
            $sendmsg = "Cannot load " . Main::PREFIX . " required config key " . $key . " not found in config file";
            Server::getInstance()->getLogger()->critical($this->translateColors("&", Main::PREFIX . $sendmsg));
            die();
        }
        // otherwise return config file value
        return $this->cfg[$key];
    }

    public function getPlayerRank(Player $player) {
        $playername = strtolower($player->getName());
        return $this->getUserRank($playername);
    }

    public function getUserRank($playername) {
        $player = $this->getServer()->getPlayer($playername);
        $player = $player instanceof Player ? $player : $this->getServer()->getOfflinePlayer($playername);
        $rank = "";
        // pull rank from PurePerms (if set in config) but if another plugin has called
        // accross to set the BaseRank use that instead (optimisation)
        if( isset($this->baseRanks[strtolower($playername)]) ) {
            $rank = $this->baseRanks[strtolower($playername)];
        } else {
            if (strtolower($this->read_cfg("read-ranks-from", "none")) == "pureperms") {
                // $rank = $this->purePerms->getUser($player)->getGroup()->getName();
                $rank = $this->purePerms->getUserDataMgr()->getGroup($player)->getName();
            }
        }

        // override the rank if override set
        if ($this->read_cfg("use-rank-override", false)) {
            if (isset($this->rankOverrides->data[strtolower($playername)])) {
                $rank = $this->rankOverrides->data[strtolower($playername)];
            }
        }

        switch (strtolower($rank)) {
            case "":
                return "";
                break;
            case "guest":
                $rank_formatted = "&6[Newbie]";
                break;
            case "newbie":
                $rank_formatted = "&6[Newbie]";
                break;
            case "junior":
                $rank_formatted = "&b[Junior]";
                break;
            case "senior":
                $rank_formatted = "&1[Senior]";
                break;
            case "elder":
                $rank_formatted = "&c[Elder]";
                break;
            case "youtuber":
                $rank_formatted = "&c[&dYou&fTuber&c]";
                break;
            case "admin":
                $rank_formatted = "&c[&4Admin&c]";
                break;
            case "owner":
                $rank_formatted = "&c[&6E&7n&8g&9i&an&be&ce&dr&c]";
                break;
            default:
                $rank_formatted = "&c[&f" . $rank . "&c]";
                break;
        }
        return $rank_formatted;
    }

    public function processNewMessage(Player $player, $message, $shouting = false) {
        $username_lcase = strtolower($player->getName());
        $message = new \BuddyChannels\Message(
                $player, $this->users[$username_lcase], // channel number
                $this->channelnames[$this->users[$username_lcase]], // channel name
                $this->getPlayerRank($player), $message, $shouting
        );
        $messageTask = new \BuddyChannels\Tasks\SendMessageTask($message, $this->messageFormatter, $this->database);
        $this->getServer()->getScheduler()->scheduleAsyncTask($messageTask);
    }

    // getplayer & validateName - copied / stole from EssentialsPE, why reinvent the wheel? thanks guys :-)
    public function getPlayer($player) {
        if (!$this->validateName($player, false)) {
            return false;
        }
        $player = strtolower($player);
        $found = false;
        foreach ($this->getServer()->getOnlinePlayers() as $p) {
            if (strtolower(TextFormat::clean($p->getDisplayName(), true)) === $player || strtolower($p->getName()) === $player) {
                $found = $p;
                break;
            }
        }
        // If cannot get the exact player name/nick, try with portions of it
        if (!$found) {
            $found = ($f = $this->getServer()->getPlayer($player)) === null ? false : $f; // PocketMine function to get from portions of name
        }
        /*
         * Copy from PocketMine's function (use above xD) but modified to work with Nicknames :P
         *
         * ALL THE RIGHTS FROM THE FOLLOWING CODE BELONGS TO POCKETMINE-MP
         */
        if (!$found) {
            $delta = \PHP_INT_MAX;
            foreach ($this->getServer()->getOnlinePlayers() as $p) {
                // Clean the Display Name due to colored nicks :S
                if (\stripos(($n = TextFormat::clean($p->getDisplayName(), true)), $player) === 0) {
                    $curDelta = \strlen($n) - \strlen($player);
                    if ($curDelta < $delta) {
                        $found = $p;
                        $delta = $curDelta;
                    }
                    if ($curDelta === 0) {
                        break;
                    }
                }
            }
        }
        return $found;
    }

    public function validateName($string, $allowColorCodes = false) {
        if (trim($string) === "") {
            return false;
        }
        $format = [];
        if ($allowColorCodes) {
            $format[] = "/(\&|\§)[0-9a-fk-or]/";
        }
        $format[] = "/[a-zA-Z0-9_]/"; // Due to color codes can be allowed, then check for them first, so after, make a normal lookup
        $s = preg_replace($format, "", $string);
        if (strlen($s) !== 0) {
            return false;
        }
        return true;
    }

}

/*

 */
?>
