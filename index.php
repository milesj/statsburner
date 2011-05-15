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

// Turn on errors
error_reporting(E_ALL);

function debug($var) {
	echo '<pre>'. print_r($var, true) .'</pre>';
}

// Include class and instantiate for http://feeds.feedburner.com/milesj
include_once 'statsburner/Statsburner.php';

$feed = new Statsburner('milesj');

// Fetch data (defaults to the past month)
debug($feed->getFeedData());

// Fetch data for multiple date ranges
// Date ranges are incremented based on "offset"
debug($feed->getFeedData(array(
	// November 1st 2010 - December 1st 2010
	'2010-11-01',

	// January 1st - February 1st 2011
	'2011-01-01' => Statsburner::TYPE_MONTH,

	// February 26th 2010
	'2011-02-26' => array('type' => Statsburner::TYPE_DAY, 'offset' => 0),

	// June 1st - August 1st 2010
	'2010-06-01' => array('type' => Statsburner::TYPE_MONTH, 'offset' => 2)
)));

// Fetch data with link views and clickthrough data
debug($feed->getItemData());

// Fetch data with referral and clickthrough data
debug($feed->getResyndicationData());