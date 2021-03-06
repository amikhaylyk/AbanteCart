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
if (! defined ( 'DIR_CORE' )) {
	header ( 'Location: static_pages/' );
}

final class ACart {
  	private $registry;
  	public function __construct($registry) {
  		$this->registry = $registry;

		$this->attribute = new AAttribute('product_option');
		$this->promotion = new APromotion();
	
		if (!isset($this->session->data['cart']) || !is_array($this->session->data['cart'])) {
      		$this->session->data['cart'] = array();
    	}
	}

	public function __get($key) {
		return $this->registry->get($key);
	}
	
	public function __set($key, $value) {
		$this->registry->set($key, $value);
	}
		      
  	public function getProducts() {
		$product_data = array();
		if ($this->customer->isLogged()) {
			$customer_group_id = $this->customer->getCustomerGroupId();
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

        $elements_with_options = HtmlElementFactory::getElementsWithOptions();
		$this->load->model('catalog/product');
		
		//process data in the cart session per each product in the cart
    	foreach ($this->session->data['cart'] as $key => $data) {
      		$array = explode(':', $key);
      		$product_id = $array[0];
      		$quantity =	 $data['qty'];
			$stock = TRUE;

      		if (isset($data['options'])) {
        		$options = $data['options'];
      		} else {
        		$options = array();
      		}

      	  	$product_query = $this->model_catalog_product->getProductDataForCart($product_id);
			if ( count($product_query) ) {
      			$option_price = 0;

      			$option_data = array();
				$groups = array();

      			//Process each option and value	
      			foreach ($options as $product_option_id => $product_option_value_id) {

				    //skip empty values
				    if ($product_option_value_id == '' || (is_array($product_option_value_id) && !$product_option_value_id)) {
					    continue;
				    }
					//Detect option element type. If single value (text, input) process diferently. 
                    $option_attribute = $this->attribute->getAttributeByProductOptionId($product_option_id);
                    if ( $option_attribute ) {
                    	$element_type = $option_attribute['element_type'];
                    	$option_query['name'] = $option_attribute['name'];
                    } else {                    
                    	//Not global attribute based option, select element type from options table
                    	$option_query = $this->model_catalog_product->getProductOption($product_id, $product_option_id);
                    	$element_type = $option_query['element_type'];
                    }

					if (!in_array($element_type, $elements_with_options)) {
						//This is single value element, get all values and expect only one	
						$option_value_query = $this->model_catalog_product->getProductOptionValues($product_id, $product_option_id);
						$option_value_query = $option_value_query[0];
						//Set value from input
						$option_value_query['name'] = $this->db->escape($options[$product_option_id]);
					} else {
						if(is_array($product_option_value_id)){
							$option_value_queries = array();
							foreach($product_option_value_id as $val_id){
								$option_value_queries[$val_id] = $this->model_catalog_product->getProductOptionValue($product_id, $val_id);
							}
						}else{
							$option_value_query = $this->model_catalog_product->getProductOptionValue($product_id, $product_option_value_id);
						}
					}

 					if ( $option_value_query ) {
						if(!$option_value_queries){
							//if group option load price from parent value
							if ( $option_value_query['group_id'] && !in_array($option_value_query['group_id'], $groups) ) {
								$group_value_query = $this->model_catalog_product->getProductOptionValue($product_id, $option_value_query['group_id']);
								$option_value_query['prefix'] = $group_value_query['prefix'];
								$option_value_query['price'] = $group_value_query['price'];
								$groups[] = $option_value_query['group_id'];
							}
							$option_data[] = array( 'product_option_value_id' => $product_option_value_id,
													'name'                    => $option_query['name'],
													'value'                   => $option_value_query['name'],
													'prefix'                  => $option_value_query['prefix'],
													'price'                   => $option_value_query['price'],
													'sku'                     => $option_value_query['sku'],
													'weight'                  => $option_value_query['weight'],
													'weight_type'             => $option_value_query['weight_type']);
						}else{
							foreach($option_value_queries as $val_id=>$item){
								$option_data[] = array( 'product_option_value_id' => $val_id,
														'name'                    => $option_query['name'],
														'value'                   => $item['name'],
														'prefix'                  => $item['prefix'],
														'price'                   => $item['price'],
														'sku'                     => $item['sku'],
														'weight'                  => $item['weight'],
														'weight_type'             => $item['weight_type']);
							}
							unset($option_value_queries);

						}




						if ($option_value_query['subtract'] && (!$option_value_query['quantity'] || ($option_value_query['quantity'] < $quantity))) {
							$stock = FALSE;
						}
					}
      			}

				
				$discount_quantity = 0;
				foreach ($this->session->data['cart'] as $k => $v) {
					$array2 = explode(':', $k);
					if ($array2[0] == $product_id) {
						$discount_quantity += $v['qty'];
					}
				}
				
				//Apply group and quantaty discount first and if non, reply product discount
				$price = $this->promotion->getProductQtyDiscount($product_id, $discount_quantity);
				if ( !$price ) {
					$price = $this->promotion->getProductSpecial($product_id );
				}
				//Still no special price, use regulr price
				if ( !$price ) {
					$price = $product_query['price'];
				} 

				foreach ( $option_data as $item ) {
					if ( $item['prefix'] == '%' ) {
						$option_price += $price * $item['price'] / 100;
					} else {
						$option_price += $item['price'];
					}
				}

				$download_data = array();     		
				$download_query_rows = $this->model_catalog_product->getProductDownloads( $product_id );
				foreach ($download_query_rows as $download) {
        			$download_data[] = array(
          				'download_id' => $download['download_id'],
						'name'        => $download['name'],
						'filename'    => $download['filename'],
						'mask'        => $download['mask'],
						'remaining'   => $download['remaining']
        			);
				}
				
				if (!$product_query['quantity'] || ($product_query['quantity'] < $quantity)) {
					$stock = FALSE;
				}
				
      			$product_data[$key] = array(
        			'key'          => $key,
        			'product_id'   => $product_query['product_id'],
        			'name'         => $product_query['name'],
        			'model'        => $product_query['model'],
					'shipping'     => $product_query['shipping'],
        			'option'       => $option_data,
					'download'     => $download_data,
        			'quantity'     => $quantity,
        			'minimum'      => $product_query['minimum'],
					'stock'        => $stock,
        			'price'        => ($price + $option_price),
        			'total'        => ($price + $option_price) * $quantity,
        			'tax_class_id' => $product_query['tax_class_id'],
        			'weight'       => $product_query['weight'],
        			'weight_class' => $product_query['weight_class'],
        			'length'       => $product_query['length'],
					'width'        => $product_query['width'],
					'height'       => $product_query['height'],
        			'length_class' => $product_query['length_class']					
      			);
			} else {
				$this->remove($key);
			}
    	}

		return $product_data;
  	}
		  
  	public function add($product_id, $qty = 1, $options = array()) {
		$product_id = (int)$product_id;
    	if (!$options) {
      		$key = $product_id;
    	} else {
      		$key = $product_id . ':' . md5(serialize($options));
    	}
    	
		if ((int)$qty && ((int)$qty > 0)) {
    		if (!isset($this->session->data['cart'][$key])) {
      			$this->session->data['cart'][$key]['qty'] = (int)$qty;
    		} else {
      			$this->session->data['cart'][$key]['qty'] += (int)$qty;
    		}
    		//TODO Add validation for correct options for the product and add error return or more stable behaviour
			$this->session->data['cart'][$key]['options'] = $options;
		}
		$this->setMinQty();
  	}

  	public function update($key, $qty) {
    	if ((int)$qty && ((int)$qty > 0)) {
      		$this->session->data['cart'][$key]['qty'] = (int)$qty;
    	} else {
	  		$this->remove($key);
		}
		$this->setMinQty();
  	}

  	public function remove($key) {
		if (isset($this->session->data['cart'][$key])) {
     		unset($this->session->data['cart'][$key]);
  		}
	}

  	public function clear() {
		$this->session->data['cart'] = array();
  	}
  	
  	public function getWeight() {
		$weight = 0;
    	foreach ($this->getProducts() as $product) {
			if ($product['shipping']) {
				$product_weight = $product['weight'];
				// if product_option has weight value
				if($product['option']){
					$hard = false;
					foreach($product['option'] as $option){
						if($option['weight']==0) continue; // if weight not set - skip
						if($option['weight_type'] != '%' && $hard) continue; // if weight was set by option hard and next option try to set another weight hard also - ignore it
						if($option['weight_type'] != '%' && $option['weight'] <= 0) continue;

						if($option['weight_type'] != '%'){
							$hard = true;
							$product_weight = $this->weight->convert($option['weight'], $option['weight_type'], $product['weight_class']);
						}else{
							// ???? we need base weight for % calculation or weight with previous options changes?
							$temp = ($option['weight'] * $product['weight']/100) + $product['weight'];
							$product_weight = $this->weight->convert($temp, $option['weight_type'], $this->config->get('config_weight_class'));
						}
					}
				}



      			$weight += $this->weight->convert($product_weight * $product['quantity'], $product['weight_class'], $this->config->get('config_weight_class'));
			}
		}

		return $weight;
	}

	public function setMinQty() {
		foreach ($this->getProducts() as $product) {
			if ($product['quantity'] < $product['minimum']) {
				$this->session->data['cart'][$product['key']]['qty'] = $product['minimum'];
			}
		}
  	}
	
  	public function getSubTotal() {
		$total = 0;
		
		foreach ($this->getProducts() as $product) {
			$total += $product['total'];
		}

		return $total;
  	}
	
	public function getTaxes() {
		$taxes = array();
		
		foreach ($this->getProducts() as $product) {
			if ($product['tax_class_id']) {
				if (!isset($taxes[$product['tax_class_id']])) {
					$taxes[$product['tax_class_id']] = $product['total'] / 100 * $this->tax->getRate($product['tax_class_id']);
				} else {
					$taxes[$product['tax_class_id']] += $product['total'] / 100 * $this->tax->getRate($product['tax_class_id']);
				}
			}
		}

		return $taxes;
  	}

  	public function getTotal() {
		$total = 0;
		
		foreach ($this->getProducts() as $product) {
			$total += $this->tax->calculate($product['total'], $product['tax_class_id'], $this->config->get('config_tax'));
		}

		return $total;
  	}
  	
  	public function countProducts() {
		$qty = 0;
		foreach ( $this->session->data['cart'] as $product) {
			$qty += $product['qty'];
		}
		return $qty;
	}
	  
  	public function hasProducts() {
    	return count($this->session->data['cart']);
  	}
  
  	public function hasStock() {
		$stock = TRUE;
		
		foreach ($this->getProducts() as $product) {
			if (!$product['stock']) {
	    		$stock = FALSE;
			}
		}
		
    	return $stock;
  	}
  
  	public function hasShipping() {
		$shipping = FALSE;
		
		foreach ($this->getProducts() as $product) {
	  		if ($product['shipping']) {
	    		$shipping = TRUE;

				break;
	  		}		
		}
		
		return $shipping;
	}
	
  	public function hasDownload() {
		$download = FALSE;
		
		foreach ($this->getProducts() as $product) {
	  		if ($product['download']) {
	    		$download = TRUE;
				
				break;
	  		}		
		}
		
		return $download;
	}	
}