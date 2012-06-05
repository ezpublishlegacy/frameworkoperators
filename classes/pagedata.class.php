<?php

class PageData
{

	const DEFAULT_ROOT_NODE_ID = 0;
	const DEFAULT_CURRENT_NODE_ID = 0;
	const DEFAULT_TEMPLATE_LOOK = 'template_look';

	protected $hasProcessedParameters = false;
	protected $FullWidthSettings = false;

	private $FullWidth = false;

	function __construct($tpl, $parameters, &$item=null){
		$this->ModuleParameters = self::moduleParameters();
		$this->RootNodeID = self::DEFAULT_ROOT_NODE_ID;
		$this->LaunchYear = false;
		$this->ErrorData = false;
		$this->MenuSettings = false;
		$this->AllowedMetaNames = false;
		$this->InfoboxSettings = false;
		$this->TemplateVariables = false;
		$this->SectionRootClassList = false;

		$this->hasSidemenu = false;
		$this->hasSidebar = false;
		$this->hasInfoboxes = false;
		$this->hasExtrainfo = false;

		$this->isSectionRoot = false;

		self::getConfigurationSettings($this);
		self::processParameters($this, $tpl, $parameters);

		$item=array(
			'module_parameters'=>$this->ModuleParameters,
			'view_mode'=>$this->ViewMode(),
			'is_error'=>$this->isError(),
			'is_search'=>$this->isSearch(),
			'is_content'=>$this->isContent(),
			'is_edit'=>$this->inEditMode(),
			'is_full_width'=>$this->FullWidth,
			'has_sidemenu'=>$this->hasSidemenu,
			'has_sidebar'=>$this->hasSidebar,
			'has_infoboxes'=>$this->hasInfoboxes,
			'has_extrainfo'=>$this->hasExtrainfo,
			'node_id'=>$this->TemplateVariables['current_node_id'],
			'root_node_id'=>$this->RootNodeID,
			'homepage'=>$this->isHomepage(),
			'show_path'=>($this->isContent() && $this->TemplateVariables['current_node_id']!=$this->RootNodeID),
			'page_depth'=>count($this->TemplateVariables['module_result']['path']),
			'template_variables'=>array_keys($this->TemplateVariables),
			'title'=>ucwords(implode(' / ', array_reverse(explode('/', $this->TemplateVariables['uri_string']))))
		);

		if($this->ModuleParameters['module_name']=='content'){
			if(!$item['is_content']){
				if($item['view_mode']){
					$item['title'] = ucwords($item['view_mode']);
				}else{
					$item['title'] = ucwords($this->ModuleParameters['function_name']);
				}
			}
		}else if($item['is_error']){
			$item['title'] = $this->ErrorData['title'];
		}

	}

	function getCurrentNodeID($parameters=false){
		if(!$this->hasProcessedParameters){
			if($parameters && isset($parameters['current_node_id'])){
				return (int)$parameters['current_node_id'];
			}
			if($this->TemplateVariables['current_node_id']){
				return (int)$this->TemplateVariables['current_node_id'];
			}
			if($ModuleResult=$this->TemplateVariables['module_result']){
				if(isset($ModuleResult['node_id'])){
					return (int)$ModuleResult['node_id'];
				}
				if(isset($ModuleResult['path'][count($ModuleResult['path'])-1]['node_id'])){
					return (int)$ModuleResult['path'][count($ModuleResult['path'])-1]['node_id'];
				}
			}
			return self::DEFAULT_CURRENT_NODE_ID;
		}
		return isset($this->TemplateVariables['current_node_id']) ? $this->TemplateVariables['current_node_id'] : self::DEFAULT_CURRENT_NODE_ID;
	}

