<?php

	ini_set("display_errors","2");
	ERROR_REPORTING(E_ALL);
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');
	
	class contentExtensionShrimpRules extends AdministrationPage
	{
		protected $_action = '';
		protected $_driver = null;
		protected $_editing = false;
		protected $_errors = array();
		protected $_fields = array();
		protected $_sections = array();
		protected $_status = '';
		protected $_uri = null;
		protected $_valid = true;
		
		public function __construct(&$parent)
		{
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/shrimp';
			$this->_driver = $this->_Parent->ExtensionManager->create('shrimp');
		}
		
		public function build($context)
		{
			if (@$context[0] == 'edit' or @$context[0] == 'new') {
				if ($this->_editing = $context[0] == 'edit') {
					$this->_fields = $this->_driver->getRule((integer)$context[1]);
				}
				
				$this->_fields = (isset($_POST['fields']) ? $_POST['fields'] : $this->_fields);
				$this->_status = $context[2];
				$this->_sections = $this->_driver->getSections();
				
			} else {
				$this->_rules = $this->_driver->getRules();
			}
			
			parent::build($context);
		}
		
		public function __actionNew() {
			$this->__actionEdit();
		}
		
		public function __actionEdit() {
			if (@array_key_exists('delete', $_POST['action'])) {
				$this->__actionEditDelete();
				
			} else {
				$this->__actionEditNormal();
			}
		}
		
		public function __actionEditDelete() {
			$this->_Parent->Database->delete('tbl_shrimp_rules', " `id` = '{$this->_fields['id']}'");
			
			redirect("{$this->_uri}/rules/");
		}
		
		public function __actionEditNormal() {
			//header('content-type: text/plain');
			
		// Validate: ----------------------------------------------------------
			
			if (empty($this->_fields['redirect'])) {
				$this->_errors['redirect'] = 'Redirect must not be empty.';
			}
			
			if (!empty($this->_errors)) {
				$this->_valid = false;
				return;
			}
			
		// Save: --------------------------------------------------------------
			
			$this->_fields['datasources'] = implode(',', $this->_fields['datasources']);
			
			$this->_Parent->Database->insert($this->_fields, 'tbl_shrimp_rules', true);
			
			if ( ! $this->_editing) {
				$redirect_mode = 'created';
				$rule_id = $this->_Parent->Database->fetchVar('id', 0, "
					SELECT
						e.id
					FROM
						`tbl_shrimp_rules` AS e
					ORDER BY
						e.id DESC
					LIMIT 1
				");				
			} else {
				$redirect_mode = 'saved';
				$rule_id = $this->_fields['id'];
			}
			
			redirect("{$this->_uri}/rules/edit/{$rule_id}/{$redirect_mode}/");
		}
		
		public function __viewNew()
		{
			$this->__viewEdit();
		}
		
		public function __viewEdit()
		{			
		# Status: -----------------------------------------------------------
			
			if (!$this->_valid) $this->pageAlert('
				An error occurred while processing this form.
				<a href="#error">See below for details.</a>',
				Alert::ERROR
			);
			
			// Status message:
			if ($this->_status) {
				$action = null;
				
				switch($this->_status) {
					case 'saved': $action = '%1$s updated at %2$s. <a href="%3$s">Create another?</a> <a href="%4$s">View all %5$s</a>'; break;
					case 'created': $action = '%1$s created at %2$s. <a href="%3$s">Create another?</a> <a href="%4$s">View all %5$s</a>'; break;
				}
				
				if ($action) $this->pageAlert(
					__(
						$action, array(
							__('Rules'), 
							DateTimeObj::get(__SYM_TIME_FORMAT__), 
							URL . '/symphony/extension/shrimp/rules/new/', 
							URL . '/symphony/extension/shrimp/rules/',
							__('Rules')
						)
					),
					Alert::SUCCESS
				);
			}
			
			// Edit:
			if ($this->_action == 'edit') {
				if ($this->_rule > 0) {
					$row = $this->_Parent->Database->fetchRow(0, "
						SELECT
							e.*
						FROM
							`tbl_shrimp_rules` AS e
						WHERE
							e.id = {$this->_rule}
					");
					
					if (!empty($row)) {
						$this->_fields = $row;
					} else {
						$this->_editing = false;
					}
				}
			}
			
		# Header: ------------------------------------------------------------
			
			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Shrimp Rules' . (
				$this->_editing ? ' &ndash; ' . $this->_fields['name'] : null
			));
			$this->appendSubheading("<a href=\"{$this->_uri}/rules/\">Rules</a> &mdash; " . (
				$this->_editing ? $this->_fields['section_name'] : 'Untitled'
			));
			
		# Form: --------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Details')));
			
			if (!empty($this->_fields['id'])) {
				$fieldset->appendChild(Widget::Input("fields[id]", $this->_fields['id'], 'hidden'));
			}
			
			# Section: --------------------------------------------------------------
			
			$label = Widget::Label(__('Section'));
			$sections = $this->_driver->getSections($this->_fields['section_id']);
			$options = array();
			
			foreach ($sections as $section) {
				$selected = ($this->_fields['section_id'] == $section['id']) ? true : false;
				$options[] = array(
					$section['id'], $selected, $section['name']
				);
			}
			
			$label = Widget::Label(__('Section'));
			$label->appendChild(Widget::Select(
				"fields[section_id]", $options
			));

			$fieldset->appendChild($label);
			
			# Redirect: --------------------------------------------------------------
			
			$label = Widget::Label(__('Redirect'));
			$label->appendChild(Widget::Input(
				'fields[redirect]',
				General::sanitize(@$this->_fields['redirect'])
			));
			
			if (isset($this->_errors['redirect'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['redirect']);
			}
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue('To access the XML, use XPath expressions: <code>{datasource/entry/field-one}/static-text/{datasource/entry/field-two}</code>. You can also use the <code>{$root}</code> for your site URL or <code>{system:id}</code> if you don&#8217;t need a datasource.');
			
			$fieldset->appendChild($label);
			$fieldset->appendChild($help);
			
		# Datasources --------------------------------------------------------
			
			$DSManager = new DatasourceManager($this->_Parent);
			$datasources = $DSManager->listAll();
			$handles = explode(',', $this->_fields['datasources']);
			
			$options = array();
			
			foreach ($datasources as $about) {
				$handle = $about['handle'];
				$selected = in_array($handle, $handles);
				
				$options[] = array(
					$handle, $selected, $about['name']
				);
			}
			
			$label = Widget::Label(__('Datasources'));
			$label->appendChild(Widget::Select(
				"fields[datasources][]", $options,
				array('multiple' => 'multiple')
			));
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('The parameter <code>%s</code> can be used in the selected datasources to get related data.', array('$shrimp-entry-id')));
			
			$fieldset->appendChild($label);
			$fieldset->appendChild($help);
			$this->Form->appendChild($fieldset);
			
		// Footer: ------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					($this->_editing ? 'Save Changes' : 'Create Rule'),
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);
			
			if ($this->_editing) {
				$button = new XMLElement('button', 'Delete');
				$button->setAttributeArray(array(
					'name'		=> 'action[delete]',
					'class'		=> 'confirm delete',
					'title'		=> 'Delete this rule'
				));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
		}
		
	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $rule_id) {
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_shrimp_rules`
								WHERE
									`id` = {$rule_id}
							");
						}
						
						redirect("{$this->_uri}/rules/");
						break;
				}
			}
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; Shrimp Rules');
			
			$this->appendSubheading('Rules', Widget::Anchor(
				'Create New', "{$this->_uri}/rules/new/",
				'Create a new rule', 'create button'
			));
			
			$tableHead = array(
				array('Section', 'col'),
				array('Redirect Rule', 'col')
			);	
			
			$tableBody = array();
			
			if (!is_array($this->_rules) or empty($this->_rules)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
				
			} else {
				foreach ($this->_rules as $rule) {
					$rule = (object) $rule;
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$rule->section_name,
							"{$this->_uri}/rules/edit/{$rule->id}/"
						)
					);
					$col_name->appendChild(Widget::Input("items[{$rule->id}]", null, 'checkbox'));
					
					if (isset($rule->redirect)) {
						$col_redirect = Widget::TableData($rule->redirect);
					} else {
						$col_redirect = Widget::TableData('None', 'inactive');
					}
					
					$tableBody[] = Widget::TableRow(array($col_name, $col_redirect), null);
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, 'With Selected...'),
				array('delete', false, 'Delete')									
			);

			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			
			$this->Form->appendChild($actions);		
		}
	}