<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright (c) 2011 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  License details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

/**
 * ExtensionsApi
 * in a coherent structure.
 * @package ExtensionsApi
 */


/**
 * short description.
 */
abstract class Extension {

	/**
	 * @var boolean Allow this extension to overload "hook" calls?
	 */
	public $overloadHooks = false;

	/**
	 * @var ExtensionsApi The current {@link ExtensionsApi} that has loaded this extension.
	 */
	public $ExtensionsApi = null;

	/**
	 * @var object Object The current object being plugged into.
	 */
	protected $baseObject = null;


	const REPLACED_METHOD = 'Indicates that a method with void return has been replaced';


	/**
	 * Load the current object being plugged into.
	 * @param object $object The current object being plugged into.
	 */
	public function loadBaseObject($object) {
		//NOTE (Pavel): Possible futute imptovment with adding wrapper layer to controll access to base controller
		//Can add wrapper class with set of mirror methods and properies to connect to base objects 
		$this->baseObject = $object;
	}

	/**
	 * Load the current {@link ExtensionsApi} that has loaded this extension.
	 * @param ExtensionsApi $object The current {@link ExtensionsApi} that has loaded this extension.
	 */
	public function loadExtensionsApi(ExtensionsApi $ExtensionsApi) {
		$this->ExtensionsApi = $ExtensionsApi;
	}

	public function __call($method, $args) {
		if ((strpos($method, 'hk') === 0) && ($this->ExtensionsApi !== null)) {
			array_unshift($args, $this);
			$return = call_user_func_array(array( $this->ExtensionsApi, $method ), $args);
			return $return;
		}
	}
}


/**
 * short description.
 *
 * long description.
 *
 * @package ExtensionCollection
 */
class ExtensionCollection {

	protected $extensions = array();

	/**
	 * @throws Exception when encounters extension not of class extension.
	 */
	public function __construct(array $extensions) {
		foreach ($extensions as $extension) {
			// another extension collection passed in
			if (($extension instanceof ExtensionCollection) === true) {
				$this->extensions = array_merge($this->extensions, $extension->extensions);
				continue;
			}

			if (is_object($extension) === false) {
				$extension = new $extension();
			}

			if (($extension instanceof Extension) === false) {
				$class = get_class($extension);
				if (!($parent = get_parent_class($extension))) {
					$parent = $class;
				}
				throw new Exception(
					'Expected "' . $class . '" to be of class Extension; was "' . $parent . '" instead.'
				);
			}

			$this->extensions[ get_class($extension) ] = $extension;
		}
	}

	protected function dispatchMethod($method, $args) {
		$return = null;

		$baseObject = array_shift($args);

		foreach ($this->extensions as $extension) {
			if (!method_exists($extension, $method) && ($extension->overloadHooks === false)) {
				continue;
			}

			// If a extension is dispatching don't change the baseObject.
			// If another extension needs to access the dispatching extension,
			//   it can use $this->ExtensionsApi->extensionName.
			if (($baseObject instanceof Extension) === false) {
				$extension->loadBaseObject($baseObject);
				$extension->loadExtensionsApi($baseObject->ExtensionsApi);
			}

			$tmp_return = call_user_func_array(array( $extension, $method ), $args);
			if ($tmp_return !== null) {
				$return = $tmp_return;
			}
		}

		return $return;
	}

	public function __get($property) {
		if (isset($this->extensions[ $property ])) {
			return $this->extensions[ $property ];
		}
		return false;
	}

	public function __call($method, $args) {
		$return = $this->dispatchMethod($method, $args);
		if (strpos($method, 'around') === 0) {
			if ($return === null) {
				$return = true;
			} elseif ($return === Extension::REPLACED_METHOD) {
				$return = null;
			}
		}
		return $return;
	}

}


/**
 * ExtensionsApi . An intricate or interwoven combination of elements or parts
 * in a coherent structure.
 *
 * long description.
 *
 * @package MyExtensionsApi
 */
class ExtensionsApi {

