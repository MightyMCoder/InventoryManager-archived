<?php
/**
 ***********************************************************************************************
 * Class to manage the items of the InventoryManager plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * methods:
 * 
 * __construct($database, $organizationId)                      : Constructor that will initialize variables and read the item field structure
 * setDatabase($database)                                       : Set the database object for communication with the database of this class
 * __sleep()                                                    : Called on serialization of this object. The database object could not be serialized and should be ignored
 * clearItemData()                                              : Item data of all item fields will be initialized
 * getProperty($fieldNameIntern, $column, $format = '')         : Returns the value of a column from the table adm_inventory_manager_fields for a given internal field name
 * getPropertyById($fieldId, $column, $format = '')             : Returns the value of a column from the table adm_inventory_manager_fields for a given field ID
 * getListValue($fieldNameIntern, $value, $format)              : Returns the list values for a given field name intern (imf_name_intern)
 * getHtmlValue($fieldNameIntern, $value,)                      : Returns the value of the field in html format with consideration of all layout parameters
 * getValue($fieldNameIntern, $format = '')                     : Returns the item value for this column
 * showFormerItems($newValue = null)                            : This method reads or stores the variable for showing former items
 * isNewItem()                                                  : If the recordset is new and wasn't read from database or was not stored in database then this method will return true otherwise false
 * isDeletedItem()                                              : If the recordset was deleted from database then this method will return true otherwise false
 * readItemData($itemId, $organizationId)                       : Reads the item data of all item fields out of database table adm_inventory_manager_data
 * saveItemData()                                               : Save data of every item field
 * readItemFields($organizationId)                              : Reads the item fields structure out of database table adm_inventory_manager_fields
 * readItems($organizationId)                                   : Reads the items out of database table adm_inventory_manager_items
 * readItemsByUser($organizationId, $userId)                    : Reads the items for a user out of database table adm_inventory_manager_items
 * setValue($fieldNameIntern, $newValue)                        : Set a new value for the item field of the table adm_inventory_manager_data
 * getNewItemId($organizationId)                                : Generates a new ItemId. The new value will be stored in mItemId
 * deleteItem($itemId, $organizationId)                         : Deletes an item from the database
 * makeItemFormer($itemId, $organizationId)                     : Marks an item as former in the database
 * sendNotification($organizationId)                            : Sends a notification to all users that have the right to see the item
 * 
 ***********************************************************************************************
 */

class CItems
{
	public $mItemFields         = array();  ///< Array with all item fields objects
    public $mChangedItemData    = array();  ///< Array with all changed item data objects for notification
	public $mItemData           = array();  ///< Array with all item data objects
	public $items               = array();  ///< Array with all item objects

    private $mItemId;                       ///< ItemId of the current item of this object
    private $mDb;                           ///< An object of the class Database for communication with the database
    private $itemCreated;                   ///< flag if a new item was created
    private $itemChanged;                   ///< flag if a new item was changed
    private $itemDeleted;                   ///< flag if a item was deleted
    private $itemMadeFormer;                ///< flag if a item was made to former item
    private $showFormerItems;               ///< if true, than former items will be showed
    private $organizationId;                ///< ID of the organization for which the item field structure should be read
    public $columnsValueChanged;            ///< flag if a value of one field had changed

    private $itemFieldsSort = array();
   
    /**
     * Constructor that will initialize variables and read the item field structure
     * @param \Database $database       Database object (should be @b $gDb)
     * @param int       $organizationId The id of the organization for which the item field structure should be read
     */
    public function __construct($database, $organizationId)
    {
        $this->organizationId = $organizationId;
        $this->setDatabase($database);
        $this->readItemFields($this->organizationId);
        $this->mItemId = 0;
        $this->columnsValueChanged = false;
        $this->itemCreated = false;
        $this->itemChanged = false;
        $this->itemDeleted = false;
        $this->itemMadeFormer = false;
        $this->showFormerItems = true;
    }

