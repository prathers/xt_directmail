<?php
namespace TYPO3\CMS\XtDirectmail\Xclass;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use DirectMailTeam\DirectMail\DirectMailUtility;

/**
 * Xclass Direct mail Module of the tx_directmail extension
 * See <http://forge.typo3.org/issues/36467>
 *
 * @author JKummer <typo3 et enobe de>
 *
 * @package TYPO3
 * @subpackage xt_directmail
 */
class Dmail extends \DirectMailTeam\DirectMail\Module\Dmail
{

	/**
	 * Fetches recipient IDs from a given group ID
	 *
	 * Most of the functionality from cmd_compileMailGroup in order to use multiple recipient lists when sending
	 *
	 * @param integer		$group_uid: recipient group ID
	 * @return array		list of recipient IDs
	 */
	protected function getSingleMailGroup($group_uid) {
		$id_lists = array();
		if ($group_uid) {
			$mailGroup = BackendUtility::getRecord('sys_dmail_group',$group_uid);

			if (is_array($mailGroup)) {
				switch($mailGroup['type']) {
				case 0:
					// From pages
					$thePages = $mailGroup['pages'] ? $mailGroup['pages'] : $this->id;		// use current page if no else
					$pages = GeneralUtility::intExplode(',',$thePages);	// Explode the pages
					$pageIdArray = array();

					foreach ($pages AS $pageUid) {
						if ($pageUid > 0) {
							$pageinfo = BackendUtility::readPageAccess($pageUid,$this->perms_clause);
							if (is_array($pageinfo)) {
								$info['fromPages'][] = $pageinfo;
								$pageIdArray[] = $pageUid;
								if ($mailGroup['recursive']) {
									$pageIdArray = array_merge($pageIdArray,DirectMailUtility::getRecursiveSelect($pageUid,$this->perms_clause));
								}
							}
						}
					}
						// Remove any duplicates
					$pageIdArray = array_unique($pageIdArray);
					$pidList = implode(',',$pageIdArray);
					$info['recursive'] = $mailGroup['recursive'];

						// Make queries
					if ($pidList)	{
						$whichTables = intval($mailGroup['whichtables']);
						if ($whichTables&1)	{	// tt_address
							$id_lists['tt_address'] = DirectMailUtility::getIdList('tt_address',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&2)	{	// fe_users
							$id_lists['fe_users'] = DirectMailUtility::getIdList('fe_users',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($this->userTable && ($whichTables&4))	{	// user table
							$id_lists[$this->userTable] = DirectMailUtility::getIdList($this->userTable,$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&8)	{	// fe_groups
							if (!is_array($id_lists['fe_users'])) $id_lists['fe_users'] = array();
							$id_lists['fe_users'] = array_unique(array_merge($id_lists['fe_users'], DirectMailUtility::getIdList('fe_groups',$pidList,$group_uid,$mailGroup['select_categories'])));
						}
					}
					break;
				case 1: // List of mails
					if ($mailGroup['csv']==1)	{
						$recipients = DirectMailUtility::rearrangeCsvValues(DirectMailUtility::getCsvValues($mailGroup['list']), $this->fieldList);
					} else {
						$recipients = DirectMailUtility::rearrangePlainMails(array_unique(preg_split('|[[:space:],;]+|',$mailGroup['list'])));
					}
					$id_lists['PLAINLIST'] = DirectMailUtility::cleanPlainList($recipients);
					break;
				case 2:	// Static MM list
					$id_lists['tt_address'] = DirectMailUtility::getStaticIdList('tt_address',$group_uid);
					$id_lists['fe_users'] = DirectMailUtility::getStaticIdList('fe_users',$group_uid);
					$id_lists['fe_users'] = array_unique(array_merge($id_lists['fe_users'],DirectMailUtility::getStaticIdList('fe_groups',$group_uid)));
					if ($this->userTable)	{
						$id_lists[$this->userTable] = DirectMailUtility::getStaticIdList($this->userTable,$group_uid);
					}
					break;
				case 3:	// Special query list
					$mailGroup = $this->update_SpecialQuery($mailGroup);
					$whichTables = intval($mailGroup['whichtables']);
					$table = '';
					if ($whichTables&1) {
						$table = 'tt_address';
					} elseif ($whichTables&2) {
						$table = 'fe_users';
					} elseif ($this->userTable && ($whichTables&4)) {
						$table = $this->userTable;
					}
					if ($table) {
						// initialize the query generator
						$queryGenerator = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\MailSelect');
						$id_lists[$table] = DirectMailUtility::getSpecialQueryIdList($queryGenerator,$table,$mailGroup);
					}
					break;
				case 4:	//
					$groups = array_unique(DirectMailUtility::getMailGroups($mailGroup['mail_groups'],array($mailGroup['uid']),$this->perms_clause));
					foreach($groups AS $v) {
						$collect = $this->getSingleMailGroup($v);
						if (is_array($collect)) {
							$id_lists = array_merge_recursive($id_lists,$collect);
						}
					}
					break;
				}
			}
		}
/**
 * Changes start
 */
            /**
             * Hook for getSingleMailGroup
             * manipulate the generated id_lists
             */
            if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['getSingleMailGroup'])) {
                $hookObjectsArr = array();
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['getSingleMailGroup'] as $classRef) {
                    $hookObjectsArr[] = &GeneralUtility::getUserObj($classRef);
                }
                foreach($hookObjectsArr as $hookObj)    {
                    if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
                        $tempLists = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $mailGroup);
                    }
                }
                unset ($idLists);
                $idLists = $tempLists;
            }
/**
 * Changes end
 */
        }
        return $idLists;
    }
}