	/**
	 * @var $extensions - array of extensions objects
	 */
	protected $extensions;
	/**
	 * @var $extensions_dir - list of all extensions in extension dir
	 */
	protected $extensions_dir;
	/**
	 * @var $enabled_extensions - array of enabled extensions folders
	 */
	protected $enabled_extensions;
	/**
	 * @var $db_extensions - array of extensions stored in db
	 */
	protected $db_extensions;
	/**
	 * @var $missing_extensions - array of extensions stored in db but missing folder in extensions dir
	 */
	protected $missing_extensions;

	/**
	 * @var $extension_controllers - array of extensions controllers
	 */
	protected $extension_controllers;

	/**
	 * @var $extension_models - array of extensions models
	 */
	protected $extension_models;

	/**
	 * @var $extension_languages - array of extensions languages
	 */
	protected $extension_languages;

	/**
	 * @var $extension_templates - array of extensions templates
	 */
	protected $extension_templates;
	protected $extension_types = array( 'extensions', 'payment', 'shipping', 'template' );

	public function __construct() {

		$this->extensions_dir = array();
		$this->db_extensions = array();
		$this->missing_extensions = array();

		$extensions = glob(DIR_EXT . '*', GLOB_ONLYDIR);
		if ( $extensions ) {
			foreach ($extensions as $ext) {
				$this->extensions_dir[ ] = str_replace(DIR_EXT, '', $ext);
			}
		}

		$registry = Registry::getInstance();
		if ($registry->has('db')) {

			$this->db = $registry->get('db');

			//get extensions from db
			$query = $this->getExtensionsList();
			foreach ($query->rows as $result) {
				$this->db_extensions[ ] = $result[ 'key' ];
			}

			//check if we have extensions that has record in db, but missing files
			// if so, disable them
			$this->missing_extensions = array_diff($this->db_extensions, $this->extensions_dir);
			if (!empty($this->missing_extensions))
				foreach ($this->missing_extensions as $ext) {
					$warning = new AWarning($ext . ' directory is missing');
					$warning->toMessages();
				}

			//check if we have extensions in dir that has no record in db
			$diff = array_diff($this->extensions_dir, $this->db_extensions);
			if (!empty($diff))
				foreach ($diff as $ext) {
					$data[ 'key' ] = $ext;
					$data[ 'status' ] = 0;
					$misext = new ExtensionUtils($ext);
					$data[ 'type' ] = $misext->getConfig('type');
					$data[ 'version' ] = $misext->getConfig('version');
					$data[ 'priority' ] = $misext->getConfig('priority');
					$data[ 'category' ] = $misext->getConfig('category');
					$data[ 'license_key' ] = $registry->get('load')->session->data[ 'package_info' ][ 'extension_key' ];

					if ($registry->has('extension_manager'))
						$registry->get('extension_manager')->add($data);
				}
		}
	}

	public function getInstalled( $type = '' ) {
		$type = (string)$type;
		$extension_data = array();
		if (in_array($type, $this->extension_types)) {
			$sql = "SELECT DISTINCT e.key
					FROM " . DB_PREFIX . "extensions e
					RIGHT JOIN " . DB_PREFIX . "settings s ON s.group = e.key
					WHERE e.type = '" . $this->db->escape($type) . "'";
		}elseif( $type == 'exts' ){
			$sql = "SELECT DISTINCT e.key
					FROM " . DB_PREFIX . "extensions e
					RIGHT JOIN " . DB_PREFIX . "settings s ON s.group = e.key
					WHERE e.type IN ('".implode("', '",$this->extension_types)."')";
		}elseif ($type == '') {
			$sql = "SELECT DISTINCT e.key
					FROM " . DB_PREFIX . "extensions e
					RIGHT JOIN " . DB_PREFIX . "settings s ON s.group = e.key";
		} else {
			$sql = "SELECT DISTINCT e.key
					FROM " . DB_PREFIX . "extensions e";
		}
		
		$query = $this->db->query($sql);
		foreach ($query->rows as $result) {
			if($result[ 'key' ]){
				$extension_data[ ] = $result[ 'key' ];
			}
		}

		return $extension_data;
	}

