<?php
use CodeIgniter\I18n\Time;

// apply timezone in config to date
function localDate($dateTime, string $format=NULL, string $timezone=NULL){
	if($dateTime === NULL) return $format ? '' : NULL;
	if(is_string($dateTime)) $dateTime = new Time($dateTime);
	$dateTime = $dateTime->setTimezone($timezone ?? config('App')->localTimezone ?? config('App')->appTimezone);
	return empty($format) ? $dateTime : $dateTime->format($format);
}
function serverDate($dateTime, string $format=NULL, string $timezone=NULL){
	if($dateTime === NULL) return $format ? '' : NULL;
	if(is_string($dateTime)) $dateTime = new Time($dateTime);
	$dateTime = $dateTime->setTimezone($timezone ?? config('App')->appTimezone);
	return empty($format) ? $dateTime : $dateTime->format($format);
}