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

class ALayoutManager {
	protected $registry;
	private $pages = array ();
	private $layouts = array ();
	private $blocks = array ();
	private $main_placeholders = array ('header', 'header_bottom', 'column_left', 'content_top', 'content_bottom', 'column_right', 'footer_top', 'footer' );
	private $tmpl_id;
	private $layout_id;
	private $active_layout;
	private $page_id;
	private $custom_blocks = array();
	public $errors = 0;
	
	public function __construct($tmpl_id = '', $page_id = '', $layout_id = '') {
		if (! IS_ADMIN) { // forbid for non admin calls
			throw new AException ( AC_ERR_LOAD, 'Error: permission denied to change page layout' );
		}
		
		$this->registry = Registry::getInstance ();

        $this->tmpl_id = ! empty ( $tmpl_id ) ? $tmpl_id : $this->config->get ( 'config_storefront_template' );

		$this->pages = $this->getPage ('','','',$layout_id);

		foreach ( $this->pages as $page ) {
			if (! empty ( $page_id )) {
				if ($page ['page_id'] == $page_id) {
					if(!$layout_id || ($layout_id && $layout_id==$page ['layout_id']) ){
						$this->page = $page;
						break;
					}
				}
			} else {
				if ($page ['controller'] == 'generic') {
					$this->page = $page;
					break;
				}
			}
		}
		$this->page_id = $this->page ['page_id'];

		//Get the page layouts
		$this->layouts = $this->getLayouts ();

		foreach ( $this->layouts as $layout ) {
			if (! empty ( $layout_id )) {
				if ($layout ['layout_id'] == $layout_id) {
					$this->active_layout = $layout;
					break;
				}
			} else {
				if ($layout ['layout_type'] == 1) {
					$this->active_layout = $layout;
					break;
				}
			}
		}

		if (count ( $this->active_layout ) == 0) {
			$this->active_layout = $this->getLayouts ( 0 );
			if (count ( $this->active_layout ) == 0) {
				throw new AException ( AC_ERR_LOAD_LAYOUT, 'No layout found for page_id/controller ' . $this->page_id . '::' . $this->page ['controller'] . '!' );
			}
		}

		$this->layout_id = $this->active_layout ['layout_id'];

        ADebug::variable('Template id', $this->tmpl_id);
        ADebug::variable('Page id', $this->page_id);
        ADebug::variable('Layout id', $this->layout_id);

		// Get blocks
		$this->all_blocks = $this->getAllBlocks ();
		$this->blocks = $this->_getLayoutBlocks ();
	}

	public function __get($key) {
		return $this->registry->get ( $key );
	}

	public function __set($key, $value) {
		$this->registry->set ( $key, $value );
	}
	//???? this is duplicate. Class must be extend ALayout and this method need to delete
	public function getPage($controller='', $key_param = '',$key_value = '', $layout_id=0) {
		$language_id = $this->session->data['content_language_id'];
        $where = "";
		if (! empty ( $controller )) { //????
			$where = "WHERE p.controller = '" . $this->db->escape ( $controller ) . "' ";

			if (! empty ( $key_param )) {
				$where .= empty ( $key_param ) ? "" : "AND p.key_param = '" . $this->db->escape ( $key_param ) . "' ";
				$where .= empty ( $key_value ) ? "" : "AND p.key_value = '" . $this->db->escape ( $key_value ) . "' ";
			}
		}
		if($layout_id){
			$where .= (empty($where) ? "WHERE " : " AND ")."l.template_id = '" . $this->db->escape ( $this->tmpl_id ) . "'";
		}

		$sql = " SELECT p.page_id,
						p.controller,
						p.key_param,
						p.key_value,
						p.created,
						p.updated,
						CASE WHEN l.layout_type = 2 THEN CONCAT(pd.name,' (draft)') ELSE pd.name END as `name`,
						pd.title,
						pd.seo_url,
						pd.keywords,
						pd.description,
						pd.content,
						pl.layout_id,
						l.layout_name
				FROM " . DB_PREFIX . "pages p " . "
				LEFT JOIN " . DB_PREFIX . "page_descriptions pd ON (p.page_id = pd.page_id AND pd.language_id = '".(int)$language_id."' )
				LEFT JOIN " . DB_PREFIX . "pages_layouts pl ON pl.page_id = p.page_id
				LEFT JOIN " . DB_PREFIX . "layouts l ON l.layout_id = pl.layout_id
				". $where ."
				ORDER BY p.page_id ASC";

		$query = $this->db->query ( $sql );
		$pages = $query->rows;
		return $pages;
	}

	public function getLayouts($layout_type = '') {
		$cache_name = 'layout.a.layouts.' . $this->tmpl_id . '.' . $this->page_id . (! empty ( $layout_type ) ? '.' . $layout_type : '');
		if (( string ) $layout_type == '0') {
			$cache_name = 'layout.a.default.' . $this->tmpl_id;
		}
		$layouts = $this->cache->get ( $cache_name, '', (int)$this->config->get('config_store_id') );
		if (! empty ( $layouts )) {
			// return cached layouts
			return $layouts;
		}
		
		$where = 'WHERE template_id = "' . $this->db->escape ( $this->tmpl_id ) . '" ';
		$join = '';
		
		if (( string ) $layout_type != '0') {
			$where .= "AND pl.page_id = '" . ( int ) $this->page_id . "' ";
			$join = "LEFT JOIN " . DB_PREFIX . "pages_layouts as pl ON (l.layout_id = pl.layout_id) ";
		}
		if (! empty ( $layout_type )) {
			$where .= empty ( $layout_type ) ? "" : "AND layout_type = '" . ( int ) $layout_type . "' ";
		}
		
		$sql = "SELECT " . "l.layout_id as layout_id, "
		                . "l.template_id as template_id, "
		                . "l.store_id as store_id, "
		                . "l.layout_type as layout_type, "
		                . "l.layout_name as layout_name, "
		                . "l.created as created, "
		                . "l.updated as updated "
		       . "FROM " . DB_PREFIX . "layouts as l "
		       . $join
		       . $where
		       . " ORDER BY " . "l.layout_id ASC"; 
		
		$query = $this->db->query ( $sql );
		
		if (( string ) $layout_type == '0') {
			$layouts = $query->row;
		} else {
			$layouts = $query->rows;
		}
		
		$this->cache->set ( $cache_name, $layouts, '', (int)$this->config->get('config_store_id') );
		
		return $layouts;
	}
	
	private function _getLayoutBlocks($layout_id = 0) {
		$layout_id = ! $layout_id ? $this->layout_id : $layout_id;
		
		$cache_name = 'layout.a.blocks.' . $layout_id;
		$blocks = $this->cache->get ( $cache_name, '', (int)$this->config->get('config_store_id') );
		if (! empty ( $blocks )) {
			// return cached blocks
			return $blocks;
		}
		
		$sql = "SELECT bl.instance_id as instance_id,
		               bl.layout_id as layout_id,
		               b.block_id as block_id,
		               bl.custom_block_id as custom_block_id,
		               bl.parent_instance_id as parent_instance_id,
		               bl.position as position,
		               bl.status as status,
		               b.block_txt_id as block_txt_id,
		               b.controller as controller
		        FROM " . DB_PREFIX . "blocks as b
		        LEFT JOIN " . DB_PREFIX . "block_layouts as bl ON (bl.block_id = b.block_id)
		        WHERE bl.layout_id = '" . $layout_id . "'
		        ORDER BY bl.parent_instance_id ASC, bl.position ASC";
		
		$query = $this->db->query ( $sql );
		$blocks = $query->rows;
		
		$this->cache->set ( $cache_name, $blocks, '', (int)$this->config->get('config_store_id') );
		
		return $blocks;
	}
	
	public function getAllBlocks() {
		$cache_name = 'layout.a.blocks.all';
		$blocks = $this->cache->get ( $cache_name, '', (int)$this->config->get('config_store_id') );
		if (! empty ( $blocks )) {
			// return cached blocks
			return $blocks;
		}
		
		$sql = "SELECT b.block_id as block_id, "
		            . "b.block_txt_id as block_txt_id, "
		            . "b.controller as controller, "
		            . "bt.parent_block_id as parent_block_id, "
		            . "bt.template as template, "
		            . "COALESCE(cb.custom_block_id,0) as custom_block_id, "
		            . "b.created as block_date_added "
		       . "FROM " . DB_PREFIX . "blocks as b "
		       . "LEFT JOIN " . DB_PREFIX . "block_templates as bt ON (b.block_id = bt.block_id) "
		       . "LEFT JOIN " . DB_PREFIX . "custom_blocks as cb ON (b.block_id = cb.block_id ) "
		       . "ORDER BY b.block_id ASC";

		$query = $this->db->query ( $sql );
		if($query->num_rows){
			foreach($query->rows as $block){
				if($block['custom_block_id']){
					$block['block_name'] = $this->getCustomBlockName($block['custom_block_id'],$this->config->get ( 'admin_language' ));
				}
				$blocks[]=$block;
			}
		}

		$this->cache->set ( $cache_name, $blocks, '', (int)$this->config->get('config_store_id') );
		return $blocks;
	}
	
