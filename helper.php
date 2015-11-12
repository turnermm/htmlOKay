<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Myron Turner <turnermm02@shaw.ca>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('HTMLOK_ACCESS_DIR')) define('HTMLOK_ACCESS_DIR', realpath(dirname(__FILE__)) . '/conf/access');

class helper_plugin_htmlOKay extends DokuWiki_Plugin {

  var $access_level = 0;
  var $saved_inf;
  var $current_file;
   var $files;
   var $users;
   var $groups;
   var $display;
   var $namespace;
   var $db_msg;
   
  function getMethods(){
    $result = array();
    $result[] = array(
      'name'   => 'set_permissions',
      'desc'   => "initialize current user's access permissions",
       'params' =>array()           
    );
    $result[] = array(
       'name'   => 'get_namespace',
      'desc'   => 'returns current access namespace', 
      'params' =>array(),     
      'return' => array('namespace' => 'string')
    );
    $result[] = array(
       'name'   => 'get_access_scope',
      'desc'   => 'returns current access scope with namespace colons replaced with hashes: ns#ns2#page',   
       'params' =>array('path' => 'string') , 
      'return' => array('access_scope' => 'string')
    );    
   $result[] = array(
       'name'   => 'set_DokuWikiDefault_perm',
       'desc'   => 'sets htmlok=0,$INFO[htmlOK_client] = false',
       'params' =>array()           
    ); 
   $result[] = array(
       'name'   => 'get_access_file',
       'desc'   => 'returns system path to file holding access data',   
       'params' =>array('access_dir' => 'string', 'namespace' => 'string') ,     
      'return' => array('acces_file' => 'string')
    );    
   $result[] = array(
       'name'   => 'in_groups',
       'desc'   => 'checks htmlOKay user groups against INFO[userinfo][groups] to see if this is htmlOKay user',   
       'params' =>array('INF0_groups' => 'string', 'groups' => 'string'),      
      'return' => array('in_group' => 'bool')
    );
   $result[] = array(
       'name'   => 'get_permission_level',
       'desc'   => 'checks htmlOKay user groups against INFO[userinfo][groups] to see if this is htmlOKay user',   
       'params' =>array('info' => 'mixed', 'htmlok' => 'mixed'),      
      'return' => array('level' => 'integer')
    );
   $result[] = array(
       'name'   => 'get_access ',
       'desc'   => 'gets current access level',   
       'params' =>array(),      
      'return' => array('level' => 'integer')
    );
    return $result;
  }
  
  
      function get_info()
    {
        global $conf;
        global $INFO, $ID;

   if (empty($INFO['namespace'])) {
     $INFO['namespace'] = getNS($ID);
  }
 
        if (!empty($INFO['namespace']))
        {
            $namespace = $INFO['namespace'];
        }
        
        else
        {
            $namespace = '_ROOT_';
        }

        $this->namespace = $namespace;
        $namespace = str_replace(':', '#', $namespace);

        $access_file = $this->get_access_file(HTMLOK_ACCESS_DIR, $namespace);
         if (!defined('ACCESS_FILE')) define('ACCESS_FILE', $access_file);
        $access_file = ACCESS_FILE;
        if (file_exists($access_file))
        {
            $INFO['htmlOK_access_scope'] = $this->get_access_scope($access_file);            
           $this->saved_inf =file_get_contents ($access_file);
         
            if (!$this->saved_inf)
            {
                return;
            }
            $this->saved_inf = unserialize($this->saved_inf);
            $this->files = $this->saved_inf['filespecs'];
            $this->users = $this->saved_inf['user'];
            $this->groups = $this->saved_inf['group'];
            if(!empty($INFO['filepath']))  {
               $this->curent_file = basename($INFO['filepath']);               
            }
            else {
               $this->curent_file = noNS($ID) . '.txt';
            }
           
            $this->access_file = $access_file;
        }
    }
    
    function get_saved_inf() {
         return $this->saved_inf;
    }
      
    function get_namespace() {
         return $this->namespace;
    }
    