	public function getExtensionInfo($key = '') {

		$sql = "SELECT * FROM " . DB_PREFIX . "extensions
				" . ($key ? "WHERE `key` = '" . $this->db->escape($key) . "'" : '');
		$query = $this->db->query($sql);
		if ($query->num_rows == 1) {
			$extension_data = $query->row;
		} else {
			if ($query->num_rows) {
				foreach ($query->rows as $result) {
					$extension_data[ $result[ 'key' ] ] = $result;
				}
			}
		}

		return $extension_data;
	}

	/**
	 * load extensions list from DB
	 *
	 * @param array $data
	 *  key - search extensions by key and name
	 *  category - search extensions by category
	 *  page - page number ( limit should be defined also )
	 *  limit - number of rows in page ( page should be defined also )
	 * @return array of extensions
	 */
	public function getExtensionsList($data = array()) {

		$sql = "SELECT DISTINCT e.*, s.store_id, st.name as store_name, s.value as status
				FROM " . DB_PREFIX . "extensions e
				LEFT JOIN " . DB_PREFIX . "settings s ON ( TRIM(s.`group`) = TRIM(e.`key`) AND TRIM(s.`key`) = CONCAT(TRIM(e.`key`),'_status') )
				LEFT JOIN " . DB_PREFIX . "stores st ON st.store_id = s.store_id
				WHERE e.`type` ";

		if (!empty($data[ 'filter' ]) && $data[ 'filter' ] != 'extensions') {
			$sql .= " = '" . $this->db->escape($data[ 'filter' ]) . "'";
		} else {
			$sql .= " IN ('".implode("', '",$this->extension_types)."')";
		}

		if (!empty($data[ 'search' ])) {

			$keys = array();
			$ext_list = $this->getExtensionsList(array( 'filter' => $data[ 'filter' ] ));

			if ($ext_list->total) {
				foreach ($ext_list->rows as $extension) { // searching ext by name
					$name = $this->getExtensionName($extension[ 'key' ]);
					if (stripos($name, $data[ 'search' ]) !== false) {
						$keys[ ] = $extension[ 'key' ];
					}
				}
			}
			if ($keys) {
				$sql .= "AND (e.`key` LIKE '%" . $this->db->escape($data[ 'search' ]) . "%' ";
				$sql .= " OR  e.`key` IN ('" . implode("','", $keys) . "')) ";
			} else {
				$sql .= "AND e.`key` LIKE '%" . $this->db->escape($data[ 'search' ]) . "%' ";
			}
		}
		if (!empty($data[ 'category' ])) {
			$sql .= "AND e.`category` = '" . $this->db->escape($data[ 'category' ]) . "' ";
		}
		if (!empty($data[ 'status' ])) {
			$sql .= "AND s.value = '" . (int)$data[ 'status' ] . "' ";
		}

		if (!empty($data[ 'store_id' ])) {
			$sql .= "AND s.`store_id` = '" . (int)$data[ 'store_id' ] . "' ";
		}

		if (!empty($data[ 'page' ]) && !empty($data[ 'limit' ])) {
			$total = $this->db->query($sql);
			$sql .= " LIMIT " . (int)(($data[ 'page' ] - 1) * $data[ 'limit' ]) . ", " . (int)($data[ 'limit' ]) . " ";
		}

		$result = $this->db->query($sql);
		$result->total = $total ? $total->num_rows : $result->num_rows;

		return $result;
	}

	public function getExtensionName($extension = '') {
		if (!$extension) {
			return false;
		}
		$name = '';
		$registry = Registry::getInstance();
		if(file_exists(DIR_EXT . $extension . '/admin/language/' . $registry->get('language')->language_details[ 'directory' ] . '/' . $extension . '/' . $extension . '.xml')){
			$filename = DIR_EXT . $extension . '/admin/language/' . $registry->get('language')->language_details[ 'directory' ] . '/' . $extension . '/' . $extension . '.xml';
		}else{
			$filename = DIR_EXT . $extension . '/admin/language/english/' . $extension . '/' . $extension . '.xml';
		}
		$xml = simplexml_load_file( $filename );
		if ($xml && $xml->definition) {
			foreach ($xml->definition as $def) {
				if ((string)$def->key == $extension . '_name') {
					$name = (string)$def->value;
					break;
				}
			}
		}
		return $name;
	}