	function getPersistentVariable($parameters=false){
		$Default = $parameters ? $parameters : array();
		$OperatorVariable = PageDataOperator::getPersistentVariable();
		if(!$this->hasProcessedParameters){
			if($ModuleResult = $this->TemplateVariables['module_result']){
				if(isset($ModuleResult['content_info']) && isset($ModuleResult['content_info']['persistent_variable']) && $ModuleResult['content_info']['persistent_variable']){
					$Default = array_merge($Default, array('module_result'=>$ModuleResult['content_info']['persistent_variable']));
				}
			}
			if($OperatorVariable && is_array($OperatorVariable)){
				return array_merge($Default, $OperatorVariable);
			}
			return empty($Default) ? false : $Default;
		}
		return isset($this->TemplateVariables['persistent_variable']) ? $this->TemplateVariables['persistent_variable'] : PageDataOperator::getPersistentVariable();
	}

	function getTemplateLook($parameters=false){
		if(!$this->hasProcessedParameters){
			if(!isset($parameters['template_look'])){
				// determine the class identifier to use for the template look
				$parameters['template_look'] = isset($parameters['template_look_class']) ? $parameters['template_look_class'] : self::DEFAULT_TEMPLATE_LOOK;
			}
			if(!is_object($parameters['template_look']) && is_string($parameters['template_look'])){
				// determine the template look object
				$ClassID = eZContentObjectTreeNode::classIDByIdentifier($parameters['template_look']);
				if(!$ObjectList = eZContentObject::fetchFilteredList(array('contentclass_id'=>$ClassID), 0, 1)){
					return false;
				}
				$parameters['template_look'] = $ObjectList[0];
			}
			return $parameters['template_look'];
		}
		return isset($this->TemplateVariables['template_look']) ? $this->TemplateVariables['template_look'] : false;
	}

	function getTemplateVariable($key=false){
		if($key){
			return isset($this->TemplateVariables[$key]) ? $this->TemplateVariables[$key] : false;
		}
		return $this->TemplateVariables;
	}

	function hasPersistentPageTitle(){
		if(!$this->isSectionRoot && isset($this->TemplateVariables['persistent_variable']['page_title']) && $this->TemplateVariables['persistent_variable']['page_title']){
			return $this->TemplateVariables['persistent_variable']['page_title'];
		}
		return false;
	}

	function inEditMode(){
		if($this->TemplateVariables['ui_context']=='edit' && strpos($this->TemplateVariables['ui_string'],'user/edit')===false){
			return (!isset($this->TemplateVariables['module_result']['content_info']) || strpos($this->TemplateVariables['uri_string'],'content/action')===false);
		}
		return false;
	}

	function isContent(){
		$NonContentViews=array('dashboard', 'search');
		$NonContentModes=array('sitemap', 'tagcloud');
		if($this->ModuleParameters['module_name']=='content' && !in_array($this->ModuleParameters['function_name'], $NonContentViews)){
			if($ViewMode = $this->viewMode()){
				return !in_array($ViewMode, $NonContentModes);
			}
		}
		return false;
	}

	function isError(){
		return $this->ModuleParameters['module_name']=='error';
	}

	function isHomepage(){
		return ($this->isContent() && $this->TemplateVariables['current_node_id']==$this->RootNodeID);
	}

	function isSearch(){
		return $this->ModuleParameters['module_name']=='content' && $this->ModuleParameters['function_name']=='search';
	}

	function showWebsiteToolbar($parameters=false){
		if(!$this->hasProcessedParameters){
			if(!isset($parameters['website_toolbar'])){
				$CurrentUser = $this->TemplateVariables['current_user'];
				if($this->isContent() && $CurrentUser->isLoggedIn() && $CurrentUser->hasAccessTo('websitetoolbar', 'use')){
					return !($this->inEditMode() || $this->ModuleParameters['function_name']=='versionview');
				}
				return false;
			}
			return $parameters['website_toolbar'];
		}
		return isset($this->TemplateVariables['website_toolbar']) ? $this->TemplateVariables['website_toolbar'] : false;
	}

