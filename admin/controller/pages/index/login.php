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

class ControllerPagesIndexLogin extends AController {
	private $error = array();
	public $data = array();
	          
	public function main() {

        //init controller data
        $this->extensions->hk_InitData($this,__FUNCTION__);

    	$this->loadLanguage('common/login');

		$this->cache->delete('admin_menu');

		$this->document->setTitle( $this->language->get('heading_title') );

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->_validate()) {
			$this->session->data['token'] = AEncryption::getHash(mt_rand());
			//login is sussessful redirect to otiginaly requested page 
			if (isset($this->request->post['redirect']) && !preg_match("/rt=index\/login/i", $this->request->post['redirect'])) {
                $redirect = $this->html->removeQueryVar( $this->request->post['redirect'], array('token')  );
                $redirect .=  "&token=".$this->session->data['token'];
				$this->redirect($redirect);
			} else {
				$this->redirect($this->html->getSecureURL('index/home'));
				
			}
		}

		if (
			(isset($this->session->data['token']) && !isset($this->request->get['token']))
			|| ((isset($this->request->get['token']) && (isset($this->session->data['token']) && ($this->request->get['token'] != $this->session->data['token']))))) {
			$this->error['warning'] = $this->language->get('error_token');
		}

		//There was no login done, so clear the session for new login screen 
		$this->session->clear();
		
		if($_COOKIE['new_cart']==1 && $this->error['warning'] && $this->request->server['REQUEST_METHOD'] == 'GET'){
			$this->error['warning'] = '';
		}

		$this->data['action'] = $this->html->getSecureURL('index/login');
		$this->data['update'] = '';
		$form = new AForm('ST');

		$form->setForm(
			array(
				'form_name' => 'loginFrm',
				'update' => $this->data['update'],
			)
		);

		$this->data['form']['id'] = 'loginFrm';
		$this->data['form']['form_open'] = $form->getFieldHtml(
			array(
				'type' => 'form',
				'name' => 'loginFrm',
				'action' => $this->data['action'],
			)
		);
		$this->data['form']['submit'] = $form->getFieldHtml(
			array(
				'type' => 'button',
				'name' => 'submit',
				'text' => $this->language->get('button_login'),
				'style' => 'button3',
			)
		);

		$fields = array('username', 'password');
		foreach ( $fields as $f ) {
			$this->data['form']['fields'][$f] = $form->getFieldHtml(
				array(
					'type' => ($f == 'password' ? 'password' : 'input'),
					'name' => $f,
					'value' => $this->data[$f],
				)
			);
		}
		
		$this->view->assign('error_warning', $this->error['warning']);
    	$this->view->assign('forgot_password', $this->html->getSecureURL('index/forgot_password'));

		if (isset($this->request->get['rt'])) {
			$route = $this->request->get['rt'];
			unset($this->request->get['rt']);
			if (isset($this->request->get['token'])) {
				unset($this->request->get['token']);
			}
			$url = '';
			if ($this->request->get) {
				$url = '&' . http_build_query($this->request->get);
			}
			if($this->request->server['REQUEST_METHOD'] == 'POST'){
				$this->view->assign('redirect', $this->request->post['redirect'] ); // if login attempt failed - save path for redirect
			}else{
				$this->view->assign('redirect', $this->html->getSecureURL( $route , $url));
			}
		} else {
			
			$this->view->assign('redirect', '' );
		}

		$this->view->batchAssign( $this->data );
		
        $this->processTemplate('pages/index/login.tpl' );

        //update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);
  	}
		
	private function _validate() {
		if (isset($this->request->post['username']) && isset($this->request->post['password']) && !$this->user->login($this->request->post['username'], $this->request->post['password'])) {
			$this->error['warning'] = $this->language->get('error_login');
		}
		
		if (!$this->error) {
			return TRUE;
		} else {		
			$this->messages->saveNotice($this->language->get('error_login_message').$this->request->server['REMOTE_ADDR'],$this->language->get('error_login_message_text').$this->request->post['username']);
			return FALSE;
		}
	}
}  
?>