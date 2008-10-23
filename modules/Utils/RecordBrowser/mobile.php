<?php
//!!! $table,$crits and sort variables are passed globally
defined("_VALID_ACCESS") || die();

//init
$ret = DB::GetRow('SELECT caption, recent, favorites FROM recordbrowser_table_properties WHERE tab=%s',array($table));
$type = isset($_GET['type'])?$_GET['type']:Base_User_SettingsCommon::get('Utils_RecordBrowser',$table.'_default_view');
$order_num = (isset($_GET['order']) && isset($_GET['order_dir']))?$_GET['order']:-1;
$order = false;
//print(Base_LangCommon::ts('Utils_RecordBrowser',$ret['caption']).' - '.Base_LangCommon::ts('Utils_RecordBrowser',ucfirst($type)).'<br>');

//TODO: simple search

//cols
$cols = Utils_RecordBrowserCommon::init($table);
$cols_out = array();
foreach($cols as $k=>$col) {
	if($col['visible'] && (array_key_exists($col['id'],$sort) || array_key_exists($col['id'],$info))) {
		if(count($cols_out)==$order_num) $order=$col['id'];
		if($type!='recent')
			$cols_out[] = array('name'=>$col['name'], 'order'=>$col['id'], 'width'=>1, 'record'=>$col);
		else
			$cols_out[] = array('name'=>$col['name'], 'width'=>1, 'record'=>$col);
	}
}

print('<ul class="form">');
//views
if($ret['recent'] && $type!='recent') print('<li><a '.(IPHONE?'class="button red" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'recent','rb_offset'=>0))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','Recent').'</a></li> ');
if($ret['favorites'] && $type!='favorites') print('<li><a '.(IPHONE?'class="button green" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'favorites','rb_offset'=>0))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','Favorites').'</a></li> ');
if(($ret['recent'] || $ret['favorites']) && $type!='all') print('<li><a '.(IPHONE?'class="button white" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'all','rb_offset'=>0))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','All').'</a></li>');
if($type!='recent')
	print('<li><form method="POST" action="mobile.php?'.http_build_query($_GET).'"><input type="text" name="search" value="Search" id="some_name" onclick="clickclear(this, \'Search\')" onblur="clickrecall(this,\'Search\')" /></form></li>');
print('</ul>');
$search_crits = array();
if(isset($_POST['search'])) {
	$search_string = $_POST['search'];
	$search_string = DB::Concat(DB::qstr('%'),DB::qstr($search_string),DB::qstr('%'));
//	$cols_out[$i]['record']
	$chr = '(';
	foreach ($cols_out as $col) {
		$args = $col['record'];
		$c = $args['id'];
		if ($args['type']=='text' || $args['type']=='currency' || ($args['type']=='calculated' && $args['param']!='')) {
			$search_crits[$chr.'"~'.$c] = $search_string;
			$chr='|';
			continue;
		}
		if ($args['type']!='commondata' && $args['type']!='multiselect' && $args['type']!='select') continue;
		$str = explode(';', $args['param']);
		$ref = explode('::', $str[0]);
		if ($ref[0]!='' && isset($ref[1])) $search_crits[$chr.'"~:Ref:'.$c] = $search_string;
		if ($args['type']=='commondata' || $ref[0]=='__COMMON__')
			if (!isset($ref[1]) || $ref[0]=='__COMMON__') $search_crits[$chr.':RefCD:'.$c] = $search_string;
/*		foreach ($search as $k=>$v) {
			$k = str_replace('__',':',$k);
			$type = explode(':',$k);
			if ($k[0]=='"') {
				$search_res['~_'.$k] = $v;
				continue;
			}
			if (isset($type[1]) && $type[1]=='RefCD') {
				$search_res['~"'.$k] = $v;
				continue;
			}
			if (!is_array($v)) $v = array($v);
			$r = array();
			foreach ($v as $w)
				$r[] = DB::Concat(DB::qstr('%'),DB::qstr($w),DB::qstr('%'));
			$search_res['~"'.$k] = $r;
		}*/
	}
	
}
$crits = self::merge_crits($crits, $search_crits);
//$crits = array();
//$sort = array();
switch($type) {
	case 'favorites':
		$crits[':Fav'] = true;
		break;
	case 'recent':
		$crits[':Recent'] = true;
		$sort = array(':Visited_on' => 'DESC');
		break;
}
if(!IPHONE && $type!='recent' && $order && ($_GET['order_dir']=='asc' || $_GET['order_dir']=='desc')) {
	$sort = array($order => strtoupper($_GET['order_dir']));
}
$offset = isset($_GET['rb_offset'])?$_GET['rb_offset']:0;
if(IPHONE)
	$num_rows = 20;
