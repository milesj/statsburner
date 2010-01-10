<?php
/**
 * statsburner.php
 *
 * FACTS: Feedburner Awareness API Circulation Tracking and Statistics
 * Grabs the statistics (circulation and hits) from your feedburner feed.
 * Can also build a local xml of your stats for easier and faster access.
 * 
 * @author 		Miles Johnson - www.milesj.me
 * @copyright	Copyright 2006-2009, Miles Johnson, Inc.
 * @license 	http://www.opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @package     StatsBurner - Feedburner Stats Aggregator
 * @version     2.3
 * @link		www.milesj.me/resources/script/statsburner
 * @link		http://www.feedburner.com/
 * @link		http://code.google.com/apis/feedburner/awareness_api.html
 */

class StatsBurner {
	
	/**
	 * Constant for day type.
	 *
	 * @var string
	 */
	const TYPE_DAY = 'd';
	
	/**
	 * Constant for month type.
	 *
	 * @var string
	 */
	const TYPE_MONTH = 'm';
	
	/**
	 * Constant for year type.
	 *
	 * @var string
	 */
	const TYPE_YEAR = 'y';

	/**
	 * Current version: www.milesj.me/files/logs/statsburner
	 *
	 * @access public
	 * @var string
	 * @static
	 */
	public static $version = '2.3';
	
	/**
	 * Should we allow duplicate or overlapping dates while doing discrete date ranges.
	 *
	 * @access public
	 * @var boolean
	 * @static
	 */
	public static $allowDupeDates = false;
	
	/**
	 * Should we use cURL to grab data from feedburner? (must have cURL enabled on your server).
	 *
	 * @access public
	 * @var boolean
	 * @static
	 */
	public static $useCURL = true;
	
	/**
	 * Array that contains response errors.
	 *
	 * @access private
	 * @var array
	 * @static
	 */
	private static $__errors = array(
		0 => 'Unsupported date type',
		1 => 'Feed not found',
		2 => 'This feed does not permit Awareness API access',
		3 => 'Item not found In feed',
		4 => 'Data restricted; this feed does not have FeedBurner Stats PRO item view tracking enabled',
		5 => 'Missing required parameter (URI)',
		6 => 'Malformed parameter (DATES)'
	);
	
	/**
	 * Array to your local xml files for each feedburner feed (that was built locally by crons); relative to root.
	 *
	 * @access private
	 * @var array
	 * @static
	 */
	private static $__localXML = array(
		'feed_uri' => '/path/to/local/xml.xml'
	);
	
	/**
	 * Disabled, use getInstance().
	 *
	 * @access private
	 * @return void
	 */ 
	private function __construct() {
	} 
	
	/**
	 * Adds an xml to the feedburner xml array.
	 *
	 * @access public
	 * @param string $feedURI
	 * @param string $xmlPath
	 * @return void
	 * @static
	 */
	public static function addXml($feedURI = '', $xmlPath = '') {
		if (!empty($feedURI) && !empty($xmlPath)) {
			if (is_dir($_SERVER['DOCUMENT_ROOT'] . dirname($xmlPath))) {
				$xmlPath = DIRECTORY_SEPARATOR . ltrim($xmlPath, DIRECTORY_SEPARATOR);
				self::$__localXML[$feedURI] = $xmlPath;
			} else {
				trigger_error('StatsBurner::addXml(): Local XML destination folder does not exist', E_USER_WARNING);
				return;
			}
		}
	}

