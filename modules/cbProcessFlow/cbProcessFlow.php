<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';
require_once 'modules/BusinessActions/BusinessActions.php';
include_once 'include/Webservices/upsert.php';

class cbProcessFlow extends CRMEntity {
	public $table_name = 'vtiger_cbprocessflow';
	public $table_index= 'cbprocessflowid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	public $moduleIcon = array('library' => 'standard', 'containerClass' => 'slds-icon_container slds-icon-standard-account', 'class' => 'slds-icon', 'icon'=>'flow');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_cbprocessflowcf', 'cbprocessflowid');
	// related_tables variable should define the association (relation) between dependent tables
	// FORMAT: related_tablename => array(related_tablename_column[, base_tablename, base_tablename_column[, related_module]] )
	// Here base_tablename_column should establish relation with related_tablename_column
	// NOTE: If base_tablename and base_tablename_column are not specified, it will default to modules (table_name, related_tablename_column)
	// Uncomment the line below to support custom field columns on related lists
	// public $related_tables = array('vtiger_cbprocessflowcf' => array('cbprocessflowid', 'vtiger_cbprocessflow', 'cbprocessflowid', 'cbprocessflow'));

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_cbprocessflow', 'vtiger_cbprocessflowcf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_cbprocessflow'   => 'cbprocessflowid',
		'vtiger_cbprocessflowcf' => 'cbprocessflowid',
	);

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'cbprocessflow_no'=> array('cbprocessflow' => 'cbprocessflow_no'),
		'processflowname' => array('cbprocessflow' => 'processflowname'),
		'pfmodule' => array('cbprocessflow' => 'pfmodule'),
		'pffield' => array('cbprocessflow' => 'field'),
		'pfinitialstates' => array('cbprocessflow' => 'pfinitialstates'),
		'active' => array('cbprocessflow' => 'active'),
		'Assigned To' => array('crmentity' => 'smownerid')
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'cbprocessflow_no'=> 'cbprocessflow_no',
		'processflowname' => 'processflowname',
		'pfmodule' => 'pfmodule',
		'pffield' => 'field',
		'pfinitialstates' => 'pfinitialstates',
		'active' => 'active',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'cbprocessflow_no';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'cbprocessflow_no'=> array('cbprocessflow' => 'cbprocessflow_no'),
		'processflowname' => array('cbprocessflow' => 'processflowname'),
		'pfmodule' => array('cbprocessflow' => 'pfmodule'),
		'pffield' => array('cbprocessflow' => 'field'),
		'pfinitialstates' => array('cbprocessflow' => 'pfinitialstates'),
		'active' => array('cbprocessflow' => 'active'),
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'cbprocessflow_no'=> 'cbprocessflow_no',
		'processflowname' => 'processflowname',
		'pfmodule' => 'pfmodule',
		'pffield' => 'field',
		'pfinitialstates' => 'pfinitialstates',
		'active' => 'active',
	);

	// For Popup window record selection
	public $popup_fields = array('cbprocessflow_no');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'cbprocessflow_no';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'cbprocessflow_no';

	// Required Information for enabling Import feature
	public $required_fields = array('cbprocessflow_no'=>1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'cbprocessflow_no';
	public $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'cbprocessflow_no');

	public function save_module($module) {
		global $current_user;
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
		$tabid = getTabid($this->column_fields['pfmodule']);
		$moduleInstance = Vtiger_Module::getInstance($tabid);
		BusinessActions::addLink(
			$tabid,
			'DETAILVIEWWIDGET',
			'Push Along Flow',
			'module=cbProcessFlow&action=cbProcessFlowAjax&file=pushAlongFlow&id=$RECORD$&pflowid='.$this->id,
			'',
			'',
			'',
			true,
			0
		);
		$moduleInstance->addLink('HEADERSCRIPT', 'Push Along Flow', 'modules/cbProcessFlow/mermaid.min.js', '', 0, '', true);
		/////////
		$rec = array(
			'mapname' => 'ProcessFlowFor'.$this->column_fields['pfmodule'].$this->column_fields['pffield'].'_Validations',
			'maptype' => 'Validations',
			'targetname' => $this->column_fields['pfmodule'],
			'description' => '',
			'assigned_user_id' => vtws_getEntityId('Users').'x'.$current_user->id,
		);
		$rec['content'] = '<map>
<originmodule>
	<originname>'.$this->column_fields['pfmodule'].'</originname>
</originmodule>
<fields>
	<field>
	<fieldname>'.$this->column_fields['pffield'].'</fieldname>
	<validations>
		<validation>
		<rule>custom</rule>
		<restrictions>
		<restriction>modules/cbProcessFlow/validateFlowStep.php</restriction>
		<restriction>validateFlowStep</restriction>
		<restriction>validateFlowStep</restriction>
		</restrictions>
		<message>Invalid flow transition for {field}</message>
		</validation>
	</validations>
	</field>
</fields>
</map>';
		vtws_upsert('cbMap', $rec, 'mapname', implode(',', array_keys($rec)), $current_user);
		$modplog = Vtiger_Module::getInstance('ProcessLog');
		$field = Vtiger_Field::getInstance('relatedflow', $modplog);
		$field->setRelatedModules(array($this->column_fields['pfmodule']));
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
			$this->setModuleSeqNumber('configure', $modulename, 'bpmflw-', '0000001');
		} elseif ($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}

	public static function getDestinationStatesArray($processflow, $fromstate, $record, $screenvalues) {
		global $adb;
		$columns = $screenvalues;
		$exists = isRecordExists($record);
		if ($exists) {
			$cbmap = new cbMap();
			$cbmap->mode = '';
			if (empty($screenvalues)) {
				$recordType = getSalesEntityType($record);
				$entity = CRMEntity::getInstance($recordType);
				$entity->mode = '';
				$entity->retrieve_entity_info($record, $recordType);
				$columns = $entity->column_fields;
				$columns['record'] = $record;
				$columns['module'] = $recordType;
			} else {
				if (empty($columns['module'])) {
					$recordType = getSalesEntityType($record);
					$columns['module'] = $recordType;
				}
			}
		}
		$cn = $adb->getColumnNames('vtiger_cbprocessstep');
		$moreinfomapid = (in_array('moreinfomapid', $cn) ? 'moreinfomapid' : '0 as moreinfomapid');
		$rs = $adb->pquery(
			'select tostep, pfmodule, isactivevalidation, showstepvalidation, buttonlabel, '.$moreinfomapid
			.' from vtiger_cbprocessstep
			inner join vtiger_crmentity on crmid=cbprocessstepid
			inner join vtiger_cbprocessflow on processflow=cbprocessflowid
			where deleted=0 and processflow=? and fromstep=? and vtiger_cbprocessstep.active',
			array($processflow, $fromstate)
		);
		$states = $moreinfo = array();
		while ($st = $adb->fetch_array($rs)) {
			if (!empty($st['moreinfomapid'])) {
				if (isRecordExists($st['moreinfomapid'])) {
					if (getSalesEntityType($st['moreinfomapid'])=='cbMap') {
						$st['moreinfomapid'] = coreBOS_Rule::evaluate($st['moreinfomapid'], $record);
					}
					$check = $adb->pquery(
						'select 1 from vtiger_cbprocessinfo
						inner join vtiger_crmobject on crmid=cbprocessinfoid
						where crmid=? and setype=? and deleted=0 and active=1',
						[$st['moreinfomapid'], 'cbProcessInfo']
					);
					if (!$check || $adb->num_rows($check)!=1) {
						$st['moreinfomapid'] = 0;
					}
				} else {
					$st['moreinfomapid'] = 0;
				}
			} else {
				$st['moreinfomapid'] = 0;
			}
			$label = (empty($st['buttonlabel']) ? $st['tostep'] : $st['buttonlabel']);
			if (substr($label, 0, 1)=='{') { // it is a JSON specification > make sure it has a label
				$toinfo = json_decode($label, true);
				if (empty($toinfo['label'])) {
					$toinfo['label'] = getTranslatedString($st['tostep'], $st['pfmodule']);
				}
				$label = json_encode($toinfo);
			}
			if ($exists && !empty($st['showstepvalidation'])) {
				$cbmap->id = $st['showstepvalidation'];
				$cbmap->retrieve_entity_info($st['showstepvalidation'], 'cbMap');
				if ($cbmap->Validations($columns, $record, false)===true) {
					$states[$st['tostep']] = getTranslatedString($label, $st['pfmodule']);
					$moreinfo[$st['tostep']] = $st['moreinfomapid'];
				}
			} else {
				if ($exists && !empty($st['isactivevalidation'])) {
					$cbmap->id = $st['isactivevalidation'];
					$cbmap->retrieve_entity_info($st['isactivevalidation'], 'cbMap');
					if ($cbmap->Validations($columns, $record, false)===true) {
						$states[$st['tostep']] = getTranslatedString($label, $st['pfmodule']);
						$moreinfo[$st['tostep']] = $st['moreinfomapid'];
					}
				} else {
					$states[$st['tostep']] = getTranslatedString($label, $st['pfmodule']);
					$moreinfo[$st['tostep']] = $st['moreinfomapid'];
				}
			}
		}
		return [$states, $moreinfo];
	}

	public static function getDestinationStatesGraph($processflow, $fromstate, $record, $askifsure, $screenvalues) {
		global $adb;
		$rs = $adb->pquery('select pfmodule from vtiger_cbprocessflow where cbprocessflowid=?', array($processflow));
		$module = $adb->query_result($rs, 0, 0);
		list($states, $moreinfo) = self::getDestinationStatesArray($processflow, $fromstate, $record, $screenvalues);
		if (empty($states)) {
			return '';
		}
		$askifsure = empty($askifsure) ? 'false' : 'true';
		$graph = "graph LR\n";
		$from = 'A("'.getTranslatedString($fromstate, $module).'") --> ';
		$letters = range('B', 'Z');
		$minfo = reset($moreinfo);
		$links = '';
		foreach ($states as $state => $to) {
			if (substr($to, 0, 1)=='{') { // it is a JSON specification
				// {"label":"some label", "type":"approve|decline|css_class"}
				$toinfo = json_decode($to, true);
				$to = (empty($toinfo['label']) ? $to : $toinfo['label']);
			}
			$letter = next($letters);
			$graph .= $from.$letter.'('.getTranslatedString($to, $module).")\n";
			$links .= "click $letter \"javascript:processflowmoveto$processflow('$state', $record, $askifsure, $minfo)\"\n";
			$minfo = next($moreinfo);
		}
		return $graph.$links;
	}

	public static function getDestinationStatesButtons($processflow, $fromstate, $record, $askifsure, $screenvalues) {
		global $adb;
		$rs = $adb->pquery('select pfmodule from vtiger_cbprocessflow where cbprocessflowid=?', array($processflow));
		$module = $adb->query_result($rs, 0, 0);
		list($states, $moreinfo) = self::getDestinationStatesArray($processflow, $fromstate, $record, $screenvalues);
		if (empty($states)) {
			return '';
		}
		$askifsure = empty($askifsure) ? 'false' : 'true';
		$minfo = reset($moreinfo);
		$graph = '';
		foreach ($states as $state => $to) {
			$to = trim($to);
			if (substr($to, 0, 1)=='{') { // it is a JSON specification
				// {"label":"some label", "type":"approve|decline|css_class"}
				$toinfo = json_decode($to, true);
				$type = (empty($toinfo['type']) ? 'slds-button_brand' :$toinfo['type']);
				$link_label = (empty($toinfo['label']) ? 'Undefined' : $toinfo['label']);
			} else {
				$type = 'slds-button_brand';
				$link_label = $to;
			}
			$slds_class = '';
			if ($type == 'approve') {
				$slds_class = 'slds-button_brand';
				$link_label = 'Approve';
			} elseif ($type == 'decline') {
				$slds_class = 'slds-button_destructive';
				$link_label = 'Decline';
			} else {
				$slds_class = $type;
			}
			$graph .= '<div class="slds-col slds-size_1-of-1 slds-p-around_xx-small"><a class="slds-button '.$slds_class.'" href="javascript:processflowmoveto'.$processflow."('$state', $record, $askifsure, $minfo)".'">'
				.getTranslatedString($link_label, $module).'</a></div>';
			$minfo = next($moreinfo);
		}
		return $graph;
	}

	/**
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// public function save_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
?>
