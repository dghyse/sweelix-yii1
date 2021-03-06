<?php
/**
 * File ClientScript.php
 *
 * PHP version 5.4+
 *
 * @author    Philippe Gaultier <pgaultier@sweelix.net>
 * @copyright 2010-2014 Sweelix
 * @license   http://www.sweelix.net/license license
 * @version   3.1.0
 * @link      http://www.sweelix.net
 * @category  behaviors
 * @package   sweelix.yii1.behaviors
 * @since     1.1
 */

namespace sweelix\yii1\behaviors;

/**
 * Class ClientScript
 *
 * This behavior implement script management for
 * element used in @see Html
 *
 * <code>
 * 	...
 *		'clientScript' => [
 *			'behaviors' => [
 *				'sweelixClientScript' => [
 *					'class' => 'sweelix\yii1\behaviors\ClientScript',
 *				],
 *			],
 *		],
 * 	...
 * </code>
 *
 * With this behavior active, we can now perform :
 * <code>
 * 	...
 * 	class MyController extends CController {
 * 		...
 * 		public function actionTest() {
 * 			...
 * 			Yii::app()->clientScript->registerSweelixScript('sweelix');
 * 			...
 * 		}
 * 		...
 * 	}
 * 	...
 * </code>
 *
 * @author    Philippe Gaultier <pgaultier@sweelix.net>
 * @copyright 2010-2014 Sweelix
 * @license   http://www.sweelix.net/license license
 * @version   3.1.0
 * @link      http://www.sweelix.net
 * @category  behaviors
 * @package   sweelix.yii1.behaviors
 * @since     1.1
 */
class ClientScript extends \CBehavior {
	public $sweelixScript=array();
	public $sweelixPackages=null;
	private $_assetUrl;
	private $_config;
	private $_shadowboxConfig;
	private $_init=false;
	private $_sbInit=false;
	/**
	 * Attaches the behavior object only if owner is instance of CClientScript
	 * or one of its derivative
	 * @see CBehavior::attach()
	 *
	 * @param CClientScript $owner the component that this behavior is to be attached to.
	 *
	 * @return void
	 * @since  1.1.0
	 */
	public function attach($owner) {
		if($owner instanceof \CClientScript) {
			parent::attach($owner);
		} else {
			throw new \CException(__CLASS__.' can only be attached ot a CClientScript instance');
		}
	}

	/**
	 * Publish assets to allow script and css appending
	 *
	 * @return string
	 * @since  1.1.0
	 */
	public function getSweelixAssetUrl() {
		if($this->_assetUrl === null) {
			$this->_assetUrl = \Yii::app()->getAssetManager()->publish(dirname(__DIR__).DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'source');
		}
		return $this->_assetUrl;
	}

	/**
	 * Register sweelix script
	 *
	 * @param string  $name      name of the package we want to register
	 * @param boolean $importCss do not load packaged css
	 *
	 * @return CClientScript
	 * @since  1.1.0
	 */
	public function registerSweelixScript($name, $importCss=true) {
		if(isset($this->sweelixScript[$name]))
			return $this->getOwner();
		if($this->sweelixPackages===null)
			$this->sweelixPackages=require(dirname(__DIR__).DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'packages.php');
		if(isset($this->sweelixPackages[$name]))
			$package=$this->sweelixPackages[$name];
		if(isset($package)) {
			if(!empty($package['depends'])) {
				foreach($package['depends'] as $p) {
					if(array_key_exists($p, $this->sweelixPackages) == true) {
						$this->registerSweelixScript($p);
					} else {
						$this->getOwner()->registerCoreScript($p);
					}
				}
			}
			if(isset($package['js']) == true) {
				foreach($package['js'] as $js) {
					$this->getOwner()->registerScriptFile($this->getSweelixAssetUrl().'/'.$js);
				}
			}
			if(($importCss === true) && (isset($package['css']) == true)) {
				foreach($package['css'] as $css) {
					$this->getOwner()->registerCssFile($this->getSweelixAssetUrl().'/'.$css);
				}
			}
			if($name==='shadowbox') {
				$this->_initShadowbox();
			}
			$this->sweelixScript[$name]=$package;
			if($this->_init === false) {
				if(isset($this->_config['debug']) === true) {
					if(isset($this->_config['debug']['mode']) === true) {
						if(is_string($this->_config['debug']['mode']) === true) {
							$this->_config['debug']['mode'] = array($this->_config['debug']['mode']);
						}
						$appenders = array();
						foreach($this->_config['debug']['mode'] as $debugMode => $parameters) {
							if((is_integer($debugMode) === true) && (is_string($parameters) === true)) {
								$debugMode = $parameters;
								$parameters = null;
							}
							$debugMode = strtolower($debugMode);
							if($parameters !== null) {
								$parameters = \CJavaScript::encode($parameters);
							} else {
								$parameters = '';
							}

							switch($debugMode) {
								case 'popup' :
									$appenders[] = 'js:new log4javascript.PopUpAppender('.$parameters.')';
									break;
								case 'browser' :
									$appenders[] = 'js:new log4javascript.BrowserConsoleAppender('.$parameters.')';
									break;
								case 'inpage' :
									$appenders[] = 'js:new log4javascript.InPageAppender('.$parameters.')';
									break;
								case 'alert' :
									$appenders[] = 'js:new log4javascript.AlertAppender('.$parameters.')';
									break;
							}
						}
						unset($this->_config['debug']['mode']);
						if(count($appenders)>0) {
							$this->_config['debug']['appenders'] = 'js:'.\CJavaScript::encode($appenders);
						}

					}
				}
				$this->getOwner()->registerScript('sweelixInit', 'sweelix.configure('.\CJavaScript::encode($this->_config).');', \CClientScript::POS_HEAD);
				$this->_init=true;
			}
		}
		return $this->getOwner();
	}

	/**
	 * Register shadowbox script and init it in the
	 * page
	 *
	 * @return void
	 * @since  1.1.0
	 */
	private function _initShadowbox() {
		if($this->_sbInit === false) {
			$this->getOwner()->registerScript('shadowboxInit', 'Shadowbox.init('.\CJavaScript::encode($this->_shadowboxConfig).');', \CClientScript::POS_READY);
			$this->_sbInit=true;
		}
	}

	/**
	 * Define configuration parameters for
	 * javascript packages
	 *
	 * @param array $data initial config
	 *
	 * @return void
	 * @since  1.1.0
	 */
	public function setConfig($data=array()) {
		if(isset($data['shadowbox']) == true) {
			$this->_shadowboxConfig = $data['shadowbox'];
			unset($data['shadowbox']);
		}
		if(!isset($this->_shadowboxConfig['skipSetup'])) {
			$this->_shadowboxConfig['skipSetup'] = true;
		}
		$this->_config=$data;
	}
}