	protected function setExtensionCollection(ExtensionCollection $extensions) {
		$this->extensions = $extensions;
	}

	public function getExtensionCollection() {
		return $this->extensions;
	}

	public function getMissingExtensions() {
		return $this->missing_extensions;
	}

	public function getEnabledExtensions() {
		return $this->enabled_extensions;
	}

	public function getExtensionsDir() {
		return $this->extensions_dir;
	}

	public function getDbExtensions() {
		return $this->db_extensions;
	}

    public function getExtensionControllers() {
        return $this->extension_controllers;
    }
    public function setExtensionControllers($value) {
        $this->extension_controllers = $value;
    }

    public function getExtensionTemplates() {
        return $this->extension_templates;
    }
    public function setExtensionTemplates($value) {
        $this->extension_templates = $value;
    }

    public function getExtensionLanguages() {
        return $this->extension_languages;
    }
    public function setExtensionLanguages($value) {
        $this->extension_languages = $value;
    }

    public function getExtensionModels() {
        return $this->extension_models;
    }
    public function setExtensionModels($value) {
        $this->extension_models = $value;
    }

	public function loadEnabledExtensions() {
		$registry = Registry::getInstance();
		$ext_controllers = $ext_models = $ext_languages = $ext_templates = array();
		$enabled_extensions = $extensions = array();

		foreach ($this->db_extensions as $store_id => $ext) {
			if ($registry->get('config')->get($ext . '_status') && !in_array($ext,$enabled_extensions)) {

				$priority = $registry->get('config')->get($ext . '_priority');
				// prevent rewriting of array if we have exts with same priority
				if (isset($enabled_extensions[ $priority ])) {
					while (isset($enabled_extensions[ $priority ]) && $priority < 20000) {
						$priority++;
					}
				}
				$enabled_extensions[ $priority ] = $ext;
				

				$controllers = $languages = $models = $templates = array(
					'storefront' => array(),
					'admin' => array(),
				);
				if (is_file(DIR_EXT . $ext . '/main.php')) {
					include(DIR_EXT . $ext . '/main.php');
				}
				$ext_controllers[ $ext ] = $controllers;
				$ext_models[ $ext ] = $models;
				$ext_languages[ $ext ] = $languages;
				$ext_templates[ $ext ] = $templates;

				$class = 'Extension' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
				if (class_exists($class)) {
					$extensions[ ] = $class;
				}
			}
		}

		$this->setExtensionCollection(new ExtensionCollection($extensions));

		ksort($enabled_extensions);
        $this->enabled_extensions = $enabled_extensions;
		ADebug::variable('List of loaded extensions', $enabled_extensions);

		$this->setExtensionControllers($ext_controllers);
		ADebug::variable('List of controllers used by extensions', $ext_controllers);

        $this->setExtensionModels($ext_models);
		ADebug::variable('List of models used by extensions', $ext_models);

		$this->setExtensionLanguages($ext_languages);
		ADebug::variable('List of languages used by extensions', $ext_languages);

		$this->setExtensionTemplates($ext_templates);
		ADebug::variable('List of templates used by extensions', $ext_templates);
	}

	/**
	 * check if language file exists in extension resource
	 *
	*/
	public function isExtensionLanguageFile($route, $lang_directory, $section) {
		$registry = Registry::getInstance();
		if (!$registry->has('config')) return false;
		
		$file = ($section ? DIR_EXT_ADMIN : DIR_EXT_STORE) . 'language/' .
		        $lang_directory . '/' . $route . '.xml';
		$source = $this->extension_languages;

		//we can include language file from all extensions too
		foreach ($this->extensions_dir as $ext) {
		    $f = DIR_EXT . $ext . $file;
		    if (is_file($f)) {
		    	return array(
		    		'file' => $f,
		    		'extension' => $ext,
		    	);
		    }
		}
		return false;
	}

