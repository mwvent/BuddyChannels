<?php

namespace BuddyChannels;

use BuddyChannels\Main;
use BuddyChannels\Message;
use BuddyChannels\ForeignMessage;
use pocketmine\Player;
use pocketmine\Server;

class Database {

    private $plugin;
    private $tables;
    private $db;
    private $db_statements;
    private $cached_user_channels = array();
    private $cached_user_haspublicmuted = array();
    private $cached_user_metadata = array();
    private $cached_channelNames = array(
        "0" => "Public"
    );

    function __construct(\BuddyChannels\Main $plugin) {
        $this->plugin = $plugin;
        $this->db_statements = array();
        $this->tables = array(
            "bpgroupmembers" => $this->plugin->read_cfg("buddypress-bp_groups_members-tablename"),
            "bpgroups" => $this->plugin->read_cfg("buddypress-bp_groups-tablename"),
            "bpactivity" => $this->plugin->read_cfg("buddypress-activity-tablename"),
            "wpusers" => $this->plugin->read_cfg("wordpress-users-tablename"),
            "bcusers" => $this->plugin->read_cfg("buddychannels-users-table"),
            "chatlog" => $this->plugin->read_cfg("buddychannels-chatlog-table"),
            "servers" => $this->plugin->read_cfg("buddychannels-servers-table"),
            "usermeta" => $this->plugin->read_cfg("buddychannels-user-meta-table")
        );

        if ($this->plugin->read_cfg("use-rank-override", false)) {
            $this->tables["rank-override-table"] = $this->plugin->read_cfg("rank-override-table");
        }

        // try and open connection
        $this->db = new \mysqli($this->plugin->read_cfg("mysql-server"), $this->plugin->read_cfg("mysql-user"), $this->plugin->read_cfg("mysql-pass"), $this->plugin->read_cfg("database"));
        if ($this->db->connect_errno) {
            $errmsg = $this->criticalError("Error connecting to database: " . $db->error);
        }
        $this->database_Setup();
        $this->prepareStatements();
    }

    private function criticalError($errmsg) {
        $errmsg = Main::translateColors("&", Main::PREFIX . $errmsg);
        $this->plugin->getServer()->getInstance()->getLogger()->critical($errmsg);
        $this->plugin->getServer()->getInstance()->shutdown();
    }

