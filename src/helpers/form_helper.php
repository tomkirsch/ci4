<?php 
// properly transform variables into strings (such as DateTime) for comparison to POST
// requires date_helper
function make_string($val, array $options):string{
	// easy stuff
	if(is_string($val)) return $val;
	if(is_null($val)) return '';
	if(is_bool($val)) return $val ? '1' : '0';
	
	if($val instanceof DateTime){
		// try to load the date helper for localDate
		if(!function_exists('localDate')) helper('Tomkirsch\Crud\helpers\date_helper');
		// was there a timezone passed?
		if(isset($options['timezone'])){
			// a specific timezone was passed, use it
			$val = localDate($val, NULL, $options['timezone']);
		}else if(!empty($options['localTimezone'])){
			// use the default local timezone, if any. Defaults to the app's timezone
			$val = localDate($val);
		}
		// was there no specific format passed?
		if(empty($options['format'])){
			// make an educated guess if there's a time involved
			$options['format'] = ($val->toTimeString() === '00:00:00') ? 'Y-m-d' : 'Y-m-d H:i:s';
		}
		return $val->format($options['format']);
	}
	// it's something else... just transform it to a string
	return (string) $val;
}
// remove brackets from input name
function input_to_property($field){
	preg_match('/\[(\w+)\]/', $field, $matches);
	return empty($matches) ? $field : $matches[1];
}
// determine if object has the given property, and if so, return it
function get_obj_value($obj, $prop, $default=NULL){
	// this does the weird work of checking for NULLs, because isset() will return FALSE if the property is NULL.
	if(is_a($obj, '\CodeIgniter\Entity')){
		// Entities use magic methods, which will return FALSE on property_exists()
		// if we convert it to an array, then we can just use array_key_exists()
		$obj = $obj->toArray();
	}
	if(is_array($obj)){
		if(!array_key_exists($prop, $obj)) return $default;
		return $obj[$prop];
	}else{
		if(!property_exists($obj, $prop)) return $default;
		return $obj->{$prop};
	}
}
function set_value_from(string $field, $obj, $default='', bool $escape=TRUE, array $options=[]):string{
	if(empty($obj)){
		$default = make_string($default, $options);
		return set_value($field, $default, $escape);
	}
	$property = input_to_property($field);
	$objValue = get_obj_value($obj, $property, $default);
	$val = make_string($objValue, $options);
	return set_value($field, $val, $escape);
}
function set_checkbox_from(string $field, $obj, $val, bool $default=FALSE, array $options=[]){
	if(empty($obj)){
		$val = make_string($val, $options);
		return set_checkbox($field, $val, $default);
	}
	$property = input_to_property($field);
	$notSetString = '____NOTSET____';
	$objValue = get_obj_value($obj, $property, $notSetString);
	$checked = ($objValue !== $notSetString && $objValue == $val);
	if($objValue === $notSetString) $objValue = NULL;
	if($checked) $default = TRUE;
	$val = make_string($objValue, $options);
	return set_checkbox($field, $val, $default);
}
function set_select_from(string $field, $obj, $val, bool $default=FALSE, array $options=[]){
	if(empty($obj)){
		$val = make_string($val, $options);
		return set_select($field, $val, $default);
	}
	$property = input_to_property($field);
	$notSetString = '____NOTSET____';
	$objValue = get_obj_value($obj, $property, $notSetString);
	$checked = ($objValue !== $notSetString && $objValue == $val);
	if($objValue === $notSetString) $objValue = NULL;
	if($checked) $default = TRUE;
	$val = make_string($objValue, $options);
	return set_select($field, $val, $default);
}
function set_radio_from(string $field, $obj, $val, bool $default=FALSE, array $options=[]){
	return set_checkbox_from($field, $obj, $val, $default, $options);
}
function set_textarea_from(string $field, $obj, $default='', bool $escape=TRUE, array $options=[]){
	return set_value_from($field, $obj, $default, $escape, $options);
}
