<?php

namespace BuddyChannels;

use BuddyChannels\Main;

class MessageFormatter {
	private $use_spamTest;
	private $spamTestTimeInterval;
	private $spamTestMaxDupesPerInterval;
	private $spamTestMaxMessagesPerInterval;
	private $use_babyFilter;
	private $babyFilterTime;
	private $use_wordFilter;
	private $users_babyendtime = array ();
	private $lastmessages = array (); // array of arrays key is player name sub array is recent messages with timestamp
	private $badWordList;
	public function __construct($use_spamTest, $spamTestTimeInterval, $spamTestMaxDupesPerInterval, $spamTestMaxMessagesPerInterval, $use_babyFilter, $babyFilterTime, $use_wordFilter, $badWordList = array()) {
		$this->use_spamTest = $use_spamTest;
		$this->use_babyFilter = $use_babyFilter;
		$this->babyFilterTime = $babyFilterTime;
		$this->use_wordFilter = $use_wordFilter;
		$this->spamTestTimeInterval = $spamTestTimeInterval;
		$this->spamTestMaxDupesPerInterval = $spamTestMaxDupesPerInterval;
		$this->spamTestMaxMessagesPerInterval = $spamTestMaxMessagesPerInterval;
		$this->badWordList = $badWordList;
	}
	public function formatUserMessage(\BuddyChannels\Message $message) {
		// check for spam - return instantly if fail (dont waste more cpu cycles on spammers)
		if ($this->use_spamTest) {
			if ($this->spamTest ( $message )) {
				return;
			}
		}
		
		// caps filter
		$this->antiCaps ( $message );
		
		// hard-coded swear filter with baby punishment
		if ($this->use_babyFilter) {
			$this->babyFilter ( $message );
		}
		
		// wordlist filter
		if ($this->use_wordFilter) {
			$this->wordFilter ( $message );
		}
	}
	
	private function antiCaps(\BuddyChannels\Message $message) {
		$msglen = strlen ( $message->msg );
		if( $msglen < 4 ) {
			return;
		}
		$captials_count = strlen( preg_replace('![^A-Z]+!', '', $message->msg) );
		$all_letters_count = strlen(preg_replace('![^A-Z]+!', '', strtoupper($message->msg)));
		if ( $captials_count == 0 || $all_letters_count == 0 ) {
			return;
		}
		$captials_percent = ( $captials_count / $all_letters_count );
		
		if( $captials_percent > 0.6 ) {
			$message->msgs_info [] = "&3Is your caps lock stuck?";
			$message->msg = strtolower( $message->msg );
		}
	}
	