    private function database_Setup() {
        // array of queries to setup database
        $db_setup_queries = array();
        $db_setup_queries ["create buddychat users table"] = "
	    CREATE TABLE IF NOT EXISTS `" . $this->tables ["bcusers"] . "` (
		`username` VARCHAR(50),
		`channel` INT,
		`hasmutedpublic` INT,
		PRIMARY KEY (`username`)
	    );";
        $db_setup_queries ["create buddychat chatlog table"] = "
		CREATE TABLE IF NOT EXISTS `" . $this->tables ["chatlog"] . "` (
		`messagetime` DATETIME DEFAULT CURRENT_TIMESTAMP,
		`serverid` INT,
		`username` VARCHAR(50),
		`user_rank` TEXT,
		`channel` INT,
		`channelname` TEXT,
		`message` TEXT,
		`unfiltered_message` TEXT,
		`was_baby` TINYINT,
		`was_shouting` TINYINT,
		`was_blocked` TINYINT
	    );";
        $db_setup_queries ["create buddychat servers table"] = "
	 CREATE TABLE IF NOT EXISTS `" . $this->tables ["servers"] . "` (
		`serverid` INT,
		`servername` VARCHAR(50),
		`connect_chat` TINYINT,
		PRIMARY KEY (`serverid`)
	    );";
        $db_setup_queries ["create buddychat user meta table"] = "
	 CREATE TABLE IF NOT EXISTS `" . $this->tables ["usermeta"] . "` (
		`username` VARCHAR(50),
		`data_type` VARCHAR(25),
		`data` TEXT
	    );";
        $db_setup_queries ["insert this server entry into buddychannels servers table"] = "
	 INSERT INTO `" . $this->tables ["servers"] . "` 
		(`serverid`, `servername`, `connect_chat`)
	    VALUES
		( '" . $this->db->real_escape_string($this->plugin->read_cfg("server-id", 0)) . "',
		'" . $this->db->real_escape_string($this->plugin->read_cfg("server-name", "untitled")) . "' ,
		'" . $this->db->real_escape_string($this->plugin->read_cfg("connect-server-chat", false)) . "')
	    ON DUPLICATE KEY UPDATE 
		`serverid` = '" . $this->db->real_escape_string($this->plugin->read_cfg("server-id", 0)) . "',
		`servername` = '" . $this->db->real_escape_string($this->plugin->read_cfg("server-name", "untitled")) . "',
		`connect_chat` = '" . $this->db->real_escape_string($this->plugin->read_cfg("connect-server-chat", false)) . "'
	    ;";

        if ($this->plugin->read_cfg("use-rank-override", false)) {
            $db_setup_queries ["create buddychat rank override table"] = "
			 CREATE TABLE IF NOT EXISTS `" . $this->tables ["rank-override-table"] . "` (
				`username` VARCHAR(50),
				`useCustomRank` INT,
				`customRankExpires` BIGINT,
				`customRankNiceName` VARCHAR(50),
				`customRankFormattedName` VARCHAR(50)
				);";
        }

        $setup_optimisations_ignore_errors = array(
            "CREATE INDEX `messagetime` ON `" . $this->tables ["chatlog"] . "` (messagetime);",
            "CREATE INDEX `serverid` ON `" . $this->tables ["chatlog"] . "` (serverid);",
            "CREATE INDEX `serveridandtime` ON `" . $this->tables ["chatlog"] . "` (serverid,messagetime);",
            "CREATE INDEX `username` ON `" . $this->tables ["usermeta"] . "` (username);",
            "CREATE INDEX `username` ON `" . $this->tables ["bcusers"] . "` (username);"
        );
        // run setup queries
        foreach ($db_setup_queries as $query_name => $query_sql) {
            $stmnt = $this->checkPreparedStatement($query_name, $query_sql);
            if ($stmnt !== false) {
                $qresult = $this->db_statements [$query_name]->execute();
                if ($qresult === false) {
                    $this->criticalError("Database set-up error executing " . $query_name . " " . $this->db_statements [$query_name]->error);
                }
                $this->db_statements [$query_name]->free_result();
            }
        }
        // run optimsations and upgrades that ignore errors
        foreach ($setup_optimisations_ignore_errors as $sql) {
            @$this->db->query($sql);
        }
    }

    private function checkPreparedStatement($queryname, $sql) {
        if (!isset($this->db_statements [$queryname])) {
            $this->db_statements [$queryname] = $this->db->prepare($sql);
        }
        if ($this->db_statements [$queryname] === false) {
            $this->criticalError("Database error preparing query for  " . $queryname . ": " . $this->db->error);
            return false;
        }
        return true;
    }