	/**
	 * check if resource ( model, language, template ) is an extension resource
	 *
	 * @param  $resource_type - resource type - M, L, T  ( model, language, template )
	 * @param  $route - resource route to check
	 * @return array|bool - false if not found, array with extension name and file name if found
	 */
	public function isExtensionResource($resource_type, $route) {

		$registry = Registry::getInstance();
		if (!$registry->has('config')) return false;

		switch ($resource_type) {
			case 'M' :
				$file = (IS_ADMIN ? DIR_EXT_ADMIN : DIR_EXT_STORE) . 'model/' . $route . '.php';
				$source = $this->extension_models;
				break;
			case 'L' :
				$query = $registry->get('db')->query("SELECT directory FROM " . DB_PREFIX . "languages
                    WHERE code='" . $registry->get('session')->data[ 'language' ] . "'");
				$file = (IS_ADMIN ? DIR_EXT_ADMIN : DIR_EXT_STORE) . 'language/' .
				        $query->row[ 'directory' ] . '/' . $route . '.xml';
				$source = $this->extension_languages;
				break;
			case 'T' :
				$tmpl_id = IS_ADMIN ? $registry->get('config')->get('admin_template')
						: $registry->get('config')->get('config_storefront_template');
				$file = (IS_ADMIN ? DIR_EXT_ADMIN
						: DIR_EXT_STORE) . DIR_EXT_TEMPLATE . $tmpl_id . '/template/' . $route;
				$source = $this->extension_templates;
				break;
			default:
				return false;
		}

		$section = trim((IS_ADMIN ? DIR_EXT_ADMIN : DIR_EXT_STORE), '/');
		foreach ($this->enabled_extensions as $ext) {
			$f = DIR_EXT . $ext . $file;
			if (in_array($route, $source[ $ext ][ $section ])) {
				if (is_file($f)) {
					return array(
						'file' => $f,
						'extension' => $ext,
					);
				}
				if ($resource_type == 'T') {
					//check default template
					$f = DIR_EXT . $ext . (IS_ADMIN ? DIR_EXT_ADMIN
							: DIR_EXT_STORE) . DIR_EXT_TEMPLATE . 'default/template/' . $route;
					if (is_file($f)) {
						return array(
							'file' => $f,
							'extension' => $ext,
						);
					}
				}
			}
		}

		//we can include language file from all extensions too
		if ($resource_type == 'L') {
			foreach ($this->extensions_dir as $ext) {
				$f = DIR_EXT . $ext . $file;
				if (is_file($f)) {
					return array(
						'file' => $f,
						'extension' => $ext,
					);
				}
			}
		}
		return false;
	}

	/**
	 * check if route is an extension controller
	 *
	 * @param  $route - controller route to check
	 * @return array|bool - extension name, file, class name and method
	 */
	public function isExtensionController($route) {

		$section = trim((IS_ADMIN ? DIR_EXT_ADMIN : DIR_EXT_STORE), '/');
		$path_build = '';
		$path_nodes = explode('/', $route);

		foreach ($path_nodes as $path_node) {
			$path_build .= $path_node;

			foreach ($this->enabled_extensions as $ext) {
				$file = DIR_EXT . $ext . (IS_ADMIN ? DIR_EXT_ADMIN
						: DIR_EXT_STORE) . 'controller/' . $path_build . '.php';
				if (in_array($path_build, $this->extension_controllers[ $ext ][ $section ]) && is_file($file)) {
					//remove current node
					array_shift($path_nodes);
					//check for method
					$method_to_call = array_shift($path_nodes);
					if ($method_to_call) {
						$method = $method_to_call;
					} else {
						$method = 'main';
					}

					return array(
						'route' => $path_build,
						'extension' => $ext,
						'file' => $file,
						'class' => 'Controller' . preg_replace('/[^a-zA-Z0-9]/', '', $path_build),
						'method' => $method,
					);
				}
			}

			$path_build .= '/';
			array_shift($path_nodes);
		}

		return false;
	}

	/**
	 * Check if a {@link Extension} from the {@link ExtensionCollection} for this ExtensionsApi exists.
	 * {@source}
	 * Use like <code>isset($ExtensionsApi->extensionName)</code>
	 * @param string $property Name of the {@link extension} to check.
	 * @return boolean
	 */
	public function __isset($property) {
		if ($this->extensions->$property !== false) {
			return true;
		}
		return false;
	}

	/**
	 * Get a {@link Extension} from the {@link ExtensionCollection} for this ExtensionsApi.
	 * {@source}
	 * Use like <code>$ExtensionsApi->extensionName</code>
	 * @param string $property Name of the {@link extension} to get.
	 * @return extension
	 */
	public function __get($property) {
		if ($this->extensions->$property !== false) {
			return $this->extensions->$property;
		}
		throw new Exception(
			'Extensions of name "' . $property . '" not found in ExtensionsApi ' . $this
		);
	}

	public function __call($method, array $args) {
		if (substr($method, 0, 2) == 'hk') {
			return $this->__ExtensionsApiCall(substr($method, 2), $args);
		}
	}

	protected function __ExtensionsApiCall($method, array $args) {
		$return = null;

		if ((sizeof($args) > 0) && is_object($args[ 0 ])) {
			$baseObject = $args[ 0 ];
			$baseObject->ExtensionsApi = $this;
		} else {
			$baseObject = $this;
			$baseObject->ExtensionsApi = $this;
			array_unshift($args, $baseObject);
		}

		$method = strtolower($method[ 0 ]) . substr($method, 1);

		$extension_method = ucfirst(get_class($baseObject)) . ucfirst($method);

		// before hook - runs before method; allows parameters to be changed
		$before_args = $args;
		array_shift($before_args);
		$args[ ] =& $before_args;
		call_user_func_array(array( $this->extensions, 'before' . $extension_method ), $args);
		$args = $before_args;
		array_unshift($args, $baseObject);

		$can_run = true;
		if (method_exists($baseObject, $method) || method_exists($baseObject, '__call')) {

			// callback surrounds the method execution
			$can_run = call_user_func_array(array( $this->extensions, 'around' . $extension_method ), $args);

			// method is allowed to run
			if ($can_run === true) {
				$object_args = $args;
				array_shift($object_args);
				$return = call_user_func_array(array( $baseObject, $method ), $object_args);

				// have replaced the method
			} elseif ($can_run !== false) {
				$return = $can_run;
			}
		}

		if ($can_run !== false) {
			$on_args = $args;
			$on_args[ ] =& $return;
			call_user_func_array(array( $this->extensions, 'on' . $extension_method ), $on_args);
		}

		call_user_func_array(array( $this->extensions, 'after' . $extension_method ), $args);

		return $return;
	}

}


/**
 * validate extension requirements before install
 *
 * long description.
 *
 * @package MyExtensionsApi
 */
class ExtensionUtils {
	protected $registry;
	protected $name;
	protected $config;
	protected $store_id;
	protected $error = array();

