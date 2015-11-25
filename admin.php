<?php
/**
*
* @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
* @author Myron Turner <mturner@cc.umanitoba.ca>
*/

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
define ('HTMLOK_WIKI_PATH', DOKU_INC . 'data/pages/');
if(!defined('DOKU_CONF')) define('DOKU_CONF',DOKU_INC.'conf/');
define('AUTH_USERFILE', DOKU_CONF . 'users.auth.php');
require_once(DOKU_PLUGIN . 'admin.php');

/**
* All DokuWiki plugins to extend the admin function
* need to inherit from this class
*/
class admin_plugin_htmlOKay extends DokuWiki_Admin_Plugin
{
    var $output;
    var $ajax_script = 'directory_scan-3.php';
    var $path = HTMLOK_WIKI_PATH;
    var $wiki_home = HTMLOK_WIKI_PATH;
    var $directories = array();
    var $global_conf;
    var $plugin_name;
    var $current_namespace;
    var $users;
    var $groups = array();
    var $scrollbars = false;
    var $namespace_descriptor = '_ROOT_';
    var $saved_inf; // either data read from access file at startup or data saved from $_POST
    var $error_msg;
    var $user_entries = 0;
    var $show_debug = false;

    function admin_plugin_htmlOKay()
    {
        global $conf;
        $this->plugin_name ='htmlOKay'; 
        $this->current_namespace = rtrim($this->path, '/');

        $this->_loadUserData();
        
        $script = DOKU_PLUGIN . $this->plugin_name . '/' . $this->ajax_script;        
        $document_root = $_SERVER['DOCUMENT_ROOT'];
        $url = preg_replace('/' . preg_quote($document_root, '/') . '/', "", $script);


        $this->ajax_script = $url;
        $this->global_conf = $conf;
        $this->init();
        $this->traverseDirTree($this->path, 'outputpath', 'outputpath');
    }

    /**
    * return some info
    */
    function getInfo()
    {
        return array('author' => 'Myron Turner',
            'email' => 'Myron_Turner@shaw.ca',
            'date' => '2015-09-03',
            'name' => 'HTML Access Manager',
            'desc' => 'sets permissions for html write/edit access',
            'url' => 'http://www.mturner.org/htmlaccess/',
            );
    }

    /**
    * return sort order for position in admin menu
    */
    function getMenuSort()
    {
        return 929;
    }

    function getMenuText($language) {
         return 'HTML Access Manager';
    }
    
    
    /**
    * handle user request
    */
    function handle()
    {
        if (!isset($_REQUEST['abs_path'])) // first time through
        {
            $this->namespace_file = preg_replace('/\:/', '#', $this->namespace_descriptor);
            $data_file = DOKU_PLUGIN . $this->plugin_name . '/conf/access/' . $this->namespace_file;
            $this->saved_inf = io_readFile($data_file, false); // 'false' returns uncleaned string for unserialize
            if(isset($this->saved_inf)) {
                $this->saved_inf = unserialize($this->saved_inf);
            }

            return;
        }

                             // Saving access files begins here
        $this->filespecs = $_POST['filespecs'];
        $this->request = $_REQUEST;
        $this->output = $_POST;
        $this->current_namespace = $_POST['abs_path'];
        $this->namespace_descriptor = $this->directories[$this->current_namespace]['namespace'];

        $this->namespace_file = preg_replace('/\:/', '#', $this->namespace_descriptor);

        if (!isset($_POST['group']) && !isset($_POST['user']))
        {
            $this->error_msg = "HTML Permissions for " . $this->namespace_file ." have been removed.";
        
        }
        elseif ($this->filespecs[0] == 'none')
        {
            $this->error_msg = "Incomplete data:  No files selected";
            return;
        }

        $data_file = DOKU_PLUGIN . $this->plugin_name . '/conf/access/' . $this->namespace_file;
        $this->namespace_file = $data_file;

        $inf = $this->get_output_array();
        if(!$inf) $inf = array();    
        $this->saved_inf = $inf;
        $inf = serialize($inf);
        io_saveFile($data_file, $inf, false);
               
        
    }