    private function prepareStatements() {
        $thisQueryName = "readUserMetaData";
        $sql = "SELECT 
		    `data_type`, `data` 
		FROM 
		    " . $this->tables ["usermeta"] . "
		WHERE
		    `username`= ?";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "addUserMetaData";
        $sql = "INSERT INTO " . $this->tables ["usermeta"] . "
		    (`username`, `data_type`, `data`)
		VALUES
		    ( ?, ? , ?)";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "removeUserMetaData";
        $sql = "DELETE FROM " . $this->tables ["usermeta"] . "
		WHERE
		    `username` = ? AND `data_type` = ? AND `data` = ?
	       ";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "setUserChannel";
        $sql = "
	    INSERT INTO `" . $this->tables ["bcusers"] . "` 
		(`username`, `channel`, `hasmutedpublic`)
	    VALUES
		( ?, ? , 0)
	    ON DUPLICATE KEY UPDATE `channel` = ?;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "setUserPublicMute";
        $sql = "
	    INSERT INTO `" . $this->tables ["bcusers"] . "` 
		(`username`, `channel`, `hasmutedpublic`)
	    VALUES
		( ?, 0 , ?)
	    ON DUPLICATE KEY UPDATE `hasmutedpublic` = ?;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "getPlayersCurrentChannel";
        $sql = "
	    SELECT
		`" . $this->tables ["bpgroupmembers"] . "`.`group_id` AS `channelnumber`,
		`" . $this->tables ["bpgroups"] . "`.`name` AS `groupname`,
		`" . $this->tables ["bcusers"] . "`.`username` AS `username`,
		`" . $this->tables ["bcusers"] . "`.`hasmutedpublic` AS `hasmutedpublic`
	    FROM `" . $this->tables ["wpusers"] . "`
		INNER JOIN `" . $this->tables ["bpgroupmembers"] . "`
		    ON `" . $this->tables ["bpgroupmembers"] . "`.`user_id`=`" . $this->tables ["wpusers"] . "`.`ID`  
		INNER JOIN `" . $this->tables ["bpgroups"] . "`
		    ON `" . $this->tables ["bpgroupmembers"] . "`.`group_id`=`" . $this->tables ["bpgroups"] . "`.`id`
		INNER JOIN `" . $this->tables ["bcusers"] . "`
		    ON `" . $this->tables ["bcusers"] . "`.`channel`= `" . $this->tables ["bpgroupmembers"] . "`.`group_id`
	    WHERE
		LCASE(`" . $this->tables ["bcusers"] . "`.`username`)=?
		AND is_confirmed=1
		AND is_banned=0
		LIMIT 1;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "getAvailibleChannelsForUser";
        $sql = "
		SELECT `" . $this->tables ["bpgroupmembers"] . "`.`group_id`, `" . $this->tables ["bpgroups"] . "`.`name` 
		    FROM `" . $this->tables ["wpusers"] . "`
			INNER JOIN `" . $this->tables ["bpgroupmembers"] . "`
			    ON `" . $this->tables ["bpgroupmembers"] . "`.`user_id`=`" . $this->tables ["wpusers"] . "`.`ID`  
			INNER JOIN " . $this->tables ["bpgroups"] . "
			    ON `" . $this->tables ["bpgroupmembers"] . "`.`group_id`=`" . $this->tables ["bpgroups"] . "`.`id`
		WHERE
		  LCASE(`" . $this->tables ["wpusers"] . "`.`user_nicename`) = ?
		  AND is_confirmed=1
		  AND is_banned=0;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "validateListOfUsersThatCanReceiveMsg-public";
        $sql = "SELECT `username` FROM `" . $this->tables ["bcusers"] . "` 
		WHERE `username` REGEXP ? AND (`hasmutedpublic`= 0 OR `channel`= 0);";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "validateListOfUsersThatCanReceiveMsg-group";
        $sql = "SELECT `username` FROM `" . $this->tables ["bcusers"] . "` WHERE `username` REGEXP ? AND `channel` = ?;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "saveChatMessage";
        $sql = "INSERT INTO `" . $this->tables ["chatlog"] . "`
		(`serverid`, `username`, `user_rank`, `channel`, `channelname`, `message`, 
		 `unfiltered_message`, `was_baby`, `was_shouting`, `was_blocked`)
		VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ? );";

        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "readNewForeignMessages";
        $sql = "SELECT
		    `" . $this->tables ["chatlog"] . "`.`messagetime`,
		    `" . $this->tables ["chatlog"] . "`.`serverid`,
		    `" . $this->tables ["servers"] . "`.`servername`,
		    `username`, `user_rank`, `channel`, `channelname`, `message`, 
		    `unfiltered_message`, `was_baby`, `was_shouting`, `was_blocked`
		FROM 
		    `" . $this->tables ["chatlog"] . "`
		INNER JOIN `" . $this->tables ["servers"] . "`
		    ON `" . $this->tables ["chatlog"] . "`.`serverid` =  `" . $this->tables ["servers"] . "`.`serverid`
		WHERE 
		    `" . $this->tables ["chatlog"] . "`.`serverid` != '" . $this->db->real_escape_string($this->plugin->read_cfg("server-id", 0)) . "'
		    AND `messagetime` > ?
		    AND `connect_chat`=true
			AND `" . $this->tables ["chatlog"] . "`.`channel` > -1
		ORDER BY
		    `" . $this->tables ["chatlog"] . "`.`messagetime` ASC;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        $thisQueryName = "readFirstForeignMessageTime";
        $sql = "SELECT 
		    `" . $this->tables ["chatlog"] . "`.`messagetime`
		FROM 
		    `" . $this->tables ["chatlog"] . "`
		INNER JOIN `" . $this->tables ["servers"] . "`
		    ON `" . $this->tables ["chatlog"] . "`.`serverid` =  `" . $this->tables ["servers"] . "`.`serverid`
		WHERE 
		    `" . $this->tables ["servers"] . "`.`serverid` != '" . $this->db->real_escape_string($this->plugin->read_cfg("server-id", 0)) . "'
		    AND `connect_chat`=true
		ORDER BY
		    `" . $this->tables ["chatlog"] . "`.`messagetime` DESC
		LIMIT 1;";
        $this->checkPreparedStatement($thisQueryName, $sql);