	function useInfoboxes(){
		if($this->InfoboxSettings){
			$InfoboxesEnabled = $this->InfoboxSettings['UseInfoboxes'];
			if($this->isContent() && $ClassIdentifier = $this->getTemplateVariable('class_identifier')){
				if(in_array($ClassIdentifier, $this->InfoboxSettings['ExcludeClassList'])){
					return false;
				}
			}
			return $InfoboxesEnabled && !$this->FullWidth;
		}
		return false;
	}

	function viewMode(){
		return isset($this->ModuleParameters['parameters']['ViewMode']) ? $this->ModuleParameters['parameters']['ViewMode'] : false;
	}

	static function isInEditMode($tpl=false){
		if($tpl && $tpl->hasVariable('pagedata') && $PageData=$tpl->variable('pagedata')){
			return $PageData['is_edit'];
		}
		return null;
	}

	static function moduleParameters($userParameters=true){
		if($userParameters){
			return array_merge($GLOBALS['eZRequestedModuleParams'], array('user_parameters'=>$GLOBALS['module']->UserParameters));
		}
		return $GLOBALS['eZRequestedModuleParams'];
	}

	static function setTemplateVariables($object, $tpl, $filter=true){
		if($object->TemplateVariables){
			foreach($object->TemplateVariables as $Name=>$Value){
				if($tpl->hasVariable($Name)){
					if($tpl->variable($Name)===$Value){
						continue;
					}
				}
				if($filter && is_array($Value)){
					$Value = array_filter($Value, function($item){
						return !($item===null);
					});
				}
				$tpl->setVariable($Name, $Value);
			}
		}
	}

	static private function generateModuleList($settings, $exclude=false){
		if($exclude && is_array($exclude)){
			// remove the enabled module/view pairs
			foreach($exclude as $Key=>$Value){
				if(($Index = array_search($Value, $settings))!==false){
					array_splice($settings, $Index, 1);
					continue;
				}
				$Components=explode('/', $Value);
				$ExcludeModuleList[$Components[0]][] = isset($Components[1]) ? $Components[1] : false;
			}
			// find entire module settings
			$ModuleList=array();
			foreach($settings as $Value){
				$Components=explode('/', $Value);
				if(!isset($Components[1])){
					$ModuleList[]=$Components[0];
				}
			}
			foreach($ModuleList as $Name=>$Item){
				// assume entire module is being provided
				if(isset($ExcludeModuleList[$Item])){
					$Module = eZModule::findModule($Item);
					array_remove($settings, $Item);
					$ModuleViews = array_keys($Module->Functions);
					foreach(array_diff($ModuleViews, $ExcludeModuleList[$Item]) as $View){
						$settings[]="$Item/$View";
					}
				}
			}
		}
		return $settings;
	}