	/**
	 * Grabs the feedburner stats and writes them to a local xml file.
	 *
	 * @access public
	 * @param string $feedURI
	 * @param string $dateType
	 * @param int $dateOffset
	 * @param array $dateDiscrete
	 * @return boolean
	 * @static
	 */
	public static function buildXml($feedURI, $dateType = self::TYPE_MONTH, $dateOffset = 1, $dateDiscrete = '') {
		if (!isset(self::$__localXML[$feedURI])) {
			trigger_error('StatsBurner::buildXml(): Local XML location is not set', E_USER_WARNING);
			return;
		}
		
		$path = $_SERVER['DOCUMENT_ROOT'] . self::$__localXML[$feedURI];
		$feed = array();
		
		// Get date range and build api url
		$getFeed = self::__parseXml($feedURI, $dateType, $dateOffset, $dateDiscrete);
		if (!is_array($getFeed)) {
			return $getFeed;
		}
		
		$handle = fopen($path, 'w');
		
		// Loop the xml and save it
		$xmlCache  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xmlCache .= '<root>';
		$xmlCache .= '
		<feed>
			<id>'. $getFeed['id'] .'</id>
			<uri>'. $getFeed['uri'] .'</uri>
			<api>'. str_replace('&', '&amp;', $getFeed['api']) .'</api>
			<circulation>'. $getFeed['circulation'] .'</circulation>
			<hits>'. $getFeed['hits'] .'</hits>
			<dates>';
			
			if (is_array($getFeed['dates'])) {
				foreach($getFeed['dates'] as $id => $date) {
					$xmlCache .= '\n<date>'. $date .'</date>';
				}
			} else {
				$xmlCache .= $getFeed['dates'];
			}
			
		$xmlCache .= '</dates>
		</feed>';
		$xmlCache .= '</root>';
		
		fwrite($handle, $xmlCache);
		fclose($handle);
			
		return true;
	}

	/**
	 * Grabs the feedburner statistics from the defined date range.
	 *
	 * @access public
	 * @param string $feedURI
	 * @param string $dateType
	 * @param int $dateOffset
	 * @param array $dateDiscrete
	 * @return mixed
	 * @static
	 */
	public static function getStats($feedURI, $dateType = self::TYPE_MONTH, $dateOffset = 1, $dateDiscrete = '') {
		return self::__parseXml($feedURI, $dateType, $dateOffset, $dateDiscrete);
	}

	/**
	 * Grabs the feedburner statistics from a local xml; Which is created by the buildLocalXml() method.
	 *
	 * @access public
	 * @param string $feedURI
	 * @return mixed
	 * @static
	 */
	public static function getStatsFromXml($feedURI) {
		if (!isset(self::$__localXML[$feedURI])) {
			trigger_error('StatsBurner::buildXml(): Local XML location is not set', E_USER_WARNING);
			return;
		}
		
		$path = $_SERVER['DOCUMENT_ROOT'] . self::$__localXML[$feedURI];
		$feed = array();
		
		if (!file_exists($path)) {
			trigger_error('StatsBurner::buildXml(): Local XML file does not exist', E_USER_WARNING);
			return;
		}
		
		// Build the feedburner array
		$xmlFeed = simplexml_load_file($path);
		$feed['id']  = (int) $xmlFeed->feed->id;
		$feed['uri'] = (string) $xmlFeed->feed->uri;
		$feed['api'] = (string) $xmlFeed->feed->api;
		$feed['circulation'] = (int) $xmlFeed->feed->circulation;
		$feed['hits'] = (int) $xmlFeed->feed->hits;
		$feed['downloads'] = (int) $xmlFeed->feed->downloads;
		$feed['reach'] = (int) $xmlFeed->feed->reach;
		
		$totalDates = count($xmlFeed->feed->dates->date);
		if ($totalDates > 1) {
			for ($i = 0; $i <= ($totalDates - 1); ++$i) {
				$feed['date'][] = (string) $xmlFeed->feed->dates->date[$i];
			}
		} else {
			$feed['date'] = (string) $xmlFeed->feed->dates;
		}
		
		return $feed;
	}
	