else
	$num_rows = 10;
$data = Utils_RecordBrowserCommon::get_records($table,$crits,array(),$sort,array('numrows'=>$num_rows,'offset'=>$num_rows*$offset));

//parse data
if(IPHONE) {
	$letter = null;
	$letter_col = current($cols_out);
	$letter_col = $letter_col['record']['id'];
	print('<ul>');
} else
	$data_out = array();
foreach($data as $v) {
	if(IPHONE) {
		$row_sort = '';
		$row_info = '';
	} else
		$row = array();
	foreach($cols_out as $col) {
		$i = array_key_exists($col['record']['id'],$info);
		$val = Utils_RecordBrowserCommon::get_val($table,$col['name'],$v,$v['id'],IPHONE,$col['record']);
		if(IPHONE) {
			if($val==='') continue;
			if($type!='recent' && $col['record']['id'] == $letter_col && $letter!==$val{0}) {
				$letter=$val{0};
				print('</ul><h4>'.$letter.'</h4><ul>');
			}
			if($i)
				$row_info .= ($info[$col['record']['id']]?$col['name'].': ':'').$val.'<br>';
			else
				$row_sort .= $val.' ';
		} else
			$row[] = $val;
	}
	if(IPHONE) {
		$open = self::record_link_open_tag($table, $v['id'], false);
		$close = self::record_link_close_tag();
		$row = $open.$row_sort.$close.$open.$row_info.$close;
		print('<li class="arrow">'.$row.'</li>');
	} else
		$data_out[] = $row;
}

//display table
if(IPHONE) {
	print('</ul>');
} else {
	Utils_GenericBrowserCommon::mobile_table($cols_out,$data_out,false);
}

//display paging
$cur_num_rows = Utils_RecordBrowserCommon::get_records_limit($table,$crits);
if($offset>0) print('<a '.(IPHONE?'class="button red" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('rb_offset'=>($offset-1)))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','prev').'</a>');
if($offset<$cur_num_rows/$num_rows-1) print(' <a '.(IPHONE?'class="button green" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('rb_offset'=>($offset+1)))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','next').'</a>');
if($cur_num_rows>$num_rows) {
	$qf = new HTML_QuickForm('rb_page', 'get','mobile.php?'.http_build_query($_GET));
	$qf->addElement('text', 'rb_offset', Base_LangCommon::ts('Base_User_Login','Page(0-%d)',array($cur_num_rows/$num_rows)));
	$qf->addElement('submit', 'submit_button', Base_LangCommon::ts('Base_User_Login','OK'),IPHONE?'class="button white"':'');
	$qf->addRule('rb_offset', Base_LangCommon::ts('Base_User_Login','Field required'), 'required');
	$qf->addRule('rb_offset', Base_LangCommon::ts('Base_User_Login','Invalid page number'), 'numeric');
	$renderer =& $qf->defaultRenderer();
/*	if(IPHONE) {
		$renderer->setFormTemplate("<form{attributes}>{hidden}<ul>{content}</ul></form>");
		$renderer->setElementTemplate('<li class="error"><!-- BEGIN required --><span style="color: #ff0000">*</span><!-- END required -->{label}<!-- BEGIN error --><span style=\"color: #ff0000\">{error}</span><!-- END error -->{element}</li>');
		$renderer->setRequiredNoteTemplate("<li>{requiredNote}</li>");
	}		*/
	$qf->accept($renderer);
	print($renderer->toHtml());
}
?>