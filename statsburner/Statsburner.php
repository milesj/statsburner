<?php
/**
 * Statsburner - Feedburner Stats Aggregator
 *
 * A class that aggregates, calculates and averages statistics from the Feedburner Awareness API.
 * Supports all standard API calls: getFeedData(), getItemData() and getResyndicationData().
 * Furthermore, each request can support multiple dates, discrete ranges and offset ranges.
 * Minor caching is also built in to reduce the heavyness of these HTTP requests.
 * 
 * @author		Miles Johnson - http://milesj.me
 * @copyright	Copyright 2006-2011, Miles Johnson, Inc.
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		http://milesj.me/code/php/statsburner
 * @link		http://feedburner.com/
 * @link		http://code.google.com/apis/feedburner/awareness_api.html
 */

class Statsburner {

	/**
	 * Constants for date types.
	 */
	const TYPE_DAY = 'd';
	const TYPE_MONTH = 'm';
	const TYPE_YEAR = 'y';

	/**
	 * Current version.
	 *
	 * @access public
	 * @var string
	 */
	public $version = '3.0';

	/**
	 * Directory path to write cached files.
	 *
	 * @access protected
	 * @var string
	 */
	protected $_cachePath;

	/**
	 * How long the cache should last until being overwritten.
	 *
	 * @access protected
	 * @var string
	 */
	protected $_cacheDuration = '+1 day';

	/**
	 * Array that contains response errors.
	 *
	 * @access private
	 * @var array
	 */
	private $__errors = array(
		0 => 'Unsupported date type.',
		1 => 'Feed not found.',
		2 => 'This feed does not permit Awareness API access.',
		3 => 'Item not found In feed.',
		4 => 'Data restricted; this feed does not have FeedBurner Stats PRO item view tracking enabled.',
		5 => 'Missing required parameter (URI).',
		6 => 'Malformed parameter (DATES).'
	);

	/**
	 * Feedburner URI.
	 *
	 * @access private
	 * @var string
	 */
	private $__uri;

	/**
	 * Save the URI and set the default cache path.
	 *
	 * @access public
	 * @param string $uri
	 * @return void
	 */
	public function __construct($uri) {
		$this->__uri = $uri;
		$this->setCaching(dirname(__FILE__) .'/cache/', $this->_cacheDuration);
	}

	/**
	 * Build the API url and include the date and URI query params.
	 *
	 * @access public
	 * @param string $url
	 * @param array $query
	 * @return string
	 */
	public function buildApiUrl($url, $query) {
		$query = array('uri' => $this->__uri) + $query;
		$params = array();

		if (!empty($query['dates'])) {
			$query['dates'] = implode('/', $query['dates']);
		}

		// Don't use http_build_query() as it escapes commas and breaks
		foreach (array_filter($query) as $key => $value) {
			$params[] = $key .'='. $value;
		}

		return $url .'?'. implode('&', $params);
	}