    function get_output_array()
    {
        $new_inf = array();
        $keys = array_keys($this->output);
        if (!in_array('group', $keys) && !in_array('user', $keys)) return false;

        $levels = array('none' => 0, 'strict' => 1, 'medium' => 2, 'lax' => 3, 'su' => 4);
        $display = 4;
        foreach($this->output as $item => $val)
        {
            if ($item != 'filespecs' && $item != 'group' && $item != 'user' && $item != 'abs_path') continue;

            if ($item == 'group' || $item == 'user') // get lowest HTML permissions for display
            {
                foreach($val as $name => $level) // display is in effect when HTML viewed by non-editor
                {
                    if ($levels[$level] < $display)
                    {
                        $display = $levels[$level];
                    }
                }
            }

            $new_inf[$item] = $val;
        }

        $levels = array_keys($levels);
        $new_inf['display'] = $levels[$display];
        $new_inf['namespace'] = $this->namespace_file;
        return $new_inf;
    }

    function init()
    {
        $this->wiki_home = rtrim($this->wiki_home, '/');
        $this->directories[$this->wiki_home]['name'] = '_ROOT_';
        $this->directories[$this->wiki_home]['namespace'] = '_ROOT_';
        $this->wiki_home = ltrim($this->wiki_home, '/');
        $this->wiki_home = preg_quote($this->wiki_home, '/');
    }

    /**
    * Load all user data
    *
    * loads the user file into a datastructure
    *
    *    adapted from DokuWiki plain.class.php
    */
    function _loadUserData()
    {
        $this->users = array();

        if (!@file_exists(AUTH_USERFILE)) return;

        $lines = file(AUTH_USERFILE);
        foreach($lines as $line)
        {
            $line = preg_replace('/#.*$/', '', $line); //ignore comments
            $line = trim($line);
            if (empty($line)) continue;

            $row = explode(":", $line, 5);
            $groups = explode(",", $row[4]);

            $this->users[$row[0]]['name'] = urldecode($row[2]);
            $this->users[$row[0]]['mail'] = $row[3];
            $this->users[$row[0]]['grps'] = $groups;

            foreach($groups as $grp)
            {
                $this->groups[$grp][] = $row[0];
            }
        }
    }

    /**
    * Constructs namespace from directory path
    *    Called by output_path when constructing $directories array
    */
    function get_namespace($path)
    {
        $namespace_string = trim($path, '/');
        $namespace_string = preg_replace('/^' . $this->wiki_home . '/', "", $namespace_string);
        $namespace_string = preg_replace('%/%', ':', $namespace_string);
        return ltrim($namespace_string, ':');
    }

    /**
    * Adapted from http://www.safalra.com/programming/php/directry-tree-traversal.php
    */
    function traverseDirTree($base, $fileFunc, $dirFunc = null, $afterDirFunc = null)
    {
       if(!is_readable ($base)) {
           msg("$base is not readable",2);
           return;       
        }   
        $subdirectories = opendir($base);
        while (($subdirectory = readdir($subdirectories)) !== false)
        {
            $path = $base . $subdirectory;
            if (is_file($path))
            {
                if ($fileFunc !== null) $this->$fileFunc($path);
            }
            else
            {
                if ($dirFunc !== null) $this->$dirFunc($path);
                if (($subdirectory != '.') && ($subdirectory != '..'))
                {
                    $this->traverseDirTree($path . '/', $fileFunc, $dirFunc, $afterDirFunc);
                }
                if ($afterDirFunc !== null) $this->$afterDirFunc($path);
            }
        }
        closedir($subdirectories);
    }

    function outputPath($path)
    {
        $name = basename($path);
        if ($name == '.' || $name == '..') return;

        if (is_dir($path))
        {
            $this->directories[$path] = array();
            $this->directories[$path]['name'] = $name;
            $this->directories[$path]['namespace'] = $this->get_namespace($path);
            $this->directories[$path]['files'] = array();
        } elseif (is_file($path))
        {
            $dir = dirname($path);
            $this->directories[$dir]['files'][] = $name;
        }
    }

    function get_directory_options()
    {
        $options = array();

        foreach($this->directories as $dir => $info)
        {
            if (!isset($info['namespace'])) continue;
            $selected = "";
            if ($dir == $this->current_namespace)
            {
                $selected = 'SELECTED';
            }
            $options[] = "<option value=\"$dir\"  $selected>" . $info['namespace'] . '</option>' ;
        }

        return $options;
    }

