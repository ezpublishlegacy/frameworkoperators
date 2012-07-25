<?php

class PageDataOperator
{
	var $Operators;

	// Internal version of the $persistent_variable used on view that don't support it
	static protected $PersistentVariable=null;

	function __construct(){
		$this->Operators = array('pagedata', 'pagedata_set', 'pagedata_merge', 'pagedata_get', 'pagedata_delete', 'pagedata_exit');
	}

	function &operatorList(){
		return $this->Operators;
	}

	function namedParameterPerOperator(){
		return true;
	}

	function namedParameterList(){
		return array(
			'pagedata'=>array(
				'params'=>array('type'=>'array', 'required'=>false, 'default'=>array())
			),
			'pagedata_set'=>array(
				'key'=>array('type'=>'string', 'required'=>true, 'default'=>false),
				'value'=>array('type'=>'mixed', 'required'=>true, 'default'=>false),
				'allow_boolean'=>array('type'=>'array', 'required'=>false, 'default'=>true),
				'append'=>array('type'=>'boolean', 'required'=>false, 'default'=>false)
			),
			'pagedata_merge'=>array(
				'hash'=>array('type'=>'array', 'required'=>true, 'default'=>false),
				'allow_boolean'=>array('type'=>'array', 'required'=>false, 'default'=>true),
				'append'=>array('type'=>'boolean', 'required'=>false, 'default'=>false)
			),
			'pagedata_get'=>array(
				'variable'=>array('type'=>'string', 'required'=>true, 'default'=>''),
				'delete'=>array('type'=>'boolean', 'required'=>false, 'default'=>false)
			),
			'pagedata_delete'=>array(
				'variable'=>array('type'=>'string', 'required'=>true, 'default'=>'')
			),
			'pagedata_exit'=>array()
		);
	}

	function modify(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters){
		switch($operatorName){
			case 'pagedata_set':{
				if($namedParameters['allow_boolean'] || (!$namedParameters['allow_boolean'] && $Value)){
					self::setPersistentVariable($namedParameters['key'], $namedParameters['value'], $tpl, $namedParameters['append']);
					// set the template persistent_variable to the new persistent_variable value
					if($tpl->hasVariable('persistent_variable')){
						$tpl->setVariable('persistent_variable', self::$PersistentVariable);
					}
				}
				break;
			}
			case 'pagedata_merge':{
				if($namedParameters['hash']){
					foreach($namedParameters['hash'] as $Key=>$Value){
						if($namedParameters['allow_boolean'] || (!$namedParameters['allow_boolean'] && $Value)){
							self::setPersistentVariable($Key, $Value, $tpl, $namedParameters['append']);
						}
					}
					// set the template persistent_variable to the new persistent_variable value
					if($tpl->hasVariable('persistent_variable')){
						$tpl->setVariable('persistent_variable', self::$PersistentVariable);
					}
				}
				break;
			}
			case 'pagedata_get':{
				$operatorValue = self::getPersistentVariable($namedParameters['variable']);
				if($namedParameters['delete']){
					self::deletePersistentVariable($namedParameters['variable']);
				}
				break;
			}
			case 'pagedata_delete':{
				self::deletePersistentVariable($namedParameters['variable']);
				break;
			}
			case 'pagedata_exit':{
				eZDebug::accumulatorStart('is_production', 'pagelayout_total', 'Production Server Check');
				if(file_exists('/mnt/ebs/iamproduction.txt')){
					$ShowDebug = false;
					$ModuleParameters = PageData::moduleParameters();
					if(isset($ModuleParameters['user_parameters'])  && isset($ModuleParameters['user_parameters']['debug'])){
						$ShowDebug = $ModuleParameters['user_parameters']['debug']=='true';
					}
					$GLOBALS['eZDebugEnabled'] = $ShowDebug;
				}
//			if (file_exists("/mnt/ebs/iamproduction.txt")) {
//				$GLOBALS['eZDebugEnabled'] = false;
//			} else {
//				$ini = eZINI::instance();
//				$ini->setVariable('OutputSettings', 'OutputFilterName', '');
//			}
				eZDebug::accumulatorStop('is_production');

				eZDebug::accumulatorStop('pagelayout_total');
				break;
			}
			default:{
				eZDebug::createAccumulatorGroup('pagelayout_total', 'Page Layout Template Total');
				eZDebug::accumulatorStart('pagelayout_total');
				self::pagedata($tpl, $operatorValue, $namedParameters['params'] ? $namedParameters['params'] : array());
			}
		}
		return true;
	}

	// reusable function for deleting the persistent_variable or a persistent_variable item
	static public function deletePersistentVariable($key=null){
		if($key!==null && !is_bool($key) && isset(self::$PersistentVariable[$key])){
			unset(self::$PersistentVariable[$key]);
			if(empty(self::$PersistentVariable)){
				self::deletePersistentVariable(true);
			}
			return true;
		}elseif($key===true){
			self::$PersistentVariable = null;
			return true;
		}
		return false;
	}

