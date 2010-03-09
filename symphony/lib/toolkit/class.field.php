<?php
	/**
	 * The abstract field type that new fields must extend.
	 */
	Class Field{
		protected $_key = 0;
		protected $_fields;
		protected $_Parent;
		protected $_engine;
		protected $_required;
		protected $_showcolumn;
		protected $Database;

		const __OK__ = 100;
		const __ERROR__ = 150;
		const __MISSING_FIELDS__ = 200;
		const __INVALID_FIELDS__ = 220;
		const __DUPLICATE__ = 300;
		const __ERROR_CUSTOM__ = 400;
		const __INVALID_QNAME__ = 500;

		const __TOGGLEABLE_ONLY__ = 600;
		const __UNTOGGLEABLE_ONLY__ = 700;
		const __FILTERABLE_ONLY__ = 800;
		const __UNFILTERABLE_ONLY__ = 900;	
		const __FIELD_ALL__ = 1000;

		/**
		 * Construct a new instance of this field.
		 *
		 * @param reference $parent
		 *	a reference to the parent of this field.
		 */
		function __construct(&$parent){
			$this->_Parent = $parent;
			
			$this->_fields = array();
			$this->_required = false;
			$this->_showcolumn = true;
			
			$this->_handle = (strtolower(get_class($this)) == 'field' ? 'field' : strtolower(substr(get_class($this), 5)));

			if(class_exists('Administration')) $this->_engine = Administration::instance();
			elseif(class_exists('Frontend')) $this->_engine = Frontend::instance();
			else trigger_error(__('No suitable engine object found'), E_USER_ERROR);
			
			$this->creationDate = DateTimeObj::getGMT('c');
			
			$this->Database = Symphony::Database();

		}

		/**
		 * Test whether this field can show the table column.
		 *
		 * @return boolean
		 *	true if this can, false otherwise.
		 */
		public function canShowTableColumn(){
			return $this->_showcolumn;
		}

		/**
		 * Test whether this field can be toggled?
		 *
		 * @return boolean
		 *	true if it can be toggled, false otherwise.
		 */
		public function canToggle(){
			return false;
		}
		
		/**
		 * Test whether this field can be filtered. This default implementation
		 * prohibits filtering. Filtering allows the xml output results to be limited
		 * according to an input parameter. Subclasses should override this if
		 * filtering is supported.
		 *
		 * @return boolean
		 *	true if this can be filtered, false otherwise.
		 */
		public function canFilter(){
			return false;
		}
		
		/**
		 * Test whether this field can be imported. This default implementation
		 * prohibits importing. Subclasses should override this is importing is
		 * supported.
		 *
		 * @return boolean
		 *	true if this can be imported, false otherwise.
		 */
		public function canImport(){
			return false;
		}

		/**
		 * Test whether this field supports data-source output grouping. This
		 * default implementation prohibits grouping. Data-source grouping allows
		 * clients of this field to group the xml output according to this field.
		 * Subclasses should override this if grouping is supported.
		 *
		 * @return boolean
		 *	true if this field does support data-source grouping, false otherwise.
		 */
		public function allowDatasourceOutputGrouping(){
			return false;
		}

		/**
		 * Test whether this field supports data-source parameter output. This
		 * default implementation prohibits parameter output. Data-source
		 * parameter output allows this field to be provided as a parameter
		 * to other data-sources or xslt. Subclasses should override this if
		 * parameter output is supported.
		 *
		 * @return boolean
		 *	true if this supports data-source parameter output, false otherwise.
		 */
		public function allowDatasourceParamOutput(){
			return false;
		}	
		
		/**
		 * Test whether the contents of this field must be unique. This default
		 * implementation always returns false.
		 *
		 * @return boolean
		 *	true if the content of this field must be unique, false otherwise.
		 */
		public function mustBeUnique(){
			return false;
		}
			
		/**
		 * Accessor to the toggle states. This default implementation returns
		 * an empty array.
		 *
		 * @return array
		 *	the array of toggle states.
		 */
		public function getToggleStates(){
			return array();
		}

		/**
		 * Toggle the field data. This default implementation always returns
		 * the input data.
		 *
		 * @param mixed $data
		 *	the data to toggle.
		 * @param mixed $newState
		 *	the new state of the toggle?
		 * @return mixed
		 *	the toggled data.
		 */
		public function toggleFieldData($data, $newState){
			return $data;
		}

		/**
		 * Accessor to the handle of this.
		 *
		 * @return string
		 *	the textual handle of this field.
		 */
		public function handle(){
			return $this->_handle;
		}

		/**
		 * Accessor to the name of this field. If the name differs from the handle
		 * the name will be returned. Otherwise the handle is returned in its place.
		 *
		 * @return string
		 *	the name of this field if set, the handle otherwise.
		 */
		public function name(){
			return ($this->_name ? $this->_name : $this->_handle);
		}		

		/**
		 * Set the input field to the input value. This will write over any existing
		 * setting for this field.
		 *
		 * @param mixed $field
		 *	the field key.
		 * @param mixed $value
		 *	the new value of the key.
		 */
		public function set($field, $value){
			$this->_fields[$field] = $value;
		}

		/**
		 * Remove an entry of this field from the database.
		 *
		 * @param number $entry_id
		 *	the id of the entry to delete.
		 * @param $data (optional)
		 *	the date to use to select which entry to delete? This is an optional
		 *	argument and defaults to null.
		 * @return boolean
		 *	true if the cleanup was successful, false otherwise.
		 */
		public function entryDataCleanup($entry_id, $data=NULL){
			$this->Database->delete('tbl_entries_data_' . $this->get('id'), " `entry_id` = '$entry_id' ");
			
			return true;
		}

		/**
		 * Fill the input data array with default values for known keys provided
		 * these fields are not already set. The input array is then used to set
		 * the values of the corresponding fields in this.
		 *
		 * @param array $data
		 *	the data array to initialize if necessary.
		 */
		public function setFromPOST($data) {
			$data['location'] = (isset($data['location']) ? $data['location'] : 'main');
			$data['required'] = (isset($data['required']) && @$data['required'] == 'yes' ? 'yes' : 'no');
			$data['show_column'] = (isset($data['show_column']) && @$data['show_column'] == 'yes' ? 'yes' : 'no');
			$this->setArray($data);
		}

		/**
		 * Add or overwrite the fields in this with the corresponding fields
		 * in the input array. This will do nothing if the input array is
		 * empty. No fields are removed from the fields in this.
		 *
		 * @param array $array
		 *	the source for the field values.
		 */
		public function setArray($array){
			if(empty($array) || !is_array($array)) return;
			foreach($array as $field => $value) $this->set($field, $value);
		}

		/**
		 * Accessor to the named field of this. If no field is provided all the
		 * fields of this are returned.
		 *
		 * @param string $field (optional)
		 *	the name of the field to access the data for. This is optional and
		 *	defaults to null in which case all fields are returned.
		 * @return null|mixed|array
		 *	the value of the input field name if there is one, all the fields if
		 *	the input field name was null, null if the input field was supplied but
		 *	there is no value for that field.
		 */
		public function get($field=NULL){
			if(!$field) return $this->_fields;
			
			if (!isset($this->_fields[$field])) return null;
			
			return $this->_fields[$field];
		}

		/**
		 * Unset the value of the input field name.
		 *
		 * @param string $field
		 *	the key of the field to unset.
		 */
		public function remove($field){
			unset($this->_fields[$field]);
		}

		/**
		 * Permanently remove a section association from this field in the database.
		 *
		 * @param number $child_field_id
		 *	the id of the child section field
		 */
		public function removeSectionAssociation($child_field_id){
			$this->Database->query("DELETE FROM `tbl_sections_association` WHERE `child_section_field_id` = '$child_field_id'");
		}

		/**
		 * Create an asscoiation between a section and a field.
		 *
		 * @param number $parent_section_id
		 * @param number $child_field_id
		 * @param number $parent_field_id (optional)
		 *	an optional parent field identifier. this defaults to null.
		 * @param boolean $cascading_deletion (optional)
		 *	optional cascading delete flag which defaults to false.
		 * @return boolean
		 *	true if the association was successfully made, false otherwise.
		 */
		public function createSectionAssociation($parent_section_id, $child_field_id, $parent_field_id=NULL, $cascading_deletion=false){

			if($parent_section_id == NULL && !$parent_field_id) return false;
			
			if($parent_section_id == NULL) $parent_section_id = $this->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '$parent_field_id' LIMIT 1");
			
			$child_section_id = $this->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '$child_field_id' LIMIT 1");
			
			$fields = array('parent_section_id' => $parent_section_id, 
							'parent_section_field_id' => $parent_field_id, 
							'child_section_id' => $child_section_id, 
							'child_section_field_id' => $child_field_id,
							'cascading_deletion' => ($cascading_deletion ? 'yes' : 'no'));

			if(!$this->Database->insert($fields, 'tbl_sections_association')) return false;
				
			return true;		
		}

		/**
		 * Clear the fields of this.
		 */
		public function flush(){
			$this->_fields = array();
		}

		/**
		 * Display the publish panel for this field. The display panel is the
		 * interface to create the data in instances of this field once added
		 * to a section.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the html defined user interface to this
		 *	field.
		 * @param array $data (optional)
		 *	any existing data that has been supplied for this field instance.
		 *	this is encoded as an array of columns, each column maps to an
		 *	array of row indexes to the contents of that column. this defaults
		 *	to null.
		 * @param mixed $flagWithError (optional)
		 *	flag with error? defaults to null.
		 * @param string $fieldnamePrefix (optional)
		 *	the string to be prepended to the display of the name of this field.
		 *	this defaults to null.
		 * @param string $fieldnameSuffix (optional)
		 *	the string to be appended to the display of the name of this field.
		 *	this defaults to null.
		 * @param number $entry_id (optional)
		 *	the entry id of this field. this defaults to null.
		 */
		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnameSuffix=NULL, $entry_id = null){
		}

		/**
		 * Test whether this field can be prepoulated with data. This default
		 * implementation does not support pre-population and, thus, returns false.
		 *
		 * @return boolean
		 *	true if this can be pre-populated, false otherwise.
		 */
		public function canPrePopulate(){
			return false;
		}

		/**
		 * Append the formatted xml output of this field as utilized as a data source.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the xml representation of this to.
		 * @param array $data
		 *	the current set of values for this field. the values are structured as
		 *	for displayPublishPanel.
		 * @param boolean $encode (optional)
		 *	flag as to whether this should be html encoded prior to output. this
		 *	defaults to false.
		 * @param mixed $mode
		 *	??.defaults to null.
		 * @param number $entry_id (optional)
		 *	the identifier of this field entry instance. defaults to null.
		 */
		public function appendFormattedElement(&$wrapper, $data, $encode=false, $mode=NULL, $entry_id=NULL) {
			$wrapper->appendChild(new XMLElement($this->get('element_name'), ($encode ? General::sanitize($this->prepareTableValue($data)) : $this->prepareTableValue($data))));
		}

		/**
		 * Accessor to the parameter pool value of this. By default this returns
		 * the prepared table value of this.
		 *
		 * @param array $data
		 *	??
		 * @return string
		 *	??
		 */
		public function getParameterPoolValue($data){
			return $this->prepareTableValue($data);
		}

		/**
		 * Clean the input value using html entity encode and database specific
		 * clean methods.
		 *
		 * @param mixed $value
		 *	the value to clean.
		 * @return string
		 *	the cleaned value.
		 */
		public function cleanValue($value) {
			return html_entity_decode($this->Database->cleanValue($value));
		}

		/**
		 * Check the fields for validity populating the input error array with
		 * any errors discovered.
		 *
		 * @param array $errors
		 *	the array to populate with the errors found.
		 * @param boolean $checkFoeDuplicates (optional)
		 *	if set to true, duplicate field entries will be flagged as errors.
		 *	this defaults to true.
		 * @return number
		 *	returns the status of the checking. if errors has been populated with
		 *	any errors self::__ERROR__, self__OK__ otherwise.
		 */
		public function checkFields(&$errors, $checkForDuplicates = true) {
			$parent_section = $this->get('parent_section');
			$element_name = $this->get('element_name');
			
			//echo $this->get('id'), ': ', $this->get('required'), '<br />';
			
			if (!is_array($errors)) $errors = array();
			
			if ($this->get('label') == '') {
				$errors['label'] = __('This is a required field.');
			}
			
			if ($this->get('element_name') == '') {
				$errors['element_name'] = __('This is a required field.');
				
			} elseif (!preg_match('/^[A-z]([\w\d-_\.]+)?$/i', $this->get('element_name'))) {
				$errors['element_name'] = __('Invalid element name. Must be valid QName.');
				
			} elseif($checkForDuplicates) {
				$sql_id = ($this->get('id') ? " AND f.id != '".$this->get('id')."' " : '');
				$sql = "
					SELECT
						f.*
					FROM
						`tbl_fields` AS f
					WHERE
						f.element_name = '{$element_name}'
						{$sql_id} 
						AND f.parent_section = '{$parent_section}'
					LIMIT 1
				";
				
				if ($this->Database->fetchRow(0, $sql)) {
					$errors['element_name'] = __('A field with that element name already exists. Please choose another.');
				}
			}
			
			return (is_array($errors) && !empty($errors) ? self::__ERROR__ : self::__OK__);
		}

		/**
		 * Accessor to the default field values? This default implementation does
		 * nothing.
		 *
		 * @param array $fields
		 *	the array of fields to populate with their defaults.
		 */
		public function findDefaults(&$fields){
		}

		/**
		 * Test whether this field can be sorted. This default implementation
		 * returns false.
		 *
		 * @return boolean
		 *	true if this field is sortable, false otherwise.
		 */
		public function isSortable(){
			return false;
		}

		/**
		 * Test whether this field requires grouping. This default implementation
		 * returns false.
		 *
		 * @return boolean
		 *	true if this field requires grouping, false otherwise.
		 */
		public function requiresSQLGrouping(){
			return false;
		}

		/**
		 * Build the SQL command to append to the default query to enable
		 * sorting of this field. By default this will sort the results by
		 * the entry id in ascending order.
		 *
		 * @param string $joins
		 *	the join element of the query to append the custom join sql to.
		 * @param string $where
		 *	the where condition of the query to append to the existing where clause.
		 * @param string $sort
		 *	the existing sort component of the sql query to append the custom
		 *	sort sql code to.
		 * @param string $order (optional)
		 *	an optional sorting direction. this defaults to ascending. if this
		 *	is declared either 'random' or 'rand' then a random sort is applied.
		 */
		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`value` $order");
		}

		/**
		 * Test whether the input string is a regex filter.
		 *
		 * @param string $string
		 *	the string to test.
		 * @return boolean
		 *	true if the string is prefixed with 'regexp:', false otherwise.
		 */
		protected static function isFilterRegex($string){
			if(preg_match('/^regexp:/i', $string)) return true;				
		}

		/**
		 * Construct the SQL statement fragments to use to retrieve the data of this
		 * field when utilized as a data source.
		 *
		 * @param array $data
		 *	the supplied form data to use to construct the query from??
		 * @param string $joins
		 *	the join sql statement fragment to append the additional join sql to.
		 * @param string $where
		 *	the where condition sql statement fragment to which the additional
		 *	where conditions will be appended.
		 * @param boolean $andOperation (optional)
		 *	true if the values of the input data should be appended as part of
		 *	the where condition. this defaults to false.
		 * @return boolean
		 *	true if the construction of the sql was successful, false otherwise.
		 */
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			
			if (self::isFilterRegex($data[0])) {
				$this->_key++;
				$pattern = str_replace('regexp:', '', $this->cleanValue($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.value REGEXP '{$pattern}'
				";
				
			} elseif ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{$this->_key}.value = '{$value}'
					";
				}
				
			} else {
				if (!is_array($data)) $data = array($data);
				
				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}
				
				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.value IN ('{$data}')
				";
			}
			
			return true;
		}

		/**
		 * Check the field data that has been posted from a form. This will set the
		 * input message to the error message or to null if there is none. Any existing
		 * message value will be overwritten.
		 *
		 * @param array $data
		 *	the input data to check.
		 * @param string $message
		 *	the place to set any generated error message. any previous value for
		 *	this variable will be overwritten.
		 * @param number $entry_id (optional)
		 *	the optional id of this field entry instance. this defaults to null.
		 * @return number
		 *	self::__MISSING_FIELDS__ if there are any missing required fields,
		 *	self::__OK__ otherwise.
		 */
		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;
			
			if ($this->get('required') == 'yes' && strlen($data) == 0){
				$message = __("'%s' is a required field.", array($this->get('label')));
				
				return self::__MISSING_FIELDS__;
			}
			
			return self::__OK__;		
		}
		
		/**
		 * Process the raw field data.
		 *
		 * @param mixed $data
		 *	post data from the entry form
		 * @param reference $status
		 *	the status code resultant from processing the data.
		 * @param boolean $simulate (optional)
		 *	true if this will tell the CF's to simulate data creation, false
		 *	otherwise. this defaults to false. this is important if clients
		 *	will be deleting or adding data outside of the main entry object
		 *	commit function.
		 * @param mixed $entry_id (optional)
		 *	the current entry. defaults to null.
		 * @return array[string]mixed
		 *	the processed field data.
		 */
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=null) {	
			
			$status = self::__OK__;
			
			return array(
				'value' => $data,
			);
		}

		/**
		 * Format this field value for display in the administration pages summary tables.
		 *
		 * @param array $data
		 *	the data to use to generate the summary string.
		 * @param XMLElement $link (optional)
		 *	an xml link structure to append the content of this to provided it is not
		 *	null. it defaults to null.
		 * @return string
		 *	the formatted string summary of the values of this field instance.
		 */
		public function prepareTableValue($data, XMLElement $link=NULL) {
			$max_length = Symphony::Configuration()->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);
			
			$value = strip_tags($data['value']);
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');
			
			if (strlen($value) == 0) $value = __('None');
			
			if ($link) {
				$link->setValue($value);
				
				return $link->generate();
			}
			
			return $value;
		}

		/**
		 * The default method for constructing the example form markup containing this
		 * field when utilized as part of an event.
		 *
		 * @return Widget
		 *	a label widget containing the formatted field element name of this.
		 */
		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').']'));
			
			return $label;
		}

		/**
		 * Default accessor for the includable elements of this field.
		 *
		 * @return array
		 *	the array of includable elements from this field.
		 */
		public function fetchIncludableElements(){
			return array($this->get('element_name'));
		}
		
		/**
		 * Accessor to the associated entry search value for this field
		 * instance. This default implementation simply returns the input
		 * data argument.
		 *
		 * @param array $data
		 *	the data from which to construct the associated search entry value.
		 * @param number $field_id (optional)
		 *	an optional id of the associated field? this defaults to null.
		 * @param number $parent_entry_id (optional)
		 *	an optional parent identifier of the associated field entry? this defaults
		 *	to null.
		 * @return array
		 *	the associated entry search value. this implementation returns the input
		 *	data argument.
		 */
		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			return $data;
		}

		/**
		 * Fetch the count of the associate entries for the input value. This default
		 * implementation does nothing.
		 *
		 * @param mixed $value
		 *	the value to find the associated entry count for.
		 * @return void|number
		 *	this default implementation returns void. overriding implementations should
		 *	return a number.
		 */
		public function fetchAssociatedEntryCount($value){
		}

		/**
		 * Accessor to the ids associated with this field instance.
		 *
		 * @param mixed $value
		 *	the value to find the associated entry ids for.
		 * @return void|array
		 *	this default implementation returns void. overriding implementations should
		 *	return an array of the associated entry ids.
		 */
		function fetchAssociatedEntryIDs($value){
		}

		/**
		 * Display the default data-source filter panel.
		 *
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed $data (optional)
		 *	the input data. this defaults to null.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 * @param string $fieldNamePrefix
		 *	the prefix to apply to the display of this.
		 * @param string $fieldNameSuffix
		 *	the suffix to apply to the display of this.
		 */
		public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));		
			$wrapper->appendChild($label);	
		}
		
		/**
		 * Display the default import panel.
		 *
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed $data (optional)
		 *	the input data. this defaults to null.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 * @param string $fieldNamePrefix
		 *	the prefix to apply to the display of this.
		 * @param string $fieldNameSuffix
		 *	the suffix to apply to the display of this.
		 */
		public function displayImportPanel(&$wrapper, $data = null, $errors = null, $fieldnamePrefix = null, $fieldnamePostfix = null) {
			$this->displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);	
		}
		
		/**
		 * Display the default settings panel.
		 *
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 */
		public function displaySettingsPanel(&$wrapper, $errors=NULL){		
			$wrapper->appendChild(new XMLElement('h4', ucwords($this->name())));
			$wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][type]', $this->handle(), 'hidden'));
			if($this->get('id')) $wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][id]', $this->get('id'), 'hidden'));
			
			$wrapper->appendChild($this->buildSummaryBlock($errors));			

		}

		/**
		 * Construct the html block to display a summary of this. Any error messages
		 * generated are appended to the optional input error array.
		 *
		 * @param array $errors (optional)
		 *	an array to append html formatted error messages to. this defaults to null.
		 * @return XMLElement
		 *	the root xml element of the html display of this.
		 */
		public function buildSummaryBlock($errors=NULL){

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Label'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][label]', $this->get('label')));
			if(isset($errors['label'])) $div->appendChild(Widget::wrapFormElementWithError($label, $errors['label']));
			else $div->appendChild($label);		
			
			$div->appendChild($this->buildLocationSelect($this->get('location'), 'fields['.$this->get('sortorder').'][location]'));

			return $div;
			
		}

		/**
		 * Append and set a labelled html checkbox to the input xml element if this
		 * field is set as a required field.
		 *
		 * @param XMLElement $wrapper
		 *	the parent xml element to append the constructed html checkbox to if
		 *	necessary.
		 */
		public function appendRequiredCheckbox(&$wrapper) {
			if (!$this->_required) return;
			
			$order = $this->get('sortorder');
			$name = "fields[{$order}][required]";
			
			$wrapper->appendChild(Widget::Input($name, 'no', 'hidden'));
			
			$label = Widget::Label();
			$input = Widget::Input($name, 'yes', 'checkbox');
			
			if ($this->get('required') == 'yes') $input->setAttribute('checked', 'checked');
			
			$label->setValue(__('%s Make this a required field', array($input->generate())));
			
			$wrapper->appendChild($label);
		}

		/**
		 * Append the show column html widget to the input parent xml element.
		 *
		 * @param XMLElement $wrapper
		 *	the parent xml element to append the checkbox to.
		 */
		public function appendShowColumnCheckbox(&$wrapper) {
			if (!$this->_showcolumn) return;
			
			$order = $this->get('sortorder');
			$name = "fields[{$order}][show_column]";
			
			$wrapper->appendChild(Widget::Input($name, 'no', 'hidden'));
			
			$label = Widget::Label();
			$label->setAttribute('class', 'meta');
			$input = Widget::Input($name, 'yes', 'checkbox');
			
			if ($this->get('show_column') == 'yes') $input->setAttribute('checked', 'checked');
			
			$label->setValue(__('%s Show column', array($input->generate())));
			
			$wrapper->appendChild($label);
		}

		/**
		 * Build the location select widget. This widget allows users to select
		 * whether this field will appear as main content or in the sidebar.
		 *
		 * @param string $selection (optional)
		 *	the currently selected location, if there is one. this defaults to null.
		 * @param string $name (optional)
		 *	the name of this field. this is optional and defaults to "fields[location]".
		 * @param string $label_value (optional)
		 *	any predefined label for this widget. this is an optional argument that defaults
		 *	to null.
		 * @return Widget
		 *	the constructed location select html.
		 */
		public function buildLocationSelect($selected = null, $name = 'fields[location]', $label_value = null) {
			if (!$label_value) $label_value = __('Placement');
			
			$label = Widget::Label($label_value);
			$options = array(
				array('main', $selected == 'main', __('Main content')),
				array('sidebar', $selected == 'sidebar', __('Sidebar'))				
			);
			$label->appendChild(Widget::Select($name, $options));
			
			return $label;
		}

		/**
		 * Construct the html widget for selecting a text formatter for this field.
		 *
		 * @param string $selected (optional)
		 *	the currently selected text formatter name if there is one. this defaults
		 *	to null.
		 * @param string $name (optional)
		 *	the name of this field in the form. this is optional and defaults to
		 *	"fields[format]".
		 * @param string $label_value
		 *	the default label for the widget to construct. if null is passed in then
		 *	this defaults to the localization of "Formatting".
		 * @return Widget
		 *	the constructed html representation of the text formatter selector.
		 */
		public function buildFormatterSelect($selected=NULL, $name='fields[format]', $label_value){
			
			include_once(TOOLKIT . '/class.textformattermanager.php');
			
			$TFM = new TextformatterManager($this->_engine);
			$formatters = $TFM->listAll();
					
			if(!$label_value) $label_value = __('Formatting');
			$label = Widget::Label($label_value);
		
			$options = array();
		
			$options[] = array('none', false, __('None'));
		
			if(!empty($formatters) && is_array($formatters)){
				foreach($formatters as $handle => $about) {
					$options[] = array($handle, ($selected == $handle), $about['name']);
				}	
			}
		
			$label->appendChild(Widget::Select($name, $options));
			
			return $label;			
		}

		/**
		 * Append a constructed html representation of a validator selection form
		 * fragment.to the input xml element.
		 *
		 * @param XMLElement $wrapper
		 *	the parent element to append the constructed validator selector to.
		 * @param string $selection (optional)
		 *	the current validator selection if there is one. defaults to null if there
		 *	isn't.
		 * @param string $name (optional)
		 *	the form element name of this field. this defaults to "fields[validator]".
		 * @param string $type (optional)
		 *	the type of input for the validation to apply to. this defaults to 'input'
		 *	but also accepts 'upload'.
		 */
		public function buildValidationSelect(&$wrapper, $selected=NULL, $name='fields[validator]', $type='input'){

			include(TOOLKIT . '/util.validators.php');
			$rules = ($type == 'upload' ? $upload : $validators);

			$label = Widget::Label(__('Validation Rule <i>Optional</i>'));
			$label->appendChild(Widget::Input($name, $selected));
			$wrapper->appendChild($label);
			
			$ul = new XMLElement('ul', NULL, array('class' => 'tags singular'));
			foreach($rules as $name => $rule) $ul->appendChild(new XMLElement('li', $name, array('class' => $rule)));
			$wrapper->appendChild($ul);
			
		}

		/**
		 * Default implementation of record grouping. This default implementation
		 * will trigger an error if used. Thus, clients must overload this method
		 * for grouping to be successful.
		 *
		 * @param array $records
		 *	the records to group.
		 */
		public function groupRecords($records){			
			trigger_error(__('Data source output grouping is not supported by the <code>%s</code> field', array($this->get('label'))), E_USER_ERROR);		
		}

		/**
		 * Commit the settings form data to create an instance of this field in a
		 * section.
		 *
		 * @return boolean
		 *	true if the commit was successful, false otherwise.
		 */
		public function commit(){
			
			$fields = array();

			$fields['element_name'] = Lang::createHandle($this->get('label'));
			if(is_numeric($fields['element_name']{0})) $fields['element_name'] = 'field-' . $fields['element_name'];
			
			$fields['label'] = $this->get('label');
			$fields['parent_section'] = $this->get('parent_section');
			$fields['location'] = $this->get('location');
			$fields['required'] = $this->get('required');
			$fields['type'] = $this->_handle;
			$fields['show_column'] = $this->get('show_column');
			$fields['sortorder'] = (string)$this->get('sortorder');
			
			if($id = $this->get('id')){
				return $this->_Parent->edit($id, $fields);				
			}
			
			elseif($id = $this->_Parent->add($fields)){
				$this->set('id', $id);
				$this->createTable();
				return true;
			}
			
			return false;
			
		}

		/**
		 * The default field table construction method. This constructs the bare
		 * minimum set of columns for a valid field table. Subclasses are expected
		 * to overload this method to create a table structure that contains
		 * additional columns to store the specific data created by the field.
		 */
		public function createTable(){
			
			return $this->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) TYPE=MyISAM;"
			
			);
		}

	}