    function get_group_options()
    {
        $options = array();

        $groups = $this->groups;
        foreach($groups as $group => $val)
        {
            list($checked_strict, $checked_medium, $checked_lax, $su) = $this->get_checked($group, $this->saved_inf['group']);

            $options[] = "<td class='centeralign'><input type='radio' value='strict' $checked_strict name='group[$group]' />" . "<td class='centeralign'><input type='radio' value='medium' $checked_medium name='group[$group]' />" . "<td class='centeralign'><input type='radio' value='lax' $checked_lax name='group[$group]' />" . "<th><a href='javascript:show_this(\"group[$group]\");'>R</a></th>" . "<td>$group</td>";
        }

        return $options;
    }

    function get_file_options($dir)
    {
        $options = array();

        $default_selected = true;
        $options[] = '<option value="none" style="color:white; background-color:white;">' . 'No Files Selected&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . '</option>' ;
        $options[] = '<option value="all">All</option>' ;
        $files = $this->directories[$dir]['files'];
        foreach($files as $file)
        {
            $selected = "";
            if (isset($this->saved_inf['filespecs']))
            {
                if (in_array ($file, $this->saved_inf['filespecs']))
                {
                    $default_selected = false;
                    $selected = "selected";
                }
            }
            $options[] = "<option value='$file'  $selected>" . $file . '</option>' ;
        }
        if ($default_selected)
        {
            $options[0] = '<option value="none" SELECTED style="color:white; background-color:white;">' . 'No Files Selected&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . '</option>' ;
        }
        return $options;
    }
    // find previously checked radio buttons for form's user and group elements
    function get_checked($name, $info_array)
    {
        $checked_strict = "";
        $checked_medium = "";
        $checked_lax = "";
        $checked_su = "";

        if (isset($info_array))
        {
            if (array_key_exists($name, $info_array))
            {
                switch ($info_array[$name])
                {
                    case 'strict':
                        $checked_strict = 'checked';
                        break;
                    case 'medium':
                        $checked_medium = 'checked';
                        break;
                    case 'lax':
                        $checked_lax = 'checked';
                    case 'su':
                        $checked_su = 'checked';

                        break;
                }
            }
        }

        return array($checked_strict, $checked_medium, $checked_lax, $checked_su);
    }

    function get_user_options()
    {
        $options = array();

        foreach($this->users as $user => $user_array)
        {
            $groups = implode(',', $user_array['grps']);

            list($checked_strict, $checked_medium, $checked_lax, $checked_su) = $this->get_checked($user, $this->saved_inf['user']);

            $options[] = '<td class="centeralign">' . '<input type="radio" name="user[' . $user . ']" value="strict" ' . $checked_strict . ' /></td>' . '<td class="centeralign">' . '<input type="radio" name="user[' . $user . ']"  value= "medium" ' . $checked_medium . ' /></td>' . '<td class="centeralign">' . '<input type="radio" name="user[' . $user . ']"  value= "lax" ' . $checked_lax . ' /></td>' . '<td class="centeralign">' . '<input type="radio" name="user[' . $user . ']"  value= "su" ' . $checked_su . ' /></td>' . "<th><a href='javascript:show_this(\"user[$user]\");'>R</a></th>" . "\n<td>$user</td><td>" . $user_array['name'] . '</td><td>' . '<a href="mailto:' . $user_array['mail'] . '"  class="email">' . $user_array['mail'] . '</a></td><td>' . $groups . '</td>';
        }

        /* causes javascript to call scrollbars_htmlOKay() on loading
           IE doesn't require scrollbars because overflow doesn't overwrite elements
           beneath the users table but instead pushes them down the page
         */
        if (count($options) > 4 && !preg_match('/MSIE\s+\d+/', $_SERVER['HTTP_USER_AGENT']))
        {
            $this->scrollbars = true;
            $this->user_entries = count($options);
        }

        return $options;
    }