	public function getTemplateId() {
		return $this->tmpl_id;
	}
	
	public function getPages() {
		return $this->pages;
	}
	
	public function getPageData() {
		return $this->page;
	}
	
	public function getPageByController($controller) {
		foreach ( $this->pages as $page ) {
			if ($page ['controller'] == $controller) {
				return $page;
			}
		}
	}
	
	public function getActiveLayout() {
		return $this->active_layout;
	}
	
	public function getLayoutBlockByTxtId($block_txt_id) {
		foreach ( $this->blocks as $block ) {
			if ($block ['block_txt_id'] == $block_txt_id) {
				return $block;
			}
		}
	}

	public function getBlockByTxtId($block_txt_id) {
		foreach ( $this->all_blocks as $block ) {
			if ($block ['block_txt_id'] == $block_txt_id) {
				return $block;
			}
		}
	}
	
	public function getBlockChildren($instance_id = 0) {
		$blocks = array ();
		foreach ( $this->blocks as $block ) {
			if (( string ) $block ['parent_instance_id'] == ( string ) $instance_id) {
				array_push ( $blocks, $block );
			}
		}
		return $blocks;
	}
	
	public function getInstalledBlocks() {
		$blocks = array ();
		
		foreach ( $this->all_blocks as $block ) {
			// do not include main level blocks
			if (! in_array ( $block ['block_txt_id'], $this->main_placeholders )) {
				$blocks [] = $block;
			}
		}
		
		return $blocks;
	}
	
	public function getLayoutBlocks() {
		$blocks = array ();
		
		foreach ( $this->main_placeholders as $placeholder ) {
			$block = $this->getLayoutBlockByTxtId ( $placeholder );
			if (! empty ( $block )) {
				$blocks [$block ['block_id']] = $block;
				$blocks [$block ['block_id']] ['children'] = $this->getBlockChildren ( $block ['instance_id'] );
			}
		}
		
		return $blocks;
	}
	
	public function getLayoutByType($layout_type) {
		$layouts = array ();
		
		foreach ( $this->layouts as $layout ) {
			if ($layout ['layout_type'] == $layout_type) {
				$layouts [] = $layout;
			}
		}
		
		return $layouts;
	}
	
	public function getLayoutDrafts() {
		return $this->getLayoutByType ( 2 );
	}
	
	public function getLayoutTemplates() {
		return $this->getLayoutByType ( 3 );
	}
	public function getLayoutId(){
		return $this->layout_id;
	}
	
