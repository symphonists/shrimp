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
		protected $_pages = array();
		protected $_status = '';
		protected $_templates = array();
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
			$this->_driver->getSections();
			exit();
			
			if (@$context[0] == 'edit' or @$context[0] == 'new') {
				if ($this->_editing = $context[0] == 'edit') {
					$this->_fields = $this->_driver->getRules((integer)$context[1]);
				}
				
				$this->_fields = (isset($_POST['fields']) ? $_POST['fields'] : $this->_fields);
				$this->_conditions = (isset($_POST['conditions']) ? $_POST['conditions'] : $this->_conditions);
				$this->_status = $context[2];
				$this->_pages = $this->_driver->getPages();
				
			} else {
				$this->_templates = $this->_driver->getRules();
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
			
			if (empty($this->_fields['name'])) {
				$this->_errors['name'] = 'Name must not be empty.';
			}
			
			foreach ($this->_conditions as $sortorder => $condition) {
				if (empty($condition['subject'])) {
					$this->_errors["{$sortorder}:subject"] = 'Subject must not be empty.';
				}
				
				if (empty($condition['sender'])) {
					$this->_errors["{$sortorder}:sender"] = 'Sender Name must not be empty.';
				}
				
				if (empty($condition['senders'])) {
					$this->_errors["{$sortorder}:senders"] = 'Senders must not be empty.';
				}
				
				if (empty($condition['recipients'])) {
					$this->_errors["{$sortorder}:recipients"] = 'Recipients must not be empty.';
				}
				
				if (empty($condition['page'])) {
					$this->_errors["{$sortorder}:page"] = 'Page must not be empty.';
				}
			}
			
			if (!empty($this->_errors)) {
				$this->_valid = false;
				return;
			}
			
		// Save: --------------------------------------------------------------
			
			$this->_fields['conditions'] = (integer)count($this->_conditions);
			$this->_fields['datasources'] = implode(',', $this->_fields['datasources']);
			
			$this->_Parent->Database->insert($this->_fields, 'tbl_etf_templates', true);
			
			if (!$this->_editing) {
				$redirect_mode = 'created';
				$template_id = $this->_Parent->Database->fetchVar('id', 0, "
					SELECT
						e.id
					FROM
						`tbl_etf_templates` AS e
					ORDER BY
						e.id DESC
					LIMIT 1
				");
				
			} else {
				$redirect_mode = 'saved';
				$template_id = $this->_fields['id'];
			}
			
			// Remove all existing conditions before inserting the remaining ones
			$this->_Parent->Database->delete('tbl_etf_conditions', " `template_id` = '{$this->_fields['id']}'");

			foreach ($this->_conditions as $condition) {
				$condition['template_id'] = $template_id;
				
				$this->_Parent->Database->insert($condition, 'tbl_etf_conditions', true);
			}
			
			redirect("{$this->_uri}/templates/edit/{$template_id}/{$redirect_mode}/");
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
							__('Template'), 
							DateTimeObj::get(__SYM_TIME_FORMAT__), 
							URL . '/symphony/extension/emailtemplatefilter/templates/new/', 
							URL . '/symphony/extension/emailtemplatefilter/templates/',
							__('Templates')
						)
					),
					Alert::SUCCESS
				);
			}
			
			// Edit:
			if ($this->_action == 'edit') {
				if ($this->_template > 0) {
					$row = $this->_Parent->Database->fetchRow(0, "
						SELECT
							e.*
						FROM
							`tbl_etf_templates` AS e
						WHERE
							e.id = {$this->_template}
					");
					
					if (!empty($row)) {
						$this->_fields = $row;
					} else {
						$this->_editing = false;
					}
				}
			}
			
		// Header: ------------------------------------------------------------
			
			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Email Templates' . (
				$this->_editing ? ' &ndash; ' . $this->_fields['name'] : null
			));
			$this->appendSubheading("<a href=\"{$this->_uri}/templates/\">Templates</a> &mdash; " . (
				$this->_editing ? $this->_fields['name'] : 'Untitled'
			));
			
		// Form: --------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));
			
			if (!empty($this->_fields['id'])) {
				$fieldset->appendChild(Widget::Input("fields[id]", $this->_fields['id'], 'hidden'));
			}
			
			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input(
				'fields[name]',
				General::sanitize(@$this->_fields['name'])
			));
			
			if (isset($this->_errors['name'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['name']);
			}
			
			$fieldset->appendChild($label);
			
		// Datasources --------------------------------------------------------
			
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
			$help->setValue(__('The parameter <code>%s</code> can be used in the selected datasources to get related data.', array('$etf-entry-id')));
			
			$fieldset->appendChild($label);
			$fieldset->appendChild($help);
			$this->Form->appendChild($fieldset);
			
		// Conditions -------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Conditions')));
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'subsection');
			$div->appendChild(new XMLElement('h3', __('Conditions')));
			$ol = new XMLElement('ol');
			
			// Add existing conditions:
			foreach ($this->_conditions as $sortorder => $condition) {
				$wrapper = new XMLElement('li');
				
				$this->displayCondition($wrapper, $sortorder, $condition);
				
				$ol->appendChild($wrapper);
			}
			
			// Add condition template:
			$wrapper = new XMLElement('li');
			$wrapper->setAttribute('class', 'template');
			
			$this->displayCondition($wrapper, '-1', array(
				'type'		=> 'XPath Condition'
			));
			
			$ol->appendChild($wrapper);
			
			$div->appendChild($ol);
			$fieldset->appendChild($div);
			$this->Form->appendChild($fieldset);
			
		// Footer: ------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					($this->_editing ? 'Save Changes' : 'Create Template'),
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
					'title'		=> 'Delete this template'
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
						foreach ($checked as $template_id) {
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_etf_templates`
								WHERE
									`id` = {$template_id}
							");
							
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_etf_conditions`
								WHERE
									`template_id` = {$template_id}
							");
						}
						
						redirect("{$this->_uri}/templates/");
						break;
				}
			}
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; Email Templates');
			
			$this->appendSubheading('Templates', Widget::Anchor(
				'Create New', "{$this->_uri}/templates/new/",
				'Create a new email template', 'create button'
			));
			
			$tableHead = array(
				array('Template Name', 'col'),
				array('Conditions', 'col')
			);	
			
			$tableBody = array();
			
			if (!is_array($this->_templates) or empty($this->_templates)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
				
			} else {
				foreach ($this->_templates as $template) {
					$template = (object)$template;
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$template->name,
							"{$this->_uri}/templates/edit/{$template->id}/"
						)
					);
					$col_name->appendChild(Widget::Input("items[{$template->id}]", null, 'checkbox'));
					
					if (!empty($template->conditions)) {
						$col_conditions = Widget::TableData($template->conditions);
						
					} else {
						$col_conditions = Widget::TableData('None', 'inactive');
					}
					
					$tableBody[] = Widget::TableRow(array($col_name, $col_conditions), null);
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