<?php
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');
if (!defined('HTMLOK_ACCESS_DIR')) define('HTMLOK_ACCESS_DIR', realpath(dirname(__FILE__)) . '/conf/access');
define ('CONFIG_FILE', DOKU_INC . 'conf/local.php');
require_once(DOKU_INC . 'inc/cache.php');
// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', "on");
class action_plugin_htmlOKay extends DokuWiki_Action_Plugin
{
    var $saved_inf;
    var $files;
    var $users;
    var $groups;
    var $display;
    var $action_event;
    var $INFO_Writable;
    var $db_msg = "";
    var $do_dbg = false;
    var $access_scope;
    var $access_file;
    var $namespace;

    function register(&$controller)
    {
        $controller->register_hook('HTMLOK_ACCESS_EVENT', 'BEFORE', $this, 'get_permission');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'bypasss_cache');
        $controller->register_hook('TEMPLATE_USERTOOLS_DISPLAY', 'BEFORE', $this, 'action_link', array('user'));    
       $controller->register_hook('TEMPLATE_HTMLOKAYTOOLS_DISPLAY', 'BEFORE', $this, 'action_link', array('ok'));
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'modify_headers', array());   
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'dw_started', array());   
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'act_before', array());   
               
    }

     function act_before(&$event, $param) {
         $act = act_clean($event->data);         
         if($act == 'admin') {
            $ftm = filemtime (CONFIG_FILE);        
            setcookie('act_time', $ftm , null,DOKU_BASE);
         }
         else  setcookie('act_time', $ftm , time() -3600,DOKU_BASE);
     }

     function dw_started(&$event, $param) {
        $act = act_clean($event->data);
        if($_COOKIE["act_time"]) {
                touch(CONFIG_FILE, $_COOKIE["act_time"] -20);    
                setcookie('act_time', $_COOKIE["act_time"] , time() -3600,DOKU_BASE);
        }
     }
     
    
   function modify_headers(&$event, $param) {
        global $INFO, $ID;
   
        $this->get_permission(); 
        $page = noNS($ID) . '.txt';
     
        if(!$this->files) {    
            return;
        }
        if(!in_array('all', $this->files) && !in_array($page, $this->files)) {  
              return;
        }
        $event->data[] = 'Last-Modified: Tue, 15 Nov 1994 12:45:26 GMT';
        $event->data[] =  'Cache-control: no-cache,must-revalidate,no-store';
        
    }

    function bypasss_cache(&$event, $param)
    {
        global $INFO;

        if ($INFO['htmlOK_client'] || isset($_GET['refresh']))
        {
            $event->preventDefault();
            $event->stopPropagation();
            $event->result = false;
        }
        $this->action_event = $event;
    }

    function get_info()
    {
        global $conf;
        global $INFO;

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
        $this->set_dbg_msg("access file: $access_file");
        if (file_exists($access_file))
        {
            $INFO['htmlOK_access_scope'] = $this->get_access_scope($access_file);            
            $this->set_dbg_msg("file exists: access file: $access_file");
            $this->saved_inf = io_readFile($access_file, false); // 'false' returns uncleaned string for unserialize
            if (!$this->saved_inf)
            {
                return;
            }
            $this->saved_inf = unserialize($this->saved_inf);
            $this->files = $this->saved_inf['filespecs'];
            $this->users = $this->saved_inf['user'];
            $this->groups = $this->saved_inf['group'];
            $this->curent_file = end(preg_split('/\//', $INFO['filepath']));
            $this->access_file = $access_file;
        }
    }

   function action_link(&$event, $param)
    {
          global $INFO;
         // if($INFO['client']) {  
         if($INFO['htmlOK_client'] && $INFO['hmtlOK_access_level'] > 0) {
              $name = "HTML Error Window";
              if($param[0] == 'ok') {
                  $htm_open = '<span>';
                  $htm_close = '</span>';
              }
              else {
                  $htm_open = '<li>';
                  $htm_close = '</li>';
              }           
              $event->data['items']['htmlokay'] = $htm_open .'<a href="javascript: htmlOK_ERRORS(0);jQuery(\'#htmlOKDBG_ERRORWIN\').toggle();void(0);"  rel="nofollow"   title="' .$name. '">'. $name.'</a>' . $htm_close;
          }         
  
    }
    /**
    * This function first checks to see whether the current namespace has a an access file
    *        if not, it goes back, one directory at a time, and tests whether an access file exists for that namespace
    *        If one does exits, it returns the filespec and the current namespace will
    *        be governed by this access file
    *        This comes into play if the found access file has set its filespace parameter to 'all'
    */
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


    function get_access_scope($path) {
         $access_dir = preg_quote(HTMLOK_ACCESS_DIR, '/');
         $access_scope = preg_replace('/' . $access_dir . '/', "", $path);
         $access_scope = trim($access_scope,'/' );
         $access_scope = preg_replace('/#/', ':',$access_scope);
         return $access_scope;
    }

    function set_dbg_msg($msg) {
       if(!$this->do_dbg) return;
       $this->db_msg .= $msg . '<br />';
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

    /**
    * checks for groups that are common to both the user and the current page
    *       returns an array of the common groups
    *
    * @param array $INF0_groups groups assigned to page found in DokuWiki global $INFO
    * @param array $groups groups assigned to user by htmlOK admin
    * @return mixed array of common groups or FALSE if none in common
    */
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

        if (is_string($info)) // looking for user level via $INFO['client'] or save_inf['display']
            {
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

        return $level;
    }

    function set_permissions()
    {
        global $INFO;
        global $ID;
        global $conf;
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
                $this->set_dbg_msg("Display:  {$INFO['hmtlOK_access_level']}");
                $conf['htmlok'] = 1;
                $INFO['htmlOK_visitor'] = true;
               // touch($INFO['filepath']);

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

    function get_permission(&$event, $param)
    {
        global $INFO;
        if(isset($_GET['htmlOKay_dbg'])) {
           $this->do_dbg = true;
        }
        $this->get_info();
        $this->set_permissions();
        $this->format_error_window();
        $this->debug();
    }

    function htmlOK_plugin_shutdown()
    {
        global $conf;
        plugin_disable('htmlOK');
    }

    function format_error_window()
    {
        echo <<<ERRORWINDOW
      <script language="javascript"><!--//--><![CDATA[//><!--
       var htmlOK_ERRORS_ARRAY = new Array();
       function htmlOK_ERRORS(viz) {
             var dom = document.getElementById("htmlOKDBG_ERRORWIN");
             var str = "";
             for(i=0; i<htmlOK_ERRORS_ARRAY.length;i++) {
                  if(htmlOK_ERRORS_ARRAY[i]) {
                      str += (htmlOK_ERRORS_ARRAY[i] + "<br />");
                  }
             }

             dom.innerHTML = str;
             if(!viz) return;
             dom.style.display=viz;
       }
       function show_htmlOKay_ERRORSLINK() {
             var dom = document.getElementById("htmlOKDBG_ERRORWINLINK");
             dom.style.display="block";
       }
       function display_htmlOKay_ERRORS(msg) {
             var dom = document.getElementById("htmlOKDBG_ERRORWIN");
             dom.innerHTML = msg;
       }

      //--><!]]></script>

      <div id="htmlOKDBG_ERRORWINLINK"  style="display:none; padding-top:2em;">
      <a href="javascript:htmlOK_ERRORS('block');">show errors</a>&nbsp;&nbsp;
      <a href="javascript:htmlOK_ERRORS('none');">close errors</a>
      </div>

      <div id="htmlOKDBG_ERRORWIN" style="display:none; padding:1em; background-color:white;"></div>

ERRORWINDOW;
    }



    function debug()
    {
        global $INFO;
        global $ID;
        global $conf;
        global $NS;
        if(!isset($INFO['htmlOK_client'])  || !$INFO['htmlOK_client']) return; 
        if(!$this->do_dbg) return;

        echo <<<DBG_JS
<script language="javascript"><!--//--><![CDATA[//><!--
function show_htmlOKayDBG_DIV(mode) {
   var dom = document.getElementById('htmlOKDBG_DIV');
   dom.style.display = mode;
}
//--><!]]></script>
<style type="text/css">
#htmlOKDBG_DIV { position:relative; height: 12em; overflow:scroll; display: none; background-color:white; }
</style>
<a href="javascript:show_htmlOKayDBG_DIV('block');">show debug</a>&nbsp;&nbsp;<a href="javascript:show_htmlOKayDBG_DIV('none');">close debug</a>
DBG_JS;

        echo '<div>&nbsp;</div><div id="htmlOKDBG_DIV">';
        echo $this->db_msg;
        echo '<br />';

        echo "Current File: $this->curent_file<br />";
        echo "Access Directory: " . HTMLOK_ACCESS_DIR . "<br />Access File: $this->access_file <br /> \n";
        echo "Client: " . $INFO['client'] . '<br />';
        echo "HTML_OK: " . $conf['htmlok'] . "<br />";
	    echo "Name Space: " . getNS($ID). " -->  $NS\n";

        echo "\$INFO['htmlOK_client']:  " . $INFO['htmlOK_client'] . "&nbsp;&nbsp;&nbsp;--Writable: " . $INFO['writable'] . " &nbsp;&nbsp;&nbsp;--Editable: " . $INFO['editable'] . '<br />';
        echo "Access level: {$INFO['hmtlOK_access_level']}<br />";
        
        echo "<pre>";
        echo "<br />\$INFO array:<br />";
        $str = print_r($INFO,true); 
        echo htmlentities($str,ENT_QUOTES);

        echo "<br />\$conf array:<br />";
        $str = print_r($conf,true); 
        echo htmlentities($str,ENT_QUOTES);


        echo "<br />Saved Info:";
        print_r($this->saved_inf);

        echo "</pre>";

        echo "</div>";
    }
}

?>