    /**
     * Set the database object for communication with the database of this class.
     * @param \Database $database       An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase($database)
    {
        $this->mDb = $database;
    }

    /**
     * Called on serialization of this object. The database object could not
     * be serialized and should be ignored.
     * @return string[]                 Returns all class variables that should be serialized.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('mDb'));
    }
      
    /**
     * Item data of all item fields will be initialized
     * the fields array will not be renewed
     */
    public function clearItemData()
    {
        $this->mChangedItemData = array();
        $this->mItemData = array();
        $this->mItemId = 0;
        $this->columnsValueChanged = false;
    }

    /**
     * Retrieves the ID of the item.
     *
     * @return int The ID of the item.
     */
    public function getItemId()
    {
        return $this->mItemId;
    }

    /**
     * Returns the value of a column from the table adm_inventory_manager_fields for a given internal field name.
     * @param string $fieldNameIntern   Expects the @b imf_name_intern of table @b adm_inventory_manager_fields
     * @param string $column            The column name of @b adm_inventory_manager_fields for which you want the value
     * @param string $format            Optional the format (is necessary for timestamps)
     * @return array|string             Returns the value for the column.
     */
    public function getProperty($fieldNameIntern, $column, $format = '')
    {
        if (!array_key_exists($fieldNameIntern, $this->mItemFields)) {
            // if id-field not exists then return zero
            return (strpos($column, '_id') > 0) ? 0 : '';
        }

        $value = $this->mItemFields[$fieldNameIntern]->getValue($column, $format);

        if ($column === 'imf_value_list' && in_array($this->mItemFields[$fieldNameIntern]->getValue('imf_type'), ['DROPDOWN', 'RADIO_BUTTON'])) {
            $value = $this->getListValue($fieldNameIntern, $value, $format);
        }

        return $value;
    }

