<?php

use CodeIgniter\I18n\Time;

// apply timezone in config to date
function localDate($dateTime, string $format = NULL, string $timezone = NULL)
{
	$app = config('App');
	if (empty($app->localTimezone)) throw new Exception('localDate() expects a localTimezone property in config/App.php');
	if (empty($app->appTimezone)) throw new Exception('localDate() expects an appTimezone property in config/App.php');
	if ($dateTime === NULL) return $format ? '' : NULL;
	if (is_string($dateTime)) $dateTime = new Time($dateTime, $app->appTimezone);
	if (!is_a($dateTime, 'CodeIgniter\I18n\Time')) throw new Exception('localDate() expects a string or Time object');
	$dateTime = $dateTime->setTimezone($timezone ?? $app->localTimezone);
	return empty($format) ? $dateTime : $dateTime->format($format);
}
function serverDate($dateTime, string $format = NULL, string $timezone = NULL)
{
	$app = config('App');
	if (empty($app->localTimezone)) throw new Exception('localDate() expects a localTimezone property in config/App.php');
	if (empty($app->appTimezone)) throw new Exception('serverDate() expects an appTimezone property in config/App.php');
	if ($dateTime === NULL) return $format ? '' : NULL;
	if (is_string($dateTime)) $dateTime = new Time($dateTime, $app->localTimezone);
	if (!is_a($dateTime, 'CodeIgniter\I18n\Time')) throw new Exception('serverDate() expects a string or Time object');
	$dateTime = $dateTime->setTimezone($timezone ?? $app->appTimezone);
	return empty($format) ? $dateTime : $dateTime->format($format);
}