	/**
	 * Creates the date range for the feedburner api, also allows discrete date ranges and types.
	 *
	 * @access private
	 * @param string $dateType
	 * @param int $dateOffset
	 * @param array|boolean $dateDiscrete
	 * @return array
	 * @static
	 */
	private static function __buildDateRange($dateType, $dateOffset, $dateDiscrete = false) {
		$dateOffset = (is_numeric($dateOffset) ? (int)$dateOffset : 1);
		$allowedTypes = array('y', 'm', 'd', '');
		$currDate = date('Y-m-d');
		
		if (!in_array(mb_strtolower($dateType), $allowedTypes)) {
			trigger_error('StatsBurner::__buildDateRange(): '. self::$__errors[0], E_USER_WARNING);
			return;
		}
			
		// Build main date
		$dates = array();
		if (!empty($dateType)) {
			$dates[$currDate]['type'] = $dateType;
			$dates[$currDate]['offset'] = $dateOffset;
		}
		
		// We adding discrete dates also?
		if (is_array($dateDiscrete)) {
			foreach ($dateDiscrete as $date => $dateOptions) {
			
				// Do some error checking first
				$dateCheck = (is_string($date)) ? $date : $dateOptions;
					
				list($y, $m, $d) = explode('-', $dateCheck);
				if (!preg_match('/^(?:\d{4})-(?:0?[1-9]|1[0-2])-(?:0?[1-9]|[1-2]\d|3[0-1])$/', $dateCheck) || !checkdate($m, $d, $y)) {
					trigger_error('StatsBurner::__buildDateRange(): '. self::$__errors[6], E_USER_WARNING);
					return;
				}
					
				// Continue with processing	
				if (is_string($date)) {
					$parts = explode('|', $dateOptions);
					
					// If type not equal to m/d/y, dont add it
					if (in_array(mb_strtolower($parts[0]), $allowedTypes) && is_numeric($parts[1])) { 
						$dates[$date]['type'] = $parts[0];
						$dates[$date]['offset'] = $parts[1];
					}
				} else {
					$dates[$dateOptions] = $dateOptions;
				}
			}
		}
		
		// Build the date ranges
		$dateRange = array();
		foreach ($dates as $date => $dateLoop) {
			if (is_array($dateLoop)) {
				if ($dateLoop['offset'] == 0) {
					$dateRange[$date] = $date;
				} else {
					$dateRange[$date]['finish'] = $date;
					
					$parts = explode('-', $date);
					switch (mb_strtolower($dateLoop['type'])) {
						case self::TYPE_DAY:
							$pastDate = mktime(0, 0, 0, $parts[1], $parts[2] - $dateLoop['offset'], $parts[0]);
						break;
						case self::TYPE_YEAR:
							$pastDate = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0] - $dateLoop['offset']);
						break;
						case self::TYPE_MONTH:
							$pastDate = mktime(0, 0, 0, $parts[1] - $dateLoop['offset'], $parts[2], $parts[0]);	
						break;
					}
					
					$dateRange[$date]['start'] = date('Y-m-d', $pastDate);
				}
			} else {
				$dateRange[$dateLoop] = $dateLoop;
			}
		}
		
		// Build the final date url
		$datesFinal = array();
		foreach ($dateRange as $date => $dateRanges) {
			if (is_array($dateRanges)) {
				$datesFinal[] = $dateRanges['start'] .','. $dateRanges['finish'];
			} else {
				$datesFinal[] = $dateRanges;
			}
		}
		
		// Do we check for duplicates?
		if (self::$allowDupeDates === false) {
			$duplicates = self::__detectDupeDates($datesFinal);
			
			if ($duplicates >= 1) {
				trigger_error('StatsBurner::__buildDateRange(): Duplicate date range detected', E_USER_WARNING);
				return;
			}
		}
		
		return $datesFinal;
	}

	/**
	 * Checks to see if there are duplicates dates in the dates array, if so trigger error.
	 *
	 * @access private
	 * @param array $dates
	 * @return int
	 * @static
	 */
	private static function __detectDupeDates($dates) {
		$totalDates = count($dates);
		$detected = 0;
		
		// Go through each section of the array
		for ($i = 0; $i <= ($totalDates - 1); ++$i) {
		
			// Go thru each date of each section
			foreach ($dates as $id => $date) {
				if ($id != $i) {
				
					// If date is a range
					if (mb_strlen($dates[$i]) > 10) {
						$parts = explode(',', $dates[$i]);
						$dateStart = $parts[0];
						$dateFinish = $parts[1];
						
						// If date being checked is a range
						if (mb_strlen($date) > 10) {
							$subParts = explode(',', $date);
							
							foreach ($subParts as $newDate) {
								if ($newDate > $dateStart && $newDate < $dateFinish) {
									++$detected;
								}
							}
						// If date being checked is a single
						} else {
							if ($date > $dateStart && $date < $dateFinish) {
								++$detected;
							}
						}
					// If date is a single
					} else {
						if (mb_strpos($dates[$i], $date) !== false) {
							++$detected;
						}
					}
				}
			}
		}
		
		return $detected;
	}
	
	/**
	 * Grabs the feedburner statistics from the awareness api link.
	 *
	 * @access private
	 * @param string $feedURI
	 * @param string $dateType
	 * @param int $dateOffset
	 * @param array $dateDiscrete
	 * @return mixed
	 * @static
	 */
	private static function __parseXml($feedURI, $dateType, $dateOffset, $dateDiscrete = '') {
		$getDates = self::__buildDateRange($dateType, $dateOffset, $dateDiscrete);
		$dateRange = (!empty($getDates)) ? '&dates='. implode('/', $getDates) : '';
		$apiFeed = 'https://feedburner.google.com/api/awareness/1.0/GetFeedData?uri='. $feedURI . $dateRange;
			
		// Do we use curl?
		if (self::$useCURL === true) {
			if (function_exists('curl_init')) {
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $apiFeed);
				curl_setopt($curl, CURLOPT_HEADER, false);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				$content = curl_exec($curl);
				
				if (curl_errno($curl) || curl_error($curl)) {
					trigger_error('StatsBurner::__parseXml(): '. curl_error($curl) .' ('. curl_errno($curl) .')', E_USER_WARNING);
					return;
				}
				
				curl_close($curl);
				$xmlFeed = simplexml_load_string($content);
			} else {
				$xmlFeed = simplexml_load_file($apiFeed);
			}
		} else {
			$xmlFeed = simplexml_load_file($apiFeed);
		}
		
		// Error checking
		$rspStat = (string) $xmlFeed['stat'];
		if ($rspStat == 'fail') {
			$errCode = (int) $xmlFeed->err[0]['code'];
			
			trigger_error('StatsBurner::__parseXml(): '. self::$__errors[$errCode], E_USER_WARNING);
			return;
		}
		
		// Get statistics average
		$totalDays = count($xmlFeed->feed->entry);
		
		if ($totalDays > 1) {
			$circAvg = 0;
			$hitsAvg = 0;
			$dloadAvg = 0;
			$reachAvg = 0;
			
			for ($i = 0; $i <= $totalDays; ++$i) {
				$circAvg = $circAvg + $xmlFeed->feed->entry[$i]['circulation'];
				$hitsAvg = $hitsAvg + $xmlFeed->feed->entry[$i]['hits'];
				$dloadAvg = $dloadAvg + $xmlFeed->feed->entry[$i]['downloads'];
				$reachAvg = $reachAvg + $xmlFeed->feed->entry[$i]['reach'];
			}
			
			$circAvg = round($circAvg / $totalDays);
			$hitsAvg = round($hitsAvg / $totalDays);
			$dloadAvg = round($dloadAvg / $totalDays);
			$reachAvg = round($reachAvg / $totalDays);
		} else {
			$circAvg = (int) $xmlFeed->feed->entry[0]['circulation'];
			$hitsAvg = (int) $xmlFeed->feed->entry[0]['hits'];
			$dloadAvg = (int) $xmlFeed->feed->entry[0]['downloads'];
			$reachAvg = (int) $xmlFeed->feed->entry[0]['reach'];
		}
	
		// Build the feedburner array
		$xmlParsed = array();
		foreach ($xmlFeed->feed[0]->attributes() as $key => $value) {
			$xmlParsed[$key] = (string) $value;
		}
		
		$xmlParsed['api'] = $apiFeed;
		$xmlParsed['circulation'] = $circAvg;
		$xmlParsed['hits'] = $hitsAvg;
		$xmlParsed['downloads'] = $dloadAvg;
		$xmlParsed['reach'] = $reachAvg;
		
		$datesUsed = count($getDates);
		
		if ($datesUsed > 1) {
			foreach ($getDates as $date) {
				$xmlParsed['dates'][] = $date;
			}
		} else {
			$xmlParsed['dates'] = (is_array($getDates) && !empty($getDates)) ? $getDates[0] : date('Y-m-d');
		}
		
		return $xmlParsed;
	}
	
} 
