<?php

	error_reporting(E_ALL);
	ini_set("display_errors", 1);
	
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.frontendpage.php');

	class Extension_Shrimp extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public static $sm;
		public static $em;
		public static $fm;
		public static $page = null;
		
		public function __construct(&$context)
		{
			$this->_Parent =& $context['parent'];
			self::$em = new EntryManager($this->_Parent);	
			self::$sm = new SectionManager($this->_Parent);		
			self::$page = new FrontendPage($this->_Parent);
		}
		
		public function about()
		{
			return array(
				'name'			=> 'Shrimp',
				'version'		=> '1.0.0',
				'release-date'	=> '2009-06-24',
				'author'		=> array(
					'name'			=> 'Max Wheeler',
					'website'		=> 'http://makenosound.com/',
					'email'			=> 'max@makenosound.com'
				),
				'description'	=> 'Intelligent short URL redirection.'
			);
		}
		
		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendPrePageResolve',
					'callback'	=> 'hijack_page',
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'append_preferences'
				),
			);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'  => 200,
					'name'  => 'Shrimp',
					'link'  => '/rules/'
				)
			);
		}
		
		/*-------------------------------------------------------------------------
			Housekeeping:
		-------------------------------------------------------------------------*/
		
		public function install()
		{
			return $this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_shrimp_rules` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`section_id` int(11) NOT NULL,
					`redirect` varchar(255) NOT NULL,
					`datasources` text default NULL,
					PRIMARY KEY (`id`)
				)
			");
		}
		
		public function uninstall()
		{
			Symphony::Database()->query("DROP TABLE `tbl_shrimp_rules`");
			return true;
		}
		
		public function getRules()
		{
			$rules = Symphony::Database()->fetch("
				SELECT
					r.*
				FROM
					`tbl_shrimp_rules` AS r
				ORDER BY
					r.section_id ASC
			");
			
			foreach ($rules as &$rule)
			{
				$section = self::$sm->fetch($rule['section_id']);
				$rule['section_name'] = $section->_data['name'];
			}
			return $rules;
		}
		
		public function getRule($id)
		{
			$rule = Symphony::Database()->fetchRow(0, "
				SELECT
					r.*
				FROM
					`tbl_shrimp_rules` AS r
				WHERE
					r.id = '{$id}'
				LIMIT 1
			");
			$section = self::$sm->fetch($rule['section_id']);
			$rule['section_name'] = $section->_data['name'];
			return $rule;
		}
		
		public function getRuleSections()
		{
			return Symphony::Database()->fetchCol("section_id", "
				SELECT
					`section_id`
				FROM
					`tbl_shrimp_rules`
				ORDER BY
					section_id ASC
			");
		}
		
		public function getSections($section_id = NULL)
		{
			$used = $this->getRuleSections();
			if(isset($section_id))
			{
				foreach ($used as $key => $used_rule)
				{
					if ($section_id == $used_rule) unset($used[$key]);
				}
			}
			if( ! empty($used) AND is_array($used)) $where = 'WHERE `s`.id NOT IN ('.implode($used,",").')';
			
			$sql = "SELECT `s`.id,`s`.name,`s`.handle,`s`.navigation_group
			FROM `tbl_sections` AS `s`
			$where
			ORDER BY `s`.sortorder ASC
			";
			if( ! $sections = Symphony::Database()->fetch($sql)) return false;
			return $sections;
		}
		
		/*-------------------------------------------------------------------------
			Delegated:
		-------------------------------------------------------------------------*/
		public function hijack_page($context)
		{
			# Retrieve URL prefix
			$url_prefix = $this->_get_url_prefix();
			$request = preg_split('/\//', trim($context['page'], '/'), -1, PREG_SPLIT_NO_EMPTY);
			
			# Check the request matches, ID is set and that’s all.
			if ($request[0] != $url_prefix OR ! isset($request[1]) OR isset($request[2])) return false;
			
			# Check ID exists in system
			$request_id = $request[1];
			$entries = self::$em->fetch($request_id);
			if(empty($entries)) return false;
			$entry = $entries[0];
			
			# Get the rule by section ID
			$section_id = $entry->_fields['section_id'];
			$rule = $this->_get_section_rule($section_id);
			if(empty($rule)) return false;
			
			# Get the XML
			$simplexml = $this->_construct_entry_xml($request_id, $rule['datasources']);
			
			$replacements = array();
			preg_match_all('/\{[^\}]+\}/', $rule['redirect'], $matches);
			foreach ($matches[0] as $match)
			{
				switch (trim($match, '{}')) {
					case '$root':
						$replacements[$match] = URL;
						break;
					case 'system:id':
						$replacements[$match] = $request_id;
						break;
					default:
						$result = $simplexml->xpath(trim($match, '{}'));
						$replacements[$match] = (string) $result[0];
						break;
				}
			}
			
			# Build redirect
			$redirect = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$rule['redirect']
			);
			header("Location: $redirect", true, 302);
			exit();
		}
		
		/*-------------------------------------------------------------------------
			Helpers:
		-------------------------------------------------------------------------*/		
	/**
		* Retrieves users URL prefix from preferences
		*
		*/
		private function _get_url_prefix()
		{
			$val = $this->_Parent->Configuration->get('prefix', 'shrimp');
			return (isset($val)) ? $val : 's';
		}
			/**
		*	Retrieves XPATH rule
		*		@param	$section_id		int
		* 
		*/
		private function _get_section_rule($section_id)
		{
			if (is_numeric($section_id))
			{
				return $this->_Parent->Database->fetchRow(0, "
					SELECT
						s.redirect, s.datasources 
					FROM
						`tbl_shrimp_rules` AS s
					WHERE
						s.section_id = {$section_id}
					ORDER BY
						s.id ASC
				");				
			}
			return false;
		}
	/**
		*	Processes relevant datasources and returns as SimpleXML
		*		@param	$entry_id			int
		*		@param	$datasources	string
		* 
		*/
		private function _construct_entry_xml($entry_id, $datasources)
		{
			$data = new XMLElement('data');
			self::$page->__processDatasources($datasources, $data, array(
				'shrimp-entry-id'	=> $entry_id
			));
			
			$simplexml = simplexml_load_string($data->generate(true));
			return $simplexml;
		}
		
		/*-------------------------------------------------------------------------
			Preferences:
		-------------------------------------------------------------------------*/
		public function append_preferences($context)
		{
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Shrimp'));

			$label = Widget::Label('URL Prefix');
			$label->appendChild(Widget::Input('settings[shrimp][prefix]', General::Sanitize($this->_get_url_prefix())));
			$group->appendChild($label);
						
			$context['wrapper']->appendChild($group);	
		}
	}