	protected $tags = array();

	public function __construct($ext,$store_id=0) {
		$this->registry = Registry::getInstance();
		$this->name = $ext;
		$this->store_id = (int)$store_id;
		$filename = DIR_EXT . str_replace('../', '', $this->name) . '/config.xml';
		if (!is_file($filename)) {
			$error = new AError(sprintf('Error: Could not load config for <b>%s</b>!', $this->name));
			$error->toLog()->toDebug();
			return;
		}

		$this->config = simplexml_load_file($filename);
		if (!$this->config) {
			$err = sprintf('Error: Could not load config for <b>%s</b> ( '.$filename.')!', $this->name);
			foreach (libxml_get_errors() as $error) {
				$err .= "  " . $error->message;
			}
			$error = new AError($err);
			$error->toLog()->toDebug();
			$this->error[]= $err;
			return;
		}
	}

	public function getConfig($val = null) {
		return !empty($val) ? isset($this->config->$val) ? (string)$this->config->$val : null : $this->config;
	}

	public function validate() {
		$this->validateFreeSpace();
		$this->validateInstalled();
		$this->validateCoreVersion();
		$this->validatePhpModules();
		$this->validateDependencies();
	}

	/**
	 *  check free space
	 */
	public function validateFreeSpace() {
		return null;
	}

	/**
	 *  is extension already installed ( extension upgrade )
	 */
	public function validateInstalled() {
		$ext = new ExtensionsApi();
		return in_array($this->name, $ext->getDbExtensions());
	}