	// reusable function for getting persistent_variable
	static public function getPersistentVariable($key=null){
		return ($key!==null) ? (isset(self::$PersistentVariable[$key]) ? self::$PersistentVariable[$key] : null) : self::$PersistentVariable;
	}

	// primary function to restrive information about the page
	static public function pagedata($tpl, &$operatorValue, $parameters){
		eZDebug::createAccumulatorGroup('pagedata_total', 'Page Data Total');

		eZDebug::accumulatorStart('pagedata_total');
		eZDebug::accumulatorStart('pagedata','pagedata_total', 'Page Data Class');
		$PageData = new PageData($tpl, $parameters, $Result);
		eZDebug::accumulatorStop('pagedata');

//		eZDebug::writeDebug($PageData,'Page Data');

		eZDebug::accumulatorStart('pagedata_operator', 'pagedata_total', 'Page Data Operator');
		$CurrentNode = $PageData->getTemplateVariable('current_node');

		$hasTitleOverride = false;
		if($Result['is_content'] && $CurrentNodeID=$PageData->getTemplateVariable('current_node_id')){
			$CurrentDataMap = $CurrentNode->dataMap();
			// determine page title
			eZDebug::accumulatorStart('title', 'pagedata_total', 'Page Title');
			if(isset($CurrentDataMap['meta_title']) && $CurrentDataMap['meta_title']->hasContent()){
				$hasTitleOverride = true;
				$Result['title'] = $CurrentDataMap['meta_title']->content();
			}else{
				$Result['title'] = preg_replace('/\n|\t/s', '', TemplateDataOperator::includeTemplate($tpl, 'design:page_toppath.tpl', array(
					'text_only' =>true,
					'reverse' => true,
					'current_node' => $CurrentNode
				)));
			}
			eZDebug::accumulatorStop('title');

			// determine page meta information
			eZDebug::accumulatorStart('meta', 'pagedata_total', 'Page Meta Information');
			if($PageData->AllowedMetaNames && is_array($PageData->AllowedMetaNames) && count($PageData->AllowedMetaNames)){
				foreach($PageData->TemplateVariables['site']['meta'] as $Key=>$Item){
					if(!$Result['is_error'] && in_array($Key, $PageData->AllowedMetaNames)){
							if(isset($CurrentDataMap["meta_$Key"]) && $CurrentDataMap["meta_$Key"]->hasContent()){
								$PageData->TemplateVariables['site']['meta'][$Key] = $CurrentDataMap["meta_$Key"]->content();
								continue;
							}
					}
					if(empty($PageData->TemplateVariables['site']['meta'][$Key])){
						unset($PageData->TemplateVariables['site']['meta'][$Key]);
					}
				}
			}
			eZDebug::accumulatorStop('meta');
		}
		if(!$hasTitleOverride){
			$Result['title'] .= ' - '.$PageData->TemplateVariables['site']['title'];
		}

		// set infoboxes
		if($PageData->useInfoboxes()){
			eZDebug::createAccumulatorGroup('process_infoboxes', 'Process Infoboxes');
			$InfoboxItems = array('left'=>array(), 'right'=>array(), 'top'=>array(), 'bottom'=>array());
			$InfoboxPositions = array_keys($InfoboxItems);
			$InfoboxSettings = $PageData->InfoboxSettings;
			$DefaultPosition = $InfoboxSettings['InfoboxDefaultPosition'];
			$InfoboxTopNodeID = $InfoboxSettings['InfoboxTopNodeID'];
			$InfoboxParameters = array(
				'Depth' => 1,
				'SortBy' => array('priority', true),
				'ClassFilterType' => 'include',
				'ClassFilterArray' => array('infobox')
			);

			$CurrentPath = array_reverse($CurrentNode->pathArray());
			$InfoboxLimit = false;
			do{
				$PathNodeID = current($CurrentPath);
				if($InfoboxSettings['InfoboxPersistence'] && ($PathNodeID!=$CurrentNode->NodeID)){
					$InfoboxParameters=array_merge($InfoboxParameters, array(
						'AttributeFilter' => array(array('infobox/'.$InfoboxSettings['InfoboxPersistent'], '=', 1))
					));
				}
				$InfoboxNodeList = eZContentObjectTreeNode::subTreeByNodeID($InfoboxParameters, $PathNodeID);
				if($InfoboxNodeList){
					if($InfoboxSettings['InfoboxPositioning']){
						foreach($InfoboxNodeList as $InfoboxNode){
							$Position = self::getInfoboxPosition($InfoboxNode, $InfoboxSettings['InfoboxPosition']);
							if(is_numeric($Position)){
								$Position = $InfoboxPositions[$Position];
							}
							$InfoboxItems[$Position ? $Position : $DefaultPosition][] = $InfoboxNode;
						}
					}else{
						$InfoboxItems[$DefaultPosition] = array_merge($InfoboxItems[$DefaultPosition], $InfoboxNodeList);
					}
				}
				// test "next" to break loop if at the end of the array
				if(in_array($PathNodeID,$InfoboxTopNodeID) || (next($CurrentPath)===false)){
					$InfoboxLimit = true;
				}
			}while(!$InfoboxLimit);

			$isInfoboxAccumulating = false;
			foreach($InfoboxItems as $Key=>$Item){
				$ItemCount = count($Item);
				if(!$ItemCount){
					$InfoboxItems[$Key] = false;
					continue;
				}
				if($ItemCount){
					if($InfoboxSettings['ProcessInfobox'] && isset($InfoboxSettings['InfoboxTemplate'])){
						if(is_array($InfoboxItems[$Key])){$InfoboxItems[$Key] = '';}
						if(!$isInfoboxAccumulating){
//							eZDebug::addTimingPoint('Begin Processing Infoboxes');
							eZDebug::accumulatorStart('process_infoboxes', 'pagedata_total', 'Process Infoboxes');
							$isInfoboxAccumulating = true;
						}
						foreach($Item as $Index=>$Infobox){
							$InfoboxItems[$Key] .= TemplateDataOperator::includeTemplate($tpl, 'design:'.$InfoboxSettings['InfoboxTemplate'], array('node'=>$Infobox));
						}
					}
					$Result['has_infoboxes'] = true;
				}
			}
			if($isInfoboxAccumulating){
//				eZDebug::addTimingPoint('End Processing Infoboxes');
				eZDebug::accumulatorStop('process_infoboxes');
			}

			// check to make sure that "has_sidebar" is set to true when there are infoboxes
			if(!$Result['has_sidebar'] && $Result['has_infoboxes'] && $InfoboxItems['left']){
				$Result['has_sidebar'] = true;
			}

			// check to make sure that "has_extrainfo" is set to true when there are infoboxes
			if(!$Result['has_extrainfo'] && $Result['has_infoboxes'] && $InfoboxItems['right']){
				$Result['has_extrainfo'] = true;
			}

			$PageData->TemplateVariables['infoboxes'] = $InfoboxItems;
		}

		// set the new values for the site classes based on configured results
		if($Result['homepage']){
			$PageData->TemplateVariables['site_classes']['pagetype'] = 'homepage';
		}
		if($Result['has_sidebar']){
			$PageData->TemplateVariables['site_classes']['sidebar'] = 'sidebar';
		}
		if($Result['has_extrainfo']){
			$PageData->TemplateVariables['site_classes']['extrainfo'] = 'extrainfo';
		}

		// set the page title from a persistent value or the name of a content object tree node
		$Result['page_title'] = false;
		if($Result['is_content'] && !$PageData->isSectionRoot){
			$PersistentPageTitle = $PageData->hasPersistentPageTitle();
			$Result['page_title'] = $PersistentPageTitle ? $PersistentPageTitle : $PageData->TemplateVariables['current_node']->Name;
		}

		$operatorValue = $Result;
		PageData::setTemplateVariables($PageData, $tpl);

		eZDebug::accumulatorStop('pagedata_operator');
		eZDebug::accumulatorStop('pagedata_total');
		return true;
	}

