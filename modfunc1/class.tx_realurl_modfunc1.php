<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
 *  (c) 2005-2010 Dmitry Dulepov (dmitry@typo3.org)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

$GLOBALS['LANG']->includeLLfile('EXT:realurl/modfunc1/locallang.xml');

/**
 * Speaking Url management extension
 *
 * @author    Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl_modfunc1 extends t3lib_extobjbase
{

    // Internal, dynamic:
    public $searchResultCounter = 0;

    /**
     * Returns the menu array
     *
     * @return    array
     */
    public function modMenu()
    {
        $modMenu = array(
            'depth' => array(
                0 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_0'),
                1 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_1'),
                2 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_2'),
                3 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf.php:labels.depth_3'),
                99 => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_infi'),
            ),
            'type' => array(
                'pathcache' => 'ID-to-path mapping',
                'decode' => 'Decode cache',
                'encode' => 'Encode cache',
                'uniqalias' => 'Unique Aliases',
                'config' => 'Configuration',
                'log' => 'Error Log'
            )
        );

        $modMenu['type'] = t3lib_BEfunc::unsetMenuItems($this->pObj->modTSconfig['properties'], $modMenu['type'],
            'menu.realurl_type');

        return $modMenu;
    }

    /**
     * MAIN function for cache information
     *
     * @return    string        Output HTML for the module.
     */
    public function main()
    {
        if ($this->pObj->id) {
            $result = $this->createModuleContentForPage();
        } else {
            $result = '<p>' . $GLOBALS['LANG']->getLL('no_page_id') . '</p>';
        }

        return $result;
    }

    /**
     * Enter description here ...
     */
    protected function createModuleContentForPage()
    {
        $this->addModuleStyles();

        $result = $this->getFunctionMenu() . ' ';

        switch ($this->pObj->MOD_SETTINGS['type']) {
            case 'pathcache':
                $result .= $this->getDepthSelector();
                $moduleContent = $this->renderModule($this->initializeTree());
                //$result .= $this->renderSearchForm();
                $result .= $moduleContent;
                break;
            case 'encode':
                $result .= $this->getDepthSelector();
                $result .= $this->encodeView($this->initializeTree());
                break;
            case 'decode':
                $result .= $this->getDepthSelector();
                $result .= $this->decodeView($this->initializeTree());
                break;
            case 'uniqalias':
                $this->edit_save_uniqAlias();
                $result .= $this->uniqueAlias();
                break;
            case 'config':
                $result .= $this->getDepthSelector();
                $result .= $this->configView();
                break;
            case 'log':
                $result .= $this->logView();
                break;
        }

        return $result;
    }

    /**
     * Obtains function selection menu.
     *
     * @return string
     */
    protected function getFunctionMenu()
    {
        return $GLOBALS['LANG']->getLL('function') . ' ' .
        t3lib_BEfunc::getFuncMenu($this->pObj->id, 'SET[type]',
            $this->pObj->MOD_SETTINGS['type'], $this->pObj->MOD_MENU['type'],
            'index.php');
    }

    /**
     * Adds module-specific styles to the output.
     *
     * @return void
     */
    protected function addModuleStyles()
    {
        $this->pObj->doc->inDocStyles .= '
			TABLE.c-list TR TD { white-space: nowrap; vertical-align: top; }
			TABLE#tx-realurl-pathcacheTable TD { vertical-align: top; }
			FIELDSET { border: none; padding: 16px 0; }
			FIELDSET DIV { clear: left; border-collapse: collapse; margin-bottom: 5px; }
			FIELDSET DIV LABEL { display: block; float: left; width: 100px; }
		';
    }

    /**
     * Creates depth selector HTML for the page tree.
     *
     * @return string
     */
    protected function getDepthSelector()
    {
        return $GLOBALS['LANG']->getLL('depth') .
        t3lib_BEfunc::getFuncMenu($this->pObj->id, 'SET[depth]', $this->pObj->MOD_SETTINGS['depth'],
            $this->pObj->MOD_MENU['depth'], 'index.php');
    }

    /**
     * Initializes the page tree.
     *
     * @return \TYPO3\CMS\Backend\Tree\View\PageTreeView
     */
    protected function initializeTree()
    {
        $tree = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\View\PageTreeView::class);
        $tree->addField('nav_title', true);
        $tree->addField('alias', true);
        $tree->addField('l18n_cfg');
        $tree->addField('tx_realurl_pathsegment', true);
        $tree->init('AND ' . $GLOBALS['BE_USER']->getPagePermsClause(1));

        $treeStartingPoint = intval($this->pObj->id);
        $treeStartingRecord = t3lib_BEfunc::getRecord('pages', $treeStartingPoint);
        t3lib_BEfunc::workspaceOL('pages', $treeStartingRecord);

        // Creating top icon; the current page
        $tree->tree[] = array(
            'row' => $treeStartingRecord,
            'HTML' => t3lib_iconWorks::getIconImage('pages', $treeStartingRecord, $GLOBALS['BACK_PATH'], 'align="top"')
        );

        // Create the tree from starting point:
        if ($this->pObj->MOD_SETTINGS['depth'] > 0) {
            $tree->getTree($treeStartingPoint, $this->pObj->MOD_SETTINGS['depth'], '');
        }

        return $tree;
    }


    /****************************
     *
     * Path Cache rendering:
     *
     ****************************/
    /**
     * MAIN function for page information of localization
     *
     * @return    string        Output HTML for the module.
     */
    public function renderModule(t3lib_pageTree $tree)
    {
        if ($this->pObj->id) {
            $theOutput = '';

            $this->cachemgmt = t3lib_div::makeInstance('tx_realurl_cachemgmt', $GLOBALS['BE_USER']->workspace, 0, 1);

            $this->pathgen = t3lib_div::makeInstance('tx_realurl_pathgenerator');
            $this->pathgen->init(array());

            //Add action buttons:
            $theOutput .= '
				<table><tr><td valign="top">
				<h3>Actions:</h3>
				<input name="id" value="' . $this->pObj->id . '" type="hidden"><input type="submit" value="clear all (complete cache and history)" name="_action_clearall">';
            $theOutput .= '<br /><input type="submit" value="clear visible tree" name="_action_clearvisible">';
            $theOutput .= '<br /><input type="submit" value="mark visible tree as dirty" name="_action_dirtyvisible">';
            $theOutput .= '<br /><input type="submit" value="clear complete history cache" name="_action_clearallhistory">';
            $theOutput .= '<br /><input type="submit" value="regenerate (FE-calls)" name="_action_regenerate"></td><td valign="top">
				<h3>Colors:</h3>
					<table border="0">
					<tr><td class="c-ok">Cache found</td></tr>
					<tr><td class="c-ok-expired">Cache expired</td></tr>
					<tr><td class="c-shortcut">Shortcut (no cache needed)</td></tr>
					<tr><td class="c-delegation">Delegation (no cache needed)</td></tr>
					<tr><td class="c-nok">No cache found</td></tr></table>
				</td></tr></table>';
            //$theOutput.='<input type="submit" value="regenerate!" name="_action_clearall">';
            //check actions:
            if (t3lib_div::_GP('_action_clearall') != '') {
                $this->cachemgmt->clearAllCache();
            }
            if (t3lib_div::_GP('_action_clearallhistory') != '') {
                $this->cachemgmt->clearAllCacheHistory();
            }

            // Add CSS needed:
            $css_content = '
				TABLE#langTable {
					margin-top: 10px;
				}
				TABLE#langTable TR TD {
					padding-left : 2px;
					padding-right : 2px;
					white-space: nowrap;
				}

				TR.odd { background-color:#ddd; }

				TD.c-ok { background-color: #A8E95C; }
				TD.c-ok-expired { background-color: #B8C95C; }
				TD.c-shortcut { background-color: #B8E95C; font-weight: 200}
				TD.c-delegation { background-color: #EE0; }
				/*TD.c-nok { background-color: #E9CD5C; }*/
				TD.c-leftLine {border-left: 2px solid black; }
				TD.bgColor5 { font-weight: bold; }
			';
            $marker = '/*###POSTCSSMARKER###*/';
            if (!stristr($this->pObj->content, $marker)) {
                $theOutput = '<style type="text/css">' . $css_content . '</style>' . chr(10) . $theOutput;
            } else {
                $this->pObj->content = str_replace($marker, $css_content . chr(10) . $marker, $this->pObj->content);
            }
            $theOutput .= '<hr />AOE realurl path cache for workspace: ' . $GLOBALS['BE_USER']->workspace;
            // Render information table:
            $theOutput .= $this->renderTable($tree);
        }

        return $theOutput;
    }

    /**
     * Links to the module script and sets necessary parameters (only for pathcache display)
     *
     * @param    string        Additional GET vars
     * @return    string        script + query
     */
    public function linkSelf($addParams)
    {
        return htmlspecialchars('index.php?id=' . $this->pObj->id . '&showLanguage=' . rawurlencode(t3lib_div::_GP('showLanguage')) . $addParams);
    }

    /**
     * Create search form
     *
     * @return    string        HTML
     */
    public function renderSearchForm()
    {
        $output = '<fieldset>';
        $output .= $this->getLanguageSelector();
        $output .= '<div>' . $this->getSearchField() . '</div>';
        $output .= $this->getReplaceAndDeleteFields();

        $output .= '<input type="hidden" name="id" value="' . $this->pObj->id . '" />';
        $output .= '</fieldset>';

        return $output;
    }

    /**
     * Obtains fields for replace/delete.
     *
     * @return string
     */
    private function getReplaceAndDeleteFields()
    {
        $output = '';

        if ($this->searchResultCounter && !t3lib_div::_POST('_replace') && !t3lib_div::_POST('_delete')) {
            $output .= '<div><label for="pathPrefixReplace">Replace with:</label> <input type="text" name="pathPrefixReplace" value="' . htmlspecialchars(t3lib_div::_GP('pathPrefixSearch')) . '" />';
            $output .= '<input type="submit" name="_replace" value="Replace" /> or <input type="submit" name="_delete" value="Delete" /></div>';
            $output .= '<div><b>' . sprintf('Found: %d result(s).', $this->searchResultCounter) . '</b></div>';
        }

        return $output;
    }

    /**
     * Enter description here ...
     *
     * @param output
     */
    protected function getSearchField()
    {
        $output = '<label for="pathPrefixSearch">' . $GLOBALS['LANG']->getLL('search_path', true) .
            '</label> <input type="text" name="pathPrefixSearch" id="pathPrefixSearch" value="' .
            htmlspecialchars(t3lib_div::_GP('pathPrefixSearch')) . '" />' .
            '<input type="submit" name="_" value="' .
            $GLOBALS['LANG']->getLL('look_up', true) . '" />';

        return $output;
    }

    /**
     * Generates language selector.
     *
     * @return string
     */
    protected function getLanguageSelector()
    {
        $languages = $this->getSystemLanguages();

        $options = array();
        $showLanguage = t3lib_div::_GP('showLanguage');
        foreach ($languages as $language) {
            $selected = $showLanguage === $language['uid'] ? ' selected="selected"' : '';
            $options[] = '<option value="' . $language['uid'] . '"' . $selected . '>' .
                htmlspecialchars($language['title']) . '</option>';
        }

        return '<div><label for="showLanguage">' . $GLOBALS['LANG']->getLL('language', true) .
        '</label> <select name="showLanguage">' . implode('', $options) . '</select></div>';
    }

    /**
     * Obtains system languages.
     *
     * @return array
     */
    protected function getSystemLanguages()
    {
        $languages = (array)t3lib_BEfunc::getRecordsByField('sys_language', 'pid', 0, '', '', 'title');

        $defaultLanguageLabel = $this->getDefaultLanguageName();

        array_unshift($languages, array('uid' => 0, 'title' => $defaultLanguageLabel));
        array_unshift($languages, array('uid' => '', 'title' => $GLOBALS['LANG']->getLL('all_languages')));

        return $languages;
    }

    /**
     * Obtains the name of the default language.
     *
     * @return string
     */
    protected function getDefaultLanguageName()
    {
        $tsConfig = t3lib_BEfunc::getPagesTSconfig($this->pObj->id);
        if (isset($tsConfig['mod.']['SHARED.']['defaultLanguageLabel'])) {
            $label = $tsConfig['mod.']['SHARED.']['defaultLanguageLabel'];
        } else {
            $label = $GLOBALS['LANG']->getLL('default_language');
        }

        return $label;
    }

    /**
     * Save / Cancel buttons
     *
     * @param    string        Extra code.
     * @return    string        Form elements
     */
    public function saveCancelButtons($extra = '')
    {
        $output = '<input type="submit" name="_edit_save" value="Save" /> ';
        $output .= '<input type="submit" name="_edit_cancel" value="Cancel" />';
        $output .= $extra;

        return $output;
    }



    /**************************
     *
     * Decode view
     *
     **************************/

    /**
     * Rendering the decode-cache content
     *
     * @param    array        The Page tree data
     * @return    string        HTML for the information table.
     */
    public function decodeView(t3lib_pageTree $tree)
    {

        // Delete entries:
        $cmd = t3lib_div::_GP('cmd');
        $subcmd = '';
        if ($cmd === 'deleteDC') {
            $subcmd = t3lib_div::_GP('entry');
            $this->clearDEncodeCache($subcmd, true);
        }

        // Traverse tree:
        $output = '';
        $cc = 0;
        $countDisplayed = 0;
        foreach ($tree->tree as $row) {

            // Select rows:
            $displayRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'tx_realurl_urldecodecache',
                'page_id=' . intval($row['row']['uid']), '', 'spurl');

            // Row title:
            $rowTitle = $row['HTML'] . t3lib_BEfunc::getRecordTitle('pages', $row['row'], true);

            // Add at least one empty element:
            if (!count($displayRows) || $subcmd === 'displayed') {

                // Add title:
                $tCells = array();
                $tCells[] = '<td nowrap="nowrap">' . $rowTitle . '</td>';

                // Empty row:
                $tCells[] = '<td colspan="6" align="center">&nbsp;</td>';

                // Compile Row:
                $output .= '
					<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
						' . implode('
						', $tCells) . '
					</tr>';
                $cc++;

                if ($subcmd === 'displayed') {
                    foreach ($displayRows as $c => $inf) {
                        $this->clearDEncodeCache('urlhash_' . $inf['url_hash'], true);
                    }
                }
            } else {
                foreach ($displayRows as $c => $inf) {

                    // Add icon/title and ID:
                    $tCells = array();
                    if (!$c) {
                        $tCells[] = '<td nowrap="nowrap" rowspan="' . count($displayRows) . '">' . $rowTitle . '</td>';
                        $tCells[] = '<td nowrap="nowrap" rowspan="' . count($displayRows) . '">' . $row['row']['uid'] . '</td>';
                        $tCells[] = '<td rowspan="' . count($displayRows) . '">' .
                            '<a href="' . $this->linkSelf('&cmd=deleteDC&entry=page_' . intval($row['row']['uid'])) . '">' .
                            '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                                'width="11" height="12"') . ' title="Delete entries for page" alt="" />' .
                            '</a>' .
                            '</td>';
                    }

                    // Path:
                    $tCells[] = '<td>' . htmlspecialchars($inf['spurl']) . '</td>';

                    // Get vars:
                    $queryValues = unserialize($inf['content']);
                    $queryParams = '?id=' . $queryValues['id'] .
                        (is_array($queryValues['GET_VARS']) ? t3lib_div::implodeArrayForUrl('',
                            $queryValues['GET_VARS']) : '');
                    $tCells[] = '<td>' . htmlspecialchars($queryParams) . '</td>';

                    // Delete:
                    $tCells[] = '<td>' .
                        '<a href="' . $this->linkSelf('&cmd=deleteDC&entry=urlhash_' . intval($inf['url_hash'])) . '">' .
                        '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                            'width="11" height="12"') . ' title="Delete entry" alt="" />' .
                        '</a>' .
                        '</td>';

                    // Timestamp:
                    $tCells[] = '<td>' . htmlspecialchars(t3lib_BEfunc::datetime($inf['tstamp'])) . ' / ' . htmlspecialchars(t3lib_BEfunc::calcAge(time() - $inf['tstamp'])) . '</td>';

                    // Compile Row:
                    $output .= '
						<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
							' . implode('
							', $tCells) . '
						</tr>';
                    $cc++;
                    $countDisplayed++;
                }
            }
        }

        list($count_allInTable) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('count(*) AS count',
            'tx_realurl_urldecodecache', '');

        // Create header:
        $tCells = array();
        $tCells[] = '<td>Title:</td>';
        $tCells[] = '<td>ID:</td>';
        $tCells[] = '<td>&nbsp;</td>';
        $tCells[] = '<td>Path:</td>';
        $tCells[] = '<td>GET variables:</td>';
        $tCells[] = '<td>&nbsp;</td>';
        $tCells[] = '<td>Timestamp:</td>';

        $output = '
			<tr class="bgColor5 tableheader">
				' . implode('
				', $tCells) . '
			</tr>' . $output;

        // Compile final table and return:
        $output = '<br/><br/>
		Displayed entries: <b>' . $countDisplayed . '</b> ' .
            '<a href="' . $this->linkSelf('&cmd=deleteDC&entry=displayed') . '">' .
            '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                'width="11" height="12"') . ' title="Delete displayed entries" alt="" />' .
            '</a>' .
            '<br/>
		Total entries in decode cache: <b>' . $count_allInTable['count'] . '</b> ' .
            '<a href="' . $this->linkSelf('&cmd=deleteDC&entry=all') . '">' .
            '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                'width="11" height="12"') . ' title="Delete WHOLE decode cache!" alt="" />' .
            '</a>' .
            '<br/>
		<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">' . $output . '
		</table>';

        return $output;
    }











    /**************************
     *
     * Encode view
     *
     **************************/

    /**
     * Rendering the encode-cache content
     *
     * @param    array        The Page tree data
     * @return    string        HTML for the information table.
     */
    public function encodeView(t3lib_pageTree $tree)
    {

        // Delete entries:
        $cmd = t3lib_div::_GP('cmd');
        $subcmd = '';
        if ($cmd === 'deleteEC') {
            $subcmd = t3lib_div::_GP('entry');
            $this->clearDEncodeCache($subcmd);
        }

        // Traverse tree:
        $cc = 0;
        $countDisplayed = 0;
        $output = '';
        $duplicates = array();

        foreach ($tree->tree as $row) {

            // Select rows:
            $displayRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'tx_realurl_urlencodecache',
                'page_id=' . intval($row['row']['uid']), '', 'content');

            // Row title:
            $rowTitle = $row['HTML'] . t3lib_BEfunc::getRecordTitle('pages', $row['row'], true);

            // Add at least one empty element:
            if (!count($displayRows) || $subcmd === 'displayed') {

                // Add title:
                $tCells = array();
                $tCells[] = '<td nowrap="nowrap">' . $rowTitle . '</td>';
                $tCells[] = '<td nowrap="nowrap">&nbsp;</td>';

                // Empty row:
                $tCells[] = '<td colspan="7" align="center">&nbsp;</td>';

                // Compile Row:
                $output .= '
					<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
						' . implode('
						', $tCells) . '
					</tr>';
                $cc++;

                if ($subcmd === 'displayed') {
                    foreach ($displayRows as $c => $inf) {
                        $this->clearDEncodeCache('urlhash_' . $inf['url_hash']);
                    }
                }
            } else {
                foreach ($displayRows as $c => $inf) {
                    // Add icon/title and ID:
                    $tCells = array();
                    if (!$c) {
                        $tCells[] = '<td nowrap="nowrap" rowspan="' . count($displayRows) . '">' . $rowTitle . '</td>';
                        $tCells[] = '<td nowrap="nowrap" rowspan="' . count($displayRows) . '">' . $row['row']['uid'] . '</td>';
                        $tCells[] = '<td rowspan="' . count($displayRows) . '">' .
                            '<a href="' . $this->linkSelf('&cmd=deleteEC&entry=page_' . intval($row['row']['uid'])) . '">' .
                            '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                                'width="11" height="12"') . ' title="Delete entries for page" alt="" />' .
                            '</a>' .
                            '</td>';
                    }

                    // Get vars:
                    $tCells[] = '<td>' . htmlspecialchars(t3lib_div::fixed_lgd_cs($inf['origparams'], 100)) . '</td>';

                    // Internal Extras:
                    $tCells[] = '<td>' . ($inf['internalExtras'] ? t3lib_div::arrayToLogString(unserialize($inf['internalExtras'])) : '&nbsp;') . '</td>';

                    // Path:
                    $tCells[] = '<td>' . htmlspecialchars(t3lib_div::fixed_lgd_cs($inf['content'], 100)) . '</td>';

                    // Delete:
                    $tCells[] = '<td>' .
                        '<a href="' . $this->linkSelf('&cmd=deleteEC&entry=urlhash_' . intval($inf['url_hash'])) . '">' .
                        '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                            'width="11" height="12"') . ' title="Delete entry" alt="" />' .
                        '</a>' .
                        '</td>';

                    // Error:
                    $eMsg = ($duplicates[$inf['content']] && $duplicates[$inf['content']] !== $row['row']['uid'] ? $this->pObj->doc->icons(2) . 'Already used on page ID ' . $duplicates[$inf['content']] . '<br/>' : '');
                    $tCells[] = '<td>' . $eMsg . '</td>';

                    // Timestamp:
                    $tCells[] = '<td>' . htmlspecialchars(t3lib_BEfunc::datetime($inf['tstamp'])) . ' / ' . htmlspecialchars(t3lib_BEfunc::calcAge(time() - $inf['tstamp'])) . '</td>';

                    // Compile Row:
                    $output .= '
						<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
							' . implode('
							', $tCells) . '
						</tr>';
                    $cc++;

                    $countDisplayed++;

                    if (!isset($duplicates[$inf['content']])) {
                        $duplicates[$inf['content']] = $row['row']['uid'];
                    }
                }
            }
        }

        list($count_allInTable) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('count(*) AS count',
            'tx_realurl_urlencodecache', '');

        // Create header:
        $tCells = array();
        $tCells[] = '<td>Title:</td>';
        $tCells[] = '<td>ID:</td>';
        $tCells[] = '<td>&nbsp;</td>';
        $tCells[] = '<td>Host | GET variables:</td>';
        $tCells[] = '<td>Internal Extras:</td>';
        $tCells[] = '<td>Path:</td>';
        $tCells[] = '<td>&nbsp;</td>';
        $tCells[] = '<td>Errors:</td>';
        $tCells[] = '<td>Timestamp:</td>';

        $output = '
			<tr class="bgColor5 tableheader">
				' . implode('
				', $tCells) . '
			</tr>' . $output;

        // Compile final table and return:
        $output = '

		<br/>
		<br/>
		Displayed entries: <b>' . $countDisplayed . '</b> ' .
            '<a href="' . $this->linkSelf('&cmd=deleteEC&entry=displayed') . '">' .
            '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                'width="11" height="12"') . ' title="Delete displayed entries" alt="" />' .
            '</a>' .
            '<br/>
		Total entries in encode cache: <b>' . $count_allInTable['count'] . '</b> ' .
            '<a href="' . $this->linkSelf('&cmd=deleteEC&entry=all') . '">' .
            '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                'width="11" height="12"') . ' title="Delete WHOLE encode cache!" alt="" />' .
            '</a>' .
            '<br/>
		<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">' . $output . '
		</table>';

        return $output;
    }

    /**
     *
     */
    public function clearDEncodeCache($cmd, $decodeCache = false)
    {
        $table = $decodeCache ? 'tx_realurl_urldecodecache' : 'tx_realurl_urlencodecache';

        list($keyword, $id) = explode('_', $cmd);

        switch ((string)$keyword) {
            case 'all':
                $GLOBALS['TYPO3_DB']->exec_DELETEquery($table, '');
                break;
            case 'page':
                $GLOBALS['TYPO3_DB']->exec_DELETEquery($table, 'page_id=' . intval($id));
                break;
            case 'urlhash':
                $GLOBALS['TYPO3_DB']->exec_DELETEquery($table, 'url_hash=' . intval($id));
                break;
            default:
                break;
        }
    }









    /*****************************
     *
     * Unique Alias
     *
     *****************************/

    /**
     * Shows the mapping between aliases and unique IDs of arbitrary tables
     *
     * @return    string        HTML
     */
    public function uniqueAlias()
    {
        $tableName = t3lib_div::_GP('table');
        $cmd = t3lib_div::_GET('cmd');
        $entry = t3lib_div::_GET('entry');
        $search = t3lib_div::_POST('search');

        // Select rows:
        $overviewRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('tablename,count(*) as number_of_rows',
            'tx_realurl_uniqalias', '', 'tablename', '', '', 'tablename');

        if ($tableName && isset($overviewRows[$tableName])) {    // Show listing of single table:

            // Some Commands:
            if ($cmd === 'delete') {
                if ($entry === 'ALL') {
                    $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_uniqalias',
                        'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName, 'tx_realurl_uniqalias'));
                } else {
                    $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_uniqalias',
                        'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName,
                            'tx_realurl_uniqalias') . ' AND uid=' . intval($entry));
                }
            }
            if ($cmd === 'flushExpired') {
                $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_uniqalias',
                    'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName,
                        'tx_realurl_uniqalias') . ' AND expire>0 AND expire<' . intval(time()));
            }

            // Select rows:
            $tableContent = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                '*',
                'tx_realurl_uniqalias',
                'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tableName, 'tx_realurl_uniqalias') .
                ($search ? ' AND (value_id=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($search,
                        $tableName) . ' OR value_alias LIKE \'%' . $GLOBALS['TYPO3_DB']->quoteStr($search,
                        $tableName) . '%\')' : ''),
                '',
                'value_id, lang, expire'
            );

            $cc = 0;
            $field_id = $field_alias = $output = '';
            $duplicates = array();
            foreach ($tableContent as $aliasRecord) {
                // Add data:
                $tCells = array();
                $tCells[] = '<td>' . htmlspecialchars($aliasRecord['value_id']) . '</td>';

                if ((string)$cmd === 'edit' && ($entry === 'ALL' || !strcmp($entry, $aliasRecord['uid']))) {
                    $tCells[] = '<td>' .
                        '<input type="text" name="edit[' . $aliasRecord['uid'] . ']" value="' . htmlspecialchars($aliasRecord['value_alias']) . '" />' .
                        ($entry !== 'ALL' ? $this->saveCancelButtons('') : '') .
                        '</td>';
                } else {
                    $tCells[] = '<td' . ($aliasRecord['expire'] ? ' style="font-style: italic; color:#999999;"' : '') . '>' . htmlspecialchars($aliasRecord['value_alias']) . '</td>';
                }

                $tCells[] = '<td>' . htmlspecialchars($aliasRecord['lang']) . '</td>';
                $tCells[] = '<td' . ($aliasRecord['expire'] && $aliasRecord['expire'] < time() ? ' style="color: red;"' : '') . '>' . htmlspecialchars(t3lib_BEfunc::dateTimeAge($aliasRecord['expire'])) . '</td>';

                $tCells[] = '<td>' .
                    // Edit link:
                    '<a href="' . $this->linkSelf('&table=' . rawurlencode($tableName) . '&cmd=edit&entry=' . $aliasRecord['uid']) . '">' .
                    '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/edit2.gif',
                        'width="11" height="12"') . ' title="" alt="" />' .
                    '</a>' .
                    // Delete link:
                    '<a href="' . $this->linkSelf('&table=' . rawurlencode($tableName) . '&cmd=delete&entry=' . $aliasRecord['uid']) . '">' .
                    '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                        'width="11" height="12"') . ' title="" alt="" />' .
                    '</a>' .
                    '</td>';

                $keyForDuplicates = $aliasRecord['value_alias'] . ':::' . $aliasRecord['lang'];
                $tCells[] = '<td>' .
                    (isset($duplicates[$keyForDuplicates]) ? $this->pObj->doc->icons(2) . 'Already used by ID ' . $duplicates[$aliasRecord['value_alias']] : '&nbsp;') .
                    '</td>';

                $field_id = $aliasRecord['field_id'];
                $field_alias = $aliasRecord['field_alias'];

                // Compile Row:
                $output .= '
					<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
						' . implode('
						', $tCells) . '
					</tr>';
                $cc++;

                $duplicates[$keyForDuplicates] = $aliasRecord['value_id'];
            }

            // Create header:
            $tCells = array();
            $tCells[] = '<td>ID (Field: ' . $field_id . ')</td>';
            $tCells[] = '<td>Alias (Field: ' . $field_alias . '):</td>';
            $tCells[] = '<td>Lang:</td>';
            $tCells[] = '<td>Expire:' .
                (!$search ? '<a href="' . $this->linkSelf('&table=' . rawurlencode($tableName) . '&cmd=flushExpired') . '">' .
                    '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                        'width="11" height="12"') . ' title="Flush expired" alt="" />' .
                    '</a>' : '') .
                '</td>';
            $tCells[] = '<td>' .
                (!$search ? '<a href="' . $this->linkSelf('&table=' . rawurlencode($tableName) . '&cmd=edit&entry=ALL') . '">' .
                    '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/edit2.gif',
                        'width="11" height="12"') . ' title="Edit all" alt="" />' .
                    '</a>' .
                    '<a href="' . $this->linkSelf('&table=' . rawurlencode($tableName) . '&cmd=delete&entry=ALL') . '" onclick="return confirm(\'Delete all?\');">' .
                    '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                        'width="11" height="12"') . ' title="Delete all" alt="" />' .
                    '</a>' : '') .
                '</td>';
            $tCells[] = '<td>Error:</td>';

            $output = '
				<tr class="bgColor5 tableheader">
					' . implode('
					', $tCells) . '
				</tr>' . $output;
            // Compile final table and return:
            $output = '

			<br/>
			Table: <b>' . htmlspecialchars($tableName) . '</b><br/>
			Aliases: <b>' . htmlspecialchars(count($tableContent)) . '</b><br/>
			Search: <input type="text" name="search" value="' . htmlspecialchars($search) . '" /><input type="submit" name="_" value="Search" />
			<input type="hidden" name="table" value="' . htmlspecialchars($tableName) . '" />
			<input type="hidden" name="id" value="' . htmlspecialchars($this->pObj->id) . '" />
			<br/><br/>
			<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">' . $output . '
			</table>';

            if ($entry === 'ALL') {
                $output .= $this->saveCancelButtons('<input type="hidden" name="table" value="' . htmlspecialchars($tableName) . '" /><input type="hidden" name="id" value="' . htmlspecialchars($this->pObj->id) . '" />');
            }
        } else {    // Create overview:
            $cc = 0;
            $output = '';
            if (count($overviewRows)) {
                foreach ($overviewRows as $aliasRecord) {

                    // Add data:
                    $tCells = array();
                    $tCells[] = '<td><a href="' . $this->linkSelf('&table=' . rawurlencode($aliasRecord['tablename'])) . '">' . $aliasRecord['tablename'] . '</a></td>';
                    $tCells[] = '<td>' . $aliasRecord['number_of_rows'] . '</td>';

                    // Compile Row:
                    $output .= '
						<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
							' . implode('
							', $tCells) . '
						</tr>';
                    $cc++;
                }

                // Create header:
                $tCells = array();
                $tCells[] = '<td>Table:</td>';
                $tCells[] = '<td>Aliases:</td>';

                $output = '
					<tr class="bgColor5 tableheader">
						' . implode('
						', $tCells) . '
					</tr>' . $output;

                // Compile final table and return:
                $output = '
				<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">' . $output . '
				</table>';
            }
        }

        return $output;
    }

    /**
     * Changes the "alias" value of an entry in the unique alias table
     *
     * @param    integer        UID of unique alias
     * @param    string        New value for the alias
     * @return    void
     */
    public function editUniqAliasEntry($cache_id, $value)
    {
        $field_values = array(
            'value_alias' => $value
        );
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_uniqalias', 'uid=' . intval($cache_id), $field_values);
    }

    /**
     * Will look for submitted unique alias entries to save
     *
     * @return    void
     */
    public function edit_save_uniqAlias()
    {
        if (t3lib_div::_POST('_edit_save')) {
            $editArray = t3lib_div::_POST('edit');
            foreach ($editArray as $cache_id => $value) {
                $this->editUniqAliasEntry($cache_id, trim($value));
            }
        }
    }











    /*****************************
     *
     * Configuration view:
     *
     *****************************/

    /**
     * Shows configuration of the extension.
     *
     * @return string
     */
    public function configView()
    {
        $arrayBrowser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Lowlevel\Utility\ArrayBrowser::class);
        $arrayBrowser->expAll = true;
        $arrayBrowser->fixedLgd = false;
        $arrayBrowser->dontLinkVar = true;

        // Create the display code:
        $theVar = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
        $tree = $arrayBrowser->tree($theVar, '', '');

        $tree = '<hr/>
		<b>$TYPO3_CONF_VARS[\'EXTCONF\'][\'realurl\']</b>
		<br/>
		<span class="nobr">' . $tree . '</span>';

        return $tree;
    }











    /*****************************
     *
     * Log view:
     *
     *****************************/

    /**
     * View error log
     *
     * @return    string        HTML
     */
    public function logView()
    {
        $cmd = t3lib_div::_GP('cmd');
        if ($cmd === 'deleteAll') {
            $GLOBALS['TYPO3_DB']->exec_DELETEquery(
                'tx_realurl_errorlog',
                ''
            );
        }

        $list = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_realurl_errorlog',
            '',
            '',
            'counter DESC, tstamp DESC',
            100
        );

        if (is_array($list)) {
            $output = '';
            $cc = 0;

            foreach ($list as $rec) {
                $host = '';
                if ($rec['rootpage_id'] != 0) {
                    if (isset($hostCacheName[$rec['rootpage_id']])) {
                        $host = $hostCacheName[$rec['rootpage_id']];
                    } else {
                        $hostCacheName[$rec['rootpage_id']] = $host = $this->getHostName($rec['rootpage_id']);
                    }
                }

                // Add data:
                $tCells = array();
                $tCells[] = '<td>' . $rec['counter'] . '</td>';
                $tCells[] = '<td>' . t3lib_BEfunc::dateTimeAge($rec['tstamp']) . '</td>';
                $tCells[] = '<td><a href="' . htmlspecialchars($host . '/' . $rec['url']) . '" target="_blank">' . ($host ? $host . '/' : '') . htmlspecialchars($rec['url']) . '</a>' . '</td>';
                $tCells[] = '<td>' . htmlspecialchars($rec['error']) . '</td>';
                $tCells[] = '<td>' .
                    ($rec['last_referer'] ? '<a href="' . htmlspecialchars($rec['last_referer']) . '" target="_blank">' . htmlspecialchars($rec['last_referer']) . '</a>' : '&nbsp;') .
                    '</td>';
                $tCells[] = '<td>' . t3lib_BEfunc::datetime($rec['cr_date']) . '</td>';

                // Compile Row:
                $output .= '
					<tr class="bgColor' . ($cc % 2 ? '-20' : '-10') . '">
						' . implode('
						', $tCells) . '
					</tr>';
                $cc++;
            }
            // Create header:
            $tCells = array();
            $tCells[] = '<td>Counter:</td>';
            $tCells[] = '<td>Last time:</td>';
            $tCells[] = '<td>URL:</td>';
            $tCells[] = '<td>Error:</td>';
            $tCells[] = '<td>Last Referer:</td>';
            $tCells[] = '<td>First time:</td>';

            $output = '
				<tr class="bgColor5 tableheader">
					' . implode('
					', $tCells) . '
				</tr>' . $output;

            // Compile final table and return:
            $output = '
			<br/>
				<a href="' . $this->linkSelf('&cmd=deleteAll') . '">' .
                '<img' . t3lib_iconWorks::skinImg($this->pObj->doc->backPath, 'gfx/garbage.gif',
                    'width="11" height="12"') . ' title="Delete All" alt="" />' .
                ' Flush log</a>
				<br/>
			<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">' . $output . '
			</table>';

            return $output;
        }
    }

    public function getHostName($rootpage_id)
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'] as $host => $config) {
            if ($host != '_DEFAULT') {
                $hostName = $host;
                while ($config !== false && !is_array($config)) {
                    $host = $config;
                    $config = (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$host]) ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$host] : false);
                }
                if (is_array($config) && isset($config['pagePath']) && isset($config['pagePath']['rootpage_id']) && $config['pagePath']['rootpage_id'] == $rootpage_id) {
                    return 'http://' . $hostName;
                }
            }
        }

        return '';
    }

    /**
     * Rendering the  information table.
     *
     * @param    array        The Page tree data
     * @return    string        HTML for the information table.
     */
    protected function renderTable(&$tree)
    {
        global $LANG;
        // Title length:
        $titleLen = $GLOBALS['BE_USER']->uc['titleLen'];

        // Put together the TREE:
        $output = '';
        $languageList = $this->getSystemLanguages();

        //traverse Tree:
        $rows = 0;
        foreach ($tree->tree as $data) {
            $tCells = array();
            $editUid = $data['row']['uid'];
            //check actions:
            if (t3lib_div::_GP('_action_clearvisible') != '') {
                $this->cachemgmt->delCacheForCompletePid($editUid);
            }
            if (t3lib_div::_GP('_action_dirtyvisible') != '') {
                $this->cachemgmt->markAsDirtyCompletePid($editUid);
            }

            //first cell (tree):
            // Page icons / titles etc.
            $tCells[] = '<td' . ($data['row']['_CSSCLASS'] ? ' class="' . $data['row']['_CSSCLASS'] . '"' : '') . '>' . $data['HTML'] . htmlspecialchars(t3lib_div::fixed_lgd_cs($data['row']['title'],
                    $titleLen)) . (strcmp($data['row']['nav_title'],
                    '') ? ' [Nav: <em>' . htmlspecialchars(t3lib_div::fixed_lgd_cs($data['row']['nav_title'],
                        $titleLen)) . '</em>]' : '') . '</td>';
            //language cells:
            foreach ($languageList as $language) {
                if ($language['uid'] === '') {
                    continue;
                }

                $langId = $language['uid'];
                if (t3lib_div::_GP('_action_regenerate') != '') {
                    $url = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id=' . $editUid . '&no_cache=1&L=' . $langId;
                    fopen($url, 'r');
                }
                $info = '';
                $params = '&edit[pages][' . $editUid . ']=edit';

                $this->cachemgmt->setLanguageId($langId);
                $cacheRow = $this->cachemgmt->getCacheRowForPid($editUid);
                $cacheHistoryRows = $this->cachemgmt->getCacheHistoryRowsForPid($editUid);
                $isValidCache = $this->cachemgmt->_isCacheRowStillValid($cacheRow);
                $hasEntry = false;
                $path = '';
                if (is_array($cacheRow)) {
                    $hasEntry = true;
                    $path = $cacheRow['path'] . ' <small style="color: #555"><i>' . ($cacheRow['dirty'] ? 'X' : '') . '(' . $cacheRow['rootpid'] . ')</i></small>';
                }
                if ($this->pathgen->isDelegationDoktype($data['row']['doktype'])) {
                    $path .= ' [Delegation]';
                }
                if (count($cacheHistoryRows) > 0) {
                    $path .= '[History:' . count($cacheHistoryRows) . ']';
                }
                if ($isValidCache) {
                    $status = 'c-ok';
                } elseif ($hasEntry) {
                    $status = 'c-ok-expired';
                } elseif ($data['row']['doktype'] == 4) {
                    $path = '--- [shortcut]';
                    $status = 'c-shortcut';
                } elseif ($this->pathgen->isDelegationDoktype($data['row']['doktype'])) {
                    $status = 'c-delegation';
                } else {
                    $status = 'c-nok';
                }
                $viewPageLink = '<a href="#" onclick="' . htmlspecialchars(t3lib_BEfunc::viewOnClick($data['row']['uid'],
                        $GLOBALS['BACK_PATH'], '', '', '',
                        '&L=###LANG_UID###')) . '">' . '<img' . t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],
                        'gfx/zoom.gif', 'width="12" height="12"') . ' title="' . $LANG->getLL('lang_viewPage',
                        '1') . '" border="0" alt="" />' . '</a>';
                $viewPageLink = str_replace('###LANG_UID###', $langId, $viewPageLink);
                if ($langId == 0) {
                    //Default
                    //"View page" link is created:
                    $viewPageLink = '<a href="#" onclick="' . htmlspecialchars(t3lib_BEfunc::viewOnClick($data['row']['uid'],
                            $GLOBALS['BACK_PATH'], '', '', '',
                            '&L=###LANG_UID###')) . '">' . '<img' . t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],
                            'gfx/zoom.gif', 'width="12" height="12"') . ' title="' . $LANG->getLL('lang_viewPage',
                            '1') . '" border="0" alt="" />' . '</a>';
                    $info .= '<a href="#" onclick="' . htmlspecialchars(t3lib_BEfunc::editOnClick($params,
                            $GLOBALS['BACK_PATH'])) . '">' . '<img' . t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],
                            'gfx/edit2.gif',
                            'width="11" height="12"') . ' title="' . $LANG->getLL('lang_editDefaultLanguagePage',
                            '1') . '" border="0" alt="" />' . '</a>';
                    /*	$info.= '<a href="#" onclick="'.htmlspecialchars('top.loadEditId('.intval($data['row']['uid']).',"&SET[language]=0"); return false;').'">'.
                            '<img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/edit_page.gif','width="12" height="12"').' title="'.$LANG->getLL('lang_editPage','1').'" border="0" alt="" />'.
                            '</a>';
                            */
                    $info .= str_replace('###LANG_UID###', '0', $viewPageLink);
                    $info .= $path;
                    // Put into cell:
                    $tCells[] = '<td class="' . $status . ' c-leftLine">' . $info . '</td>';
                } else {

                    //Normal Languages:
                    $tCells[] = '<td class="' . $status . ' c-leftLine">' . $viewPageLink . $path . '</td>';
                }
            }
            $rows++;
            $output .= '
			<tr' . (($rows % 2) ? ' class="odd"' : '') . '>
				' . implode('
				', $tCells) . '
			</tr>';
        }
        //first ROW:
        //****************
        $firstRowCells[] = '<td style="min-width:300px">' . $LANG->getLL('lang_renderl10n_page', '1') . ':</td>';
        foreach ($languageList as $language) {
            if ($language['uid'] !== '') {
                $firstRowCells[] = '<td class="c-leftLine">' . $language['title'] . ' [' . $language['uid'] . ']</td>';
            }
        }
        $output = '
			<tr class="bgColor2">
				' . implode('
				', $firstRowCells) . '
			</tr>' . $output;
        $output = '

		<table border="0" cellspacing="0" cellpadding="0" id="langTable">' . $output . '
		</table>';

        return $output;
    }
}
