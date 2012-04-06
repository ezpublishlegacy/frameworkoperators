<?php

class PageDesignOperator
{
	var $Operators;

	function __construct(){
		$this->Operators=array('pagedesign_stylesheets', 'pagedesign_javascript');
	}

	function &operatorList(){
		return $this->Operators;
	}

	function namedParameterPerOperator(){
		return true;
	}

	function namedParameterList(){
		return array(
			'pagedesign_stylesheets'=>array(
				'is_edit'=>array('type'=>'boolean', 'required'=>false, 'default'=>false),
				'load'=>array('type'=>'boolean', 'required'=>false, 'default'=>true)
			),
			'pagedesign_javascript'=>array(
				'is_edit'=>array('type'=>'boolean', 'required'=>false, 'default'=>false),
				'load'=>array('type'=>'boolean', 'required'=>false, 'default'=>true)
			)
		);
	}

	function modify(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters, &$placement){
		eZDebug::createAccumulatorGroup('pagedesign','Page Design');
		switch($operatorName){
			case 'pagedesign_stylesheets':{
				eZDebug::accumulatorStart('head_style','pagedesign','Page Head StyleSheets');
				$StyleSheets=self::stylesheets($tpl, $namedParameters);
				if($namedParameters['load']){
					$operatorValue=self::load($tpl, 'ezcss_load', array(array(8,$StyleSheets)), $rootNamespace, $currentNamespace);
					eZDebug::accumulatorStop('head_style');
					break;
				}
				$operatorValue=$StyleSheets;
				eZDebug::accumulatorStop('head_style');
				break;
			}
			case 'pagedesign_javascript':{
				eZDebug::accumulatorStart('head_script','pagedesign','Page Head JavaScript');
				$JavaScript=self::javascript($tpl, $namedParameters);
				if($namedParameters['load']){
					$operatorValue=self::load($tpl, 'ezscript_load', array(array(8, $JavaScript)), $rootNamespace, $currentNamespace);
					eZDebug::accumulatorStop('head_script');
					break;
				}
				$operatorValue=$JavaScript;
				eZDebug::accumulatorStop('head_script');
				break;
			}
			default:{
				$operatorValue='';
				return false;
			}
		}
		return true;
	}

	static function javascript($tpl, $namedParameters){
		$JavaScriptSettings=eZINI::instance('design.ini')->group('JavaScriptSettings');

		// set default javascript files
		$JavaScript=$JavaScriptSettings['LibraryScripts'];

		// load the site and page specific javascript files
		if($JavaScriptSettings['FrontendJavaScriptList']){
			$JavaScript=array_merge($JavaScript, $JavaScriptSettings['JavaScriptList'], $JavaScriptSettings['FrontendJavaScriptList']);
		}else{
			$JavaScript=array_merge($JavaScript, $JavaScriptSettings['JavaScriptList']);
		}

		// load cufon if cufon is being used
		if(isset($JavaScriptSettings['CufonFontsList']) && $JavaScriptSettings['CufonFontsList']){
			$tpl->setVariable('has_cufon',true);
			$JavaScript=array_merge($JavaScript, array($JavaScriptSettings['CufonYUI']), $JavaScriptSettings['CufonFontsList']);
		}

		// remove excluded javascript files from the list
		if(isset($JavaScriptSettings['ExcludeJavaScriptList']) && $JavaScriptSettings['ExcludeJavaScriptList']){
			$JavaScript=array_diff($JavaScript, $JavaScriptSettings['ExcludeJavaScriptList']);
		}

		return $JavaScript;
	}

	static function stylesheets($tpl, $namedParameters){
		$StyleSheetSettings=eZINI::instance('design.ini')->group('StylesheetSettings');
		$DebugSettings=eZINI::instance('site.ini')->group('DebugSettings');

		// set default stylesheets
		$StyleSheet=$StyleSheetSettings['DefaultCSS'];

		// load the website toolbar stylesheet if user has permissions
		if($tpl->hasVariable('website_toolbar') && $tpl->variable('website_toolbar')){
			$StyleSheet[]=$StyleSheetSettings['UserCSS']['toolbar'];
		}

		// load the debug stylesheet if debug outout is being displayed
		if($DebugSettings['DebugOutput']=='enabled' && isset($StyleSheetSettings['UserCSS']['debug'])){
			$CurrentUser=eZUser::currentUser();
			if(($DebugSettings['DebugByUser']=='disabled') || ($DebugSettings['DebugByUser']=='enabled' && $CurrentUser->isLoggedIn())){
				$StyleSheet[]=$StyleSheetSettings['UserCSS']['debug'];
			}
		}

		// load the edit stylesheet if the system is in edit mode
		if($namedParameters['is_edit']){
			$StyleSheet[]=$StyleSheetSettings['UserCSS']['edit'];
		}

		// load the site and page specific stylesheets
		if($StyleSheetSettings['FrontendCSSFileList']){
			$StyleSheet=array_merge($StyleSheet, $StyleSheetSettings['CSSFileList'], $StyleSheetSettings['FrontendCSSFileList']);
		}else{
			$StyleSheet=array_merge($StyleSheet, $StyleSheetSettings['CSSFileList']);
		}

		// remove excluded stylesheets files from the list
		if(isset($StyleSheetSettings['ExcludeCSSFileList']) && $StyleSheetSettings['ExcludeCSSFileList']){
			$StyleSheet=array_diff($StyleSheet, $StyleSheetSettings['ExcludeCSSFileList']);
		}

		return $StyleSheet;
	}

	static private function load(&$tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace){
		$tpl->processOperator($operatorName, array($operatorParameters), $rootNamespace, $currentNamespace, $Result);
		return $Result['value'];
	}

}

?>