	/**
	 * Build the date ranges into a suitable array format.
	 *
	 * @access private
	 * @param array $ranges
	 * @return array
	 */
	public function buildDateRange(array $ranges = array()) {
		$dates = array();

		// No ranges, default to past month
		if (empty($ranges)) {
			$dates[date('Y-m-d', strtotime('-1 month'))] = array(
				'type' => self::TYPE_MONTH,
				'offset' => 1
			);

		// We are adding a range of dates
		} else {
			foreach ($ranges as $date => $options) {
				if (is_string($date)) {
					if (!is_array($options)) {
						$options = array('type' => $options);
					}
				} else {
					if (strpos($options, ',') !== false) {
						list($date,) = explode(',', $options);
					} else {
						$date = $options;
						$options = array();
					}
				}

				list($y, $m, $d) = explode('-', $date);

				if (!preg_match('/^(?:\d{4})-(?:0?[1-9]|1[0-2])-(?:0?[1-9]|[1-2]\d|3[0-1])$/', $date) || !checkdate($m, $d, $y)) {
					$this->_error(__METHOD__, 6);
					continue;
				}

				if (is_array($options)) {
					$dates[$date] = $options + array(
						'type' => self::TYPE_MONTH,
						'offset' => 1
					);
				} else {
					$dates[$date] = $options;
				}
			}
		}

		// Build the date ranges
		$dateRange = array();

		foreach ($dates as $date => $options) {
			if (is_string($options)) {
				$dateRange[] = $options;
				
			} else if ($options['offset'] == 0) {
				$dateRange[] = $date;

			} else {
				$parts = explode('-', $date);

				switch (strtolower($options['type'])) {
					case self::TYPE_DAY:
						$future = mktime(0, 0, 0, $parts[1], $parts[2] + $options['offset'], $parts[0]);
					break;
					case self::TYPE_YEAR:
						$future = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0] + $options['offset']);
					break;
					case self::TYPE_MONTH:
					default:
						$future = mktime(0, 0, 0, $parts[1] + $options['offset'], $parts[2], $parts[0]);
					break;
				}

				$dateRange[] = $date .','. date('Y-m-d', $future);
			}
		}

		// Check for duplicates
		if ($this->detectDupeDates($dateRange) > 0) {
			return $this->_error(__METHOD__, 'Duplicate date range detected.');
		}

		return $dateRange;
	}

	/**
	 * Checks to see if there are duplicate dates in the dates array.
	 *
	 * @access public
	 * @param array $dates
	 * @return int
	 */
	public function detectDupeDates($dates) {
		$totalDates = count($dates) - 1;
		$detected = 0;

		// Go through each section of the array
		for ($i = 0; $i <= $totalDates; ++$i) {

			// Go thru each date of each section
			foreach ($dates as $id => $date) {
				if ($id == $i) {
					continue;
				}

				// If date is a range
				if (strlen($dates[$i]) > 10) {
					list($dateStart, $dateFinish) = explode(',', $dates[$i]);

					// If date being checked is a range
					if (strlen($date) > 10) {
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
					if (strpos($dates[$i], $date) !== false) {
						++$detected;
					}
				}
			}
		}

		return $detected;
	}

	/**
	 * Grab basic feed data. Will return averages and totals of statistic values.
	 *
	 * @access public
	 * @param array $ranges
	 * @return array
	 */
	public function getFeedData(array $ranges = array()) {
		return $this->request('https://feedburner.google.com/api/awareness/1.0/GetFeedData', $ranges);
	}

	/**
	 * Grab basic feed data including a list of items. Will return averages and totals of statistic values.
	 *
	 * @access public
	 * @param array $ranges
	 * @param string $item
	 * @return array
	 */
	public function getItemData(array $ranges = array(), $item = null) {
		return $this->request('https://feedburner.google.com/api/awareness/1.0/GetItemData', $ranges, $item);
	}

	/**
	 * Grab basic feed data including a list of items and related referrers. Will return averages and totals of statistic values.
	 *
	 * @access public
	 * @param array $ranges
	 * @param string $item
	 * @return array
	 */
	public function getResyndicationData(array $ranges = array(), $item = null) {
		return $this->request('https://feedburner.google.com/api/awareness/1.0/GetResyndicationData', $ranges, $item);
	}

	/**
	 * Grabs the statistics from the Awareness API URL. Will cache results automatically.
	 *
	 * @access private
	 * @param string $url
	 * @param array $ranges
	 * @return mixed
	 */
	public function request($url, array $ranges = array(), $item = null) {
		$dates = $this->buildDateRange($ranges);
		$url = $this->buildApiUrl($url, array(
			'dates' => $dates,
			'itemurl' => $item
		));
		$key = md5($url);

		if ($cache = $this->_isCached($key)) {
			return $cache;
		}

		// Fetch with cURL
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Statsburner API v'. $this->version
		));

		$response = curl_exec($curl);
		
		if (curl_error($curl)) {
			return $this->_error(__METHOD__, curl_error($curl), '('. curl_errno($curl) .')');
		}

		curl_close($curl);

		$xml = simplexml_load_string($response);
		$hasError = ((string) $xml['stat'] == 'fail');

		// Get statistics average
		$count = count($xml->feed->entry);
		$items = array();
		$circ = 0;
		$hits = 0;
		$dload = 0;
		$reach = 0;

		// Has items
		if ($xml->feed->entry) {
			foreach ($xml->feed->entry as $item) {
				$date = (string) $item['date'];
				$items[$date] = array(
					'circulation' => (int) $item['circulation'],
					'downloads' => (int) $item['downloads'],
					'reach' => (int) $item['reach'],
					'hits' => (int) $item['hits']
				);

				// Has links
				if ($item->item) {
					$links = array();
					$views = 0;
					$clicks = 0;

					foreach ($item->item as $link) {
						$data = array(
							'title' => (string) $link['title'],
							'url' => (string) $link['url'],
							'views' => (int) $link['itemviews'],
							'clicks' => (int) $link['clickthroughs']
						);

						// Has referrers
						if ($link->referrer) {
							$data['referrers'] = array();

							foreach ($link->referrer as $referrer) {
								$data['referrers'][] = array(
									'url' => (string) $referrer['url'],
									'views' => (int) $referrer['itemviews'],
									'clicks' => (int) $referrer['clickthroughs']
								);
							}
						}

						$views = $views + $data['views'];
						$clicks = $clicks + $data['clicks'];
						$links[] = $data;
					}

					// Merge with item
					$items[$date] = $items[$date] + array(
						'total' => array(
							'clicks' => $clicks,
							'views' => $views
						),
						'average' => array(
							'clicks' => round($clicks / count($links)),
							'views' => round($views / count($links))
						),
						'links' => $links
					);
				}

				$circ = $circ + $items[$date]['circulation'];
				$dload = $dload + $items[$date]['downloads'];
				$reach = $reach + $items[$date]['reach'];
				$hits = $hits + $items[$date]['hits'];
			}
		}

		// Set to 1 incase of divide by zero
		if ($count == 0) {
			$count = 1;
		}

		// Build the Feedburner array
		$output = array(
			'id' => (string) $xml->feed['id'],
			'uri' => (string) $xml->feed['uri'],
			'api' => $url,
			'total' => array(
				'circulation' => $circ,
				'downloads' => $dload,
				'reach' => $reach,
				'hits' => $hits
			),
			'average' => array(
				'circulation' => round($circ / $count),
				'downloads' => round($dload / $count),
				'reach' => round($reach / $count),
				'hits' => round($hits / $count)
			),
			'dates' => $dates,
			'items' => $items
		);

		// Don't cache if errors
		if ($hasError) {
			$this->_error(__METHOD__, (int) $xml->err[0]['code'], $url);
			
			return $output;
		}

		return $this->_cache($key, $output);
	}

	/**
	 * Set the cache path and duration.
	 *
	 * @access public
	 * @param string $path
	 * @param string $duration
	 * @return void
	 */
	public function setCaching($path, $duration) {
		$path = str_replace('\\', '/', $path);

		if (substr($path, -1) != '/') {
			$path .= '/';
		}

		if (!is_dir($path)) {
			@mkdir($path, 0777);

		} else if (!is_writeable($path)) {
			@chmod($path, 0777);
		}

		$this->_cachePath = $path;
		$this->_cacheDuration = $duration;
	}

	/**
	 * Cache and serialize the contents of the request.
	 *
	 * @access protected
	 * @param string $key
	 * @param array $content
	 * @return array
	 */
	protected function _cache($key, $content) {
		$duration = is_numeric($this->_cacheDuration) ? $this->_cacheDuration : strtotime($this->_cacheDuration);
		$cache = $duration ."\n";
		$cache .= serialize($content);

		file_put_contents($this->_cachePath . $key, $cache);

		return $content;
	}

	/**
	 * Throw an error.
	 *
	 * @access protected
	 * @param string $method
	 * @param string|int $error
	 * @param string $msg
	 * @return void
	 */
	protected function _error($method, $error, $msg = '') {
		if (isset($this->__errors[$error])) {
			$error = $this->__errors[$error];
		}

		trigger_error(sprintf('%s(): %s %s', $method, $error, $msg), E_USER_WARNING);
		return;
	}

	/**
	 * Check to see if the results are cached and is within the cache duration. If cache exists, return unserialized.
	 *
	 * @access protected
	 * @param string $key
	 * @return array|boolean
	 */
	protected function _isCached($key) {
		$path = $this->_cachePath . $key;

		if (file_exists($path)) {
			list($timestamp, $content) = explode("\n", file_get_contents($path));

			if ($timestamp >= time()) {
				return unserialize(trim($content));
			}
		}

		return false;
	}

}
