<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011 Belavier Commerce LLC

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

class AListingManager {
	protected $registry;
	public $errors = 0;
	private $custom_block_id;

	public function __construct($custom_block_id) {
		$this->registry = Registry::getInstance();
		$this->custom_block_id = (int)$custom_block_id;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function __set($key, $value) {
		$this->registry->set($key, $value);
	}

	public function getCustomList(){
		if(!$this->custom_block_id){
			return false;
		}
		$result = $this->db->query("SELECT *
		                            FROM ".DB_PREFIX."custom_lists
								    WHERE custom_block_id = '".$this->custom_block_id."'
								    ORDER BY sort_order");
		return $result->rows;
	}



	public function getListingDataSources(){

		$output = Array(
						'catalog_product_getPopularProducts' => array( 'text'=>'text_products_popular',
																		'rl_object_name' => 'products',
																		'data_type'=>'product_id',
																		'storefront_model' => 'catalog/product',
																        'storefront_method' => 'getPopularProducts',
																		'storefront_view_path' => 'product/product',
						),
						'catalog_product_getSpecialProducts' => array( 'text'=>'text_products_special',
																		'rl_object_name' => 'products',
																		'data_type'=>'product_id',
																		'storefront_model' => 'catalog/product',
																        'storefront_method' => 'getProductSpecials',
																		'storefront_view_path' => 'product/product',
						),
						'catalog_category_getcategories' => array('text'=>'text_categories',
																  'rl_object_name' => 'categories',
																  'data_type'=>'category_id',
																  'storefront_model' => 'catalog/category',
																  'storefront_method' => 'getCategories',
																  'storefront_view_path' => 'product/category',
						),
						'media' => array( 'text'=>'text_media'),
						'custom_products' => array(
												'model'=>'catalog/product',
												'total_method' => 'getTotalProducts',
												'method' => 'getProducts',
												'language'=>'catalog/product',
												'data_type'=>'product_id',
												'view_path' => 'catalog/product/update',
												'rl_object_name' => 'products',
												'text'=>'text_custom_products',
												'storefront_model' => 'catalog/product',
												'storefront_method' => 'getProduct',
												'storefront_view_path' => 'product/product',
												),

						'custom_categories' => array(
												'model'=>'catalog/category',
												'total_method' => 'getTotalCategories',
												'method' => 'getCategoriesData',
												'language'=>'catalog/category',
												'data_type'=>'category_id',
												'view_path' => 'catalog/category/update',
												'rl_object_name' => 'categories',
												'text'=>'text_custom_categories',
												'storefront_model' => 'catalog/category',
												'storefront_method' => 'getCategory',
												'storefront_view_path' => 'product/category',
												),

						'custom_manufacturers' => array(
												'model'=>'catalog/manufacturer',
												'total_method' => 'getTotalManufacturers',
												'method' => 'getManufacturers',
												'language'=>'catalog/manufacturer',
												'data_type'=>'manufacturer_id',
												'view_path' => 'catalog/category/update',
												'rl_object_name' => 'manufacturers',
												'text'=>'text_custom_manufacturers',
												'storefront_model' => 'catalog/manufacturer',
												'storefront_method' => 'getManufacturer',
												'storefront_view_path' => 'product/manufacturer',
												),
		);

		return $output;
	}

	//method returns argument fors call_user_func function usage when call storefront model to get list
	public function getlistingArguments($model,$method,$args=array()){
		if(!$method || !$model || !$args){ return false;}
		$output = array();
		if($model=='catalog/category' && $method=='getCategories'){
			$args['parent_id'] =  is_null($args['parent_id']) ? 0 : $args['parent_id'];
			$output = array($args['parent_id'],$args['limit']);
		}elseif($model=='catalog/product' && $method=='getPopularProducts'){
			$output = array('limit' => $args['limit']);
		}elseif($model=='catalog/product' && $method=='getProductSpecials'){
			$output = array('p.sort_order','ASC',0,'limit' => $args['limit']);
		}

	return $output;
	}

	public function saveCustomListItem($data) {
		if (!IS_ADMIN) { // forbid for non admin calls
			throw new AException (AC_ERR_LOAD, 'Error: permission denied to save custom listing');
		}
		$custom_block_id = (int)$this->custom_block_id;

		$listing_properties = $this->getListingDataSources();
		$data['data_type'] = $listing_properties[$data['listing_datasource']]['data_type'];

		$result = $this->db->query("SELECT *
									FROM  " . DB_PREFIX . "custom_lists
									WHERE custom_block_id = '".$custom_block_id."'
											AND id='".$data['id']."'
											AND data_type='".$data['data_type']."'");

		if($result->num_rows && $custom_block_id){
			$this->db->query(  "UPDATE " . DB_PREFIX . "custom_lists
								SET custom_block_id = '".$custom_block_id."'
								".( !is_null($data['sort_order']) ? ", sort_order = '".(int)$data['sort_order']."'" : "")."
								WHERE custom_block_id = '".$custom_block_id."'
									  AND id='".$data['id']."'
										AND data_type='".$data['data_type']."'");
		}else{
			$this->db->query("INSERT INTO " . DB_PREFIX . "custom_lists
								( custom_block_id,
								  data_type,
								  id,
								  sort_order,
								  created )
							  VALUES ('".$custom_block_id."',
							          '".$data['data_type']."',
							          '".(int)$data['id']."',
							          '" . ( int )$data [ 'sort_order' ] . "',
								      NOW())");
		}

		return true;
	}
	// delete one item from custom list of custom listing block
	public function deleteCustomListItem($data) {
		if (!IS_ADMIN) { // forbid for non admin calls
			throw new AException (AC_ERR_LOAD, 'Error: permission denied to delete custom listing');
		}
		$listing_properties = $this->getListingDataSources();
		$data['data_type'] = $listing_properties[$data['listing_datasource']]['data_type'];

		$sql = "DELETE FROM  " . DB_PREFIX . "custom_lists
									WHERE custom_block_id = '".(int)$this->custom_block_id."'
											AND id='".$data['id']."'
											AND data_type='".$data['data_type']."'";
		$this->db->query( $sql);
	}

	// delete all custom list of custom listing block
	public function deleteCustomListing() {
		if (!IS_ADMIN) { // forbid for non admin calls
			throw new AException (AC_ERR_LOAD, 'Error: permission denied to delete custom listing');
		}
		$sql = "DELETE FROM  " . DB_PREFIX . "custom_lists WHERE custom_block_id = '".(int)$this->custom_block_id."'";
		$this->db->query( $sql );
	}
}