	private function spamTest(\BuddyChannels\Message $message) {
		$msg_lcase_nocaps = strtolower ( str_replace ( " ", "", $message->msg ) ); // remove spaces and caps;
		                                                                     // if no history store msg and return
		if (! isset ( $this->lastmessages [$message->username_lower] )) {
			$this->lastmessages [$message->username_lower] = array ();
			$this->lastmessages [$message->username_lower] [time ()] = $msg_lcase_nocaps;
			return false;
		}
		// save message in array
		$thismsgtime = time ();
		$this->lastmessages [$message->username_lower] [$thismsgtime] = $msg_lcase_nocaps;
		// has history - clear any items older than spamcheck_message_age config value
		foreach ( $this->lastmessages [$message->username_lower] as $msgtime => $prevmsg ) {
			if ((time () - $msgtime) > $this->spamTestTimeInterval) {
				unset ( $this->lastmessages [$message->username_lower] [$msgtime] );
			}
		}
		// now return true if player has posted more than two of the same in the remaining interval
		$counted_prevMsgs = array_count_values ( $this->lastmessages [$message->username_lower] );
		if (isset ( $counted_prevMsgs [$msg_lcase_nocaps] )) {
			if ($counted_prevMsgs [$msg_lcase_nocaps] > $this->spamTestMaxDupesPerInterval) {
				$message->msgs_info [] = "&cYour message has failed spam detection and has been blocked.";
				$message->msgs_server_info [] = $message->username . " blocked by spamfilter: " . $message->originalMessage;
				unset ( $this->lastmessages [$message->username_lower] [$thismsgtime] );
				$message->message_blocked = true;
				return true;
			}
		}
		
		// now return true if rate limit exceeded ( msgs in the history interval )
		$count_of_recent = count ( $this->lastmessages [$message->username_lower] );
		if ($count_of_recent >= $this->spamTestMaxMessagesPerInterval) {
			$infomsg = " exceeded the chat rate limit of " . $this->spamTestMaxMessagesPerInterval . " messages per " . $this->spamTestTimeInterval . "s";
			$message->msgs_info [] = "&cYou have " . $infomsg;
			$message->msgs_server_info [] = "&c" . $message->username . " has " . $infomsg . " sending: " . $message->originalMessage;
			unset ( $this->lastmessages [$message->username_lower] [$thismsgtime] );
			$message->message_blocked = true;
			return true;
		}
		
		// otherwise all is ok
		return false;
	}
	private function babyFilter(\BuddyChannels\Message $message) {
		$msg_has_profanity = $this->hasVeryBadLanguage ( $message->originalMessage );
		// already on a swearing punishment?
		if (isset ( $this->users_babyendtime [$message->username_lower] )) {
			$bendtime = $this->users_babyendtime [$message->username_lower];
			$timeleft = ($bendtime - time ());
			if ($timeleft > 0) {
				$message->is_baby = true;
				// now we will tell the user they are still a baby - unless they have swore again, that will be handled below
				if (! $msg_has_profanity) {
					$message->msgs_info [] = "&3You are still a baby for the next &c" . $timeleft . "&3 seconds.";
				}
			} else {
				$message->is_baby = false;
				unset ( $this->users_babyendtime [$message->username_lower] );
				$message->msgs_info [] = "&3You are no longer a baby - but may we please remind you that swearing can result in a permanent ban.";
			}
		}
		// swore (again?)
		if ($msg_has_profanity) {
			$message->msgs_info [] = "&cWe have detected you are using profanity.";
			$message->msgs_server_info [] = $message->username . " activated baby filter with: " . $message->originalMessage;
			// swore while not already a baby
			if (! $message->is_baby) {
				$message->msgs_info [] = "&3Swearing is for babies.";
				$message->msgs_info [] = "&c  You are now a baby for &c" . $this->babyFilterTime . "&3 seconds.";
				$message->is_baby = true;
				$this->users_babyendtime [$message->username_lower] = (time () + $this->babyFilterTime);
			} else { // swore while already a baby
				$bendtime = $this->users_babyendtime [$message->username_lower];
				$timeleft = (($bendtime - time ()) * 2) + $this->babyFilterTime;
				$this->users_babyendtime [$message->username_lower] = (time () + $timeleft);
				$message->msgs_info [] = "&3Your time as a baby has been extended to &c" . $timeleft . "&3 seconds.";
				$message->msgs_server_info [] = $message->username . " activated extended baby filter with: " . $message->originalMessage;
				$message->is_baby = true;
			}
		}
		
		// if a baby - format the message
		if ($message->is_baby) {
			$babyisms = array (
					"goo",
					"ga",
					"gurgle" 
			);
			$msg_words = explode ( " ", $message->msg );
			$message->msg = "";
			foreach ( $msg_words as $i ) {
				$message->msg .= $babyisms [array_rand ( $babyisms )] . " ";
			}
			$message->userrank = "&c[Swear&bBaby&c]";
		}
	}
	private function wordFilter(\BuddyChannels\Message $message) {
		$message->msg = str_ireplace ( $this->badWordList, "*", $message->msg );
	}
	public function newlined_output($tagstring, $msgstartstring, $message) {
                $msgstartstring = " ⇨ "; // U+00A0 No-Break space + Normal space
		// not using newlined_output anymore
		//return $tagstring .  "&r&f > &r" . $message;
		$max_width = 75;
		$output_string = $tagstring . " " . $msgstartstring . " ";
		$output_pos = strlen ( Main::removeColors ( "&", $output_string ) );
		$message_words = explode ( " ", $message );
		
		foreach ( $message_words as $messageword ) {
			$wordlength = strlen ( Main::removeColors ( "&", $messageword ) );
			// if word would wrap do a newline
			if (($output_pos + $wordlength) > $max_width) {
				$output_string .= "\n";
				$newlinestart = $msgstartstring . $msgstartstring . $msgstartstring . $msgstartstring;
				$output_string .= $newlinestart;
				$output_pos = strlen ( Main::removeColors ( "&", $newlinestart ) );
			}
			$output_string .= $messageword . " ";
			$output_pos += $wordlength + 1;
		}
                $small_font_spacechar = "  "; // U+00A0 No-Break space + Normal space
                return  $tagstring . $msgstartstring . $message . $small_font_spacechar;
                // return $tagstring . $msgstartstring . $this->small2test($message);
	}
        
        
        public function small2test($txt) {
            $oldfont = "abcdefghijklmnopqrstuvwxyz";
            $newfont = "ａｂｃｄｅｆｇｈｉｊｋｌｍｎｏｐｑｒｓｔｕｖｗｘｙｚ";
            $oldfont .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $newfont .= "ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺ";
            $oldfont .= "0123456789:;<=>?@";
            $newfont .= "０１２３４５６７８９：；＜＝＞？＠";
            
            $newfont_array = array();
            preg_match_all('/./u', $newfont, $newfont_array);
            $newfont_array = $newfont_array[0];
            $oldfont_array = array(0);
            preg_match_all('/./u', $oldfont, $oldfont_array);
            $oldfont_array = $oldfont_array[0];
            $oldmsgarray = [];
            preg_match_all('/./u', $txt, $oldmsgarray);
            $oldmsgarray = $oldmsgarray[0];
            $newmsg = "";
            
            $preceedingwasctl = false;
            foreach($oldmsgarray as $curchar) {
                if($curchar == "&" or $curchar == "§") {
                    $preceedingwasctl = ($preceedingwasctl == false);
                    $newmsg .= $curchar;
                    continue;
                }
                if($preceedingwasctl) {
                    $preceedingwasctl = false;
                    $newmsg .= $curchar;
                    continue;
                }
                if(in_array($curchar, $oldfont_array)) {
                    $newmsg .= $newfont_array[array_search($curchar, $oldfont_array)];
                } else {
                    $newmsg .= $curchar;
                }
            }
            $small_font_spacechar = "  "; // U+00A0 No-Break space + Normal space
            return $newmsg . $small_font_spacechar;
        }