    /**
    * Output Javascript for html file
    */
    function print_scripts($url)
    {
        $path = HTMLOK_WIKI_PATH;

        echo <<<SCRIPTS

    <script language="javascript"><!--//--><![CDATA[//><!--
    var scroll_visible_htmlOKay = false;

    function handleHttpResponse_htmlOKay() {

      if (httpXMLobj_htmlOKay.readyState == 4 && httpXMLobj_htmlOKay.status==200) {
        if (httpXMLobj_htmlOKay.responseText.indexOf('invalid') == -1) {
            var f = window.document['nsdata'];
            reset_htmlOKay(f);
            var s = f['filespecs[]'];
            s.options.length = 0;

            var data = httpXMLobj_htmlOKay.responseText.split("%%");
            var opts = data[0];
            var access = data[1];

           var access_array =  get_access_array_htmlOKay(access);

            var selected_files = get_selected_files_htmlOKay(access_array['filespecs']);
            var opt_array = opts.split("|");
            var selected_default;
            for(var i=0; i<opt_array.length-1; i++) {
                var ar = opt_array[i].split(":");
                var selected = false;
                if(ar.length == 3 && !selected_default) selected_default = i;
                if(selected_files[ar[1]]) {
                        selected = true;
                        selected_default = -1;
                }

                var o = new Option(ar[0],ar[1]);
                s.options[i] = o;
                s.options[i].selected = selected;
            }
          if(selected_default &&  selected_default > -1) {
                    s.options[i].selected = true;
          }

            update_groups_htmlOKay(f, access_array['group'] )
            update_users_htmlOKay(f, access_array['user'] )
        }

      }   // readyState == 4
    }

    function update_avail_htmlOKay(qstr) {
        var url = "$url?path=$path&";
	var path = "$path";

        qstr = qstr.replace(/\&amp;/g,"&");
        httpXMLobj_htmlOKay.open("GET", url + qstr, true);
        httpXMLobj_htmlOKay.onreadystatechange = handleHttpResponse_htmlOKay;
        httpXMLobj_htmlOKay.send(null);
     }


    function getHTTPObject_htmlOKay() {

      var xmlhttp;
      /*@cc_on
      @if (@_jscript_version >= 5)
        try {
          xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
          try {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
          } catch (E) {
            xmlhttp = false;
          }
        }
      @else
      xmlhttp = false;
      @end @*/
      if (!xmlhttp && typeof XMLHttpRequest != 'undefined') {
        try {
          xmlhttp = new XMLHttpRequest();
        } catch (e) {
          xmlhttp = false;
        }
      }
      return xmlhttp;
    }

    var httpXMLobj_htmlOKay = getHTTPObject_htmlOKay(); // We create the HTTP Object

    //--><!]]></script>

<style type="text/css">
#htmlOK_user_table { position:relative;  overflow:visible; margin:auto;}
#results { width: 25em; }
</style>

SCRIPTS;
    }

