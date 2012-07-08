# Statsburner v3.1 #

A class that aggregates, calculates and averages statistics from the Feedburner Awareness API.

## Requirements ##

* PHP 5.2.x, 5.3.x
* SimpleXML - http://php.net/manual/book.simplexml.php
* cURL - http://php.net/manual/book.curl.php

## Features ##

* Grabs statistics from Feedburners Awareness API
* Averages and totals the circulation, hits, downloads, reach, views and click-through counts
* Uses the cURL module when sending requests
* Detects duplicate dates within date ranges
* Can define single dates, date ranges and discrete dates
* Caches the results to the file system for quick lookup

## Documentation ##

Thorough documentation can be found here: http://milesj.me/code/php/statsburner
