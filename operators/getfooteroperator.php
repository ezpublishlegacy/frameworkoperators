<?php

class GetFooterOperator
{
	var $Operators;

	function __construct(){
	$this->Operators=array('get_footer');
	}

	function &operatorList(){
		return $this->Operators;
	}

	function namedParameterPerOperator(){
		return true;
	}

	function namedParameterList(){
		return array(
			'get_footer'=>array(
				'reset'=>array('type'=>'boolean', 'required'=>false, 'default'=>false)
			)
		);
	}

	function modify(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters){
		$DesignKeys=$tpl->Resources['design']->Keys;
		$FooterParameters=array('ClassFilterType'=>'include', 'ClassFilterArray'=>array('footer'), 'Depth'=>1);
		$ObjectNodeID=($namedParameters['reset'] && array_key_exists('node', $DesignKeys)) ? $DesignKeys['node'] : SiteUtils::ConfigSetting('NodeSettings', 'RootNode', 'content.ini');
			if ($namedParameters['reset']){
				$ObjectNode=eZContentObjectTreeNode::fetch($ObjectNodeID);
				foreach(array_reverse($ObjectNode->pathArray()) as $PathNodeID){
					if($FooterList=eZContentObjectTreeNode::subTreeByNodeID($FooterParameters, $PathNodeID)){break;}
				}
			}else{
				$FooterList=eZContentObjectTreeNode::subTreeByNodeID($FooterParameters, $ObjectNodeID);
			}
		$operatorValue=$FooterList[0];
		return true;
	}
}

?>