	static private function getConfigurationSettings($object){
		$ConfigurationFiles = array(
			'content'=>eZINI::instance('content.ini'),
			'layout'=>eZINI::instance('layout.ini'),
			'menu'=>eZINI::instance('menu.ini'),
			'site'=>eZINI::instance('site.ini'),
			'template'=>eZINI::instance('template.ini')
		);

		if($ConfigurationFiles['content']->hasVariable('NodeSettings', 'RootNode')){
			$object->RootNodeID = (int)$ConfigurationFiles['content']->variable('NodeSettings', 'RootNode');
		}

		if($ConfigurationFiles['site']->hasVariable('SiteSettings', 'LaunchYear')){
			$object->LaunchYear = (int)$ConfigurationFiles['site']->variable('SiteSettings', 'LaunchYear');
		}

		// set menu settings
		if($ConfigurationFiles['menu']->hasGroup('MenuSettings')){
			$object->MenuSettings=$ConfigurationFiles['menu']->group('MenuSettings');
			unset($object->MenuSettings['AvailableMenuArray']);
			unset($object->MenuSettings['HideLeftMenuClasses']);
		}

		// set error data
		if($object->isError()){
			$ErrorConfiguration=eZINI::instance('error.ini');
			$object->ErrorData['error_type'] = $object->ModuleParameters['parameters']['Type'];
			if($ErrorConfiguration->hasVariable('ErrorSettings-'.$object->ErrorData['error_type'], 'HTTPError')){
				$ErrorCodes = $ErrorConfiguration->variable('ErrorSettings-'.$object->ErrorData['error_type'], 'HTTPError');
				$object->ErrorData = array_merge($object->ErrorData, array(
					'error_code' => (int)$object->ModuleParameters['parameters']['Number'],
					'http_code' => (int)$ErrorCodes[$object->ModuleParameters['parameters']['Number']]
				));
				if($ErrorConfiguration->hasVariable('HTTPError-'.$object->ErrorData['http_code'], 'HTTPName')){
					$object->ErrorData['title'] = $ErrorConfiguration->variable('HTTPError-'.$object->ErrorData['http_code'], 'HTTPName');
				}
			}
		}

		// configure full width settings
		if($ConfigurationFiles['layout']->hasGroup('FullWidthSettings')){
			$AllowFullWidth = false;
			$FullWidthSettings = $ConfigurationFiles['layout']->group('FullWidthSettings');
			if(!isset($FullWidthSettings['AllowFullWidth']) || (isset($FullWidthSettings['AllowFullWidth']) && $FullWidthSettings['AllowFullWidth']=='enabled')){
				$AllowFullWidth = true;
			}
			unset($FullWidthSettings['AllowFullWidth']);
			if($AllowFullWidth){
				foreach($FullWidthSettings as $Name=>$Settings){
					if(strpos($Name,'Exclude')===false){
						$Exclude = isset($FullWidthSettings["Exclude$Name"]) ? $FullWidthSettings["Exclude$Name"] : false;
						if($Name=='ModuleList'){
							unset($FullWidthSettings['ExcludeModuleList']);
							$FullWidthSettings[$Name] = self::generateModuleList($Settings, $Exclude);
							continue;
						}
						$FullWidthSettings[$Name] = array_diff($Settings, $Exclude ? $Exclude : array());
					}
				}
				if($object->isSearch() && in_array('content/search', $FullWidthSettings['ModuleList'])){
					if($ConfigurationFiles['site']->hasVariable('SearchSettings','DisplayFacets') && ($ConfigurationFiles['site']->variable('SearchSettings','DisplayFacets')==='enabled')){
						array_remove($FullWidthSettings['ModuleList'], 'content/search');
					}
				}
				$object->FullWidthSettings = $FullWidthSettings;
			}
		}

		if($object->isContent()){
			$object->SectionRootClassList = $ConfigurationFiles['template']->variable('FrameworkSettings', 'SectionRootClassList');
			if($ConfigurationFiles['site']->hasVariable('SiteSettings', 'AllowedMetaNames')){
				$object->AllowedMetaNames = $ConfigurationFiles['site']->variable('SiteSettings', 'AllowedMetaNames');
			}
			if($ConfigurationFiles['layout']->hasGroup('InfoboxSettings')){
				$InfoboxSettings = $ConfigurationFiles['layout']->group('InfoboxSettings');
				$Enabling = array('UseInfoboxes', 'ProcessInfobox', 'InfoboxPersistence', 'InfoboxPositioning');
				foreach($InfoboxSettings as $Setting=>$Value){
					if(in_array($Setting, $Enabling)){
						$InfoboxSettings[$Setting] = $Value==='enabled';
					}
				}
				$object->InfoboxSettings = $InfoboxSettings;
			}
		}
	}