	// reusable function for setting persistent_variable
	static public function setPersistentVariable($key, $Value, $tpl, $append=false){
		$PersistentVariable=array();
		if($tpl->hasVariable('persistent_variable') && is_array($tpl->variable('persistent_variable'))){
			$PersistentVariable = $tpl->variable('persistent_variable');
		}else if(self::$PersistentVariable!==null && is_array(self::$PersistentVariable)){
			$PersistentVariable = self::$PersistentVariable;
		}
		if($append){
			if(isset($PersistentVariable[$key]) && is_array($PersistentVariable[$key])){
				$PersistentVariable[$key][] = $Value;
			}else{
				$PersistentVariable[$key] = array($Value);
			}
		}else{
			$PersistentVariable[$key] = $Value;
		}
		// storing the value internally as well in case this is not a view that supports persistent_variable (pagedata will look for it)
		eZDebug::writeDebug($PersistentVariable);
		self::$PersistentVariable = $PersistentVariable;
		// set the template persistent_variable to the new persistent_variable value
		if($tpl->hasVariable('persistent_variable')){
			$tpl->setVariable('persistent_variable', self::$PersistentVariable);
		}
	}

	static private function getInfoboxPosition($node, $identifier){
		$DataMap = $node->dataMap();
		return current($DataMap[$identifier]->content());
	}

}

?>