	/**
	 *  is extension support current core version
	 */
	public function validateCoreVersion() {
		if (!isset($this->config->cartversions->item)) return;
		$coreversion = MASTER_VERSION.'.'.MINOR_VERSION;
		foreach ($this->config->cartversions->item as $item){
			$version = (string)$item;
			$version = explode('.',$version);
			$version = $version[0].'.'.$version[1];
			$versions[] = $version;
		}
		asort($versions,SORT_DESC);
		// if version exist in list - quite return
		if(in_array($coreversion,$versions)){
			return true;
		}
		// check is extension version less than cart version
		foreach($versions as $version){
			$result = version_compare($version,$coreversion,'<');
			if($result){
					$error_text = 'Extension <b>%s</b> written for earlier version of Abantecart (v.%s) lower that you have. ';
					$error_text .= 'Probably all will be OK.';
					$error_text = sprintf($error_text, $this->name, implode(', ',$versions));
					$registry = Registry::getInstance();
					$registry->get('session')->data[ 'error' ] = $error_text;
					$registry->get('messages')->saveWarning($this->name .' extension warning',$error_text);

			return true; }
		}

	$error_text = '<b>%s</b> extension cannot be installed. AbanteCart version incompability. ';
	$error_text .= sizeof($versions)>1 ? 'Versions <b>%s</b> are required.' : 'Version <b>%s</b> is required.';
	$this->error(sprintf($error_text, $this->name, implode(', ',$versions)));
	return false;
	}

	/**
	 *  is hosting support all php modules used by extension
	 */
	public function validatePhpModules() {
		if (!isset($this->config->phpmodules->item)) return;
		foreach ($this->config->phpmodules->item as $item){
			$item = (string)$item;
			if (!extension_loaded($item)) {
				$this->error(sprintf('<b>%s</b> extension cannot be installed: <b>%s</b> php module required', $this->name, $item));
			}
		}

	}

	/**
	 *  is dependencies present
	 */
	public function validateDependencies() {
		$extensions = $this->registry->get('extensions')->getEnabledExtensions();
		$all_extensions = $this->registry->get('extensions')->getExtensionsList();
		foreach ($all_extensions->rows as $ext) {
			$versions[ $ext[ 'key' ] ] = $ext[ 'version' ];
		}
		if (!isset($this->config->dependencies->item)) return true;
		foreach ($this->config->dependencies->item as $item) {
			$required = (boolean)$item[ 'required' ];
			$version = (string)$item[ 'version' ];
			$prior_version = (string)$item[ 'prior_version' ];

			$item = (string)$item;
			// check existing of required
			if ($required && !in_array($item, $extensions)) {
				$this->error(sprintf('<b>%s</b> extension cannot be installed: <b>%s</b> extension required and must be installed and enabled!', $this->name, $item));
			}
			// if extension installed - check version that need
			if ($version) {
				if ($required && (!version_compare($version, $versions[ $item ], '>=') || !version_compare($prior_version, $versions[ $item ], '<='))) {
					$this->error(sprintf('<b>%s</b> extension cannot be installed: <b>%s</b> extension versions <b>' . $prior_version . ' - ' . $version . '</b> are required', $this->name, $item));
				}
			}
			if (sizeof($this->error)>0) {
				return false;
			}
		}

		return true;
	}

	/**
	 *  is dependendants installed
	 */
	public function checkDependants() {
		$extensions = $this->registry->get('extensions')->getInstalled('exts');
		foreach ($extensions as $extension) {
			if ($extension == $this->name) continue;

			$filename = DIR_EXT . $extension . '/config.xml';
			$config = simplexml_load_file($filename);
			if (!isset($config->dependencies->item)) continue;
			foreach ($config->dependencies->item as $item) {
				$required = (boolean)$item[ 'required' ];
				$item = (string)$item;
				if ($item == $this->name && $required) {
					$this->error(sprintf('<b>%s</b> extension cannot be uninstalled: <b>%s</b> extension depends from it. Please uninstall it first.', $this->name, $extension));
					return false;
				}
			}
		}
		return true;
	}