	public function savePageLayout($data) {
		$page = $this->page;
		$layout = $this->active_layout;
		$new_layout = false;

		if ($layout ['layout_type'] == 0 && ($page ['controller'] != 'generic' || $data['controller'])) {
			$layout ['layout_name'] = $data ['layout_name'];
			$layout ['layout_type'] = 1;
			$this->layout_id = $this->saveLayout ( $layout );
			$new_layout = true;

			$this->db->query ( "INSERT INTO " . DB_PREFIX . "pages_layouts (layout_id,page_id)
								VALUES ('" . ( int ) $this->layout_id . "','" . ( int ) $this->page_id . "')" );
		}
		
		foreach ( $this->main_placeholders as $placeholder ) {
			$block = $this->getLayoutBlockByTxtId ( $placeholder );
			if (! empty ( $block )) {
				list($block ['block_id'],$block ['custom_block_id']) = explode("_",$block ['block_id']);
				if (! empty ( $data ['blocks'] [$block ['block_id']] )) {
					$block = array_merge ( $block, $data ['blocks'] [$block ['block_id']] );
					if ($new_layout) {
						$block ['layout_id'] = $this->layout_id;
						$instance_id = $this->saveLayoutBlocks ( $block );
					} else {
						$instance_id = $this->saveLayoutBlocks ( $block, $block ['instance_id'] );
					}
					
					if (isset ( $data ['blocks'] [$block ['block_id']] ['children'] )) {
						$this->deleteLayoutBlocks ( $this->layout_id, $instance_id );
						
						foreach ( $data ['blocks'] [$block ['block_id']] ['children'] as $key => $block_id ) {
							$child = array ();
							if (! empty ( $block_id )) {
								$child ['layout_id'] = $this->layout_id;
								list($child ['block_id'],$child ['custom_block_id']) = explode("_",$block_id);
								$child ['parent_instance_id'] = $instance_id;
								$child ['position'] = ($key + 1) * 10;
								$child ['status'] = 1;
								$child_id = $this->saveLayoutBlocks ( $child );
							}
						}
					}
				}
			}
		}

        $this->cache->delete('layout');
	}
	
	public function saveLayoutBlocks($data, $instance_id = 0) {
		if (! $instance_id) {
			$this->db->query ( "INSERT INTO " . DB_PREFIX . "block_layouts (layout_id,block_id,custom_block_id,parent_instance_id,position,status,created,updated)
										VALUES ('" . ( int ) $data ['layout_id'] . "',
												'" . ( int ) $data ['block_id'] . "',
												'" . ( int ) $data ['custom_block_id'] . "',
												'" . ( int ) $data ['parent_instance_id'] . "',
												'" . ( int ) $data ['position'] . "',
												'" . ( int ) $data ['status'] . "',
												NOW(),
												NOW())" );
			
			$instance_id = $this->db->getLastId ();
		} else {
			$this->db->query ( "UPDATE " . DB_PREFIX . "block_layouts
										 SET layout_id = '" . ( int ) $data ['layout_id'] . "',
										        block_id = '" . ( int ) $data ['block_id'] . "',
										        custom_block_id = '" . ( int ) $data ['custom_block_id'] . "',
										        parent_instance_id = '" . ( int ) $data ['parent_instance_id'] . "',
										        position = '" . ( int ) $data ['position'] . "',
										        status = '" . ( int ) $data ['status'] . "',
										        updated = NOW()
										 WHERE instance_id = '" . ( int ) $instance_id . "'" );
		}
		
		$this->cache->delete ( 'layout.a.blocks' );
		$this->cache->delete ( 'layout.blocks' );
		
		return $instance_id;
	}
	
	public function deleteLayoutBlocks($layout_id = 0, $parent_instance_id = 0) {
		if (! $parent_instance_id && ! $layout_id) {
			throw new AException ( AC_ERR_LOAD, 'Error: Cannot to delete layout block, parent_instance_id "' . $parent_instance_id . '" and layout_id "' . $layout_id . '" doesn\'t exists.' );
		} else {
			$this->db->query ( "DELETE FROM " . DB_PREFIX . "block_layouts
								WHERE layout_id = '" . ( int ) $layout_id . "' AND parent_instance_id = '" . ( int ) $parent_instance_id . "'" );
			
			$this->cache->delete ( 'layout.a.blocks' );
			$this->cache->delete ( 'layout.blocks' );
		}
	}
	
	public function saveLayout($data, $layout_id = 0) {
		if (! $layout_id) {
			$this->db->query ( "INSERT INTO " . DB_PREFIX . "layouts (template_id,store_id,layout_name,layout_type,created,updated)
								VALUES ('" . $this->db->escape ( $data ['template_id'] ) . "',
										'" . ( int ) $data ['store_id'] . "',
										'" . $this->db->escape ( $data ['layout_name'] ) . "',
										'" . ( int ) $data ['layout_type'] . "',
										NOW(),
										NOW())" );
			$layout_id = $this->db->getLastId ();
		} else {
			$this->db->query ( "UPDATE " . DB_PREFIX . "layouts
								SET template_id = '" . ( int ) $data ['template_id'] . "',
									store_id = '" . ( int ) $data ['store_id'] . "',
									layout_name = '" . $this->db->escape ( $data ['layout_name'] ) . "',
									layout_type = '" . ( int ) $data ['layout_type'] . "',
									updated = NOW()
								WHERE layout_id = '" . ( int ) $layout_id . "'" );
		}
		
		$this->cache->delete ( 'layout.a.default' );
		$this->cache->delete ( 'layout.a.layouts' );
		$this->cache->delete ( 'layout.default' );
		$this->cache->delete ( 'layout.layouts' );
		$this->cache->delete ( 'layout.a.block.descriptions' );
		
		return $layout_id;
	}
	
	public function savePage($data, $page_id = 0) {
		if (! $page_id) {
			$this->db->query ( "INSERT INTO " . DB_PREFIX . "pages (   parent_page_id,
																	controller,
																	key_param,
																	key_value,
																	created,
																	updated)
								VALUES ('" . ( int ) $data ['parent_page_id'] . "',
										'" . $this->db->escape ( $data ['controller'] ) . "',
										'" . $this->db->escape ( $data ['key_param'] ) . "',
										'" . $this->db->escape ( $data ['key_value'] ) . "',
										NOW(),
										NOW())" );
			
			$page_id = $this->db->getLastId ();


		} else {
			$this->db->query ( "UPDATE " . DB_PREFIX . "pages
								SET parent_page_id = '" . ( int ) $data ['parent_page_id'] . "',
									controller = '" . $this->db->escape ( $data ['controller'] ) . "',
									key_param = '" . $this->db->escape ( $data ['key_param'] ) . "',
									key_param = '" . $this->db->escape ( $data ['key_value'] ) . "',
									updated = NOW()
								WHERE page_id = '" . ( int ) $page_id . "'" );

			// clear all page descriptions before write
			$this->db->query ( "DELETE FROM " . DB_PREFIX . "page_descriptions WHERE page_id = '" . ( int ) $page_id . "'");

		}

		// page description
		if($data ['page_descriptions']){
			foreach($data ['page_descriptions'] as $language_id=>$description){
				if(!(int)$language_id){ continue;}
				$sql = "INSERT INTO " . DB_PREFIX . "page_descriptions (page_id,
																		language_id,
																		`name`,
																		title,
																		seo_url,
																		keywords,
																		description,
																		content,
																		created,
																		updated)
						VALUES ('" . ( int ) $page_id . "',
								'" . ( int ) $language_id . "',
								'" . $this->db->escape ( $description['name'] ) . "',
								'" . $this->db->escape ( $description['title'] ) . "',
								'" . $this->db->escape ( $description['seo_url'] ) . "',
								'" . $this->db->escape ( $description['keywords'] ) . "',
								'" . $this->db->escape ( $description['description'] ) . "',
								'" . $this->db->escape ( $description['content'] ) . "',
								NOW(),
								NOW())";
					$this->db->query ($sql);
				}
			}




		$this->cache->delete ( 'layout.a.pages' );
		$this->cache->delete ( 'layout.pages' );
		
		return $page_id;
	}
	
	public function saveBlock($data, $block_id = 0) {
		//
		if(!(int)$block_id){
			$block = $this->getBlockByTxtId($data ['block_txt_id']);
			$block_id = $block['block_id'];

		}

		if (! $block_id) {
			$this->db->query ( "INSERT INTO " . DB_PREFIX . "blocks (block_txt_id,
																	 controller,
																	 created,
																	 updated)
			       				VALUES ('" . $this->db->escape ( $data ['block_txt_id'] ) . "',
			       				        '" . $this->db->escape ( $data ['controller'] ) . "',
			       				        NOW(),
			       				        NOW())" );
			
			$block_id = $this->db->getLastId ();

			if (isset ( $data ['templates'] )) {
				foreach ( $data ['templates'] as $tmpl ) {

					if(!isset($tmpl ['parent_block_id']) && $tmpl ['parent_block_txt_id']){
						$parent = $this->getBlockByTxtId($tmpl ['parent_block_txt_id']);
						$tmpl['parent_block_id'] = $parent['block_id'];
					}

					$this->db->query ( "INSERT INTO " . DB_PREFIX . "block_templates (block_id,parent_block_id,template,created,updated)
										VALUES ('" . ( int ) $block_id . "',
												'" . ( int ) $tmpl ['parent_block_id'] . "',
												'" . $this->db->escape ( $tmpl ['template'] ) . "',
												NOW(),
												NOW())" );
				}
			}
		} else {
				if($data ['controller']){
					$this->db->query ( "UPDATE " . DB_PREFIX . "blocks
										SET block_txt_id = '" . $this->db->escape ( $data ['block_txt_id'] ) . "',
											controller = '" . $this->db->escape ( $data ['controller'] ) . "',
											updated = NOW()
										WHERE block_id = '" . ( int ) $block_id . "'" );
				}





			if (isset ( $data ['templates'] )) {
				$this->deleteBlockTemplates ( $block_id );
				foreach ( $data ['templates'] as $tmpl ) {

					if(!isset($tmpl ['parent_block_id']) && $tmpl ['parent_block_txt_id']){
						$parent = $this->getBlockByTxtId($tmpl ['parent_block_txt_id']);
						$tmpl['parent_block_id'] = $parent['block_id'];
					}
					$this->db->query ( "INSERT INTO " . DB_PREFIX . "block_templates (block_id,
																					  parent_block_id,
																					  template,
																					  created,
																					  updated)
										VALUES ('" . ( int ) $block_id . "',
												'" . ( int ) $tmpl ['parent_block_id'] . "',
												'" . $this->db->escape ( $tmpl ['template'] ) . "',
												NOW(),
												NOW())" );
				}
			}
		}
		// save block descriptions by pass
		$data['block_descriptions'] = !isset($data['block_descriptions']) && $data['block_description'] ? array($data['block_description']) : $data['block_descriptions'];
		if($data['block_descriptions']){
			foreach($data['block_descriptions'] as $block_description){
				if(!isset($block_description ['language_id']) && $block_description ['language_name']){
						$block_description ['language_id'] = $this->_getLanguageIdByName($block_description ['language_name']);
				}
				if(!$block_description ['language_id']){
					continue;
				}
			$this->saveBlockDescription($block_id,$block_description['block_description_id'],$block_description);
			}
		}

		$this->cache->delete ( 'layout.a.blocks' );
		$this->cache->delete ( 'layout.blocks' );
		
		return $block_id;
	}

	public function saveBlockDescription($block_id=0, $custom_block_id=0, $description=array()){
		$block_id = (int)$block_id;
		$custom_block_id = (int)$custom_block_id;
		if( !$description['language_id']){
			$this->errors = 'Error: Can\'t save custom block, because language_id is empty!';
			$this->log->write($this->errors);
			return false;
		}

		if($custom_block_id){
				$exist = $this->getBlockDescriptions($custom_block_id);
			    if(isset($exist[( int ) $description ['language_id']])){
				    $tmp = array();
				    if(isset($description ['name'])){
					   $tmp[] = "`name` = '" . $this->db->escape ( $description ['name'] ) . "'";
				    }
				    if(isset($description ['block_wrapper'])){
					   $tmp[] = "`block_wrapper` = '" . $this->db->escape ( $description ['block_wrapper'] ) . "'";
				    }
				    if(isset($description ['title'])){
					   $tmp[] = "`title` = '" . $this->db->escape ( $description ['title'] ) . "'";
				    }
				    if(isset($description ['description'])){
					   $tmp[] = "`description` = '" . $this->db->escape ( $description ['description'] ) . "'";
				    }
				    if(isset($description ['content'])){
					   $tmp[] = "`content` = '" . $this->db->escape ( $description ['content'] ) . "'";
				    }
					if($tmp){
						$sql = "UPDATE " . DB_PREFIX . "block_descriptions
								SET ". implode(", ",$tmp)."
								WHERE custom_block_id = '" . ( int ) $custom_block_id . "'
									AND language_id = '" . ( int ) $description ['language_id'] . "'";
						$this->db->query ( $sql );
					}
			    }else{
				    $sql  = "INSERT INTO " . DB_PREFIX . "block_descriptions
									   (custom_block_id,
										language_id,
										block_wrapper,
										`name`,
										title,
										description,
										content,
										created)
									VALUES ( '" . $custom_block_id . "',
											 '" . ( int ) $description ['language_id'] . "',
											 '" . $this->db->escape ( $description ['block_wrapper'] ). "',
											 '" . $this->db->escape ( $description ['name'] ) . "',
											 '" . $this->db->escape ( $description ['title'] ) . "',
											 '" . $this->db->escape ( $description ['description'] ) . "',
											 '" . $this->db->escape ( $description ['content'] ) . "',
											 NOW())";
					$this->db->query ($sql);

			    }
			if(isset($description['status'])){
				$sql = "UPDATE " . DB_PREFIX . "block_layouts
										SET `status` = '" . (int)$description ['status'] . "'
										WHERE custom_block_id = '" . ( int ) $custom_block_id . "'";
				$this->db->query ( $sql);
			}

			$this->cache->delete ( 'layout.a.block.descriptions.' . $custom_block_id );
			$this->cache->delete ( 'layout.a.blocks' );
		    $this->cache->delete ( 'layout.blocks' );
			return true;
		}else{
			if(!$block_id){
				$this->errors = 'Error: Can\'t save custom block, because block_id is empty!';
				return false;
			}
			$this->db->query ( "INSERT INTO " . DB_PREFIX . "custom_blocks (block_id, created) VALUES ( '" . $block_id . "', NOW())" );
			$custom_block_id = $this->db->getLastId ();

			$sql  = "INSERT INTO " . DB_PREFIX . "block_descriptions
									   (custom_block_id,
										language_id,
										block_wrapper,
										`name`,
										title,
										description,
										content,
										created)
									VALUES ( '" . $custom_block_id . "',
											 '" . ( int ) $description ['language_id'] . "',
											 '" . $this->db->escape ( $description ['block_wrapper'] ). "',
											 '" . $this->db->escape ( $description ['name'] ) . "',
											 '" . $this->db->escape ( $description ['title'] ) . "',
											 '" . $this->db->escape ( $description ['description'] ) . "',
											 '" . $this->db->escape ( $description ['content'] ) . "',
											 NOW())";
			$this->db->query ($sql);

			$this->cache->delete ( 'layout.a.block.descriptions.' . $custom_block_id );
			$this->cache->delete ( 'layout.a.blocks' );
		    $this->cache->delete ( 'layout.blocks' );
			return 	$custom_block_id;
		}

	}
	
	public function getBlockDescriptions($custom_block_id=0){
		if(!(int)$custom_block_id){
			return false;
		}
		$cache_name = 'layout.a.block.descriptions.' . $custom_block_id;
		$output = $this->cache->get ( $cache_name );

		if (! is_null( $output )) {
			// return cached blocks
			return $output;
		}
		
		$output = array();
		$sql = "SELECT bd.*, COALESCE(bl.status,0) as status
				FROM " . DB_PREFIX . "block_descriptions bd
				LEFT JOIN " . DB_PREFIX . "block_layouts bl ON bl.custom_block_id = bd.custom_block_id
				WHERE bd.custom_block_id = '" . ( int ) $custom_block_id . "'";
		$result = $this->db->query ( $sql );
		if($result->num_rows){
			foreach($result->rows as $row){
				$output[$row['language_id']] = $row;
			}
		}
		$this->cache->set ( $cache_name, $output );
		return $output;
	}

	public function getCustomBlockName($custom_block_id,$language_id=0){
		if(!(int)$custom_block_id){
			return false;
		}
		$language_id = (int)$language_id;
		$info = $this->getBlockDescriptions($custom_block_id);
		$block_name = $info[$language_id] ? $info[$language_id]['name'] : '';
		$block_name = !$block_name ? $info[key($info)]['name'] : $block_name;
	return $block_name;
	}

	public function deleteCustomBlock($custom_block_id){
		if(!(int)$custom_block_id){
			return false;
		}
		//check for link with layouts
		$usage = $this->db->query ( "SELECT *
									 FROM " . DB_PREFIX . "block_layouts
									 WHERE custom_block_id = '" . ( int ) $custom_block_id . "'" );
		if($usage->num_rows){
			return false;
		}

			$this->db->query ( "DELETE FROM " . DB_PREFIX . "block_descriptions
								WHERE custom_block_id = '" . ( int ) $custom_block_id . "'" );
			$this->db->query ( "DELETE FROM " . DB_PREFIX . "custom_blocks
								WHERE custom_block_id = '" . ( int ) $custom_block_id . "'" );

			$this->cache->delete ( 'layout.a.blocks' );
			$this->cache->delete ( 'layout.blocks' );
			$this->cache->delete ( 'layout.a.block.descriptions.' . $custom_block_id );
		return true;
	}


	public function deleteBlockTemplates($block_id = 0, $parent_block_id = 0) {
		if (! $block_id) {
			throw new AException ( AC_ERR_LOAD, 'Error: Cannot to delete block template, block_id "' . $block_id . '" doesn\'t exists.' );
		} else {
			$sql = "DELETE FROM " . DB_PREFIX . "block_templates WHERE block_id = '" . ( int ) $block_id . "'";
			if ($parent_block_id) {
				$sql .= " AND parent_block_id = '" . ( int ) $parent_block_id . "'";
			}
			$this->db->query ( $sql );
			
			$this->cache->delete ( 'layout.a.blocks' );
			$this->cache->delete ( 'layout.blocks' );
		}
	}
	public function deleteBlock($block_txt_id = '') {

		$block = $this->getBlockByTxtId($block_txt_id);
		$block_id = $block['block_id'];

		if ($block_id) {
			$this->db->query ( "DELETE FROM " . DB_PREFIX . "block_templates
								WHERE block_id = '" . ( int ) $block_id . "'" );
			//$this->db->query ( "DELETE FROM " . DB_PREFIX . "block_descriptions WHERE block_id = '" . ( int ) $block_id . "'" );
			$this->db->query ( "DELETE FROM " . DB_PREFIX . "block_layouts
								WHERE block_id = '" . ( int ) $block_id . "'" );
			$this->db->query ( "DELETE FROM " . DB_PREFIX . "blocks
								WHERE block_id = '" . ( int ) $block_id . "'" );

			$this->cache->delete ( 'layout.a.blocks' );
			$this->cache->delete ( 'layout.blocks' );
		}
	}

    public function cloneTemplateLayouts( $new_template ) {

        $sql = "SELECT * FROM " . DB_PREFIX . "layouts WHERE template_id = '".$this->tmpl_id ."' ";
        $result = $this->db->query($sql);
        foreach ( $result->rows as $layout ) {
            //clone layout
            $new_layout = array(
                'template_id' => $new_template,
                'store_id' => $layout['store_id'],
                'layout_name' => $layout['layout_name'],
                'layout_type' => $layout['layout_type'],
            );
            $layout_id = $this->saveLayout ( $new_layout );

            $sql = "SELECT *
                    FROM " . DB_PREFIX . "pages_layouts
                    WHERE layout_id = '". $layout['layout_id'] ."' ";
            $result_pages = $this->db->query($sql);
            foreach ( $result_pages->rows as $page ) {
                //connect it to page
		        $this->db->query ( "INSERT INTO " . DB_PREFIX . "pages_layouts (layout_id,page_id)
		                            VALUES ('" . ( int ) $layout_id . "','" . ( int ) $page['page_id'] . "')" );
            }

            //clone blocks
            $blocks = $this->_getLayoutBlocks($layout['layout_id']);
            $instance_map = array();
            // insert top level block first
            foreach ( $blocks as $block ) {
                if ($block['parent_instance_id'] == 0) {
                    $block['layout_id'] = $layout_id;
                    $b_id = $this->saveLayoutBlocks ( $block );
                    $instance_map[ $block['instance_id'] ] = $b_id;
                }
            }
            // insert child blocks
            foreach ( $blocks as $block ) {
                if ($block['parent_instance_id'] != 0) {
                    $block['layout_id'] = $layout_id;
                    $block['parent_instance_id'] = $instance_map[ $block['parent_instance_id'] ];
                    $this->saveLayoutBlocks ( $block );
                }
            }
        }
    }

    public function deleteTemplateLayouts() {

	    $sql = "SELECT *
                FROM " . DB_PREFIX . "layouts
                WHERE template_id = '".$this->tmpl_id ."' ";
        $result = $this->db->query($sql);
        foreach ( $result->rows as $layout ) {

            $this->db->query ( "DELETE FROM " . DB_PREFIX . "layouts
                                WHERE layout_id = '" . $layout['layout_id'] . "' " );
            $this->db->query ( "DELETE FROM " . DB_PREFIX . "pages_layouts
                                WHERE layout_id = '" . $layout['layout_id'] . "' " );
            $this->db->query ( "DELETE FROM " . DB_PREFIX . "block_layouts
                                WHERE layout_id = '" . $layout['layout_id'] . "' " );
        }

	    $this->cache->delete ( 'layout' );
	}


	
	/**
	 * loadXML() Load layout from XML file or XML String
	 *
	 * @param  $values - array:  file => [full path to file] or xml => [XML string]
	 * @return void
     
     Layout XML Sample:
     
		<template_layouts>
		  <layout>
		  	<action>insert</action>
		  	<name>Product Layout</name>
		  	<template_id>TEMPLATE1</template_id>
			<type>Active</type>
			<pages>
				<page>
					<controller>pages/product/product>
					<key_param>product_id<key_param>
					<key_value>100<key_value>
				</page>
			</pages>
			<blocks>
			<block>
			  <block_txt_id >language</block_txt_id >
			  <controller>blocks/language</controller>
			  <templates>
			  	<template>
			    	<parent_block>header</parent_block>
					<template_name>blocks/language.tpl</template_name >
			  	</template>
			  	<template>
			    	<parent_block>right</parent_block>
					<template_name>blocks/language_right.tpl</template_name>
			  	</template>
			  </templates>
			  <installed>
			  	<placeholder>header</placeholder>
			  </installed>
			</block>
	        <block>
	            <kind>custom</kind>
	            <type>html_block</type>
	            <custom_block_txt_id></custom_block_txt_id>
	            <block_descriptions>
					<block_description>
						<language></language>
						<name></name>
						<title></title>
						<block_wrapper></title>
						<description></description>
						<content></content>
					</block_description>
			  </block_descriptions>
	          <installed>
			  	<placeholder>header</placeholder>
			  </installed>
	        </block>
			</blocks>
		  </layout>
		</template_layouts>
	 */
	
	public function loadXML($data) {
		// Input possible with XML string, File or both.
		// We process both one at a time. XML string processed first		
		if ($data ['xml']) {
			$xml_obj = simplexml_load_string ( $data ['xml'] );
			if (! $xml_obj) {
				$err = "Failed loading XML data string";
				foreach ( libxml_get_errors () as $error ) {
					$err .= "  " . $error->message;
				}
				$error = new AError ( $err );
				$error->toLog ()->toDebug ();
			} else {
				$this->_processXML ( $xml_obj );
			}
		}
		
		if (isset ( $data ['file'] ) && is_file ( $data ['file'] )) {
			$xml_obj = simplexml_load_file ( $data ['file'] );
			if (! $xml_obj) {
				$err = "Failed loading XML file " . $data ['file'];
				foreach ( libxml_get_errors () as $error ) {
					$err .= "  " . $error->message;
				}
				$error = new AError ( $err );
				$error->toLog ()->toDebug ();
			} else {
				$this->_processXML ( $xml_obj );
			}
		}
	}
	
	private function _processXML($xml_obj) {
		$template_layouts = $xml_obj->xpath ( '/template_layouts/layout' );

		//process each layout 
		foreach ( $template_layouts as $layout ) {
		
			/* Determin an action tag in all patent elements. Action can be insert, update and delete 
		       Default action (if not provided) is update
		       ->>> action = insert 
					Before loading the layout, determin if same layout exists with same name, template and type comdination. 
					If does exists, return and log error 
		       ->>> action = update (default) 
					Before loading the layout, determin if same layout exists with same name, template and type comdination. 
					If does exists, write new settings over existing
		       ->>> action = delete 
					Delete the element provided from databse and delete relationships to other elements linked to currnet one
					
				NOTE: Parent level delete action is cascaded to all childer elements 
				
				TODO: Need to use transaction sql here to prevent partual load or partual delete in case of error
			*/
			
			//check if layout with same name exists
			$sql = "SELECT layout_id
					FROM " . DB_PREFIX . "layouts
			        WHERE layout_name='" . $this->db->escape ( $layout->name ) . "'
			            AND template_id='" . $this->db->escape ( $layout->template_id ) . "'";
			$result = $this->db->query ( $sql );
			$layout_id = $result->row ['layout_id'];
			
			if (! $layout_id && in_array ( $layout->action, array ("", null, "update" ) )) {
				$layout->action = 'insert';
			}
			if ( $layout_id && $layout->action == 'insert'  ) {
				$layout->action = 'update';
			}
			//layouts 			
			if ($layout->action == "delete") {
				
				if ($layout_id) {
					$sql = array ();
					$sql [] = "DELETE FROM " . DB_PREFIX . "pages_layouts
							   WHERE layout_id = '" . $layout_id . "'";
					$sql [] = "DELETE FROM " . DB_PREFIX . "block_layouts
							   WHERE  layout_id = '" . $layout_id . "'";
					$sql [] = "DELETE FROM " . DB_PREFIX . "layouts
							   WHERE layout_id= " . $layout_id;
					foreach ( $sql as $query ) {
						$this->db->query ( $query );
					}
				}
			
			} elseif ($layout->action == 'insert') {
				
				if ($layout_id) {
					$errmessage = 'Error: cannot add new layout (layout name: "' . $layout->name . '") into database because it already exists.';
					$error = new AError ( $errmessage );
					$error->toLog ()->toDebug ();
					$this->errors = 1;
					continue;
				}
				
				// if store name provided
				if (( string ) $layout->store_name) {
					$sql = "SELECT store_id
							FROM " . DB_PREFIX . "stores
							WHERE store_name='" . $this->db->escape ( $layout->store_name ) . "'";
					$result = $this->db->query ( $sql );
					$store_id = $result->row ['store_id'];
					
					if (! $store_id) {
						$this->error [] = 'Can\'t find store with name "' . $layout->store_name . '" for layout "' . $layout->name . '". Use default store.';
					}
				} else {
					$store_id = 0;
				}
				
				// check layout type				
				$layout_type = $layout->type == 'Active' ? 1 : 0;
				
				$sql = "INSERT INTO " . DB_PREFIX . "layouts (template_id,store_id,layout_name,layout_type,created,updated) 
						VALUES ('" . $this->db->escape ( $layout->template_id ) . "',
								'" . ( int ) $store_id . "',
								'" . $this->db->escape ( $layout->name ) . "',
								'" . $layout_type . "',NOW(),NOW())";
				$this->db->query ( $sql );
				$layout_id = $this->db->getLastId ();
				
				// write pages section
				if ($layout->pages->page) {
					foreach ( $layout->pages->page as $page ) {
						$this->_processPage ( $layout_id, $page );
					}
				}
			
			} else { // layout update  
				if (! $layout_id) {
					$errmessage = 'Error: cannot update layout (layout name: "' . $layout->name . '") because it not exists.';
					$error = new AError ( $errmessage );
					$error->toLog ()->toDebug ();
					$this->errors = 1;
					continue;
				}
				
				// if store name provided
				$store_id = '';
				if (( string ) $layout->store_name) {
					$sql = "SELECT store_id
							FROM " . DB_PREFIX . "stores
							WHERE store_name='" . $this->db->escape ( $layout->store_name ) . "'";
					$result = $this->db->query ( $sql );
					$store_id = $result->row ['store_id'];
					
					if (! $store_id) {
						$this->error [] = 'Can\'t find store with name "' . $layout->store_name . '" for layout "' . $layout->name . '". Use default store.';
					}
				}
				
				// check layout type				
				$layout_type = $layout->type == 'Active' ? 1 : 0;
				
				$sql = "UPDATE " . DB_PREFIX . "layouts SET template_id = '" . $this->db->escape ( $layout->template_id ) . "',
															store_id = '" . ( int ) $store_id . "',
															layout_name = '" . $this->db->escape ( $layout->name ) . "',
															layout_type = '" . $layout_type . "',
															created = NOW(),
															updated = NOW() WHERE layout_id='" . $layout_id . "'";
				$this->db->query ( $sql );
				
				// write pages section
				if ($layout->pages->page) {
					foreach ( $layout->pages->page as $page ) {
						$this->_processPage ( $layout_id, $page );
					}
				}
				
			//end layout manipulation
			}
			
			// block manipulation
			foreach ( $layout->blocks->block as $block ) {
				
				if (! $block->block_txt_id) {
					$errmessage = 'Error: cannot process block because block_txt_id is empty.';
					$error = new AError ( $errmessage );
					$error->toLog ()->toDebug ();
					$this->errors = 1;
					continue;
				}
				$layout->layout_id = $layout_id;
				//start recursion on all blocks
				$this->_processBlock ( $layout, $block );
			
			}
		
		} //end of layout manipulation
		

		return;
	}
	
	private function _processPage($layout_id, $page) {
		
		$sql = "SELECT p.page_id
				FROM " . DB_PREFIX . "pages p
				WHERE controller='" . $this->db->escape ( $page->controller ) . "'
						AND key_param = '" . $this->db->escape ( $page->key_param ) . "'
						AND key_value = '" . $this->db->escape ( $page->key_value ) . "'";
		
		$result = $this->db->query ( $sql );
	 	$page_id = ( int ) $result->row ['page_id'];
	 	
	 	if($page_id){
		 	$sql = "SELECT layout_id
					FROM " . DB_PREFIX . "pages_layouts
					WHERE page_id = '" . $page_id . "' AND layout_id= '".$layout_id."'";		
			$result = $this->db->query ( $sql );	 	
			if (! ( int ) $result->row ['layout_id']) {
				$sql = "INSERT INTO " . DB_PREFIX . "pages_layouts (layout_id,page_id) VALUES ('" . ( int ) $layout_id . "','" . ( int ) $page_id . "')";
				$this->db->query ( $sql );
			}
		 }else { // if page new
			$sql = "INSERT INTO " . DB_PREFIX . "pages (parent_page_id, controller, key_param, key_value, created, updated)
					VALUES ('0',
							'" . $this->db->escape ( $page->controller ) . "',
							'" . $this->db->escape ( $page->key_param ) . "',
							'" . $this->db->escape ( $page->key_value ) . "',NOW(),NOW())";
			$this->db->query ( $sql );
			$page_id = $this->db->getLastId ();
			$sql = "INSERT INTO " . DB_PREFIX . "pages_layouts (layout_id,page_id)
					VALUES ('" . ( int ) $layout_id . "','" . ( int ) $page_id . "')";
			$this->db->query ( $sql );
		}

		if($page->page_descriptions->page_description){
				foreach($page->page_descriptions->page_description as $page_description){
						$page_description->language = mb_strtolower ( $page_description->language, 'UTF-8' );
						$query = "SELECT language_id FROM " . DB_PREFIX . "languages 
											WHERE LOWER(name) = '" . $this->db->escape ( $page_description->language ) . "'";
						$result = $this->db->query ( $query );
						$language_id = $result->row ? $result->row ['language_id'] : 0;
						
						$query = "SELECT page_id FROM " . DB_PREFIX . "page_descriptions
											WHERE page_id = '" . $page_id . "' AND language_id= '" . ( int ) $language_id . "'";

						$result = $this->db->query ( $query );
						$exists = $result->row ['page_id'];
						
						if ($exists) {
							$sql = "UPDATE " . DB_PREFIX . "page_descriptions
									SET  	`name` = '" . $this->db->escape ( $page_description->name ) . "',
											`title` = '" . $this->db->escape ( $page_description->title ) . "',
											`description` = '" . $this->db->escape ( $page_description->description ) . "',
											`content` = '" . $this->db->escape ( $page_description->content ) . "',
											`seo_url` = '" . $this->db->escape ( $page_description->seo_url ) . "',
											`keywords` = '" . $this->db->escape ( $page_description->keywords ) . "',
											`updated` = NOW()
									WHERE page_id = '" . $page_id . "' AND language_id= '" . ( int ) $language_id . "'";
						} else {
							$sql = "INSERT INTO " . DB_PREFIX . "page_descriptions (`page_id`,`language_id`,`name`,
																					`title`,`description`,`content`,
																					`seo_url`,`keywords`,`created`,`updated`)
										VALUES ('" . ( int ) $page_id . "',
												'" . ( int ) $language_id . "',
												'" . $this->db->escape ( $page_description->name ) . "',
												'" . $this->db->escape ( $page_description->title ) . "',
												'" . $this->db->escape ( $page_description->description ) . "',
												'" . $this->db->escape ( $page_description->content ) . "',
												'" . $this->db->escape ( $page_description->seo_url ) . "',
												'" . $this->db->escape ( $page_description->keywords ) . "',
												NOW(),NOW())";
						}				
						$this->db->query ( $sql );											
				}
			}
		

	}
	
	private function _processBlock($layout, $block, $parent_instance_id = 0) {
		$instance_id = null;
		$layout_id = $layout->layout_id;

		if((string)$block->kind=='custom'){
			$this->_processCustomBlock($layout_id,$block,$parent_instance_id);
			return true;
		}

		//get block_id
		$sql = "SELECT block_id
				FROM " . DB_PREFIX . "blocks
				WHERE block_txt_id = '" . $this->db->escape ( $block->block_txt_id ) . "'";
		$result = $this->db->query ( $sql );
		$block_id = ( int ) $result->row ['block_id'];

		$action = (string)$block->action;
		if (! $block_id && in_array ( $action, array ("", null, "update" ) )) {
			$action = 'insert';
		}
		
		if ($action == 'delete') {
			//Delete block and unlink from all layouts					
			$sql = array ();
			$sql [] = "DELETE FROM " . DB_PREFIX . "block_layouts
					   WHERE block_id='" . $block_id . "' AND layout_id='" . $layout_id . "'";
			// check if block used by another layouts					
			$query = "SELECT *
					  FROM " . DB_PREFIX . "block_layouts
					  WHERE block_id='" . $block_id . "' AND layout_id<>'" . $layout_id . "'";
			$result = $this->db->query ( $query );
			if (! $result->row) {
			/*	$sql [] = "DELETE FROM " . DB_PREFIX . "block_descriptions
						   WHERE block_id='" . $block_id . "'";*/
				$sql [] = "DELETE FROM " . DB_PREFIX . "block_templates
						   WHERE block_id='" . $block_id . "'";
				$sql [] = "DELETE FROM " . DB_PREFIX . "blocks
						   WHERE block_id='" . $block_id . "'";
			}
			foreach ( $sql as $query ) {
				$this->db->query ( $query );
			}
		
		} elseif ($action == 'insert') {
			
			//If exists same block with same block_txt_id, return error and finish					
			if ($block_id) {
				$errmessage = 'Error: cannot insert block (block_txt_id: "' . $block->block_txt_id . '") into database because it already exists.';
				$error = new AError ( $errmessage );
				$error->toLog ()->toDebug ();
				$this->errors = 1;

			}
			
			// if not exists - insert and get it's block_id
			$sql = "INSERT INTO " . DB_PREFIX . "blocks (block_txt_id, controller,created,updated) 
					VALUES ('" . $this->db->escape ( $block->block_txt_id ) . "', '" . $this->db->escape ( $block->controller ) . "',NOW(),NOW())";
			$this->db->query ( $sql );
			$block_id = $this->db->getLastId ();
			
			// if parent block exists
			if ($parent_instance_id) {
				$sql = "SELECT MAX(position) as maxpos
						FROM " . DB_PREFIX . "block_layouts
						WHERE  parent_instance_id = " . ( int ) $parent_instance_id;
				$result = $this->db->query ( $sql );
				$position = $result->row ['maxpos'] + 10;
			}
			$position = !$position ? 1 : $position;
			$sql = "INSERT INTO " . DB_PREFIX . "block_layouts (layout_id,block_id,
																parent_instance_id,
																position,
																status,
																created,
																updated)
					VALUES ('" . ( int ) $layout_id . "',
							'" . ( int ) $block_id . "',
							'" . ( int ) $parent_instance_id . "',
							'" . ( int ) $position . "',
							'" . 1 . "',
							NOW(),
							NOW())";
			$this->db->query ( $sql );
			$instance_id = $this->db->getLastId ();
			
			$sql = array ();
			// insert block's info
			if ($block->block_descriptions->block_description) {
				foreach ( $block->block_descriptions->block_description as $block_description ) {
					$block_description->language = mb_strtolower ( $block_description->language, 'UTF-8' );
					$result = $this->db->query ( "SELECT language_id
												  FROM " . DB_PREFIX . "languages
												  WHERE LOWER(`name`)= '" . $this->db->escape ( $block_description->language ) . "'" );
					$language_id = $result->row ['language_id'];
					
					$sql [] = "INSERT INTO " . DB_PREFIX . "block_descriptions (instance_id, block_id,language_id,`name`,title,description,content,created,updated)
							   VALUES ('" . ( int ) $instance_id . "',
							   			'" . ( int ) $block_id . "',
										'" . ( int ) $language_id . "',
										'" . $this->db->escape ( $block_description->name ) . "',
										'" . $this->db->escape ( $block_description->title ) . "',
										'" . $this->db->escape ( $block_description->description ) . "',
										'" . $this->db->escape ( $block_description->content ) . "',
										NOW(),
										NOW())";
				}
			}
			if ($block->templates->template) {
				foreach ( $block->templates->template as $block_template ) {
					// parent block_id by parent_name
					$query = "SELECT block_id
							  FROM " . DB_PREFIX . "blocks
							  WHERE block_txt_id = '" . $this->db->escape ( $block_template->parent_block ) . "'";
					$result = $this->db->query ( $query );
					$parent_block_id = $result->row ['block_id'];
					
					$sql [] = "INSERT INTO " . DB_PREFIX . "block_templates (block_id,parent_block_id,template,created,updated) 
							   VALUES ('" . ( int ) $block_id . "',
										'" . ( int ) $parent_block_id . "',
										'" . $this->db->escape ( $block_template->template_name ) . "',NOW(),NOW())";
				}
			}
			
			foreach ( $sql as $query ) {
				$this->db->query ( $query );
			}
		
		} else { // Update or insert 
			

			if ($block_id) {
				$sql = "UPDATE " . DB_PREFIX . "blocks 
						SET controller = '" . $this->db->escape ( $block->controller ) . "', updated = NOW()
						WHERE block_id='" . $block_id . "'";
				$result = $this->db->query ( $sql );
				
				$sql = array ();
				// insert block's info
				if ($block->block_descriptions->block_description) {
					foreach ( $block->block_descriptions->block_description as $block_description ) {
						$query = "SELECT language_id
								  FROM " . DB_PREFIX . "languages
								  WHERE `name` = '" . $this->db->escape ( $block_description->language ) . "'";
						$result = $this->db->query ( $query );
						$language_id = $result->row ? $result->row ['language_id'] : 0;
						
						$query = "SELECT block_id
								  FROM " . DB_PREFIX . "block_descriptions
								  WHERE block_id = '" . $block_id . "' AND language_id= '" . ( int ) $language_id . "'";
						$result = $this->db->query ( $query );
						$exists = $result->row ['block_id'];
						
						if ($exists) {
							$sql [] = "UPDATE " . DB_PREFIX . "block_descriptions
									   SET `name` = '" . $this->db->escape ( $block_description->name ) . "',
											title = '" . $this->db->escape ( $block_description->title ) . "',
											description = '" . $this->db->escape ( $block_description->description ) . "',
											content = '" . $this->db->escape ( $block_description->content ) . "',
											updated = NOW()
									   WHERE block_id='" . $block_id . "' AND language_id= '" . ( int ) $language_id . "'";
						} else {
							$sql [] = "INSERT INTO " . DB_PREFIX . "block_descriptions (block_id,language_id,`name`,title,description,content,created,updated)
										VALUES ('" . ( int ) $block_id . "',
												'" . ( int ) $language_id . "',
												'" . $this->db->escape ( $block_description->name ) . "',
												'" . $this->db->escape ( $block_description->title ) . "',
												'" . $this->db->escape ( $block_description->description ) . "',
												'" . $this->db->escape ( $block_description->content ) . "',NOW(),NOW())";
						}
					}
				}
				if ($block->templates->template) {
					foreach ( $block->templates->template as $block_template ) {
						// parent block_id by parent_name
						$query = "SELECT block_id
								  FROM " . DB_PREFIX . "blocks
								  WHERE block_txt_id = '" . $this->db->escape ( $block_template->parent_block ) . "'";
						$result = $this->db->query ( $query );
						$parent_block_id = $result->row ? $result->row ['block_id'] : 0;
						
						$query = "SELECT block_id
								  FROM " . DB_PREFIX . "block_templates
								  WHERE block_id = '" . $block_id . "'
								      AND parent_block_id = '" . $parent_block_id . "'";
						$result = $this->db->query ( $query );
						$exists = $result->row ? $result->row ['block_id'] : 0;
						if (! $parent_block_id) {
							$errmessage = 'Error: block template "' . $block_template->template_name . '" (block_txt_id: "' . $block->block_txt_id . '") have not parent block!';
							$error = new AError ( $errmessage );
							$error->toLog ()->toDebug ();
							$this->errors = 1;
						}
						
						if ($exists) {
							$sql [] = "UPDATE " . DB_PREFIX . "block_templates
									   SET parent_block_id = '" . ( int ) $parent_block_id . "',
									       template = '" . $this->db->escape ( $block_template->template_name ) . "',
									       updated = NOW()
									   WHERE block_id='" . $block_id . "' AND parent_block_id='" . $parent_block_id . "'";
						} else {
							$sql [] = "INSERT INTO " . DB_PREFIX . "block_templates (block_id,parent_block_id,template,created,updated) 
										VALUES ('" . ( int ) $block_id . "',
												'" . ( int ) $parent_block_id . "',
												'" . $this->db->escape ( $block_template->template_name ) . "',NOW(),NOW())";
						}
					}
				}

				foreach ( $sql as $query ) {
					$this->db->query ( $query );
				}
				
				// and finally relate block with layout						
				$query = "SELECT *
							FROM " . DB_PREFIX . "block_layouts
						    WHERE layout_id = '" . ( int ) $layout_id . "'
						            AND block_id = '" . ( int ) $block_id . "'
						            AND parent_instance_id = '" . ( int ) $parent_instance_id . "'";
				$result = $this->db->query ( $query );
				$exists = $result->row ['instance_id'];
				
				$status = $block->status ? (int)$block->status : 1;
				
				if (! $exists && $layout->action != "delete") {

					// if parent block exists
					if ($parent_instance_id) {
						$sql = "SELECT MAX(position) as maxpos
								FROM " . DB_PREFIX . "block_layouts
								WHERE  parent_instance_id = " . ( int ) $parent_instance_id;
						$result = $this->db->query ( $sql );
						$position = $result->row ['maxpos'] + 10;
					}
					$position = !$position ? 1 : $position;
					$query = "INSERT INTO " . DB_PREFIX . "block_layouts
									(layout_id,block_id,parent_instance_id,position,status,created,updated)
							  VALUES ('" . ( int ) $layout_id . "','" . ( int ) $block_id . "','" . ( int ) $parent_instance_id . "',
							   	 	  '" . (int)$position . "','" . $status . "',NOW(),NOW())";
					$this->db->query ( $query );
					$instance_id = $this->db->getLastId ();
				}
			} // end if block_id
		} // end of update block
		

		// start recursion for all included blocks
		if ($block->block) {
			foreach ( $block->block as $childblock ) {
				$this->_processBlock ( $layout, $childblock, $instance_id );
			}
		}
	
	}

	private function _processCustomBlock($layout_id,$block,$parent_instance_id=0){

		switch($block->type){
			case 'html_block':
			case 'listing_block':
				//get block_id of html_block
				$sql = "SELECT block_id
						FROM " . DB_PREFIX . "blocks
						WHERE block_txt_id = '".$this->db->escape($block->type)."'";
				$result = $this->db->query ( $sql );
				$block_id = ( int ) $result->row ['block_id'];
				// get custom block
				// ???? no data about custom blocks is stored in config object. what we trying to get?
				$custom_block_id = (int)$this->config->get((string)$block->custom_block_txt_id);

				$action = (string)$block->action;
				if (! $block_id && in_array ( $action, array ("", null, "update" ) )) {
					$action = 'insert';
				}
				// DELETE BLOCK
				if ( $action == 'delete') {

					if(!$custom_block_id){ // if we don't know about this custom block - break;
						break;
					}

					//Delete block and unlink from all layouts
					$sql = array ();
					$sql [] = "DELETE FROM " . DB_PREFIX . "block_layouts
							   WHERE block_id='" . $block_id . "' AND layout_id='" . $layout_id . "' AND custom_block_id='" . $custom_block_id . "'";
					// check if block used by another layouts
					$query = "SELECT *
							  FROM " . DB_PREFIX . "block_layouts
							  WHERE block_id='" . $block_id . "' AND layout_id<>'" . $layout_id . "' AND custom_block_id='" . $custom_block_id . "'";
					$result = $this->db->query ( $query );
					if (! $result->row) {
						$sql [] = "DELETE FROM " . DB_PREFIX . "block_descriptions
								   WHERE block_id='" . $custom_block_id . "'";
						$sql [] = "DELETE FROM " . DB_PREFIX . "custom_blocks
								   WHERE custom_block_id='" . $custom_block_id . "'";
					}
					foreach ( $sql as $query ) {
						$this->db->query ( $query );
					}
				// INSERT
				} elseif ($action == 'insert') {

						//If exists same block with same block_txt_id, return error and finish
						if ($custom_block_id) {
							$errmessage = 'Error: cannot insert custom block (custom_block_txt_id: "' . $block->custom_block_txt_id . '") into database because it already exists.';
							$error = new AError ( $errmessage );
							$error->toLog ()->toDebug ();
							$this->errors = 1;

						}
						if(isset($this->custom_blocks[(string)$block->custom_block_txt_id])){
							$custom_block_id = $this->custom_blocks[(string)$block->custom_block_txt_id];
						}else{
							// if not exists - insert and get it's block_id
							$sql = "INSERT INTO " . DB_PREFIX . "custom_blocks (block_id, created)
									VALUES ('" . $block_id  . "', NOW())";
							$this->db->query ( $sql );
							$custom_block_id = $this->db->getLastId ();
							$this->custom_blocks[(string)$block->custom_block_txt_id] = $custom_block_id;
						}
						// if parent block exists
						if ($parent_instance_id) {
							$sql = "SELECT MAX(position) as maxpos
									FROM " . DB_PREFIX . "block_layouts
									WHERE  parent_instance_id = " . ( int ) $parent_instance_id;
							$result = $this->db->query ( $sql );
							$position = $result->row ['maxpos'] + 10;
						}else{
							$block_txt_id = $block->installed->placeholder;
						}


						if($parent_instance_id){
							$sql = "INSERT INTO " . DB_PREFIX . "block_layouts (layout_id,
																				block_id,
																				custom_block_id,
																				parent_instance_id,
																				position,
																				status,
																				created,
																				updated)
									VALUES ('" . ( int ) $layout_id . "',
											'" . ( int ) $block_id . "',
											'".(int)$custom_block_id."',
											'" . ( int ) $parent_instance_id . "',
											'" . ( int ) $position . "',
											'" . 1 . "',
											NOW(),
											NOW())";
							$this->db->query ( $sql );
						}else{

							foreach($block_txt_id as $parent_instance_txt_id){
								$parent_instance_id = $this->_getInstanceIdByTxtId($layout_id,(string)$parent_instance_txt_id);
								$sql = "INSERT INTO " . DB_PREFIX . "block_layouts (layout_id,
																			  	    block_id,
																					custom_block_id,
																					parent_instance_id,
																					position,
																					status,
																					created,
																					updated)
									VALUES ('" . ( int ) $layout_id . "',
											'" . ( int ) $block_id . "',
											'" . (int)$custom_block_id."',
											'" . ( int ) $parent_instance_id . "',
											'" . ( int ) $position . "',
											'" . 1 . "',
											NOW(),
											NOW())";
								$this->db->query ( $sql );

							}

						}
						// insert custom block content
						if ($block->block_descriptions->block_description) {
							foreach ( $block->block_descriptions->block_description as $block_description ) {
								$language_id = $this->_getLanguageIdByName( $block_description->language );
								if($block->type=='listing_block'){
										$content = 	array( 'listing_datasource' => trim((string)$block_description->content->listing_datasource),
														   'limit' => (string)$block_description->content->limit );
										if($content['listing_datasource']=='media'){
											$content['resource_type'] = (string)$block_description->content->resource_type;
										}
										$content = serialize( $content );

								}elseif( $block->type=='html_block' ){
										$content = (string)$block_description->content;
								}


								$desc_array = array('name' => $block_description->name,
													'title' => $block_description->title,
													'block_wrapper' => trim($block_description->block_wrapper),
													'description' => $block_description->description,
													'content' => $content,
													'language_id' => $language_id);

								$this->saveBlockDescription($block_id,$custom_block_id,$desc_array);
							}
						}
// UPDATE OR INSERT
					} else {
						if (!$custom_block_id) {
							// if not exists - insert and get it's block_id
							if(isset($this->custom_blocks[(string)$block->custom_block_txt_id])){
								$custom_block_id = $this->custom_blocks[(string)$block->custom_block_txt_id];
							}else{
								// if not exists - insert and get it's block_id
								$sql = "INSERT INTO " . DB_PREFIX . "custom_blocks (block_id, created)
										VALUES ('" . $block_id  . "', NOW())";
								$this->db->query ( $sql );
								$custom_block_id = $this->db->getLastId ();
								$this->custom_blocks[(string)$block->custom_block_txt_id] = $custom_block_id;
							}
						}

							// insert block's info
							if ($block->block_descriptions->block_description) {
								foreach ( $block->block_descriptions->block_description as $block_description ) {
									$language_id = $this->_getLanguageIdByName( $block_description->language );
									if($block->type=='listing_block'){
										$content = 	array( 'listing_datasource' => trim((string)$block_description->content->listing_datasource),
														   'limit' => (string)$block_description->content->limit );
										if($content['listing_datasource']=='media'){
											$content['resource_type'] = (string)$block_description->content->resource_type;
										}
										$content = serialize( $content );
									}elseif( $block->type=='html_block' ){
										$content = (string)$block_description->content;
									}
									$desc_array = array('name' => $block_description->name,
														'title' => $block_description->title,
														'block_wrapper' => $block_description->block_wrapper,
														'description' => $block_description->description,
														'content' => $content,
														'status' => $block_description->status,
														'language_id' => $language_id);

									$this->saveBlockDescription($block_id,$custom_block_id,$desc_array);
								}
						    }

							// and finally relate block with layout
							$query = "SELECT *
										FROM " . DB_PREFIX . "block_layouts
										WHERE layout_id = '" . ( int ) $layout_id . "'
												AND block_id = '" . ( int ) $block_id . "'
												AND custom_block_id = '" . ( int ) $custom_block_id . "'
												AND parent_instance_id = '" . ( int ) $parent_instance_id . "'";
							$result = $this->db->query ( $query );
							$exists = $result->row ['instance_id'];

							$status = $block->status ? (int)$block->status : 1;

							if (! $exists ) {

								// if parent block exists
								if ($parent_instance_id) {
									$sql = "SELECT MAX(position) as maxpos
											FROM " . DB_PREFIX . "block_layouts
											WHERE  parent_instance_id = " . ( int ) $parent_instance_id;
									$result = $this->db->query ( $sql );
									$position = $result->row ['maxpos'] + 10;
								}else{
									$block_txt_id = $block->installed->placeholder;
								}

								// if parent block exists
								if ($parent_instance_id) {
									$sql = "SELECT MAX(position) as maxpos
											FROM " . DB_PREFIX . "block_layouts
											WHERE  parent_instance_id = " . ( int ) $parent_instance_id;
									$result = $this->db->query ( $sql );
									$position = $result->row ['maxpos'] + 10;


									$query = "INSERT INTO " . DB_PREFIX . "block_layouts
													(layout_id,
													 block_id,
													 custom_block_id,
													 parent_instance_id,
													 position,
													 status,
													 created,
													 updated)
											  VALUES ('" . ( int ) $layout_id . "',
														'" . ( int ) $block_id . "',
														'" . ( int ) $custom_block_id . "',
														'" . ( int ) $parent_instance_id . "',
														'" . (int)$position . "',
														'" . (int)$block->status . "',
														NOW(),
														NOW())";
									$this->db->query ( $query );
								}else{
									foreach($block_txt_id as $parent_instance_txt_id){
										$parent_instance_id = $this->_getInstanceIdByTxtId($layout_id,(string)$parent_instance_txt_id);
										$query = "SELECT *
													FROM " . DB_PREFIX . "block_layouts
													WHERE layout_id = '" . ( int ) $layout_id . "'
															AND block_id = '" . ( int ) $block_id . "'
															AND custom_block_id = '" . ( int ) $custom_block_id . "'
															AND parent_instance_id = '" . ( int ) $parent_instance_id . "'";
										$result = $this->db->query ( $query );
										$exists = $result->row ['instance_id'];

										if(!$exists){
										$sql = "INSERT INTO " . DB_PREFIX . "block_layouts (layout_id,
																							block_id,
																							custom_block_id,
																							parent_instance_id,
																							position,
																							status,
																							created,
																							updated)
											VALUES ('" . ( int ) $layout_id . "',
													'" . ( int ) $block_id . "',
													'" . (int)$custom_block_id."',
													'" . ( int ) $parent_instance_id . "',
													'" . ( int ) $position . "',
													'" . 1 . "',
													NOW(),
													NOW())";
										}else{
											$sql = "UPDATE " . DB_PREFIX . "block_layouts
													SET position = '" . ( int ) $position . "',
													    status = '" . ( int ) $status . "'
													WHERE  layout_id = '" . ( int ) $layout_id . "'
															AND block_id = '" . ( int ) $block_id . "'
															AND custom_block_id = '" . ( int ) $custom_block_id . "'
															AND parent_instance_id = '" . ( int ) $parent_instance_id . "'";

										}
										$this->db->query ( $sql );

									}


								}
							}
					} // end of update block
				break;
			

		}

	 return true;
	}

	private function _getLanguageIdByName($language_name = '') {
		$language_name = mb_strtolower ( $language_name, 'UTF-8' );
		$query = "SELECT language_id
				  FROM " . DB_PREFIX . "languages
				  WHERE LOWER(name) = '" . $this->db->escape ( $language_name ) . "'";
		$result = $this->db->query ( $query );
		return $result->row ? $result->row ['language_id'] : 0;
	}

	private function _getInstanceIdByTxtId($layout_id,$block_txt_id){

		$layout_id = (int)$layout_id;
		if(!$layout_id || !$block_txt_id){ return false;}


		$sql = "SELECT instance_id
				FROM ".DB_PREFIX."block_layouts
				WHERE layout_id = '".$layout_id."' AND block_id = ( SELECT block_id
																	FROM ".DB_PREFIX."blocks
																	WHERE block_txt_id='".$block_txt_id."')";
		$result = $this->db->query ( $sql );
		return $result->row ['instance_id'];
	}
}