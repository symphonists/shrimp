# Shrimp #

Version: 1.0.0  
Author: [Max Wheeler](max@makenosound.com)  
Build Date: 1st July 2009  
Requirements: Symphony 2.0.3

## Installation ##
 
1. *If you've already installed the [Email Template Filter](http://github.com/rowan-lewis/emailtemplatefilter/tree/master) extension you can skip this step.* Edit `symphony/lib/toolkit/class.frontendpage.php` and replace the function on line 441 with this:

		public function __processDatasources($datasources, &$wrapper, $params = array()) {
			if (trim($datasources) == '') return;
			
			$datasources = preg_split('/,\s*/i', $datasources, -1, PREG_SPLIT_NO_EMPTY);
			$datasources = array_map('trim', $datasources);
			
			if (!is_array($datasources) || empty($datasources)) return;
			
			$this->_env['pool'] = $params;
			$pool = $params;
			$dependencies = array();
			
			foreach ($datasources as $handle) {
				$this->_Parent->Profiler->seed();
				
				$pool[$handle] =& $this->DatasourceManager->create($handle, null, false);
				
				$dependencies[$handle] = $pool[$handle]->getDependencies();
				
				unset($ds);
			}
			
			$dsOrder = $this->__findDatasourceOrder($dependencies);
			
			foreach ($dsOrder as $handle) {
				$this->_Parent->Profiler->seed();
				
				$ds = $pool[$handle];
				$ds->processParameters(array('env' => $this->_env, 'param' => $this->_param));
				
				if ($xml = $ds->grab($this->_env['pool'])) {
					if (is_object($xml)) {
						$wrapper->appendChild($xml);
						
					} else {
						$wrapper->setValue($wrapper->getValue() . self::CRLF . "\t" . trim($xml));
					}
				}
				
				$this->_Parent->Profiler->sample($handle, PROFILE_LAP, 'Datasource');
				
				unset($ds);
			}
		}
	
2. Upload the 'shrimp' folder in this archive to your Symphony 'extensions' folder.

3. Enable it by selecting the "Shrimp", choose Enable from the with-selected menu, then click Apply.

4. Change the URL prefix under "System > Preferences", the default is "s".

5. Add your redirection rules under "System > Shrimp".

## Usage ##

Shrimp works by intercepting any requests, such as `http://blah.com/s/123/` to your preferred URL prefix and constructing a new URL based on any rules you've set up. As Shrimp automatically infers the section your entry ID belongs to, rules are limited to one per section.

If a matching rule is found, any attached datasources are passed the entry id as `$shrimp-entry-id` allowing you to resolve the new URL using XPATH in your redirect.

## Shout Outs ##

Large sections of this extension has been adapted from [Rowan Lewis'](http://rowanlewis.com) [Email Template Filter](http://github.com/rowan-lewis/emailtemplatefilter/tree/master) extension.