    /**
     * validate extension resources. return warning in case conflict
     */
    public function validateResources() {
        $filename = DIR_EXT . str_replace('../', '', $this->name) . '/main.php';
        if (!is_file($filename)) return;

        //load extensions resources
        $controllers = $languages = $models = $templates = array(
            'storefront' => array(),
            'admin' => array(),
        );
        include($filename);
        $validate_resources = array(
            'controllers' => $controllers,
            'languages' => $languages,
            'models' => $models,
            'templates' => $templates,
        );

        //extensions resources
        $ext_resources = array(
            'controllers' => $this->registry->get('extensions')->getExtensionControllers(),
            'languages' => $this->registry->get('extensions')->getExtensionLanguages(),
            'models' => $this->registry->get('extensions')->getExtensionModels(),
            'templates' => $this->registry->get('extensions')->getExtensionTemplates(),
        );

        $conflict_resources = array();

        foreach ( $validate_resources as $resource_type => $resources ) {
            if ( empty($resources) ) continue;
            foreach ( $ext_resources[$resource_type] as $checked_name => $checked_resources ) {
                if ( $checked_name == $this->name ) continue;
                foreach ( $checked_resources as $section => $section_resources ) {
                    $conflict = array_intersect($resources[$section], $section_resources);
                    if ( !empty($conflict) ) {
                        $conflict_resources[$checked_name][$resource_type][$section] = $conflict;
                    }
                }
            }
        }

        return $conflict_resources;
    }


	protected function error($err_msg) {
		$this->error[ ] = $err_msg;
	}

	public function getError() {
		return $this->error;
	}

	public function getSettings() {

		$this->registry->get('load')->model('setting/setting');
		$settings = $this->registry->get('model_setting_setting')->getSetting($this->name,$this->store_id);
		$result = array();
		$this->registry->get('session')->data[ 'extension_required_fields' ] = array();
		//add other settings items
		if (isset($this->config->settings->item)) {
			$i =0;
			foreach ($this->config->settings->item as $item) {
				$result[$i] = array(   'name' => (string)$item[ 'id' ],
									   'value' => $settings[ (string)$item[ 'id' ] ],
									   'type' => (string)$item->type,
									   'resource_type' => (string)$item->resource_type,
									   'model_rt' => (string)$item->variants->data_source->model_rt,
									   'method' => (string)$item->variants->data_source->method,
									   'field1' => (string)$item->variants->fields->field[ 0 ],
									   'field2' => (string)$item->variants->fields->field[ 1 ],
				);
				if ($item->variants->item[ 0 ]) { // if just hardcoded selectbox options
					foreach ($item->variants->item as $k) {
						$k = (string)$k;
						$result[$i]['options'][$k] = $this->registry->get('language')->get((string)$item['id'].'_'.$k);
					}
				}

				if ((string)$item[ 'id' ] == $this->name . '_status') {
					$result[$i][ 'style' ] = 'btn_switch';
					$result[$i][ 'attr' ] = 'reload_on_save="true"';					
				}
				$type_attr = $item->type->attributes();
				if ((string)$type_attr[ 'required' ] == 'true') {
					$result[$i][ 'required' ] = true;
					$this->session->data[ 'extension_required_fields' ][ ] = $result[$i][ 'name' ];
				}
				if ((string)$type_attr[ 'readonly' ] == 'true') {
					$result[$i][ 'attr' ] .= ' readonly';
				}

			$i++;
			}
		}

		return $result;
	}

	public function getDefaultSettings() {

		$result = array();
		if (isset($this->config->settings->item)) {
			foreach ($this->config->settings->item as $item) {
				if ((string)$item[ 'id' ] == $this->name . '_status') continue;
				$value = (string)$item->default_value;
				if((string)$item->type == 'resource' && $value){
					$resource = new AResource( (string)$item->resource_type );
					$resource_id = $resource->getIdFromHexPath(str_replace((string)$item->resource_type, '', $value));
					$resource_info = $resource->getResource($resource_id);
					$value = (string)$item->resource_type.'/'.$resource_info['resource_path'];
				}
				$result[ (string)$item[ 'id' ] ] = $value;
			}
		}

		return $result;
	}

}