<?php 
// properly transform variables into strings (such as DateTime) for comparison to POST
// requires date_helper
function make_string($val, array $options=[]):string{
	if(is_string($val)) return $val;
	if(is_null($val)) return '';
	if(is_bool($val)) return $val ? '1' : '0';
	
	if($val instanceof DateTime){
		if(!function_exists('localDate')) helper('date');
		if(isset($options['timezone'])){
			// a specific timezone was passed, use it
			$val = localDate($val, NULL, $options['timezone']); // date_helper
		}else if(!empty($options['localTimezone'])){
			// use the default local timezone
			$val = localDate($val); // date_helper
		}
		if(empty($options['format'])){
			// guess if there's a time involved
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
	// is $obj empty/null?
	if(empty($obj)){
		return $default;
	}
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
	$property = input_to_property($field);
	$objValue = get_obj_value($obj, $property, $default);
	$val = make_string($objValue, $options);
	return set_value($field, $val, $escape);
}
function set_checkbox_from(string $field, $obj, $val, bool $default=FALSE, array $options=[]){
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
	$val = set_value_from($field, $obj, $default, FALSE, $options);
	return $escape ? htmlspecialchars($val) : $val;
}

/*
// TODO
function form_hidden_from(string $field, $obj, $default='', array $attr=[]):string{
	return form_input_from($field, $obj, $default, $attr, 'hidden');
}
function form_input_from(string $field, $obj, $default='', array $attr=[], array $options=[]):string{
	$value = set_value_from($field, $obj, $default, FALSE, $options);
	$attr = array_merge([
		'type'=>'text',
		'name'=>$field,
		'id'=>input_to_property($field), // remove brackets
		'value'=>$value,
	], $attr);
	return form_input($data);
}
function form_textarea_from(string $field, $obj, $default='', array $attr=[], array $options=[]):string{
	$value = set_textarea_from($field, $obj, $default, FALSE, $options);
	$attr = array_merge([
		'name'=>$field,
		'id'=>input_to_property($field), // remove brackets
		'value'=>$value,
	], $attr);
	return form_textarea($data);
}
*/