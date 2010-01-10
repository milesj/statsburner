<?php 
// MIGHT NOT WORK WHEN TESTING ON LOCALHOST, IF THERE IS NO HTTP SUPPORT

// Turn on error reporting
error_reporting(E_ALL);

function debug($var) {
	echo '<pre>'. print_r($var, 1) .'</pre>';
}

// Feedburner, go go!
require_once('statsburner.php');

// Create a remote connection
$remoteFeed = StatsBurner::getStats('starcraft');

echo 'Remote connection';
debug($remoteFeed);

// Remote connection with discrete dates (Todays Date, December 2008 and June 2008)
// Note: Discretes count backwards so do the date after the desired date
$discrete = array(
	'2009-01-01' => 'm|1',
	'2008-07-01' => 'm|1'
);
$remoteFeedDiscrete = StatsBurner::getStats('starcraft', null, null, $discrete);

echo 'Remote connection with discrete dates';
debug($remoteFeedDiscrete);

// Add an index for a local xml (if it isnt in the class) and build the local xml
// Path to XML should be relative to root

$path = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', dirname(__FILE__));

StatsBurner::addXml('starcraft', $path . DIRECTORY_SEPARATOR .'stats.xml');
StatsBurner::buildXml('starcraft');

// Grab data from the local xml
$localFeed = StatsBurner::getStatsFromXml('starcraft');

echo 'Built and pulled from a local XML feed';
debug($localFeed);