	static private function processParameters($object, $tpl, $parameters){
		$TemplateVariables = $object->TemplateVariables = array(
			'module_result'=>$tpl->hasVariable('module_result') ? $tpl->variable('module_result') : false,
			'site'=>$tpl->hasVariable('site') ? $tpl->variable('site') : false,
			'access_type'=>$tpl->hasVariable('access_type') ? $tpl->variable('access_type') : false,
			'current_user'=>$tpl->hasVariable('current_user') ? $tpl->variable('current_user') : false,
			'ui_context'=>$tpl->variable('ui_context'),
			'uri_string'=>$tpl->variable('uri_string'),
			'persistent_variable'=>$tpl->hasVariable('persistent_variable') ? $tpl->variable('persistent_variable') : false,
			'copyright_span'=>date('Y'),
			'is_basket_empty'=>true,
			'locales'=>false,
			'has_cufon'=>false,
			'current_node_id'=>$tpl->hasVariable('current_node_id') ? $tpl->variable('current_node_id') : false
		);

		if($object->LaunchYear && $object->LaunchYear!=$TemplateVariables['copyright_span']){
			$TemplateVariables['copyright_span'] = $object->LaunchYear.' - '.$TemplateVariables['copyright_span'];
		}

		// determine the value of the persistent variable
		$PersistentVariable = $object->getPersistentVariable($parameters);
		if($TemplateVariables['persistent_variable']){
			if($TemplateVariables['persistent_variable']!==$PersistentVariable['module_result']){
				eZDebug::writeWarning('Template persistent variable does not match the module result persistent variable.', 'PageData');
			}
		}
		$TemplateVariables['persistent_variable'] = $PersistentVariable = $PersistentVariable['module_result'];

		// determine the current user
		// if not defined as a variable in the template, call the current user
		if(!isset($TemplateVariables['current_user']) || (isset($TemplateVariables['current_user']) && !$TemplateVariables['current_user'])){
			$TemplateVariables['current_user'] = eZUser::currentUser();
		}

		// check if shop basket is empty
		if($TemplateVariables['current_user']->isLoggedIn()){
			$TemplateVariables['is_basket_empty'] = ModuleTools::ModuleFunction('shop', 'basket')->isEmpty();
		}

		$TemplateVariables['locales'] = ModuleTools::ModuleFunction('content', 'translation_list');

		// determine the current node id, and fetch the current node if the node id is found
		$CurrentNodeID = $TemplateVariables['current_node_id'] = $object->getCurrentNodeID($parameters);
		if($CurrentNodeID){
			$CurrentNode = $TemplateVariables['current_node'] = eZContentObjectTreeNode::fetch($CurrentNodeID);
			$CurrentNodeClassIdentifier = $TemplateVariables['class_identifier'] = $CurrentNode->classIdentifier();
			if($object->SectionRootClassList){
				$object->isSectionRoot = in_array($TemplateVariables['class_identifier'], $object->SectionRootClassList);
			}
		}

		// get custom template_look object. false | eZContentObject (set as parameter from caller)
		$TemplateLook = $TemplateVariables['template_look'] = $object->getTemplateLook($parameters);

		// determine if the website toolbar should be shown
		$WebsiteToolbar = $TemplateVariables['website_toolbar'] = $object->showWebsiteToolbar($parameters);

		// determine if the page is full width by default
		if($object->FullWidthSettings){
			$FullWidthSettings = $object->FullWidthSettings;
			if($CurrentNodeID){
				$object->FullWidth = in_array($CurrentNodeClassIdentifier, $FullWidthSettings['ClassList']) || in_array($CurrentNodeID, $FullWidthSettings['NodeIDList']);
			}
			if(!$object->FullWidth){
				if(isset($FullWidthSettings['ModuleList']) && count($FullWidthSettings['ModuleList'])){
					foreach($FullWidthSettings['ModuleList'] as $PathString){
						if(strpos($TemplateVariables['uri_string'], $PathString)!==false){
							$object->FullWidth = true;
							break;
						}
					}
				}
			}
			// check to make sure the current page, if content node and is in the exclude list, was incorrectly set to full width by a previous condition.
			if($object->FullWidth && in_array($CurrentNodeID,$FullWidthSettings['ExcludeNodeIDList'])){
				$object->FullWidth = false;
			}
		}

		// determine default sidemenu, sidebar, and extrainfo settings
		if($CurrentNodeID && !$object->FullWidth){
			if(isset($object->MenuSettings['HideSideMenuClassList']) && isset($object->MenuSettings['SideMenuPosition'])){
				$hasSidemenu = $object->hasSidemenu = !in_array($CurrentNodeClassIdentifier, $object->MenuSettings['HideSideMenuClassList']);
				$hasSidebar = $object->hasSidebar = $hasSidemenu ? ($object->MenuSettings['SideMenuPosition']=='left') : false;
				$hasExtrainfo = $object->hasExtrainfo = $hasSidemenu ? ($object->MenuSettings['SideMenuPosition']=='right') : false;
			}
			foreach(array_reverse($CurrentNode->pathArray()) as $ID){
				if($PathNode = eZContentObjectTreeNode::fetch($ID)){
					$DataMap = $PathNode->dataMap();
					if(isset($DataMap['hide_sidemenu']) && $DataMap['hide_sidemenu']->DataInt){
						$object->hasSidemenu = false;
						break;
					}
				}
			}
			if($PersistentVariable){
				if(isset($PersistentVariable['sidebar'])){
					$object->hasSidebar = !($PersistentVariable['sidebar']==='null' || $PersistentVariable['sidebar']===false);
				}
				if(isset($PersistentVariable['extrainfo'])){
					$object->hasExtrainfo = !($PersistentVariable['extrainfo']==='null' || $PersistentVariable['extrainfo']===false);
				}
			}
		}

		// set default value for "infoboxes" template variable
		$TemplateVariables['infoboxes'] = false;

		// process infobox settings
		if($object->useInfoboxes()){
			$InfoboxClass = eZContentClass::fetchByIdentifier('infobox');
			$InfoboxDataMap = $InfoboxClass->dataMap();
			$InfoboxSettings = $object->InfoboxSettings;
			if($InfoboxSettings['InfoboxPersistence']){
				$InfoboxSettings['InfoboxPersistence'] = isset($InfoboxDataMap[$InfoboxSettings['InfoboxPersistent']]);
			}
			if($InfoboxSettings['InfoboxPositioning']){
				$InfoboxSettings['InfoboxPositioning'] = isset($InfoboxDataMap[$InfoboxSettings['InfoboxPosition']]);
			}
			if(!in_array($object->RootNodeID, $InfoboxSettings['InfoboxTopNodeID'])){
				$InfoboxSettings['InfoboxTopNodeID'][] = $object->RootNodeID;
			}
			$object->InfoboxSettings = $InfoboxSettings;
		}

		$TemplateVariables['site_classes'] = array(
			'pagetype'=>'subpage',
			'sidebar'=>'nosidebar',
			'extrainfo'=>'noextrainfo',
			'node'=>$CurrentNodeID ? 'current-node-id-'.$CurrentNodeID : 'nonode',
			'module_name'=>'module-'.$object->ModuleParameters['module_name'],
			'module_view'=>'module-view-'.$object->ModuleParameters['function_name'],
			'content_view'=>$object->viewMode() ? 'content-view-'.$object->viewMode() : 'content-view-none',
			'class_type'=>$CurrentNodeID ? 'class-'.str_replace('_','-',$CurrentNodeClassIdentifier) : null,
			'siteaccess'=>$TemplateVariables['access_type'] ? 'siteaccess-'.$TemplateVariables['access_type']['name'] : null
		);
		$TemplateVariables['content_classes'] = array(
			'main_area'=>'main-area',
			'content_view'=>$TemplateVariables['site_classes']['content_view'],
			'class_type'=>$TemplateVariables['site_classes']['class_type'],
			'error_code'=>$object->isError() ? $object->ErrorData['error_code'] : null
		);
		if($TemplateVariables['module_result']){
			$TemplateVariables['site_classes']['section'] = isset($TemplateVariables['module_result']['section_id']) ? 'section-id-'.$TemplateVariables['module_result']['section_id'] : 'nosection';
		}

		// set the additional template variables to the instance property
		$object->TemplateVariables = array_merge($object->TemplateVariables, $TemplateVariables);
		// tell the object that the parameters have been fully processed
		$object->hasProcessedParameters = true;
	}

}

?>
