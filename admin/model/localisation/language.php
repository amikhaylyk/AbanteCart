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
if (! defined ( 'DIR_CORE' ) || !IS_ADMIN) {
	header ( 'Location: static_pages/' );
}
class ModelLocalisationLanguage extends Model {
	public function addLanguage($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "languages
							SET name = '" . $this->db->escape($data['name']) . "',
								code = '" . $this->db->escape($data['code']) . "',
								locale = '" . $this->db->escape($data['locale']) . "',
								directory = '" . $this->db->escape($data['directory']) . "',
								sort_order = '" . $this->db->escape($data['sort_order']) . "',
								status = '" . (int)$data['status'] . "'");
		
		$this->cache->delete('language');
		
		$language_id = $this->db->getLastId();

		// Category
		$query = $this->db->query("SELECT *
								   FROM " . DB_PREFIX . "category_descriptions
								   WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $category) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "category_descriptions
							  SET category_id = '" . (int)$category['category_id'] . "',
							        language_id = '" . (int)$language_id . "',
							        name = '" . $this->db->escape($category['name']) . "',
							        meta_description= '" . $this->db->escape($category['meta_description']) . "',
							        description = '" . $this->db->escape($category['description']) . "'");
		}

		$this->cache->delete('category');

		// Coupon
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "coupon_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $coupon) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "coupon_descriptions
							SET coupon_id = '" . (int)$coupon['coupon_id'] . "',
								language_id = '" . (int)$language_id . "',
								name = '" . $this->db->escape($coupon['name']) . "',
								description = '" . $this->db->escape($coupon['description']) . "'");
		}
		
		// Download
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "download_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $download) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "download_descriptions
							SET download_id = '" . (int)$download['download_id'] . "',
								language_id = '" . (int)$language_id . "',
								name = '" . $this->db->escape($download['name']) . "'");
		}
				
		// contents
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "content_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $content) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "content_descriptions
								SET content_id = '" . (int)$content['content_id'] . "',
								language_id = '" . (int)$language_id . "',
								`name` = '" . $this->db->escape($content['name']) . "',
								title = '" . $this->db->escape($content['title']) . "',
								description = '" . $this->db->escape($content['description']) . "',
								content = '" . $this->db->escape($content['content']) . "'");
		}		

		$this->cache->delete('contents');

		// Length
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "length_class_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $length) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "length_class_descriptions
								SET length_class_id = '" . (int)$length['length_class_id'] . "',
								language_id = '" . (int)$language_id . "',
								title = '" . $this->db->escape($length['title']) . "',
								unit = '" . $this->db->escape($length['unit']) . "'");
		}	
		
		$this->cache->delete('length_class');
		
		// Order Status
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "order_statuses
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $order_status) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "order_statuses
								SET order_status_id = '" . (int)$order_status['order_status_id'] . "',
									language_id = '" . (int)$language_id . "',
									name = '" . $this->db->escape($order_status['name']) . "'");
		}	
		
		$this->cache->delete('order_status');
		
		// Product
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "product_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $product) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_descriptions
							SET product_id = '" . (int)$product['product_id'] . "',
								language_id = '" . (int)$language_id . "',
								name = '" . $this->db->escape($product['name']) . "',
								meta_description= '" . $this->db->escape($product['meta_description']) . "',
								description = '" . $this->db->escape($product['description']) . "'");
		}

		$this->cache->delete('product');

		// Product Option
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "product_option_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $product_option) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_option_descriptions
								SET product_option_id = '" . (int)$product_option['product_option_id'] . "',
									language_id = '" . (int)$language_id . "',
									product_id = '" . (int)$product_option['product_id'] . "',
									name = '" . $this->db->escape($product_option['name']) . "'");
		}
		
		// Product Option Value
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "product_option_value_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $product_option_value) {
			$this->db->query(  "INSERT INTO " . DB_PREFIX . "product_option_value_descriptions
								SET product_option_value_id = '" . (int)$product_option_value['product_option_value_id'] . "',
									language_id = '" . (int)$language_id . "',
									product_id = '" . (int)$product_option_value['product_id'] . "',
									name = '" . $this->db->escape($product_option_value['name']) . "'");
		}

		// Stock Status
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "stock_statuses
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $stock_status) {
			$this->db->query(  "INSERT INTO " . DB_PREFIX . "stock_statuses
								SET stock_status_id = '" . (int)$stock_status['stock_status_id'] . "',
									language_id = '" . (int)$language_id . "',
									name = '" . $this->db->escape($stock_status['name']) . "'");
		}
		
		$this->cache->delete('stock_status');
		
		// Store
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "store_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $store) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "store_descriptions
							SET store_id = '" . (int)$store['store_id'] . "',
								language_id = '" . (int)$language_id . "',
								description = '" . $this->db->escape($store['description']) . "'");
		}
		
		$this->cache->delete('store');		
		
		// Weight Class
		$query = $this->db->query("SELECT *
									FROM " . DB_PREFIX . "weight_class_descriptions
									WHERE language_id = '" . (int)$this->config->get('storefront_language_id') . "'");

		foreach ($query->rows as $weight_class) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "weight_class_descriptions
								SET weight_class_id = '" . (int)$weight_class['weight_class_id'] . "',
									language_id = '" . (int)$language_id . "',
									title = '" . $this->db->escape($weight_class['title']) . "',
									unit = '" . $this->db->escape($weight_class['unit']) . "'");
		}	
		
		$this->cache->delete('weight_class');

		//add menu items for new language
		$menu = new AMenu_Storefront();
		$menu->addLanguage( (int)$language_id );

		return $language_id;
	}
	
	public function editLanguage($language_id, $data) {
		$update_data = array();
		foreach ( $data as $key => $val ) {
			$update_data[] = "`$key` = '" . $this->db->escape($val) . "' ";
		}
		$this->db->query("UPDATE " . DB_PREFIX . "languages SET ".implode(',', $update_data)." WHERE language_id = '" . (int)$language_id . "'");
				
		$this->cache->delete('language');
	}
	
	public function deleteLanguage($language_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "languages WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('language');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "store_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('store');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "category_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('category');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "download_descriptions WHERE language_id = '" . (int)$language_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "content_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('contents');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "length_class_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('length_class');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "order_statuses WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('order_status');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_descriptions WHERE language_id = '" . (int)$language_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_option_descriptions WHERE language_id = '" . (int)$language_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('product');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "stock_statuses WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('stock_status');
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "weight_class_descriptions WHERE language_id = '" . (int)$language_id . "'");
		
		$this->cache->delete('weight_class');

		//delete menu items for given language
		$menu = new AMenu_Storefront();
		$menu->deleteLanguage( (int)$language_id );
	}
	
	public function getLanguage($language_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "languages WHERE language_id = '" . (int)$language_id . "'");
		$result = $query->row;
		if(!$result['image']){
			$result['image'] = HTTP_ABANTECART.'storefront/language/'.$result['directory'].'/flag.png';
		}else{
			$result['image'] = HTTP_ABANTECART.$result['image'];
		}
		return $query->row;
	}

	public function getLanguages($data = array(), $mode = 'default') {
        if ($data || $mode == 'total_only') {
        	$filter = (isset($data['filter']) ? $data['filter'] : array());
			if ($mode == 'total_only') {
				$sql = "SELECT count(*) as total FROM " . DB_PREFIX . "languages";
			}
			else {
				$sql = "SELECT * FROM " . DB_PREFIX . "languages";
			}
			
			if (isset($filter['status']) && !is_null($filter['status'])) { 
				$sql .= " WHERE `status` = '".$this->db->escape( $filter['status'] )."' ";			
			} else {
				$sql .= " WHERE `status` like '%' ";			
			}

			if (isset($filter['name']) && !is_null($filter['name'])) {
				$sql .= " AND `name` LIKE '%".$this->db->escape( $filter['name'] )."%' ";
			}
			
			if ( !empty($data['subsql_filter']) ) {
				$sql .= " AND ".$data['subsql_filter'];
			}

			//If for total, we done bulding the query
			if ($mode == 'total_only') {
			    $query = $this->db->query($sql);
		    	return $query->row['total'];
			}

			$sort_data = array(
				'name',
				'code',
				'sort_order'
			);	
			
			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];	
			} else {
				$sql .= " ORDER BY sort_order, name";	
			}
			
			if (isset($data['order']) && (strtoupper($data['order']) == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}
			
			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}					

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}	
			
				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}
			
			$query = $this->db->query($sql);
			$result = $query->rows;
			foreach($result as $i=>$row){
				if(empty($row['image'])){
					$result[$i]['image'] = HTTP_CATALOG.'storefront/language/'.$row['directory'].'/flag.png';
				}else{
					$result[$i]['image'] = HTTP_CATALOG.$row['image'];
				}
			}
			return $result;
		} else {
			$language_data = $this->cache->get('language');
		
			if (!$language_data) {
				$query = $this->db->query( "SELECT *
											FROM " . DB_PREFIX . "languages
											ORDER BY sort_order, name");
	
    			foreach ($query->rows as $result) {
      				$language_data[$result['code']] = array(
        				'language_id' => $result['language_id'],
        				'name'        => $result['name'],
        				'code'        => $result['code'],
						'locale'      => $result['locale'],
						'image'       => (empty($result['image']) ? HTTP_CATALOG.'storefront/language/'.$result['directory'].'/flag.png' : HTTP_CATALOG.$result['image'] ),
						'directory'   => $result['directory'],
						'filename'    => $result['filename'],
						'sort_order'  => $result['sort_order'],
						'status'      => $result['status']
      				);
    			}
				$this->cache->set('language', $language_data);
			}
		
			return $language_data;			
		}
	}

	public function getTotalLanguages( $data = array() ) {
		return $this->getLanguages( $data, 'total_only' );
	}
}
?>