    /**
    * output appropriate html
    */
    function html()
    {
        global $ID;

        $this->print_scripts($this->ajax_script);
         
        echo "<div id='htmlOK_div' style='width:100%'>\n";
        $this->debug(false,false);
        ptln('<CENTER><H1>Embedded HTML Access Manager</H1></CENTER>');
        if ($this->error_msg)
        {
            print "<center><h4>$this->error_msg</h4></center>";
        }
        ptln('<div style="width: 85%;margin: 0; margin: auto">');
       
        ptln('<TABLE align="center" width="80%"><TR><TD>');
       echo $this->locale_xhtml('selection');
        ptln("\n</TABLE>\n");

        
        /* Start Form */
        ptln("\n" . '<form action="' . wl($ID) . '" method="POST" name="nsdata"' . ' >');
        ptln('<input type="hidden" name="do"   value="admin" />' . "\n"
             . '<input type="hidden" name="page" value="' . $this->plugin_name . '"  />');

        /*  Namespace Table */

        ptln('<table cellpadding="8"  class="inline">');

        $this->write_SELECT('Namespace', 'abs_path', 'get_directory_options', "");
        $this->write_SELECT('Files', 'filespecs[]',
            'get_file_options', rtrim($this->current_namespace, '/'),
            'multiple size="3" ', 'results'
            );

        echo "</table>\n";

        /* Buttons */
        ptln('<div  class="bar" style="width:30%; margin: 0 auto;">');
        ptln('<INPUT TYPE="SUBMIT" class="button"  VALUE="Save" />');
        ptln('<INPUT TYPE="BUTTON" class="button"  VALUE="Reset"  onclick="reset_htmlOKay(window.document[\'nsdata\']);" />');
        ptln('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT TYPE="BUTTON" class="button" id = "htmlOK_scrollbutton" VALUE="Scroll" onclick="scrollbars_htmlOKay();" />');
        ptln('</div>');

        ptln('<div id="htmlOK_user_table"><table cellpadding="5" cellspacing="16" class="htmlOK_data" border=0>'); // Start outer table
        /* Groups Table */

        ptln('<tr><td valign="top"><table cellpadding="8" class="inline">');
        ptln('<TR><TH colspan="4">Policy</TH><TH rowspan="2">Groups</TH>');
        ptln('<TR><TH>H</TH><TH>M</TH><TH>L</TH><TH>Reset</TH></tr>');
        $options = $this->get_group_options();
        foreach($options as $option)
        {
            ptln("<TR>$option");
        }
        ptln('</table>'); // End Groups table

        /* Users Table */
        ptln('<td><table cellpadding="8"  class="inline">' . '<tr><th colspan="5">Policy</th><th rowspan="2">User</th><th rowspan="2">Real Name</th>' . ' <th rowspan="2">Email</th><th rowspan="2">Groups</th>');
        ptln('<TR><TH>H</TH><TH>M</TH><TH>L</TH><TH>U</TH><TH>Reset</TH>');

        $options = $this->get_user_options();
        foreach($options as $option)
        {
            ptln('<tr class="user_info">' . $option);
        }
        ptln('</table>'); // End users table

        /* Close Table, close Form */
        ptln("\n</TABLE></div>\n");
        ptln("</form><br />\n");

        echo "</div></div>\n"; // close htmlOK_div

        if ($this->scrollbars)
        {
            ptln('<script language="javascript"> user_table_size_htmlOKay(' . $this->user_entries . '); </script>');
        }
    }

    /**
    * $th:     heading
    * $name:   name of the Select
    * $options_func:  the name of the options function from which to get optons and values
    * $param:  optional parameter to be passed in to options_func
    * $select_type:  optional multiple and size
    * $id: for file options that are being replaced vi the AJAX call
    */
    function write_SELECT($th, $name, $options_func, $param = "", $select_type = "", $id = "")
    {
        $button_fields = array('abs_path' => '<INPUT TYPE ="BUTTON"  class="button"  value = "Select" onclick="getNSdata_htmlOKay(window.document[\'nsdata\']);" />',
            'filespecs[]' => '&nbsp;Use <b>Ctrl</b> or <b>Options</b> key to <br />multiple select from:&nbsp;<br />&nbsp;<span id="current_ns">&nbsp;'
             . $this->directories[$this->current_namespace]['namespace'] . '</span>',
            'groupspecs[]' => "Button"
            );

        if ($id)
        {
            $id = ' id="' . $id . '" ';
        }
        ptln('<TR><th valign="middle" class="leftalign">' . "\n$th\n" . '<td valign="middle" class="centeralign">');
        ptln('<SELECT name="' . $name . '" class="edit" ' . $select_type . $id . '>');

        $options = $this->$options_func($param);
        foreach($options as $option)
        {
            ptln($option);
        }

        $class = "";
        if ($name == 'abs_path')
        {
            $class = 'class = "bar" ';
        }

        ptln('</SELECT> <TD colspan="2" valign="middle" align="center"' . $class . '>');
        ptln($button_fields[$name] . '</td>');
    }

    function debug($users = false, $groups = false)
    {
        if(!$this->show_debug) return;

        global $INFO;
	    global $conf;
        
        echo '<pre>';
        echo "<h4>\$INFO</h4>";
        print_r($INFO);
        echo "<h4>\$conf</h4>";
        print_r($conf);
        echo "<h4>Saved inf:</h4>";
        print_r($this->saved_inf);
        echo "<h4>request:</h4>";
        print_r($this->request);

        echo "<b>File:</b> $this->namespace_file \n";

        if($groups) {
            echo "<h4>Group(s)</h4>";
            print_r ($this->groups);
        }

        if($users) {      
            echo "<h4>User(s):</h4>";
            print_r ($this->users);        
        }
       

        
        echo "Output: <br>";print_r($this->output); 
        echo "Script: " .$this->ajax_script . " <--> Path:  $this->ajax_path_temp\n";
        echo "</pre>";
    }
}

?>