        if ($this->plugin->read_cfg("use-rank-override", false)) {
            $thisQueryName = "readRankOverrides";
            $sql = "SELECT `username`, `customRankFormattedName` 
					FROM `" . $this->tables ["rank-override-table"] . "`
					WHERE `customRankExpires` > UNIX_TIMESTAMP(now()) AND useCustomRank=true;";
            $this->checkPreparedStatement($thisQueryName, $sql);
        }

        $thisQueryName = "getActiveWebsiteUsers";
        $sql = "SELECT `user_nicename`
				FROM `" . $this->tables ["bpactivity"] . "` 
				INNER JOIN `" . $this->tables ["wpusers"] . "`  
					ON `" . $this->tables ["bpactivity"] . "`.`user_id`
							= `" . $this->tables ["wpusers"] . "`.`ID`
				WHERE 
					`date_recorded` > DATE_SUB(NOW(), INTERVAL ? HOUR) 
					AND type='last_activity';
		";
        $this->checkPreparedStatement($thisQueryName, $sql);
    }

    public function db_getActiveWebsiteUsers($hoursToCheck = 12) {
        $thisQueryName = "getActiveWebsiteUsers";

        $result = $this->db_statements [$thisQueryName]->bind_param("i", $hoursToCheck);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            @$this->db_statements [$thisQueryName]->free_result();
            return false;
        }

        $result = $this->db_statements [$thisQueryName]->bind_result($playerName);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return false;
        }

        $returnArray = array();
        while ($this->db_statements [$thisQueryName]->fetch()) {
            $returnArray[$playerName] = $playerName;
        }

        @$this->db_statements [$thisQueryName]->free_result();

        return $returnArray;
    }

    public function db_getUserMeta($username, $forceReload = false) {
        $username_lower = strtolower($username);
        // if data is already set and forceReload is not required then return cached
        if (isset($this->cached_user_metadata [$username_lower]) && !$forceReload) {
            return $this->cached_user_metadata [$username_lower];
        }

        // otherwise load from db
        $thisQueryName = "readUserMetaData";

        $result = $this->db_statements [$thisQueryName]->bind_param("s", $username_lower);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            @$this->db_statements [$thisQueryName]->free_result();
            return false;
        }

        $result = $this->db_statements [$thisQueryName]->bind_result($datatype, $data);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return false;
        }

        $returnArray = array();
        while ($this->db_statements [$thisQueryName]->fetch()) {
            if (!isset($returnArray [$datatype])) {
                $returnArray [$datatype] = array();
            }
            $returnArray [$datatype] [$data] = $data;
        }
        $this->db_statements [$thisQueryName]->free_result();
        $this->cached_user_metadata [$username_lower] = $returnArray;
        return $this->cached_user_metadata [$username_lower];
    }

    public function db_addUserMetaData($username, $datatype, $data) {
        $username_lower = strtolower($username);

        $thisQueryName = "addUserMetaData";
        $result = $this->db_statements [$thisQueryName]->bind_param("sss", $username_lower, $datatype, $data);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            @$this->db_statements [$thisQueryName]->free_result();
            return false;
        }
        $this->db_statements [$thisQueryName]->free_result();

        if (!isset($this->cached_user_metadata [$username_lower])) {
            $this->cached_user_metadata [$username_lower] = array();
        }
        if (!isset($this->cached_user_metadata [$username_lower] [$datatype])) {
            $this->cached_user_metadata [$username_lower] [$datatype] = array();
        }
        $this->cached_user_metadata [$username_lower] [$datatype] [$data] = $data;
        return true;
    }

    public function db_removeUserMetaData($username, $datatype, $data) {
        $username_lower = strtolower($username);

        $thisQueryName = "removeUserMetaData";
        $result = $this->db_statements [$thisQueryName]->bind_param("sss", $username_lower, $datatype, $data);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            @$this->db_statements [$thisQueryName]->free_result();
            return false;
        }
        $this->db_statements [$thisQueryName]->free_result();

        if (isset($this->cached_user_metadata [$username_lower] [$datatype] [$data])) {
            unset($this->cached_user_metadata [$username_lower] [$datatype] [$data]);
        }
        return true;
    }

    public function db_setUserChannel($username, $channel_number) {
        $thisQueryName = "setUserChannel";

        $result = $this->db_statements [$thisQueryName]->bind_param("sii", $username, $channel_number, $channel_number);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            @$this->db_statements [$thisQueryName]->free_result();
            return false;
        }
        $this->db_statements [$thisQueryName]->free_result();
        return true;
    }

    public function db_setUserPublicMute($username, $hasmutedpublic) {
        $username = strtolower($username);
        $thisQueryName = "setUserPublicMute";

        $result = $this->db_statements [$thisQueryName]->bind_param("sii", $username, $hasmutedpublic, $hasmutedpublic);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            @$this->db_statements [$thisQueryName]->free_result();
            return false;
        }
        $this->cached_user_haspublicmuted [$username] = $hasmutedpublic;
        $this->db_statements [$thisQueryName]->free_result();
        return true;
    }

    // return array hasmutedpublic, channelname, channelnumber
    public function db_getPlayersCurrentChannel($username) {
        $thisQueryName = "getPlayersCurrentChannel";

        $result = $this->db_statements [$thisQueryName]->bind_param("s", $username);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            return false;
        }

        $result = $this->db_statements [$thisQueryName]->bind_result($channelnumber, $groupname, $retusername, $hasmutedpublic);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return false;
        }

        $returnArray = array();
        if ($this->db_statements [$thisQueryName]->fetch()) { // found users prefs
            $returnArray ["channelnumber"] = $channelnumber;
            $returnArray ["channelname"] = stripcslashes($groupname);
            $returnArray ["username"] = $retusername;
            $returnArray ["hasmutedpublic"] = $hasmutedpublic;
        } else { // did not find any prefs for user - use defaults
            $returnArray ["channelnumber"] = 0;
            $returnArray ["channelname"] = "Public";
            $returnArray ["username"] = $retusername;
            $returnArray ["hasmutedpublic"] = false;
        }
        $this->db_statements [$thisQueryName]->free_result();
        return $returnArray;
    }

    public function db_getAvailibleChannelsForUser($username) {
        $username = strtolower($username);
        $thisQueryName = "getAvailibleChannelsForUser";

        $result = $this->db_statements [$thisQueryName]->bind_param("s", $username);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if ($result === false) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            return false;
        }

        $result = $this->db_statements [$thisQueryName]->bind_result($gid, $gname);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $returnArray = array();
        while ($this->db_statements [$thisQueryName]->fetch()) {
            $returnArray [$gid] = $gname;
        }
        $this->db_statements [$thisQueryName]->free_result();
        return $returnArray;
    }

    // kept for reference - it should be safe to gather this from the cache instead
    public function db_validateListOfUsersThatCanReceiveMsg($is_shouting, $channel_number, $arrayOfUsernamesToValidate) {
        $validatedUsers = array();
        if (count($arrayOfUsernamesToValidate) < 1) {
            return $validatedUsers;
        }
        $usernames_regex = "^(" . implode("|", $arrayOfUsernamesToValidate) . ")$";

        $thisQueryName = "validateListOfUsersThatCanReceiveMsg";
        if ($is_shouting || ($channel_number = 0)) { // pub or shout
            $thisQueryName .= "-public";
        } else { // priv
            $thisQueryName .= "-group";
        }

        if ($is_shouting || ($channel_number = 0)) { // pub
            $result = $this->db_statements [$thisQueryName]->bind_param("s", $username);
        } else { // priv
            $result = $this->db_statements [$thisQueryName]->bind_param("si", $username, $channel_number);
        }

        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if ($result === false) {
            $this->criticalError("Failed to exec statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->bind_result($username);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        while ($this->db_statements [$thisQueryName]->fetch()) {
            $validatedUsers [$username] = $username;
        }
        $this->db_statements [$thisQueryName]->free_result();
        return $validatedUsers;
    }

    public function db_saveChatMessage(\BuddyChannels\Message $message) {
        $thisQueryName = "saveChatMessage";
        $serverid = $this->plugin->read_cfg("server-id", 0);
        $result = $this->db_statements [$thisQueryName]->bind_param("ississsiii", $serverid, $message->username, $message->userrank, $message->senderChannel_number, $message->senderChannel_name, $message->msg, $message->originalMessage, $message->is_baby, $message->is_shouting, $message->message_blocked);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return false;
        }

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            return false;
        }
        $this->db_statements [$thisQueryName]->free_result();
    }

    private $lastForeignMessageTime = null;

    public function db_readNewForeignChat() {
        // attempt to get first instance of the time of the last foreign message to read from
        if (is_null($this->lastForeignMessageTime)) {
            $thisQueryName = "readFirstForeignMessageTime";
            $result = $this->db_statements [$thisQueryName]->execute();
            if (!$result) {
                $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
                return array();
            }
            $result = $this->db_statements [$thisQueryName]->bind_result($messagetime);
            if ($result === false) {
                $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
                return array();
            }
            if ($this->db_statements [$thisQueryName]->fetch()) {
                $this->lastForeignMessageTime = $messagetime;
                $this->db_statements [$thisQueryName]->free_result();
            } else {
                $this->db_statements [$thisQueryName]->free_result();
                return array();
            }
        }

        // read new messages
        $thisQueryName = "readNewForeignMessages";
        $result = $this->db_statements [$thisQueryName]->bind_param("s", $this->lastForeignMessageTime);
        if ($result === false) {
            $this->criticalError("Failed to bind to statement " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }
        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            return array();
        }
        $result = $this->db_statements [$thisQueryName]->bind_result($messagetime, $serverid, $servername, $username, $user_rank, $channel, $channelname, $message, $unfiltered_message, $was_baby, $was_shouting, $was_blocked);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }
        $newmessages = array();
        while ($this->db_statements [$thisQueryName]->fetch()) {
            $this->lastForeignMessageTime = $messagetime;
            $newmessage = new \BuddyChannels\ForeignMessage($serverid, $servername, $username, $user_rank, $channel, $channelname, $message, $was_shouting);
            $newmessages [] = $newmessage;
        }
        $this->db_statements [$thisQueryName]->free_result();
        return $newmessages;
    }

    public function db_readRankOverrides() {
        if (!$this->plugin->read_cfg("use-rank-override", false)) {
            return [];
        }
        // read new messages
        $thisQueryName = "readRankOverrides";

        $result = $this->db_statements [$thisQueryName]->execute();
        if (!$result) {
            $this->criticalError("Database error executing " . $thisQueryName . " " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $result = $this->db_statements [$thisQueryName]->bind_result($username, $customrank);
        if ($result === false) {
            $this->criticalError("Failed to bind result " . $thisQueryName . ": " . $this->db_statements [$thisQueryName]->error);
            return array();
        }

        $customRanks = array();
        while ($this->db_statements [$thisQueryName]->fetch()) {
            $customRanks[$username] = $customrank;
        }
        $this->db_statements [$thisQueryName]->free_result();

        return $customRanks;
    }

    public function read_cached_user_channels($username) {
        if (isset($this->cached_user_channels [$username])) {
            return $this->cached_user_channels [$username];
        }
        return 0;
    }

    public function read_cached_user_haspublicmuted($username) {
        if (isset($this->cached_user_haspublicmuted [$username])) {
            return $this->cached_user_haspublicmuted [$username];
        }
        return false;
    }

    public function read_cached_channelNames($channel_number) {
        if (isset($this->cached_channelNames [$channel_number])) {
            return $this->cached_channelNames [$channel_number];
        }
        return "Unknown Channel";
    }

    public function getPlayersChannel(Player $player) {
        $username = strtolower($player->getName());
        return $this->getUsersChannel($username);
    }

    public function getUsersChannel($username) { // updates cache rather than uses cache - used sparingly
        $username = strtolower($username);
        $result = $this->db_getPlayersCurrentChannel($username);
        if ($result == false) {
            return false; // also evalutes to 0 (Public) remember that if bugfixing :-)
        }

        // check the user still has the channel they are in
        // reset to public if they do not
        if ($result['channelnumber'] > 0) {
            $check = $this->db_getAvailibleChannelsForUser($username);
            if (!array_key_exists($result['channelnumber'], $check)) {
                $this->db_setUserChannel($username, 0);
                $result = $this->db_getPlayersCurrentChannel($username);
            }
        }

        $this->cached_user_haspublicmuted [$username] = $result ['hasmutedpublic'];
        $this->cached_user_channels [$username] = $result ['channelnumber'];
        if ($result ['channelnumber'] > 0) {
            $this->cached_channelNames [$result ['channelnumber']] = $result ['channelname'];
        }
        return $result ['channelnumber'];
    }

    public function joinChannel(Player $player, $channelnumber) {
        $username = strtolower($player->getName());
        $this->db_setUserChannel($username, $channelnumber);
        // read (possibly) changed player channel info from db
        $playerChannelNumber = $this->getUsersChannel($username);
        // see if channel set was correct
        if ($playerChannelNumber != $channelnumber) {
            $msg = "&cCould not join channel &6" . $channelnumber . "&c use /ch list to see availible or join channels via the website";
            $player->sendMessage(Main::translateColors("&", $msg));
            $msg = "&b" . $this->plugin->website;
            $player->sendMessage(Main::translateColors("&", $msg));
        }
        // report channel info
        $msg = "&dYou are currentley in channel &c" . $playerChannelNumber . " &o&n&6{" . $this->read_cached_channelNames($playerChannelNumber) . "}";
        $player->sendMessage(Main::translateColors("&", $msg));
        // report public channel mute status if not on public channel
        if ($playerChannelNumber != 0) {
            if ($this->read_cached_user_haspublicmuted($username)) {
                $muteStatusTxt = "&cmuted";
            } else {
                $muteStatusTxt = "&aunmuted";
            }
            $msg = "&dThe public channel is " . $muteStatusTxt;
            $player->sendMessage(Main::translateColors("&", $msg));
        }
    }

    public function getPlayersWhoWillReceiveMessage(\BuddyChannels\Message $message) {
        $sendername = $message->username_lower;
        $senderchannel = $message->senderChannel_number;
        $is_shouting = $message->is_shouting;
        $userlist = array(
            "echo_users" => array(), // this should never be >1 but following same logic path
            "samechannel_users" => array(),
            "shoutto_users" => array()
        );
        if ($message->message_blocked) {
            // return nothing for blocked message if message is from another server (cant echo to sender)
            if (!is_null($message->serverid)) {
                return $userlist;
            }
            // only return the echo user/sender for blocked messages on local
            $userlist ["echo_users"] [] = $message->sender;
            $message->msg_echo = "&c *** BLOCKED *** " . $message->originalMessage;
            return $userlist;
        }
        foreach ($this->plugin->getAuthenticatedPlayers() as $curplayer) {
            $curplayer_lcase_name = strtolower($curplayer->getName());
            // check player has block against sender
            $curplayer_metadata = $this->db_getUserMeta($curplayer_lcase_name);
            if ($curplayer_metadata !== false) {
                $isblocked = isset($curplayer_metadata ["blocked"] [strtolower($message->username)]);
                if ($isblocked) {
                    continue;
                }
            }
            $curplayer_channelnum = $this->read_cached_user_channels($curplayer_lcase_name);
            $curplayer_hasmutedpub = $this->read_cached_user_haspublicmuted($curplayer_lcase_name);
            $otherworldmessage = is_null($message->serverid) ? false : true;
            $user_metadata = $this->db_getUserMeta($curplayer_lcase_name);
            print_r($user_metadata);
            if( isset ($user_metadata["settings"]["mutemultiworld"]) ) {
                $curplayer_hasMutedMultiWorld = true;
            } else {
                $curplayer_hasMutedMultiWorld = false;
            }
            
            // if this is a message from another server check users pref to receive it
            if($curplayer_hasMutedMultiWorld and $otherworldmessage ) {
                continue;
            }
            // shouting reaches all users
            // unless the shout comes from a different channel from the target and the target has pub mute on
            // even the current player should receive the message in shout format (ppl like to see their group tagged on msg)
            if ($is_shouting && (($senderchannel == $curplayer_channelnum) || (!$curplayer_hasmutedpub))) {
                $userlist ["shoutto_users"] [] = $curplayer;
                continue;
            }
            // same user as sender (when not shouting)
            if ($curplayer_lcase_name == strtolower($sendername)) {
                $userlist ["echo_users"] [] = $curplayer;
                continue;
            }
            // same channel ( skip if sending from public we will use shouting logic in that channel )
            if ($senderchannel != 0 && ($curplayer_channelnum == $senderchannel)) {
                $userlist ["samechannel_users"] [] = $curplayer;
                continue;
            }
            // otherwise all thats left is a normal public message which is treated as a shout
            // ensure only users who are in public or have not got public muted receive
            if (($senderchannel == $curplayer_channelnum) || (!$curplayer_hasmutedpub)) {
                $userlist ["shoutto_users"] [] = $curplayer;
                continue;
            }
        }
        return $userlist;
    }

}