     function set_permissions()
    {
        global $INFO;
        global $ID;
        global $conf;
        
        $this->get_info();
        $in_group = false;
        $in_users = false;
        $file_found = false;
        // set up defaults
        $INFO['htmlOK_client'] = $INFO['client']; // set as a flag for use in syntax.php
        $INFO['htmlOK_displayOnly'] = $this->saved_inf['display'];
        $INFO['hmtlOK_access_level'] = 0;
        // have HTML permissions been set for this namespace?
       
        if (!$this->saved_inf)
        {
            $this->set_DokuWikiDefault_perm(); // Not HTML         
            return;
        }

        // have HTML permissions been set for this file in current namespace?
        $this->set_dbg_msg("permissions exist");
        foreach($this->files as $file)
        {
            if ($file == $this->curent_file || $file == 'all')
            {
                $file_found = true;
                break;
            }
        }
 
        if (!$file_found)
        {
            $this->set_DokuWikiDefault_perm(); // Not HTML
            return;
        }
        // The current file has access to embedded HTML
        // Does user belong to a group which has HTML access?
        if(isset($INFO['userinfo']))  {
            $INF0_groups = $INFO['userinfo']['grps'];
        }
        else {
            $INF0_groups = "";
        }

        if (isset($this->groups) && !empty($this->groups))
        {
            $groups = array_keys($this->groups);
            // $groups_found will be groups common to user and current page
            $groups_found = $this->in_groups($INF0_groups, $groups);
            if ($groups_found !== false)
            {
                $in_group = true;
            }
        }

        // Does user have individual HTML access?
        if (isset($this->users) && !empty($this->users))
        {
            $users = array_keys($this->users);
            if (array_search ($INFO['client'], $users) !== false)
            {
                $in_users = true;
            }
        }

        if ($in_users) $this->set_dbg_msg("User found: " . $INFO['client']);
        else $this->set_dbg_msg("User " . $INFO['client'] . '  not found');

        if ($in_group)
        {
            $str = print_r($groups_found, true);
            $this->set_dbg_msg("Group(s) found: $str");
        }
        // If the user is not among groups or users with access then permissions are according to ACL
        // check to see if the page uses HTML and has a default HTML access level
        if (!$in_users && !$in_group)
        {
            $INFO['htmlOK_client'] = false;
            if (isset($this->saved_inf['display']))
            {
                $INFO['hmtlOK_access_level'] = $this->get_permission_level('display', $this->saved_inf);
                $this->set_dbg_msg("Display: " . $INFO['hmtlOK_access_level']);
                $conf['htmlok'] = 1;
                $INFO['htmlOK_visitor'] = true;

                $cache = new cache($ID, ".xhtml");
                trigger_event('PARSER_CACHE_USE', $cache);

                return;
            }
            $this->set_dbg_msg("Permission denied");
            return;
        }

        // Now we have to check the level of access
        // and grant the user the highest level of his/her permissions
        $group_level = 0;
        $user_level = 0;
        if ($in_group)
        {
            $group_level = $this->get_permission_level($groups_found, $this->groups);
        }
        if ($in_users)
        {
            $user_level = $this->get_permission_level($INFO['client'], $this->users);
        }
        $INFO['hmtlOK_access_level'] = ($group_level > $user_level) ? $group_level : $user_level;

        if ($INFO['hmtlOK_access_level'] == 0)
        {
            $this->set_DokuWikiDefault_perm();
            return;
        }
        $conf['htmlok'] = 1;
    }
    
       function get_access_scope($path) {
         $access_dir = preg_quote(HTMLOK_ACCESS_DIR, '/');
         $access_scope = preg_replace('/' . $access_dir . '/', "", $path);
         $access_scope = trim($access_scope,'/' );
         $access_scope = preg_replace('/#/', ':',$access_scope);
         return $access_scope;
    }

       function set_DokuWikiDefault_perm()
    {
        global $INFO;
        global $conf;
        $INFO['htmlOK_client'] = false; // deactivate PARSER_CACHE_USE event, which
        // disables caching while page is being edited
        $conf['htmlok'] = 0; // Stop syntax.php from applying rules, this is not HTML
      
        // So we will let ACL determine write permissions
    }
    
      function get_access_file($access_dir, $namespace)
    {
        $file = $access_dir . '/' . $namespace;
        $this->set_dbg_msg("Original access file: $file");
        if (file_exists($file))
        {
            $this->set_dbg_msg("Tried Original access file: $file");
            return $file;
        }

        $dirs = explode('#', $namespace);
        foreach($dirs as $dir)
        {
            array_pop($dirs);
            $new_dir = implode('#', $dirs);
            $file = $access_dir . '/' . $new_dir;
            $this->set_dbg_msg("Tried access file: $file");
            
            if (file_exists($file))
            {
                $this->set_dbg_msg("File exists: $file");                
                return $file;
            }
        }
        return $access_dir . '/' . $namespace;
    }
    
    function in_groups($INF0_groups, $groups)
    {
        $groups_found = array();
        $in_group = false;

        if (!isset($INF0_groups)) return false; // no groups assigned to non-registered user
        if(!is_array($INF0_groups)) return false;
        foreach($INF0_groups as $grp)
        {
            if (array_search ($grp, $groups) !== false)
            {
                $in_group = true;
                $groups_found[] = $grp;
            }
        }
        if ($in_group) return $groups_found;
        return false;
    }

    /**
    *
    * @param mixed $info either string, which is $INFO['client'] or array of groups common to
    *                   both $INFO['userinfo']['grps'] and htmlOK's $saved_inf
    * @param array $htmlok either user or group array from htmlOK $saved_inf
    * @return integer the level as an integer
    */
    function get_permission_level($info, $htmlok)
    {
        $levels = array('none' => 0, 'strict' => 1, 'medium' => 2, 'lax' => 3, 'su' => 4);

        if (is_string($info)) 
            {
              $this->access_level = $levels[$htmlok[$info]];
                return $levels[$htmlok[$info]];
        }
        $level = 0;
        foreach($info as $name)
        {
            $temp = $htmlok[$name];
            if ($levels[$temp] > $level)
            {
                $level = $levels[$temp];
            }
        }
     
       $this->access_level = $level;
        return $level;
    }

  function get_access() {  
      global $INFO;
      
      if($INFO['hmtlOK_access_level'] ) {       
         return $INFO['hmtlOK_access_level'];
       }
       return $this->get_permission_level('display', $this->saved_inf);
  }
  
   function set_dbg_msg($msg="") {
      if(!$msg) return $this->db_msg;
      $this->db_msg .= $msg . '<br />';       
    }

}
