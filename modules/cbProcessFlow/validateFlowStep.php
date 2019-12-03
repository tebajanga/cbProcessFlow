<?php
/*************************************************************************************************
 * Copyright 2019 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
 * Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
 * file except in compliance with the License. You can redistribute it and/or modify it
 * under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
 * granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
 * applicable law or agreed to in writing, software distributed under the License is
 * distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
 * either express or implied. See the License for the specific language governing
 * permissions and limitations under the License. You may obtain a copy of the License
 * at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
 *************************************************************************************************/

function validateFlowStep($fieldname, $fieldvalue, $params, $entity) {
	global $log, $adb;
	$log->debug('> Process Alert After Save');
	$moduleName = $entity['module'];
	$rs = $adb->pquery(
		'select cbprocessflowid, pffield, pfcondition
		from vtiger_cbprocessflow
		inner join vtiger_crmentity on crmid=cbprocessflowid
		where deleted=0 and pfmodule=? and active=?',
		array($moduleName, '1')
	);
	if ($rs && $adb->num_rows($rs)>0) {
		$pffield = $rs->fields['pffield'];
		// $isNew = true;
		if (empty($entity['mode']) && empty($entity['record'])) {
			$rss = $adb->pquery(
				'SELECT 1
				FROM `vtiger_cbprocessstep`
				INNER JOIN vtiger_crmentity on crmid=cbprocessstepid
				WHERE deleted=0 and `processflow`=? and fromstep=? and active=? and fromstep not in
				(select distinct tostep
				FROM `vtiger_cbprocessstep`
				INNER JOIN vtiger_crmentity on crmid=cbprocessstepid
				WHERE deleted=0 and `processflow`=? and active=?)',
				array($rs->fields['cbprocessflowid'], $entity[$pffield], '1', $rs->fields['cbprocessflowid'], '1')
			);
			if ($rss && $adb->num_rows($rss)>0) {
				return true;
			} else {
				// return invalid initial state and log the event
				// there are no negative actions to launch because there is no transition step
				return false;
			}
		}
		// editing
		$crmid = $entity['record'];
		if ($entity[$pffield]!=$entity['current_'.$pffield]) {
			$entity['record_id'] = $entity['record'];
			$pfcondition = $rs->fields['pfcondition'];
			if (empty($pfcondition) || coreBOS_Rule::evaluate($pfcondition, $entity)) {
				$rss = $adb->pquery(
					'select cbprocessstepid, validation
					from vtiger_cbprocessstep
					inner join vtiger_crmentity on crmid=cbprocessstepid
					where deleted=0 and processflow=? and fromstep=? and tostep=? and active=?',
					array($rs->fields['cbprocessflowid'], $entity['current_'.$pffield], $entity[$pffield], '1')
				);
				if ($rss && $adb->num_rows($rss)>0) {
					if (!empty($rss->fields['validation'])) {
						$focus = new cbMap();
						$focus->mode = '';
						$focus->id = $rss->fields['validation'];
						$focus->retrieve_entity_info($rss->fields['validation'], 'cbMap');
						$validation = $focus->Validations($entity, $crmid, false);
						if (is_array($validation)) {
							$wfs = $adb->pquery('SELECT wfid FROM vtiger_cbprocesssteprel WHERE stepid=? and !positive', array($rss->fields['cbprocessstepid']));
							// insert into queue
							while ($wf = $adb->fetch_array(($wfs))) {
								$checkpresence = $adb->pquery(
									'SELECT 1 FROM vtiger_cbprocessalertqueue WHERE crmid=? AND wfid=? AND nexttrigger_time=0',
									array($crmid, $wf['wfid'])
								);
								if ($checkpresence && $adb->num_rows($checkpresence)==0) {
									$adb->pquery(
										'insert into vtiger_cbprocessalertqueue (crmid, whenarrived, alertid, wfid, nexttrigger_time) values (?,NOW(),0,?,0)',
										array($crmid, $wf['wfid'])
									);
								}
							}
							return false;
						}
					}
				} else {
					return false;
				}
			}
		}
	}
	return true;
}