<?php
/** 
 * 
 * @author Vee W.
 * @license http://opensource.org/licenses/MIT
 * 
 */

class Model_Sites extends \Orm\Model
{
	
	
	protected static $_table_name = 'sites';
	protected static $_primary_key = array('site_id');
	
	// relations
	protected static $_has_many = array(
		'account_logins' => array(
			'key_from' => 'site_id',
			'model_to' => 'Model_AccountLogins',
			'key_to' => 'site_id',
			'cascade_delete' => true,
		),
		'account_sites' => array(
			'key_from' => 'site_id',
			'model_to' => 'Model_AccountSites',
			'key_to' => 'site_id',
			'cascade_delete' => true,
		),
	);
	
	
	/**
	 * list tables that *must copy* when create new site.
	 * 
	 * @var array $multisite_tables 
	 * @todo [multisite] developers have to add *must copy* tables here when you create table that need to use differently in multi-site.
	 */
	public $multisite_tables = array(
		'account_fields',
		'account_level',// this table require data.
		'account_level_group', // this table require base level data.
		'account_level_permission',

		'config', // this table must copy "core" config data
	);
	
	
	/**
	 * add new site. new site in db and create, copy tables to contain site id prefix.
	 * 
	 * @param array $data
	 * @return boolean
	 */
	public static function addSite(array $data = array())
	{
		// additional data for inserting
		$data['site_create'] = time();
		$data['site_create_gmt'] = \Extension\Date::localToGmt();
		$data['site_update'] = time();
		$data['site_update_gmt'] = \Extension\Date::localToGmt();

		// insert into db.
		$site = self::forge($data);
		$site->save();
		$site_id = $site->site_id;
		unset($site);

		// start copy tables
		self::forge()->copyNewSiteTable($site_id);

		// @todo [theme][multisite] for any theme management that get config from db from each site. you need to add set default theme for each created site here.

		// set config for new site. this step should reset core config values in new site for security reason.
		// @todo [multisite] when developers add new core config names and values, you have to add those default values here.
		$cfg_data['site_name'] = $data['site_name'];
		$cfg_data['page_title_separator'] = ' | ';
		$cfg_data['site_timezone'] = 'Asia/Bangkok';
		$cfg_data['simultaneous_login'] = '0';
		$cfg_data['allow_avatar'] = '1';
		$cfg_data['avatar_size'] = '200';
		$cfg_data['avatar_allowed_types'] = 'gif|jpg|png';
		$cfg_data['avatar_path'] = 'public/upload/avatar/';
		$cfg_data['member_allow_register'] = '1';
		$cfg_data['member_register_notify_admin'] = '1';
		$cfg_data['member_verification'] = '1';
		$cfg_data['member_admin_verify_emails'] = 'admin@localhost';
		$cfg_data['member_disallow_username'] = 'admin, administrator, administrators, root, system';
		$cfg_data['member_max_login_fail'] = '10';
		$cfg_data['member_login_fail_wait_time'] = '30';
		$cfg_data['member_login_remember_length'] = '30';
		$cfg_data['member_confirm_wait_time'] = '10';
		$cfg_data['member_email_change_need_confirm'] = '1';
		$cfg_data['mail_protocol'] = 'mail';
		$cfg_data['mail_mailpath'] = '/usr/sbin/sendmail';
		$cfg_data['mail_smtp_host'] = '';
		$cfg_data['mail_smtp_user'] = '';
		$cfg_data['mail_smtp_pass'] = '';
		$cfg_data['mail_smtp_port'] = '25';
		$cfg_data['mail_sender_email'] = 'no-reply@localhost';
		$cfg_data['content_items_perpage'] = '10';
		$cfg_data['content_admin_items_perpage'] = '20';
		$cfg_data['media_allowed_types'] = '7z|aac|ace|ai|aif|aifc|aiff|avi|bmp|css|csv|doc|docx|eml|flv|gif|gz|h264|h.264|htm|html|jpeg|jpg|js|json|log|mid|midi|mov|mp3|mpeg|mpg|pdf|png|ppt|psd|swf|tar|text|tgz|tif|tiff|txt|wav|webm|word|xls|xlsx|xml|xsl|zip';
		$cfg_data['ftp_host'] = '';
		$cfg_data['ftp_username'] = '';
		$cfg_data['ftp_password'] = '';
		$cfg_data['ftp_port'] = '21';
		$cfg_data['ftp_passive'] = 'true';
		$cfg_data['ftp_basepath'] = '/public_html/';
		foreach ($cfg_data as $cfg_name => $cfg_value) {
			\DB::update($site_id . '_config')
					->where('config_name', $cfg_name)
					->value('config_value', $cfg_value)
					->execute();
		}
		unset($cfg_data, $cfg_name, $cfg_value);

		// done.
		return true;
	}// addSite
	
	
	/**
	 * copy new site tables and set default values for some table.
	 * 
	 * @param integer $site_id
	 * @return boolean
	 */
	public function copyNewSiteTable($site_id = '')
	{
		if (!is_numeric($site_id)) {
			return false;
		}
		
		// copy tables
		foreach ($this->multisite_tables as $table) {
			$table_withprefix = \DB::table_prefix($table);
			$table_site_withprefix = \DB::table_prefix($site_id . '_' . $table);
			
			if ($table == 'config') {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $table_site_withprefix . ' SELECT * FROM ' . $table_withprefix . ' WHERE config_core = 1';
			} elseif ($table == 'account_level') {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $table_site_withprefix . ' SELECT * FROM ' . $table_withprefix;
			} else {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $table_site_withprefix . ' LIKE ' . $table_withprefix;
			}
			
			\DB::query($sql)->execute();
			
			// create default values
			if ($table == 'account_level_group') {
				$sql = "INSERT INTO `" . $table_site_withprefix . "` (`level_group_id`, `level_name`, `level_description`, `level_priority`) VALUES
					(1, 'Super administrator', 'For site owner or super administrator.', 1),
					(2, 'Administrator', NULL, 2),
					(3, 'Member', 'For registered user.', 999),
					(4, 'Guest', 'For non register user.', 1000);";
				\DB::query($sql)->execute();
			}
		}
		
		unset($sql, $table, $table_site_withprefix, $table_withprefix);
		
		// change all accounts level to member (except super admin(id=1) and guest(id=0)).
		\DB::update($site_id . '_account_level')
				->where('account_id', '!=', '0')->where('account_id', '!=', '1')
				->value('level_group_id', '3')
				->execute();
		
		// done
		return true;
	}// copyNewSiteTable
	
	
	/**
	 * delete site tables and site data in sites table.
	 * 
	 * @param integer $site_id
	 * @return boolean
	 */
	public static function deleteSite($site_id = '')
	{
		// prevent delete site 1
		if ($site_id == '1') {
			return false;
		}
		
		// delete related _sites tables
		// this can be done by ORM relation itself. I have nothing to do here except something to remove more than just in db, example file, folder
		
		// drop [site_id]_tables
		foreach (static::forge()->multisite_tables as $table) {
			\DBUtil::drop_table($site_id . '_' . $table);
		}
		
		// delete this site from sites table
		static::find($site_id)->delete();
		
		// done
		return true;
	}// deleteSite
	
	
	/**
	 * edit site. update site name to config table too.
	 * 
	 * @param array $data
	 * @return boolean
	 */
	public static function editSite(array $data = array())
	{
		// check site_domain not exists in other site_id
		$match_sites = static::query()->where('site_id', '!=', $data['site_id'])->where('site_domain', $data['site_domain'])->count();
		if ($match_sites > 0) {
			unset($match_sites);
			return \Lang::get('siteman_domain_currently_exists');
		}
		unset($match_sites);
		
		// additional data for updating
		$data['site_update'] = time();
		$data['site_update_gmt'] = \Extension\Date::localToGmt();
		
		// filter data before update
		if ($data['site_id'] == '1') {
			// site 1 always enabled.
			$data['site_status'] = '1';
		}
		
		$site_id = $data['site_id'];
		unset($data['site_id']);
		
		// update to db
		$sites = static::find($site_id);
		$sites->set($data);
		$sites->save();
		unset($sites);
		
		// set config for new site.
		$cfg_data['site_name'] = $data['site_name'];
		
		if ($site_id == '1') {
			$config_table = 'config';
		} else {
			$config_table = $site_id . '_config';
		}
		
		foreach ($cfg_data as $cfg_name => $cfg_value) {
			\DB::update($config_table)
					->where('config_name', $cfg_name)
					->value('config_value', $cfg_value)
					->execute();
		}
		unset($cfg_data, $cfg_name, $cfg_value);
		
		// done
		return true;
	}// editSite
	
	
	/**
	 * get current site id
	 * 
	 * @param boolean $enabled_only
	 * @return int
	 */
	public static function getSiteId($enabled_only = true)
	{
		// get domain
		if (isset($_SERVER['HTTP_HOST'])) {
			$site_domain = $_SERVER['HTTP_HOST'];
		} elseif (isset($_SERVER['SERVER_NAME'])) {
			$site_domain = $_SERVER['SERVER_NAME'];
		} else {
			$site_domain = 'localhost';
		}
		
		$query = static::query();
		$query->where('site_domain', $site_domain);
		if ($enabled_only === true) {
			$query->where('site_status', 1);
		}
		$row = $query->get_one();
		
		unset($query, $site_domain);
		
		if ($row != null) {
			return $row->site_id;
		}
		
		return 1;
	}// getSiteId
	
	
	/**
	 * list websites from db
	 * 
	 * @param array $option
	 * @return array
	 */
	public static function listSites($option = array())
	{
		
		$query = self::query();
		// where conditions
		if (!isset($option['list_for']) || (isset($option['list_for']) && $option['list_for'] == 'front')) {
			$query->where('site_status', 1);
		}
		
		$output['total'] = $query->count();
		
		// sort and order
		$orders = \Security::strip_tags(trim(\Input::get('orders')));
		$allowed_orders = array('site_id', 'site_name', 'site_domain', 'site_status', 'site_create', 'site_update');
		if ($orders == null || !in_array($orders, $allowed_orders)) {
			$orders = 'site_id';
		}
		unset($allowed_orders);
		$sort = \Security::strip_tags(trim(\Input::get('sort')));
		if ($sort == null || $sort != 'DESC') {
			$sort = 'ASC';
		}
		
		// offset and limit
		if (!isset($option['offset'])) {
			$option['offset'] = 0;
		}
		if (!isset($option['limit'])) {
			if (isset($option['list_for']) && $option['list_for'] == 'admin') {
				$option['limit'] = \Model_Config::getval('content_admin_items_perpage');
			} else {
				$option['limit'] = \Model_Config::getval('content_items_perpage');
			}
		}
		
		// get the results from sort, order, offset, limit.
		$query->order_by($orders, $sort);
		if (!isset($option['unlimit']) || (isset($option['unlimit']) && $option['unlimit'] == false)) {
			$query->offset($option['offset'])->limit($option['limit']);
		}
		$output['items'] = $query->get();
		
		unset($orders, $query, $sort);
		
		return $output;
	}// listSites
	
	
}