    /**
     * Returns the value of a column from the table adm_inventory_manager_fields for a given field ID
     * @param int    $fieldId           Expects the @b imf_id of table @b adm_inventory_manager_fields
     * @param string $column            The column name of @b adm_inventory_manager_fields for which you want the value
     * @param string $format            Optional the format (is necessary for timestamps)
     * @return string                   Returns the value for the column.
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach ($this->mItemFields as $field) {
            if ((int) $field->getValue('imf_id') === (int) $fieldId) {
                return $field->getValue($column, $format);
            }
        }

        return '';
    }

    /**
     * Returns the list values for a given field name intern (imf_name_intern).
     * @param string $fieldNameIntern   Expects the @b imf_name_intern of table @b adm_inventory_manager_fields
     * @param string $value             The value to be formatted
     * @param string $format            Optional the format (is necessary for timestamps)
     * @return array                    Returns an array with the list values for the given field name intern.
     */
    protected function getListValue($fieldNameIntern, $value, $format)
    {
        $arrListValuesWithItems = array(); // array with list values and items that represents the internal value

        // first replace windows new line with unix new line and then create an array
        $valueFormatted = str_replace("\r\n", "\n", $value);
        $arrListValues = explode("\n", $valueFormatted);

        foreach ($arrListValues as $item => &$listValue) {
            if ($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'RADIO_BUTTON') {
                // if value is imagefile or imageurl then show image
                if (strpos(strtolower($listValue), '.png') > 0 || strpos(strtolower($listValue), '.jpg') > 0) {
                    // if value is imagefile or imageurl then show image
                    if (Image::isFontAwesomeIcon($listValue)
                        || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false)) {
                        // if there is imagefile and text separated by | then explode them
                        if (StringUtils::strContains($listValue, '|')) {
                            list($listValueImage, $listValueText) = explode('|', $listValue);
                        }
                        else {
                            $listValueImage = $listValue;
                            $listValueText = $this->getValue('usf_name');
                        }

                        // if text is a translation-id then translate it
                        $listValueText = Language::translateIfTranslationStrId($listValueText);

                        if ($format === 'text') {
                            // if no image is wanted then return the text part or only the position of the entry
                            if (StringUtils::strContains($listValue, '|')) {
                                $listValue = $listValueText;
                            }
                            else {
                                $listValue = $item + 1;
                            }
                        }
                        else {
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }
                    }
                }
            }

            // if text is a translation-id then translate it
            $listValue = Language::translateIfTranslationStrId($listValue);

            // save values in new array that starts with item = 1
            $arrListValuesWithItems[++$item] = $listValue;
        }
        unset($listValue);
        return $arrListValuesWithItems;
    }

    /**
     * Returns the value of the field in html format with consideration of all layout parameters
     * @param string $fieldNameIntern   Internal item field name of the field that should be html formatted
     * @param string|int $value         The value that should be formatted must be committed so that layout
     *                                  is also possible for values that aren't stored in database
     * @return string                   Returns an html formatted string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value)
    {
        global $gSettingsManager;

        if (!array_key_exists($fieldNameIntern, $this->mItemFields)) {
            return $value;
        }

        // if value is empty or null, then do nothing
        if ($value != '') {
            // create html for each field type
            $htmlValue = $value;

            $imfType = $this->mItemFields[$fieldNameIntern]->getValue('imf_type');
            switch ($imfType) {
                case 'CHECKBOX':
                    $htmlValue = $value == 1 ? '<i class="fas fa-check-square"></i>' : '<i class="fas fa-square"></i>';
                    break;
                case 'DATE':
                    if ($value !== '') {
                        // date must be formatted
                        $date = \DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof \DateTime) {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    $arrListValuesWithItems = array(); // array with list values and items that represents the internal value

                    // first replace windows new line with unix new line and then create an array
                    $valueFormatted = str_replace("\r\n", "\n", $this->mItemFields[$fieldNameIntern]->getValue('imf_value_list', 'database'));
                    $arrListValues = explode("\n", $valueFormatted);

                    foreach ($arrListValues as $index => $listValue) {
                        // if value is imagefile or imageurl then show image
                        if ($imfType === 'RADIO_BUTTON' && (Image::isFontAwesomeIcon($listValue)
                            || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false))) {
                            // if there is imagefile and text separated by | then explode them
                            if (StringUtils::strContains($listValue, '|')) {
                                list($listValueImage, $listValueText) = explode('|', $listValue);
                            }
                            else {
                                $listValueImage = $listValue;
                                $listValueText = $this->getValue('imf_name');
                            }

                            // if text is a translation-id then translate it
                            $listValueText = Language::translateIfTranslationStrId($listValueText);

                            // get html snippet with image tag
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }

                        // if text is a translation-id then translate it
                        $listValue = Language::translateIfTranslationStrId($listValue);

                        // save values in new array that starts with item = 1
                        $arrListValuesWithItems[++$index] = $listValue;
                    }

                    $htmlValue = $arrListValuesWithItems[$value];
                    break;
                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
            }

            $value = $htmlValue;
        }
        else {
            // special case for type CHECKBOX and no value is there, then show unchecked checkbox
            if ($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'CHECKBOX') {
                $value = '<i class="fas fa-square"></i>';
            }
        }

        return $value;
    }

    /**
     * Returns the item value for this column
     * format = 'html'  :               returns the value in html-format if this is necessary for that field type
     * format = 'database' :            returns the value that is stored in database with no format applied
     * @param string $fieldNameIntern   Expects the @b imf_name_intern of table @b adm_inventory_manager_fields
     * @param string $format            Returns the field value in a special format @b text, @b html, @b database
     *                                  or datetime (detailed description in method description)
     * @return string|int|bool          Returns the value for the column.
     */
    public function getValue($fieldNameIntern, $format = '')
    {
        global $gSettingsManager;
        $value = '';

        // exists a item field with that name ?
        // then check if item has a data object for this field and then read value of this object
        if (array_key_exists($fieldNameIntern, $this->mItemFields)
            && array_key_exists($this->mItemFields[$fieldNameIntern]->getValue('imf_id'), $this->mItemData)) {
            $value = $this->mItemData[$this->mItemFields[$fieldNameIntern]->getValue('imf_id')]->getValue('imd_value', $format);

            if ($format === 'database') {
                return $value;
            }

            switch ($this->mItemFields[$fieldNameIntern]->getValue('imf_type')) {
                case 'DATE':
                    if ($value !== '') {
                        // if date field then the current date format must be used
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date === false) {
                            return $value;
                        }

                        // if no format or html is set then show date format from Admidio settings
                        if ($format === '' || $format === 'html') {
                            $value = $date->format($gSettingsManager->getString('system_date'));
                        } else {
                            $value = $date->format($format);
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    // the value in db is only the position, now search for the text
                    if ($value > 0 && $format !== 'html') {
                        $valueList = $this->mItemFields[$fieldNameIntern]->getValue('imf_value_list', $format);
                        $arrListValues = $this->getListValue($fieldNameIntern, $valueList, $format);
                        $value = $arrListValues[$value];
                    }
                    break;
            }
        }

        // get html output for that field type and value
        if ($format === 'html') {
            $value = $this->getHtmlValue($fieldNameIntern, $value);
        }

        return $value;
    }

    /**
     * This method reads or stores the variable for showing former items.
     * The values will be stored in database without any inspections!
     * @param bool|null $newValue       If set, then the new value will be stored in @b showFormerItems.
     * @return bool                     Returns the current value of @b showFormerItems
     */
    public function showFormerItems($newValue = null)
    {
        if ($newValue !== null) {
            $this->showFormerItems = $newValue;
        }
        return $this->showFormerItems;
    }

    /**
     * If the recordset is new and wasn't read from database or was not stored in database
     * then this method will return true otherwise false
     * @return bool                     Returns @b true if record is not stored in database
     */
    public function isNewItem()
    {
        return $this->itemCreated;
    }

    /**
     * If the recordset was deleted from database then this method will return true otherwise false
     * @return bool                     Returns @b true if record is removed from databaseIf the recordset was deleted from database then this method will return true otherwise false
     */
    public function isDeletedItem()
    {
        return $this->itemDeleted;
    }

    /**
     * Reads the item data of all item fields out of database table @b adm_inventory_manager_data
     * and adds an object for each field data to the @b mItemData array.
     * If profile fields structure wasn't read, this will be done before.
     * @param int $itemId               The id of the item for which the item data should be read.
     * @param int $organizationId       The id of the organization for which the item fields
     *                                  structure should be read if necessary.
     */
    public function readItemData($itemId, $organizationId)
    {                                    
        if (count($this->mItemFields) === 0) {
            $this->readItemFields($organizationId);
        }

        $this->mItemData = array();

        if ($itemId > 0) {
            // remember the item
            $this->mItemId = $itemId;

            // read all item data
            $sql = 'SELECT * FROM '.TBL_INVENTORY_MANAGER_DATA.'
                    INNER JOIN '.TBL_INVENTORY_MANAGER_FIELDS.'
                        ON imf_id = imd_imf_id
                    WHERE imd_imi_id = ?;';
            $itemDataStatement = $this->mDb->queryPrepared($sql, array($itemId));

            while ($row = $itemDataStatement->fetch()) {
                if (!array_key_exists($row['imd_imf_id'], $this->mItemData)) {
                    $this->mItemData[$row['imd_imf_id']] = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_DATA, 'imd');
                }
                $this->mItemData[$row['imd_imf_id']]->setArray($row);
            }
        }
        else {
            $this->itemCreated = true;
        }
    }

    /**
     * Save data of every item field
     */
    public function saveItemData()
    {
        $this->mDb->startTransaction();

        foreach ($this->mItemData as $value) {
            if ($value->hasColumnsValueChanged()) {
                $this->columnsValueChanged = true;
                $this->itemChanged = true;
            }

            // if value exists and new value is empty then delete entry
            if ($value->getValue('imd_id') > 0 && $value->getValue('imd_value') === '') {
                $value->delete();
            }
            else {
                $value->save();
            }
        }

        // for updateFingerPrint a change in db must be executed
        // why !$this->itemCreated -> updateFingerPrint will be done in getNewItemId
        if (!$this->itemCreated && $this->columnsValueChanged) {
            $updateItem = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi', $this->mItemId);
            $updateItem->setValue('imi_usr_id_change', null, false);
            $updateItem->save();
        }

        $this->columnsValueChanged = false;
        $this->readItemData($this->mItemId, $this->organizationId);
        $this->mDb->endTransaction();
    }

    /**
     * Reads the item fields structure out of database table @b adm_inventory_manager_fields
     * and adds an object for each field structure to the @b mItemFields array.
     * @param int $organizationId       The id of the organization for which the item fields
     *                                  structure should be read.
     */
    public function readItemFields($organizationId)
    {
        // first initialize existing data
        $this->mItemFields = array();
        $this->clearItemData();

        $sql = 'SELECT * FROM '.TBL_INVENTORY_MANAGER_FIELDS.'
                WHERE imf_org_id IS NULL
                OR imf_org_id = ?;';
        $statement = $this->mDb->queryPrepared($sql, array($organizationId));

        while ($row = $statement->fetch()) {
            if (!array_key_exists($row['imf_name_intern'], $this->mItemFields)) {
                $this->mItemFields[$row['imf_name_intern']] = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');
            }
            $this->mItemFields[$row['imf_name_intern']]->setArray($row);
            $this->itemFieldsSort[$row['imf_name_intern']] = $row['imf_sequence'];
        }

        array_multisort($this->itemFieldsSort, SORT_ASC, $this->mItemFields);
    }

    /**
     * Reads the items out of database table @b adm_inventory_manager_items
     * and stores the values to the @b items array.
     * @param int $organizationId       The id of the organization for which the items should be read.
     */
    public function readItems($organizationId)
    {
        // first initialize existing data
        $this->items = array();

        $sqlWhereCondition = '';
        if (!$this->showFormerItems) {
            $sqlWhereCondition .= 'AND imi_former = 0';
        }

        $sql = 'SELECT DISTINCT imi_id, imi_former FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
                INNER JOIN '.TBL_INVENTORY_MANAGER_DATA.'
                    ON imd_imi_id = imi_id
                WHERE imi_org_id IS NULL
                OR imi_org_id = ?
                '.$sqlWhereCondition.';';
        $statement = $this->mDb->queryPrepared($sql, array($organizationId));

        while ($row = $statement->fetch()) {
            $this->items[] = array('imi_id' => $row['imi_id'], 'imi_former' => $row['imi_former']);
        }
    }

    /**
     * Reads the items for a user out of database table @b adm_inventory_manager_items
     * and stores the values to the @b items array.
     * @param int $organizationId       The id of the organization for which the items should be read.
     * @param int $userId               The id of the user for which the items should be read.
     */
    public function readItemsByUser($organizationId, $userId)
    {
        // first initialize existing data
        $this->items = array();

        $sqlWhereCondition = '';
        if (!$this->showFormerItems) {
            $sqlWhereCondition .= 'AND imi_former = 0';
        }

        $sql = 'SELECT DISTINCT imi_id, imi_former FROM '.TBL_INVENTORY_MANAGER_DATA.'
                INNER JOIN '.TBL_INVENTORY_MANAGER_FIELDS.'
                    ON imf_id = imd_imf_id
                    AND imf_id = ?
                INNER JOIN '.TBL_INVENTORY_MANAGER_ITEMS.'
                    ON imi_id = imd_imi_id
                WHERE (imi_org_id IS NULL
                    OR imi_org_id = ?)
                AND imd_value = ?
                '.$sqlWhereCondition.';';
        $statement = $this->mDb->queryPrepared($sql, array($this->getProperty('KEEPER', 'imf_id'), $organizationId, $userId));

        while ($row = $statement->fetch()) {
            $this->items[] = array('imi_id' => $row['imi_id'], 'imi_former' => $row['imi_former']);
        }
    }

    /**
     * Set a new value for the item field of the table adm_inventory_manager_data.
     * If the user log is activated then the change of the value will be logged in @b adm_inventory_manager_log.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $fieldNameIntern   The internal unique profile field name
     * @param mixed $newValue           The new value that should be stored in the database field
     * @return bool                     Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($fieldNameIntern, $newValue)
    {
        global $gSettingsManager;

        $imfId = $this->mItemFields[$fieldNameIntern]->getValue('imf_id');

        if (!array_key_exists($imfId, $this->mItemData)) {
            $oldFieldValue = '';
        }
        else {
            $oldFieldValue = $this->mItemData[$imfId]->getValue('imd_value');
        }

        // item data from adm_inventory_manager_fields table
        $newValue = (string) $newValue;

        // save old and new data for notification
        if (array_key_exists($imfId, $this->mItemData)) {
            $this->mChangedItemData[] = array($this->mItemData[$imfId]->getValue('imf_name') => array('oldValue' => $oldFieldValue, 'newValue' => $newValue));
        }
        else {
            $this->mChangedItemData[] = array($this->mItemFields[$fieldNameIntern]->getValue('imf_name') => array('oldValue' => $oldFieldValue, 'newValue' => $newValue));
        }

        // format of date will be local but database has stored Y-m-d format must be changed for compare
        if ($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'DATE') {
            $date = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $newValue);

            if ($date !== false) {
                $newValue = $date->format('Y-m-d');
            }
        }

        // only do an update if value has changed
        if (strcmp($oldFieldValue, $newValue) === 0) {
            return true;
        }

        $returnCode = false;

        if (!array_key_exists($imfId, $this->mItemData)) {
            $this->mItemData[$imfId] = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_DATA, 'imd');
            $this->mItemData[$imfId]->setValue('imd_imf_id', $imfId);
            $this->mItemData[$imfId]->setValue('imd_imi_id', $this->mItemId);
        }

        $returnCode = $this->mItemData[$imfId]->setValue('imd_value', $newValue);

        if ($returnCode && $gSettingsManager->getBool('profile_log_edit_fields')) {
            $logEntry = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_LOG, 'iml');
            $logEntry->setValue('iml_imi_id', $this->mItemId);
            $logEntry->setValue('iml_imf_id', $imfId);
            $logEntry->setValue('iml_value_old', $oldFieldValue);
            $logEntry->setValue('iml_value_new', $newValue);
            $logEntry->setValue('iml_comment', '');
            $logEntry->save();
        }

        return $returnCode;
    }

    /**
     * Generates a new ItemId. The new value will be stored in @b mItemId.
     * @param int $organizationId       The id of the organization for which the items should be read.
     * @return int @b mItemId
     */
    public function getNewItemId($organizationId)
    {
        // If an error occurred while generating an item, there is an ItemId but no data for that item.
        // the following routine deletes these unused ItemIds
        $sql = 'SELECT * FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
                LEFT JOIN '.TBL_INVENTORY_MANAGER_DATA.'
                    ON imd_imi_id = imi_id
                WHERE imd_imi_id is NULL;';
        $statement = $this->mDb->queryPrepared($sql);

        while ($row = $statement->fetch()) {
            $delItem = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi', $row['imi_id']);
            $delItem->delete();
        }

        // generate a new ItemId
        if ($this->itemCreated) {
            $newItem = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi');
            $newItem->setValue('imi_org_id', $organizationId);
            $newItem->setValue('imi_former', 0);
            $newItem->save();

            $this->mItemId = $newItem->getValue('imi_id');

            // update item table
            $this->readItems($organizationId);

            return $this->mItemId;
        }
    }

    /**
     * delete an item
     * @param int $itemId               The id of the item that should be deleted
     * @param int $organizationId       The id of the organization from which the items should be deleted
     */
    public function deleteItem($itemId, $organizationId)
    {
        $sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_LOG.' WHERE iml_imi_id = ?;';
        $this->mDb->queryPrepared($sql, array($itemId));
    
        $sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_DATA.' WHERE imd_imi_id = ?;';
        $this->mDb->queryPrepared($sql, array($itemId));
    
        $sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_ITEMS.' WHERE imi_id = ? AND (imi_org_id = ? OR imi_org_id IS NULL);';
        $this->mDb->queryPrepared($sql, array($itemId, $organizationId));
        
        $this->itemDeleted = true;
    }

    /**
     * Marks an item as former.
     * @param int $itemId 		    The ID of the item to be marked as former.
     * @param int $organizationId   The id of the organization from which the items should be marked as former
     */
    function makeItemFormer($itemId, $organizationId) {

    	$sql = 'UPDATE '.TBL_INVENTORY_MANAGER_ITEMS.' SET imi_former = 1 WHERE imi_id = ? AND (imi_org_id = ? OR imi_org_id IS NULL);';
        $this->mDb->queryPrepared($sql, array($itemId, $organizationId));

        $this->itemMadeFormer = true;
    }

    /**
     * Send a notification email that a new item was created, changed, deleted, or marked as former
     * to all members of the notification role. This role is configured within the global preference
     * **system_notifications_role**. The email contains the item name, the name of the current user,
     * the timestamp, and the details of the changes.
     * @param int $organizationId       The id of the organization from which the notifications should be sent
     * @return bool                     Returns **true** if the notification was sent
     * @throws AdmException             'SYS_EMAIL_NOT_SEND'
     * @throws Exception
     */
    public function sendNotification($organizationId): bool
    {
        global $gProfileFields, $gCurrentUser, $gSettingsManager, $gL10n;

        // check if notifications are enabled
        if ($gSettingsManager->getBool('system_notifications_new_entries')) {
            $notification = new Email();

            if ($this->itemCreated) {
                $messageTitleText = 'PLG_INVENTORY_MANAGER_NOTIFICATION_SUBJECT_ITEM_CREATED';
                $messageHead = 'PLG_INVENTORY_MANAGER_NOTIFICATION_MESSAGE_ITEM_CREATED';
            }elseif ($this->itemDeleted) {
                $messageTitleText = 'PLG_INVENTORY_MANAGER_NOTIFICATION_SUBJECT_ITEM_DELETED';
                $messageHead = 'PLG_INVENTORY_MANAGER_NOTIFICATION_MESSAGE_ITEM_DELETED';
            }elseif ($this->itemMadeFormer) {
                $messageTitleText = 'PLG_INVENTORY_MANAGER_NOTIFICATION_SUBJECT_ITEM_MADE_FORMER';
                $messageHead = 'PLG_INVENTORY_MANAGER_NOTIFICATION_MESSAGE_ITEM_MADE_FORMER';
            } elseif ($this->itemChanged) {
                $messageTitleText = 'PLG_INVENTORY_MANAGER_NOTIFICATION_SUBJECT_ITEM_CHANGED';
                $messageHead = 'PLG_INVENTORY_MANAGER_NOTIFICATION_MESSAGE_ITEM_CHANGED';
            } else {
                return false;
            }

            // if item was created or changed then sent a message with all changed fields in a table
            // if item was deleted or made former then sent a message with the item name, the user and the date
            if ($this->itemCreated || $this->itemChanged)   
            {
                $format_hdr = "<tr><th> %s </th><th> %s </th><th> %s </th></tr>\n";
                $format_row = "<tr><th> %s </th><td> %s </td><td> %s </td></tr>\n";
                $table_begin =  "<style>table, th, td {border: 1px solid black;}</style>"
                                . "<table>";
                $table_end = '</table><br>';
    
                $changes = array();
                foreach ($this->mChangedItemData as $data) {
                    foreach ($data as $key => $value) {
                        if ($value['oldValue'] != $value['newValue']) {
                            $listValues = $this->getProperty(strtoupper(str_replace('PIM_', '', $key)), 'imf_value_list');
                            if ($key === 'PIM_KEEPER') {
                                $sql = 'SELECT usr_id, CONCAT(last_name.usd_value, \', \', first_name.usd_value, IFNULL(CONCAT(\', \', postcode.usd_value),\'\'), IFNULL(CONCAT(\' \', city.usd_value),\'\'), IFNULL(CONCAT(\', \', street.usd_value),\'\') ) as name
                                        FROM ' . TBL_USERS . '
                                        JOIN ' . TBL_USER_DATA . ' as last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
                                        JOIN ' . TBL_USER_DATA . ' as first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
                                        LEFT JOIN ' . TBL_USER_DATA . ' as postcode ON postcode.usd_usr_id = usr_id AND postcode.usd_usf_id = ' . $gProfileFields->getProperty('POSTCODE', 'usf_id') . '
                                        LEFT JOIN ' . TBL_USER_DATA . ' as city ON city.usd_usr_id = usr_id AND city.usd_usf_id = ' . $gProfileFields->getProperty('CITY', 'usf_id') . '
                                        LEFT JOIN ' . TBL_USER_DATA . ' as street ON street.usd_usr_id = usr_id AND street.usd_usf_id = ' . $gProfileFields->getProperty('ADDRESS', 'usf_id') . '
                                        WHERE usr_valid = 1 AND EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ', ' . TBL_ROLES . ', ' . TBL_CATEGORIES . ' WHERE mem_usr_id = usr_id AND mem_rol_id = rol_id AND mem_begin <= \'' . DATE_NOW . '\' AND mem_end > \'' . DATE_NOW . '\' AND rol_valid = 1 AND rol_cat_id = cat_id AND (cat_org_id = ' . $organizationId . ' OR cat_org_id IS NULL)) ORDER BY last_name.usd_value, first_name.usd_value;';

                                $statement = $this->mDb->query($sql);
                                foreach ($statement->fetchAll() as $user) {
                                    $users[$user['usr_id']] = $user['name'];
                                }

                                $changes[] = array(
                                    $key,
                                    isset($users[$value['oldValue']]) ? $users[$value['oldValue']] : '',
                                    isset($users[$value['newValue']]) ? $users[$value['newValue']] : ''
                                );
                            }
                            else if ($listValues !== '') {
                                $changes[] = array(
                                    $key,
                                    isset($listValues[$value['oldValue']]) ? $listValues[$value['oldValue']] : '',
                                    isset($listValues[$value['newValue']]) ? $listValues[$value['newValue']] : ''
                                );
                            }
                            else {
                                $changes[] = array($key, $value['oldValue'], $value['newValue']);
                            }
                        }
                    } 
                }

                if ($changes) {
                    $message = $gL10n->get($messageHead, array($this->getValue('ITEMNAME','html'), $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'))) . '<br /><br />';
                    $message .= $table_begin
                        . sprintf(
                            $format_hdr,
                            $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD'),
                            $gL10n->get('SYS_PREVIOUS_VALUE'),
                            $gL10n->get('SYS_NEW_VALUE')
                        );
                    foreach ($changes as $c) {
                        $message .= sprintf($format_row, (str_contains($c[0], 'PIM_') ? $gL10n->get($c[0]) : $c[0]), $c[1], $c[2]);
                    }

                    $message .= $table_end;
                }
            }
            else {
                $messageUserText = 'SYS_CHANGED_BY';
                $messageDateText = 'SYS_CHANGED_AT';

                $message = $gL10n->get($messageHead, array($this->getValue('ITEMNAME','html'), $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'))) . '<br /><br />'
                    . $gL10n->get('PIM_ITEMNAME') . ': ' . $this->getValue('ITEMNAME','html') . '<br />'
                    . $gL10n->get($messageUserText) . ': ' . $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') . '<br />'
                    . $gL10n->get($messageDateText) . ': ' . date($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />';
            }
            
            return $notification->sendNotification(
                $gL10n->get($messageTitleText, array($this->getValue('ITEMNAME','html'))),
                $message
            );
        }
        return false;
    }
}
