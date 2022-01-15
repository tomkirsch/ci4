<?php namespace Tomkirsch\Crud;

use CodeIgniter\Entity\Entity; // if changing inheritance, ensure class name strings are replaced in this class, form_helper, etc

class BaseEntity extends Entity{
	// make empty strings into NULL values. Useful when filling from html form data
	// if certain fields are allowed to have empty strings, pass as second parameter
	public function fillNullEmpty(?array $data = NULL, array $emptyAllowedFields = [])
	{
		if(!is_array($data)) return $this;
		foreach ($data as $key => $val) {
			if ($val === '' && !in_array($key, $emptyAllowedFields)) $data[$key] = NULL;
		}
		return parent::fill($data);
	}
	
	// apply timezones to non-empty dates. Note that you do NOT need to do this with <input type="datetime-local">
	public function applyTimezone(string $timezone, array $attributes=[]){
		// apply casts to make Time instances
		foreach($this->toArray() as $key=>$val){
			if(!is_a($val, '\CodeIgniter\I18n\Time')) continue;
			if(!empty($attributes) && !in_array($key, $attributes)) continue;
			$this->$key = $val->setTimezone($timezone);
		}
		return $this;
	}
	public function applyLocalTimezone(array $attributes=[]){
		$config = config('App');
		return $this->applyTimezone($config->localTimezone, $attributes);
	}
	public function applyServerTimezone(array $attributes=[]){
		$config = config('App');
		return $this->applyTimezone($config->appTimezone, $attributes);
	}
	
	// map GROUP_CONCAT fields to an array of objects (or Entities)
	// ex: $users = $entity->csvMap(['user_ids'=>'user_id', 'user_emails'=>'user_email'], 'App\Entities\User');
	public function csvMap(array $map, string $className='object', $removeAttr=FALSE, string $separator=','):array{
		$temp = [];
		$longest = 0;
		foreach($map as $attr => $newProp){
			if(!isset($temp[$newProp])) $temp[$newProp] = [];
			if(!empty($this->attributes[$attr])){
				$val = $this->attributes[$attr];
				if(!is_array($val)) $val = explode($separator, $val);
				// do NOT use array merge!
				$temp[$newProp] += $val;
				if(count($temp[$newProp]) > $longest) $longest = count($temp[$newProp]);
			}
			if($removeAttr){
				unset($this->attributes[$attr]);
				unset($this->original[$attr]);
			}
		}
		$result = [];
		for($i=0; $i<$longest; $i++){
			$obj = new $className();
			foreach($temp as $prop => $values){
				$obj->{$prop} = $values[$i] ?? NULL;
			}
			if(is_a($obj, '\CodeIgniter\Entity\Entity')){
				$obj->syncOriginal(); // we assume the data comes from the DB and thus hasn't been changed
			}
			$result[] = $obj;
		}
		return $result;
	}
	
	// find all attributes with prefix(es) and return a new entity with those attributes
	// ex: $image = $entity->prefixMap(['image_', 'upload_'], '\App\Entities\Image');
	public function prefixMap($prefixes, string $className='object', $removePrefix=FALSE, $removeAttr=FALSE){
		if(!is_array($prefixes)){
			$prefixes = [$prefixes];
		}
		// generate entity attributes
		$attr = [];
		$deleteAttr = [];
		foreach($prefixes as $prefix){
			$prefixLen = strlen($prefix);
			foreach($this->attributes as $key=>$val){
				if(substr($key, 0, $prefixLen) === $prefix){
					if($removeAttr) $deleteAttr[] = $key;
					if($removePrefix){
						$key = substr($key, $prefixLen);
					}
					$attr[$key] = $val;
				}
			}
		}
		$entity = new $className();
		if(is_a($entity, '\CodeIgniter\Entity\Entity')){
			$entity->setAttributes($attr); // using this method makes the attributes "original", ie. not changed from database
		}
		// delete attributes
		if($deleteAttr){
			$newAttr = $this->attributes;
			foreach($this->attributes as $key=>$val){
				if(in_array($key, $deleteAttr)){
					unset($newAttr[$key]);
				}
			}
			$this->setAttributes($newAttr); // make attributes "original"
		}
		return $entity;
	}
	
	public function stripAliasPrefix(string $aliasPrefix){
		$newAttr = $this->attributes;
		$prefixLen = strlen($aliasPrefix);
		foreach($this->attributes as $attr=>$val){
			if(substr($attr, 0, $prefixLen) === $aliasPrefix){
				$newAttr[substr($attr, $prefixLen)] = $val;
				unset($newAttr[$attr]);
			}
		}
		$this->setAttributes($newAttr); // make attributes "original"
	}
}