        public function formatForChannels(\BuddyChannels\Message $message) {
		$channel_formatted = "&o&n&6{" . $message->senderChannel_name . "}";
		if ($message->senderChannel_number == 0) {
			$channel_formatted = "&o&n&1{Public}";
		}
		
		$servername_formatted = "";
		if ( ! $message->server_name == "" ) {
			$servername_formatted = "&o&n&8@" . $message->server_name;
		}
		
		// echo
		$message_elements_echo = array (
				$servername_formatted,
				$message->userrank,
				"YOU" 
		);
		$tagstring = implode ( "&r&f ", $message_elements_echo );
		$message->msg_echo = $this->newlined_output ( $tagstring, " &r ", $message->msg );
		
		// msg_private
		$message->msg_private = "&l&o" . $message->username . " ---> TO YOU &r&f: " . $message->msg;
		
		// same channel
		$message_elements_samechan = array (
				$servername_formatted,
				$message->userrank,
				$message->username 
		);
		$tagstring = implode ( "&r&f ", $message_elements_samechan );
		$message->msg_samegroup = $this->newlined_output ( $tagstring, " &r> ", $message->msg );
		
		// shouting or public
		$message_elements_shout = array (
				$servername_formatted,
				$channel_formatted,
				$message->userrank,
				$message->username 
		);
		$tagstring = implode ( "&r&f ", $message_elements_shout );
		$message->msg_shouting = $this->newlined_output ( $tagstring, " &r> ", $message->msg );
		
		// emoting
		if( substr( $message->msg , 0 , 1 ) == "#" ) {
			$message_elements_emoting = array (
					$message->userrank,
					$message->username 
			);
			$message_elements_emoting_echo = array (
					$message->userrank,
					"YOU" 
			);
			$tagstring = "*" . implode ( "&r", $message_elements_emoting );
			$tagstring_echo = "*" . implode ( "&r", $message_elements_emoting_echo );
			// override other formats if emoting
			$message->msg_shouting = 
                                $this->newlined_output($tagstring, " " ,substr( $message->msg , 1));
			$message->msg_samegroup =
                                $this->newlined_output($tagstring, " " ,substr( $message->msg , 1));
			$message->msg_echo =
                                $this->newlined_output($tagstring_echo, " " ,substr( $message->msg , 1));
		}
	}
	public function hasVeryBadLanguage($msg) {
		// try some regexps 1st
		$words_to_regexp_test = array(
				"fuck",
				"bitch",
				"bastard",
				"fucker",
				"fuk you",
				"fk you"
		);
		$regexps = array();
		foreach($words_to_regexp_test as $word) {
			$newregexp = "";
			$newregexp .= "/(";
			foreach(str_split($word) as $char) {
				$newregexp .= $char . "+ *";
			}
			$newregexp .= ")/i";
		}
		$regexps = array (
				"/(f+ *u+ *c+ *k+)/i" ,
				"/(b+ *i+ *t+ *c+ *h+)/i" ,
				
		);
		foreach ( $regexps as $regexp ) {
			if (preg_match ( $regexp, Main::removeColors ( "&", $msg ) )) {
				return true;
			}
		}
		$reallybadwords = array (
				"fuck",
				"fucking",
				"fuking",
				"bitch",
				"bastard",
				"fucker" 
		);
		$permittedVariations = array (
				"itch",
				"uck" 
		);
		
		// add fuc to permittedVariations only if 'if u c' is present in string
		if( strpos(strtolower($msg), "if u c")  !== false ) {
			$permittedVariations[] = "fuc";
		}
		
		$colourCodes = str_split ( "1234567890abcdefghijklmnopqrstuvwxyz" );
		foreach ( $colourCodes as $key => $code ) {
			$colourCodes [$key] = "&" . $code;
		}
		
		// make 1 letter missing variations
		$badwordlist = array ();
		foreach ( $reallybadwords as $badword ) {
			$badwordlist [] = $badword;
			$letters = str_split ( $badword );
			for($i = 0; $i < count ( $letters ); $i ++) {
				$variation = "";
				for($x = 0; $x < count ( $letters ); $x ++) {
					if ($x != $i)
						$variation .= $letters [$x];
				}
				if (! in_array ( $variation, $permittedVariations ))
					$badwordlist [] = $variation;
			}
		}
		
		// manual (non fudged) additions
		// $badwordlist[] = "shit";
		$badwordlist [] = "cunt";
		$badwordlist [] = "nigger";
		$badwordlist [] = "nigga";
		
		$exactMatchesOnly = array();
		$exactMatchesOnly [] = "sex";
		$exactMatchesOnly [] = "fcuk";
		$exactMatchesOnly [] = "fuk";
		$exactMatchesOnly [] = "fack";
		$exactMatchesOnly [] = "feck";
		$exactMatchesOnly [] = "fuq";
		$exactMatchesOnly [] = "fcuking";
		$exactMatchesOnly [] = "fuking";
		$exactMatchesOnly [] = "facking";
		$exactMatchesOnly [] = "fecking";
		$exactMatchesOnly [] = "fuqing";
		$exactMatchesOnly [] = "baitach";
		$exactMatchesOnly [] = "taits";
		// where users have used asterixes
		$exactMatchesOnly [] = "f";
		$exactMatchesOnly [] = "fk";
		$exactMatchesOnly [] = "dic";
		$exactMatchesOnly [] = "bih";
		$exactMatchesOnly [] = "bitc";
		
		// replace caps
		$msg = strtolower ( $msg );
		// replace l33t chars
		$msg = str_replace ( "1", "i", $msg );
		$msg = str_replace ( "3", "e", $msg );
		$msg = str_replace ( "4", "a", $msg );
		$msg = str_replace ( "5", "s", $msg );
		$msg = str_replace ( "7", "t", $msg );
		$msg = str_replace ( "8", "ate", $msg );
		// replace accented and special characters that can get used to sub letters by users
		$accented_reps = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
		$msg = strtr( $msg, $accented_reps );
		// replace codes
		$msg = str_replace ( $colourCodes, "", $msg );
		// replace some pronouns
		$msg = str_replace ( "you", "", $msg );
		// take out non alphanumeric chars
		$msg = preg_replace ( '/[^a-z\d ]/i', '', $msg );
		
		// break down msg into words
		$msg_words = explode ( " ", $msg );
		// add an entry with the spaces taken out
		$msg_words [] = str_replace ( " ", "", $msg );
		// add entry with i's replaced by 1s
		$msg_words [] = str_replace ( "i", "1", str_replace ( " ", "", $msg ) );
		
		foreach ( $exactMatchesOnly as $exactMatchWord) {
			if (in_array ( $exactMatchWord, $msg_words )) {
				return true;
			}
		}
		
		foreach ( $badwordlist as $badword ) {
			$badword = strtolower ( $badword );
			if (in_array ( $badword, $msg_words )) {
				return true;
			}
			foreach ( $msg_words as $msg_word ) {
				if (! stripos ( $msg_word, $badword ) === false) {
					return true;
				}
			}
		}
	}
	public function translateColors($symbol, $message) {
		$message = str_replace ( $symbol . "0", TextFormat::BLACK, $message );
		$message = str_replace ( $symbol . "1", TextFormat::DARK_BLUE, $message );
		$message = str_replace ( $symbol . "2", TextFormat::DARK_GREEN, $message );
		$message = str_replace ( $symbol . "3", TextFormat::DARK_AQUA, $message );
		$message = str_replace ( $symbol . "4", TextFormat::DARK_RED, $message );
		$message = str_replace ( $symbol . "5", TextFormat::DARK_PURPLE, $message );
		$message = str_replace ( $symbol . "6", TextFormat::GOLD, $message );
		$message = str_replace ( $symbol . "7", TextFormat::GRAY, $message );
		$message = str_replace ( $symbol . "8", TextFormat::DARK_GRAY, $message );
		$message = str_replace ( $symbol . "9", TextFormat::BLUE, $message );
		$message = str_replace ( $symbol . "a", TextFormat::GREEN, $message );
		$message = str_replace ( $symbol . "b", TextFormat::AQUA, $message );
		$message = str_replace ( $symbol . "c", TextFormat::RED, $message );
		$message = str_replace ( $symbol . "d", TextFormat::LIGHT_PURPLE, $message );
		$message = str_replace ( $symbol . "e", TextFormat::YELLOW, $message );
		$message = str_replace ( $symbol . "f", TextFormat::WHITE, $message );
		$message = str_replace ( $symbol . "k", TextFormat::OBFUSCATED, $message );
		$message = str_replace ( $symbol . "l", TextFormat::BOLD, $message );
		$message = str_replace ( $symbol . "m", TextFormat::STRIKETHROUGH, $message );
		$message = str_replace ( $symbol . "n", TextFormat::UNDERLINE, $message );
		$message = str_replace ( $symbol . "o", TextFormat::ITALIC, $message );
		$message = str_replace ( $symbol . "r", TextFormat::RESET, $message );
		// $message = str_replace(" ", "ᱹ", $message);
		return $message;
	}
}