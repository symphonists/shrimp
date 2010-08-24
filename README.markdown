# Shrimp #

Version: 1.0.1  
Author: [Max Wheeler](max@makenosound.com)  
Build Date: 2010-08-24
Requirements: Symphony 2.1

## Installation ##
 
1. Upload the 'shrimp' folder in this archive to your Symphony 'extensions' folder.

2. Enable it by selecting the "Shrimp", choose Enable from the with-selected menu, then click Apply.

3. Change the URL prefix under "System > Preferences", the default is "s".

4. Add your redirection rules under "System > Shrimp".

## Usage ##

Shrimp works by intercepting any requests, such as `http://blah.com/s/123/` to your preferred URL prefix and constructing a new URL based on any rules you've set up. As Shrimp automatically infers the section your entry ID belongs to, rules are limited to one per section.

If a matching rule is found, any attached datasources are passed the entry id as `$shrimp-entry-id` allowing you to resolve the new URL using XPATH in your redirect.

## Shout Outs ##

Large sections of this extension has been adapted from [Rowan Lewis'](http://rowanlewis.com) [Email Template Filter](http://github.com/rowan-lewis/emailtemplatefilter/tree/master) extension. The name "Shrimp" is stolen shamelessly from [Dan Benjamin's](http://hivelogic.com/) similarly featured extension for Expression Engine.


## Changelog ##

1.0.1 -- Made compatible with Symphony 2.1, no longer requires changes to `class.frontendpage.php`  
1.0.0 -- Initial commit




