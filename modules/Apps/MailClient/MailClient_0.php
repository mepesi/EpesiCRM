<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClient extends Module {
	private $lang;
	
	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}
	
	public function body() {
		$def_mbox = Apps_MailClientCommon::get_default_mbox();
		if($def_mbox===null) {
			print($this->lang->t('No mailboxes defined !<br />'));
			print($this->lang->t('You need to setup a mailbox first.<br />'));
			print($this->lang->t('<br /><br />Click on New Account icon in the action bar to setup the account.<br />'));
			Base_ActionBarCommon::add('add','New account',$this->create_callback_href(array($this,'account'),array(null,'new')));
			return;
		}

		$mbox_file = $this->get_module_variable('opened_mbox',$def_mbox);
		$preview_id = $this->get_path().'preview';
		$show_id = $this->get_path().'show';

		$th = $this->init_module('Base/Theme');
		$tree = $this->init_module('Utils/Tree');
		$str = Apps_MailClientCommon::get_mail_dir_structure();
		$this->set_open_mail_dir_callbacks($str);
		$tree->set_structure($str);
		$tree->sort();
		$th->assign('tree', $this->get_html_of_module($tree));
		
		$mbox = Apps_MailClientCommon::get_index(ltrim($mbox_file,'/'));
		if($mbox===false) {
			print('Invalid mailbox');
			return;
		}

		$gb = $this->init_module('Utils/GenericBrowser',null,'list');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('ID'), 'order'=>'id','width'=>'3', 'display'=>DEBUG), //this is debug only
			array('name'=>$this->lang->t('Subject'), 'search'=>1, 'order'=>'subj','order_eregi'=>'^<a [^<>]*>([^<>]*)</a>$','width'=>'40'),
			array('name'=>$this->lang->t('From'), 'search'=>1,'quickjump'=>1, 'order'=>'from','width'=>'32'),
			array('name'=>$this->lang->t('Date'), 'search'=>1, 'order'=>'date','width'=>'15'),
			array('name'=>$this->lang->t('Size'), 'search'=>1, 'order'=>'size','width'=>'10')
			));
		
		$gb->set_default_order(array($this->lang->t('Date')=>'DESC'));
	
		$limit_max = count($mbox);
		
		load_js($this->get_module_dir().'utils.js');
		
		foreach($mbox as $id=>$data) {
			$r = $gb->get_new_row();
			$r->add_data($id,'<a href="javascript:void(0)" onClick="Apps_MailClient.preview(\''.$preview_id.'\',\''.http_build_query(array('mbox'=>$mbox_file, 'msg_id'=>$id, 'pid'=>$preview_id)).'\')">'.htmlentities($data['subject']).'</a>',htmlentities($data['from']),Base_RegionalSettingsCommon::time2reg($data['date']),$data['size']);
			$lid = 'mailclient_link_'.$id;
			$r->add_action('href="javascript:void(0)" rel="'.$show_id.'" class="lbOn" id="'.$lid.'" ','View');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Delete this message?'),array($this,'remove_message'),array($mbox_file,$id)),'Delete');
			$r->add_js('Event.observe(\''.$lid.'\',\'click\',function() {Apps_MailClient.preview(\''.$show_id.'\',\''.http_build_query(array('mbox'=>$mbox_file, 'msg_id'=>$id, 'pid'=>$show_id)).'\')})');
		}
		
		$th->assign('list', $this->get_html_of_module($gb,array(true),'automatic_display'));
		$th->assign('preview_subject','<div id="'.$preview_id.'_subject"></div>');
		$th->assign('preview_from','<div id="'.$preview_id.'_from"></div>');
		$th->assign('preview_attachments','<div id="'.$preview_id.'_attachments"></div>');
		$th->assign('preview_body','<iframe id="'.$preview_id.'_body" style="width:100%;height:70%"></iframe>');
		$th->display();

		$th_show = $this->init_module('Base/Theme');
		$th_show->assign('subject','<div id="'.$show_id.'_subject"></div>');
		$th_show->assign('from','<div id="'.$show_id.'_from"></div>');
		$th_show->assign('attachments','<div id="'.$show_id.'_attachments"></div>');
		$th_show->assign('body','<iframe id="'.$show_id.'_body" style="width:95%;height:90%"></iframe>');
		$th_show->assign('close','<a class="lbAction" rel="deactivate">Close</a>');
		print('<div id="'.$show_id.'" class="leightbox">');
		$th_show->display('message');
		print('</div>');
		
		$checknew_id = $this->get_path().'checknew';
		Base_ActionBarCommon::add('folder',$this->lang->t('Check'),'href="javascript:void(0)" rel="'.$checknew_id.'" class="lbOn" id="'.$checknew_id.'b"');
		eval_js('mailclient_check_f = function() {'.
				'if($(\''.$checknew_id.'\').style.display==\'block\'){'.
					'$(\''.$checknew_id.'X\').src=\'modules/Apps/MailClient/checknew.php?'.http_build_query(array('id'=>$checknew_id,'t'=>microtime(true))).'\';'.
				'}else{'.
					'setTimeout(mailclient_check_f,100);'.
				'}};'.
			'Event.observe(\''.$checknew_id.'b\',\'click\',mailclient_check_f)');
		print('<div id="'.$checknew_id.'" class="leightbox"><div style="width:100%;text-align:center" id="'.$checknew_id.'progresses"></div>'.
			'<iframe id="'.$checknew_id.'X" frameborder=0 scrolling="No" height="80" width="100%"></iframe>'.
			'</div>');
	}
	
	private function set_open_mail_dir_callbacks(array & $str,$path='') {
		$opened_mbox = str_replace(array('__at__','__dot__'),array('@','.'),$this->get_module_variable('opened_mbox'));
		foreach($str as $k=>& $v) {
			$mpath = $path.'/'.$v['name'];
			if($mpath == $opened_mbox) {
				$v['visible'] = true;
				$v['selected'] = true;
			}
			if(isset($v['sub']) && is_array($v['sub'])) $this->set_open_mail_dir_callbacks($v['sub'],$mpath);
			if($path=='')
				$mpath .= '/Inbox';
			$v['name'] = '<a '.$this->create_callback_href(array($this,'open_mail_dir_callback'),str_replace(array('@','.'),array('__at__','__dot__'),$mpath)).'>'.$v['name'].'</a>';
		}
	}
	
	public function open_mail_dir_callback($path) {
		$this->set_module_variable('opened_mbox',$path);
	}
	
	public function remove_message($box,$id) {
		if(ereg('Trash$',$box)) {
			if(Apps_MailClientCommon::remove_msg($box,$id))
				Base_StatusBarCommon::message('Message deleted');
			else
				Base_StatusBarCommon::message('Unable to delete message','error');
		} else {
			$trash = ltrim($box,'/');
			$trash = substr($trash,0,strpos($trash,'/')).'/Trash';
			if(Apps_MailClientCommon::move_msg($box,$trash,$id))
				Base_StatusBarCommon::message('Message moved to trash');
			else
				Base_StatusBarCommon::message('Unable to move message to trash','error');
		}
	}

	////////////////////////////////////////////////////////////
	//account management
	public function account_manager() {
		$gb = $this->init_module('Utils/GenericBrowser',null,'accounts');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Mail'), 'order'=>'mail')
				));
		$ret = $gb->query_order_limit('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id='.Acl::get_user(),'SELECT count(mail) FROM apps_mailclient_accounts WHERE user_login_id='.Acl::get_user());
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['mail']);
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'edit')),'Edit');
//			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'view')),'View');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_account'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		Base_ActionBarCommon::add('add','New account',$this->create_callback_href(array($this,'account'),array(null,'new')));
	}
	
	public function account($id,$action='view') {
		if($this->is_back()) return false;

		$f = $this->init_module('Libs/QuickForm');

		$defaults=null;
		if($action!='new') {
			$ret = DB::Execute('SELECT * FROM apps_mailclient_accounts WHERE id=%d',array($id));
			$defaults = $ret->FetchRow();
		}
		
		$methods = array(
				array('auto'=>'Automatic', 'DIGEST-MD5'=>'DIGEST-MD5', 'CRAM-MD5'=>'CRAM-MD5', 'APOP'=>'APOP', 'PLAIN'=>'PLAIN', 'LOGIN'=>'LOGIN', 'USER'=>'USER'),
				array('auto'=>'Automatic', 'DIGEST-MD5'=>'DIGEST-MD5', 'CRAM-MD5'=>'CRAM-MD5', 'LOGIN'=>'LOGIN')
			);
		$methods_js = json_encode($methods);
		eval_js('Event.observe(\'mailclient_incoming_protocol\',\'change\',function(x) {'.
				'var methods = '.$methods_js.';'.
				'var opts = this.form.incoming_method.options;'.
				'opts.length=0;'.
				'$H(methods[this.value]).each(function(x,y) {opts[y] = new Option(x[1],x[0]);});'.
				'if(this.value==0) this.form.pop3_leave_msgs_on_server.disabled=false; else this.form.pop3_leave_msgs_on_server.disabled=true;'.
				'});'.
			'Event.observe(\'mailclient_smtp_auth\',\'change\',function(x) {'.
				'if(this.checked==true) {this.form.smtp_login.disabled=false;this.form.smtp_password.disabled=false;} else {this.form.smtp_login.disabled=true;this.form.smtp_password.disabled=true;}'.
				'})');

		$cols = array(
				array('name'=>'header','label'=>$this->lang->t(ucwords($action).' account'),'type'=>'header'),
				array('name'=>'mail','label'=>$this->lang->t('Mail address'),'rule'=>array(array('type'=>'email','message'=>$this->lang->t('This isn\'t valid e-mail address')))),
				array('name'=>'login','label'=>$this->lang->t('Login')),
				array('name'=>'password','label'=>$this->lang->t('Password'),'type'=>'password'),
				
				array('name'=>'in_header','label'=>$this->lang->t('Incoming mail'),'type'=>'header'),
				array('name'=>'incoming_protocol','label'=>$this->lang->t('Incoming protocol'),'type'=>'select','values'=>array(0=>'POP3',1=>'IMAP'), 'default'=>0,'param'=>array('id'=>'mailclient_incoming_protocol')),
				array('name'=>'incoming_server','label'=>$this->lang->t('Incoming server address')),
				array('name'=>'incoming_ssl','label'=>$this->lang->t('Receive with SSL')),
				array('name'=>'incoming_method','label'=>$this->lang->t('Authorization method'),'type'=>'select','values'=>$methods[(isset($defaults) && $defaults['incoming_protocol'])?1:0], 'default'=>'auto'),
				array('name'=>'pop3_leave_msgs_on_server','label'=>$this->lang->t('Remove messages from server'),'type'=>'select',
					'values'=>array(0=>'immediately',1=>'after 1 day', 3=>'after 3 days', 7=>'after 1 week', 14=>'after 2 weeks', 30=>'after 1 month', -1=>'never'), 
					'default'=>'0','param'=>((isset($defaults) && $defaults['incoming_protocol']) || ($f->getSubmitValue('submited') && $f->getSubmitValue('incoming_protocol')))?array('disabled'=>1):array()),

				array('name'=>'out_header','label'=>$this->lang->t('Outgoing mail'),'type'=>'header'),
				array('name'=>'smtp_server','label'=>$this->lang->t('SMTP server address')),
				array('name'=>'smtp_ssl','label'=>$this->lang->t('Send with SSL')),
				array('name'=>'smtp_auth','label'=>$this->lang->t('SMTP authorization required'),'param'=>array('id'=>'mailclient_smtp_auth')),
				array('name'=>'smtp_login','label'=>$this->lang->t('Login'),'param'=>((isset($defaults) && $defaults['smtp_auth']==0) || ($f->getSubmitValue('submited') && !$f->getSubmitValue('smtp_auth')))?array('disabled'=>1):array()),
				array('name'=>'smtp_password','label'=>$this->lang->t('Password'),'type'=>'password','param'=>((isset($defaults) && $defaults['smtp_auth']==0) || ($f->getSubmitValue('submited') && !$f->getSubmitValue('smtp_auth')))?array('disabled'=>1):array())
			);

		$f->add_table('apps_mailclient_accounts',$cols);
		$f->setDefaults($defaults);
		
		if($action=='view') {
			Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'account'),array($id,'edit')));
			$f->freeze();
		} else {
			$f->addElement('submit',null,'Save','style="display:none"'); //provide on ENTER submit event
			if($f->validate()) {
				$values = $f->exportValues();
				$dbup = array('id'=>$id, 'user_login_id'=>Acl::get_user());
				foreach($cols as $v) {
					if(ereg("header$",$v['name'])) continue;
					if(isset($values[$v['name']]))
						$dbup[$v['name']] = $values[$v['name']];
					else
						$dbup[$v['name']] = 0;
				}
				DB::Replace('apps_mailclient_accounts', $dbup, array('id'), true,true);
				return false;	
			}
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($f->get_submit_form_js(),'"').'"');
		}
		$f->display();

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
	}

	public function delete_account($id){
		DB::Execute('DELETE FROM apps_mailclient_accounts WHERE id=%d',array($id));
	}
	

	//////////////////////////////////////////////////////////////////
	//applet	
	public function applet($conf, $opts) {
		$opts['go'] = true;
		$accounts = array();
		$ret = array();
		foreach($conf as $key=>$on) {
			$x = explode('_',$key);
			if($x[0]=='account' && $on) {
				$id = $x[1];
				$mail = DB::GetOne('SELECT mail FROM apps_mailclient_accounts WHERE id=%d',array($id));
				if(!$mail) continue;
				$ret[$mail] = '<span id="mailaccount_'.$id.'"></span>';
				
				//interval execution
				eval_js_once('var mailclientcache = Array();'.
					'mailclientfunc = function(accid,cache){'.
					'if(!$(\'mailaccount_\'+accid)) return;'.
					'if(cache && typeof mailclientcache[accid] != \'undefined\')'.
						'$(\'mailaccount_\'+accid).innerHTML = mailclientcache[accid];'.
					'else '.
						'new Ajax.Updater(\'mailaccount_\'+accid,\'modules/Apps/MailClient/refresh.php\',{'.
							'method:\'post\','.
							'onComplete:function(r){mailclientcache[accid]=r.responseText},'.
							'parameters:{acc_id:accid}});'.
					'}');
				eval_js_once('setInterval(\'mailclientfunc('.$id.' , 0)\',600002)');

				//get rss now!
				eval_js('mailclientfunc('.$id.' , 1)');

			}
		}
		$th = $this->init_module('Base/Theme');
		$th->assign('accounts',$ret);
		$th->display('applet');
	}
}

?>