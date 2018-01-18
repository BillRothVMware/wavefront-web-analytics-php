<?php

// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';

// process options
$options = getopt("s::e::m::lt:");
// s: start date
// e: end date
// m: metric name from google. i.e. ga:sessions
// t: timeframe for metric name
// l: log to syslog

// Globals defaults

$logging = false;
$startdate = "yesterday";
$enddate = "yesterday";
$gmetric="ga:sessions";
$metrictimeframe="yesterday";
$tags="lang=php";
$sourcetag="php";
$gmetricheader = "marketing.analytics.web.";

if (array_key_exists('l', $options)) {
	$logging = true;
	openlog("Wavefront PHP Analytics", LOG_PID | LOG_PERROR, LOG_LOCAL0);
}

if(array_key_exists('s',$options) || array_key_exists('e',$options)) {
	if(array_key_exists('s',$options) && array_key_exists('e',$options)){
		$startdate=$options['s'];
		$enddate=$options['e'];
	} else {
		$estr="Need both Start and End Date";
		if($logging) 
			syslog(LOG_INFO,$estr);
		echo $estr . "\n";
		exit(1);
	}
}
if(array_key_exists('m',$options)) {
	$gmetric = $options['m'];
}

if(array_key_exists('t',$options)) {
	 $metrictimeframe = $options['t'];
} else {
	$estr="Need a metric name timeframe";
		if($logging) 
			syslog(LOG_INFO,$estr);
		echo $estr . "\n";
		exit(1);
}
/// MAIN CODE

	
$analytics = initializeAnalytics();
$response = getReport($analytics);


printResults($gmetricheader,$response);


/**
 * Initializes an Analytics Reporting API V4 service object.
 *
 * @return An authorized Analytics Reporting API V4 service object.
 */
function initializeAnalytics()
{

  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = __DIR__ . '/service-account-credentials.json';

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName("WF Analytics Reporting");
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  $analytics = new Google_Service_AnalyticsReporting($client);

  return $analytics;
}


/**
 * Queries the Analytics Reporting API V4.
 *
 * @param service An authorized Analytics Reporting API V4 service object.
 * @return The Analytics Reporting API V4 response.
 */
function getReport($analytics) {

  global $startdate, $enddate, $gmetric, $metrictimeframe;
  
  // Replace with your view ID, for example 89623538.
  $VIEW_ID = "89623538";

  // Create the DateRange object.
  // Documentation on DateRanges is at: https://developers.google.com/analytics/devguides/reporting/core/v4/rest/v4/reports/batchGet#ReportRequest.FIELDS.date_ranges
  //
  $dateRange = new Google_Service_AnalyticsReporting_DateRange();
  $dateRange->setStartDate($startdate);
  $dateRange->setEndDate($enddate);

  // Create the Metrics object.
  $sessions = new Google_Service_AnalyticsReporting_Metric();
  $sessions->setExpression($gmetric);
  // set up alias for metric name, strip :ga:"
  $mt = substr($gmetric,3);
  
  $sessions->setAlias($mt . "." . $metrictimeframe);

  // Create the ReportRequest object.
  // metric names https://developers.google.com/analytics/devguides/reporting/core/dimsmets#cats=user
  //
  
  $request = new Google_Service_AnalyticsReporting_ReportRequest();
  $request->setViewId($VIEW_ID);
  $request->setDateRanges($dateRange);
  $request->setMetrics(array($sessions));

  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
  $body->setReportRequests( array( $request) );
  return $analytics->reports->batchGet( $body );
}


/**
 * Parses and prints the Analytics Reporting API V4 response.
 *
 * @param An Analytics Reporting API V4 response.
 */
function printResults($pmetricheader, $reports) {
	global $metrictimeframe, $logging, $tags, $sourcetag;
	
  for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
    $report = $reports[ $reportIndex ];
    $header = $report->getColumnHeader();
    $dimensionHeaders = $header->getDimensions();
    $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
    $rows = $report->getData()->getRows();

    for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
      $row = $rows[ $rowIndex ];
      $dimensions = $row->getDimensions();
      $metrics = $row->getMetrics();
      for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
        print($dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
      }

      for ($j = 0; $j < count($metrics); $j++) {
        $values = $metrics[$j]->getValues();
        for ($k = 0; $k < count($values); $k++) {
          $entry = $metricHeaders[$k];
		  $estr=$pmetricheader . $entry->getName();	
		  
		  sendWavefront($estr, $values[$k],time(), $tags, $sourcetag);
		  
        }
      }
    }
  }
}

function sendWavefront($metric, $value, $ts, $tags, $source) {
	
	global $logging;
	
	$address = "10.140.44.31";
    $service_port = 2878;
	
	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
		return;
	} else {
		//
	}
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
		return;
	} else {
		//
	}
	$str = $metric . " " . $value . " " . $ts . " " . $tags . " source=" . $source . "\n";
	
	if($logging)
		syslog(LOG_INFO,$str);
	
	socket_write($socket, $str, strlen($str));
	socket_close($socket);
	
}
