<?php namespace Tomkirsch\Crud;
/*
	CRUD BaseModel
	Ensure your subclassed models set their $validationRules	
*/
use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class BaseModel extends Model{
	protected $uniqueFields		= [];		// unique field functionality, see joinModel()
	
	protected $prefix; 						// prefix to be used in all regular DB fields (ex: 'user_')
	protected $allowedInRules	= FALSE; 	// set $allowedFields from $validationRules keys
	
	// preferred column names
	protected $createdField 	= 'created';
	protected $updatedField 	= 'modified';
	protected $deletedField 	= NULL;
	
	public function __construct(ConnectionInterface &$db = null, ValidationInterface $validation = null){
		parent::__construct($db, $validation);
		// prefix CRUD fields if they are set on this model
		if(!empty($this->prefix)){
			foreach([
				'primaryKey',
				'createdField',
				'updatedField',
				'deletedField',
			] as $field){
				if(!empty($this->{$field}) && !stristr($this->{$field}, $this->prefix)){
					$this->{$field} = $this->prefix.$this->{$field};
				}
			}
		}
		// set $allowedFields from rule keys
		if($this->allowedInRules && !empty($this->validationRules)){
			$this->allowedFields = array_merge($this->allowedFields, array_keys($this->validationRules));
		}
	}
	
	// if using unique field functionality, you should reset() before making a query
	public function reset(){
		$this->resetUniqueFields()->resetQuery();
		return $this;
	}
	public function resetUniqueFields(){
		$this->uniqueFields = [];
		return $this;
	}
	
	// getter - expose the validation rules for usage by other models, etc
	public function validationRules():array{
		return $this->validationRules;
	}
	
	// getter - simply get the names of the columns from validationRules
	public function columns(bool $includePrimary=TRUE):array{
		$cols = array_keys($this->validationRules);
		
		// created/modified/deleted columns are not usually in the rules
		if($this->createdField) $cols[] = $this->createdField;
		if($this->updatedField) $cols[] = $this->updatedField;
		if($this->deletedField) $cols[] = $this->deletedField;
		
		// place primary key at the beginning of the array
		if($includePrimary && $this->primaryKey){
			array_unshift($cols, $this->primaryKey);
		}
		return $cols;
	}
	
	// expose the table, and also allow us to change the table on the fly, useful for aliases
	public function table(?string $val=NULL){
		if($val !== NULL){
			$this->table = $val;
			return $this;
		}else{
			return $this->table;
		}
	}
	
	// get the next autoincrement
	public function nextAutoIncrement():int{
		$dbName = $this->getDatabase();
		$sql = "SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name='$this->table' AND table_schema='$dbName'";
		$row = $this->db->query($sql)->getRow();
		if(!$row){
			throw new \Exception("Cannot find AUTO_INCREMENT in $dbName.$this->table");
		}
		return intval($row->AUTO_INCREMENT);
	}
	
	// override - if the Entity's primary key is EMPTY, we insert (CI's method just did isset()). Helpful when POSTing an empty string id
	public function save($data): bool{
		if (is_object($data) && $this->primaryKey && isset($data->{$this->primaryKey}) && empty($data->{$this->primaryKey})){
			unset($data->{$this->primaryKey});
		}
		return parent::save($data);
	}
	
	// utility - create an associative array using primary keys
	public function getDictionary(array $list, bool $isObject=TRUE): array{
		if(!$this->primaryKey) throw new \Exception("Cannot use getDictionary() on a model with no primaryKey.");
		$dict = [];
		foreach($list as $item){
			$id = $isObject ? $item->{$this->primaryKey} : $item[$this->primaryKey];
			$dict[$id] = $item;
		}
		return $dict;
	}
	
	/*
		Sync a simple lookup table. 
		IMPORTANT: $common_data is used as a WHERE clause on delete! Use at your own risk.
			$this->sync_one2many('foos2bars', ['foo_id'=>1], 'bar_id', [12,13], [5,13,14]); // inserts 12, deletes 5 & 14
	*/
	public function syncOneToMany(string $lookuptable, array $common_data, string $remote_id_field, $ids=[], $old_ids=[]) :array{
		if(!is_array($ids)){
			$ids = empty($ids) ? [] : explode(',', $ids);
		}
		if(!is_array($old_ids)){
			$old_ids = empty($old_ids) ? [] : explode(',', $old_ids);
		}
		$insert = array_diff($ids, $old_ids);
		if(count($insert)){
			$data = [];
			foreach($insert as $id){
				$data[] = array_merge($common_data, [$remote_id_field => $id]);
			}
			$this->db->table($lookuptable)->insertBatch($data);
		}
		$delete = array_diff($old_ids, $ids);
		if(count($delete)){
			$this->db->table($lookuptable)->where($common_data)->whereIn($remote_id_field, $delete)->delete();
		}
		return [
			'inserted'=>$insert,
			'deleted'=>$delete,
		];
	}
	
	/*
		This method simplifies joins by using a model's columns, taken from $validationRules. You MUST call selectUniqueFields() afterwards. Ex:
		$fooModel
			->reset() // always reset
			->joinModel('BarModel', 'bar.id = foo.id', 'inner')
			->joinModel('BarModel', 'bazAlias.otherid = foo.id', 'left', 'bazAlias') // use aliases if you need to query the same table
			->joinModel('TempModel', 'temp.id = foo.id', 'left', '', 'temp_') // all fields from TempModel will be prefixed
			->joinModel('BlarghModel', 'blargh.id = foo.id', 'left', 'derivedBlargh', '', $sql) // select all the columns from Blargh table, but use a derived table
			->selectUniqueFields() // always call this before getting the result
			->findAll()
		;
	*/
	public function joinModel(string $modelName, string $clause='', string $join='left', string $alias='', string $prefix='', string $derivedSql='', bool $escape=NULL){
		// we'll assume it's a App\Models namespace. If not, pass the FQN
		if(!strstr($modelName, '\\')) $modelName = '\App\Models\\'.$modelName;
		$otherModel = new $modelName();
		$otherTable = $otherModel->table();
		
		if(empty($clause)){
			$clause = "$this->table.$this->primaryKey = $otherTable.$this->primaryKey";
		}
		if(empty($alias)){
			$alias = $joinTable = $otherTable;
		}else{
			$joinTable = "$otherTable AS $alias";
		}
		if(!empty($derivedSql)){
			// wrap the SQL in parentheses, if needed
			if(substr($derivedSql, 0, 1) !== '('){
				$derivedSql = "($derivedSql)";
			}
			$joinTable = "$derivedSql AS $alias";
			// never escape!
			$escape = FALSE;
		}
		// make the join!
		$this->join($joinTable, $clause, $join, $escape);
		// now add the columns to our unique dictionary
		$dict = [];
		foreach($otherModel->columns(TRUE) as $col){
			$dict["$alias.$col"] = $prefix.$col;
		}
		$this->addUniqueSet($modelName, $dict);
		return $this;
	}
	
	/*
	 Perform a join with unique fields, which can transform them with a new table alias and/or prefixes. Ex:
	 	// create a subquery with a model
	 	$firstSql = $firstModel->joinModel('SecondModel', 'second.id = first.id')->selectUniqueFields()->getCompiledSelect();
		// now perform the join using the subquery. All unique fields from the other model(s) will be incorporated into the SELECT
	 	$thirdModel->joinUnique($firstModel->uniqueFields(), $firstSql, 'third.id = second.id')->selectUniqueFields()->findAll();
	*/
	public function joinUnique(array $uniqueFields, string $tableOrSql, string $clause, string $join='left', string $alias='', string $prefix='', bool $escape=NULL){
		if(!empty($alias)){
			// was custom SQL passed instead of a table name?
			if(strstr($tableOrSql, ' ')){
				if(substr($tableOrSql, 0, 1) !== '('){
					$tableOrSql = "($tableOrSql)";
					$escape = FALSE;
				}
				$tableOrSql = "$tableOrSql AS $alias";
			}
		}
		$this->join($tableOrSql, $clause, $join, $escape);
		foreach($uniqueFields as $modelName=>$set){
			$dict = [];
			foreach($set as $col=>$field){
				if(!empty($alias)){
					// alter this to use the given alias/prefix
					$dot = strpos($col, '.');
					$col = $alias.substr($col, $dot);
				}
				// add the prefixed field to our array
				$dict[$col] = $prefix.$field;
			}
			$this->addUniqueSet($modelName, $dict);
		}
		// now you can call selectUniqueFields()
		return $this;
	}
	
	/*
	 Getter - expose the uniqueFields array.
	 You can also transform the model's unique fields that were taken with an alias. Ex:
	 	// create a subquery with a model, adding a prefix
	 	$firstSql = $firstModel->joinModel('SecondModel', 'second.id = first.id', 'left', '', 'myPrefix_')->selectUniqueFields()->getCompiledSelect();
		$secondSql = $secondModel->joinModel('ThirdModel', 'third.id = second.id', 'left', '', $firstSql)->selectUniqueFields()->getCompiledSelect();
		// now we must select the prefixed fields in our main query, so we must transform them:
		$fields = $firstModel->uniqueFields('myPrefix_');
		// now perform the join using the subquery. All unique fields from the other model(s) will be incorporated into the SELECT
	 	$finalModel->joinUnique($fields, $secondSql, 'third.id = second.id')->selectUniqueFields()->findAll();
	*/
	public function uniqueFields(?string $alias=NULL):array{
		if(empty($alias)){
			return $this->uniqueFields;
		}
		// we use "sets" so that we can overwrite columns with the last one specified in the chain
		$dict = [];
		foreach($this->uniqueFields as $modelName=>$set){
			$newSet = [];
			foreach($set as $col=>$field){
				// find all fields that have the prefix, and change the source column
				if(strstr($field, $alias)){
					$dot = strpos($col, '.');
					$col = substr($col, 0, $dot).'.'.$field;
				}
				$newSet[$col] = $field;
			}
			$dict[$modelName] = $newSet;
		}
		return $dict;
	}
	
	// add unique fields from THIS model's rules/columns. This is called by default in selectUnique()
	public function addUniqueSelf(string $alias='', string $prefix=''){
		if(empty($alias)) $alias = $this->table;
		$dict = [];
		foreach($this->columns(TRUE) as $col){
			$dict["$alias.$col"] = $prefix.$col;
		}
		$this->addUniqueSet(get_class($this), $dict);
		return $this;
	}
	
	/*
		All the table's columns are selected by default with unique fields. This lets you only choose the ones you want.
		You can also pass the fields in selectUnique(), but this method allows you to specify which model to filter.
		If the $modelName is omitted, ALL fields NOT in the passed array will be removed!
		$myModel->joinModel('fooModel')->filterUnique(['foo_id', 'foo_created']); // only select these two fields
	*/
	public function filterUnique(array $fields, ?string $modelName=NULL){
		if($modelName) $modelName = $this->prepClassName($modelName);
		foreach($this->uniqueFields as $model=>$set){
			if($modelName && $modelName !== $model) continue; // this set is from a different model, skip
			$this->uniqueFields[$model] = array_intersect($this->uniqueFields[$model], $fields);
		}
		return $this;
	}
	
	// call this after all unique fields have been configured
	public function selectUnique(?array $fields=NULL, bool $selectThisTable=TRUE){
		// usually, we want to select this model's data
		if($selectThisTable){
			$this->addUniqueSelf();
		}
		// were fields passed? then only get those
		if(!empty($fields)){
			$this->filterUnique($fields);
		}
		// loop through the sets and pick out the unique field names. 
		// we use "sets" so that we can overwrite columns with the last one specified in the chain
		$selects = [];
		foreach($this->uniqueFields as $modelName=>$set){
			foreach($set as $col=>$field){
				// if a field name was already taken, this will just overwrite it with the last used source
				$selects[$field] = $col;
			}
		}
		// perform active record select
		foreach($selects as $field=>$col){
			$this->select("$col AS $field");
		}
		// don't reset uniqueFields here, as you may need them for subqueries
		return $this;
	}
	
	// utility - add a set of unique fields
	protected function addUniqueSet(string $modelName, array $uniqueFields){
		$this->uniqueFields[$this->prepClassName($modelName)] = $uniqueFields;
		return $this;
	}
	
	// strip namespace out of model class names. Hoping this doesn't cause issues down the road.
	protected function prepClassName(string $className):string{
		if($pos = strrpos($className, '\\')) return substr($className, $pos + 1);
    	return $className;
	}
}