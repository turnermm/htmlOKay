<?php
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
if(!defined('HTMLOK_WIKI_PATH'))define ('HTMLOK_WIKI_PATH', DOKU_INC . 'data/pages/');
require_once(DOKU_PLUGIN . 'action.php');
if (!defined('HTMLOK_ACCESS_DIR')) define('HTMLOK_ACCESS_DIR', realpath(dirname(__FILE__)) . '/conf/access');
define ('CONFIG_FILE', DOKU_INC . 'conf/local.php');
require_once(DOKU_INC . 'inc/cache.php');

class action_plugin_htmlOKay extends DokuWiki_Action_Plugin
{
    var $saved_inf;
    var $files;
    var $db_msg = "";
    var $do_dbg = false;
    var $access_file;
    var $namespace;
    var $helper;

    function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('HTMLOK_ACCESS_EVENT', 'BEFORE', $this, 'errors_top');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'bypasss_cache');
        $controller->register_hook('TEMPLATE_USERTOOLS_DISPLAY', 'BEFORE', $this, 'action_link', array('user'));    
        $controller->register_hook('TEMPLATE_HTMLOKAYTOOLS_DISPLAY', 'BEFORE', $this, 'action_link', array('ok'));
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'modify_headers', array());   
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'dw_started', array());   
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'errors_top', array());                      
    }

     function dw_started(&$event, $param) {
        $this->_init();
     }
     
   function modify_headers(&$event, $param) {
        global $INFO, $ID;
   
        $this->get_info();
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
            $cache =$event->data;
     
          if (!isset($cache->page)) return;        
            if (isset($cache->mode) &&  $cache->mode == 'metadata') return;
           $depends = p_get_metadata($cache->page, 'relation htmlokay');
           $meta_file = metaFN($cache->page,'.meta');
           if(isset($depends))    {               
               if(!empty($cache->depends['files'])) {             
                $cache->depends['files'][] = $meta_file;
                //msg($cache->cache);
               //msg('<pre>c = ' . print_r($event->data, 1) .'</pre>');
             }
            else $cache->depends['files'] = array($meta_file);        
        }         
    }

    function get_info()
    {
        global $conf;
        global $INFO;
        
        $this->helper->get_info();

       $namespace = $this->helper->get_namespace();
    
        $this->namespace = $namespace;
        $namespace = str_replace(':', '#', $namespace);
        $access_file = $this->helper->get_access_file(HTMLOK_ACCESS_DIR, $namespace);
        $this->set_dbg_msg("access file: $access_file");
        if (file_exists($access_file))
        {
            $INFO['htmlOK_access_scope'] = $this->helper->get_access_scope($access_file);            
            $this->set_dbg_msg("file exists: access file: $access_file");            
            $this->saved_inf = $this->helper->get_saved_inf(); 
            if (!$this->saved_inf)
            {
                return;
            }

            $this->files = $this->saved_inf['filespecs'];
            if(!empty($INFO['filepath']))  {
               $this->curent_file =  basename($INFO['filepath']);             
            }
            else {
               $this->curent_file = noNS($ID) . '.txt';
            }
            $this->access_file = $access_file;
        }
    }

   function action_link(&$event, $param)
    {
          global $INFO;
 
         
         if(!empty($INFO['userinfo'])) {
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
 
 
    function set_dbg_msg($msg) {
       if(!$this->do_dbg) return;
       $this->db_msg .= $msg . '<br />';
    }


    function _init()
    {
        global $INFO, $JSINFO;
        $this->helper = $this->loadhelper('htmlOKay',1);
        if(isset($_GET['htmlOKay_dbg'])) {
           $this->do_dbg = true;
        }
        $this->get_info();    

      
        $JSINFO['path'] =  HTMLOK_WIKI_PATH;
      
    }

    function htmlOK_plugin_shutdown()
    {
        global $conf;
        plugin_disable('htmlOK');
    }

    function setup_debug(&$event, $param) {              
             $this->format_error_window();
             $this->debug();
    }
    
        function get_JSErrString($msg)
    {
      
        $msg = trim($msg); 
        static $msgs_inx = -1;
      
        if (!isset($msg) || empty($msg)) return "";     
        $msgs_inx++;         
        ptln('htmlOK_ERRORS_HEADER[' . $msgs_inx . ']="' . $msg . '"');
    } 
    
    function  errors_top(&$event,$params) {    
         global $INFO;      
         static $done = false;
         
         if($done) return;
         $done = true;
         
         $this->setup_debug ($event,$params);
         ptln( '<script language="javascript">' );
         ptln('var htmlOK_ERRORS_HEADER = new Array();');     
  
        $this->JS_ErrString .= $this->get_JSErrString("<b>---End User Info---</b>");
        $this->JS_ErrString .= $this->get_JSErrString("hmtlOK_access_level: " .  $this->helper->get_access()); 
        if ($INFO['htmlOK_client'])
        {
            $this->JS_ErrString .= $this->get_JSErrString("client:  " . $INFO['htmlOK_client']);
        }
        else
        {
            $this->JS_ErrString .= $this->get_JSErrString("client:  " . $INFO['client']);
        }
     
        $this->JS_ErrString .= $this->get_JSErrString("Scope: " . $INFO['htmlOK_access_scope']);
        $this->JS_ErrString .= $this->get_JSErrString("<b>User Info:</b>");
        if ($INFO['hmtlOK_access_level'] > 0)
        {
            $this->access_level = $INFO['hmtlOK_access_level'];
        }
        else
        {
            $this->access_level = $INFO['htmlOK_displayOnly'];          
        }
          ptln( '</script>');  
   }    
    function format_error_window()
    {
        echo <<<ERRORWINDOW
      <script language="javascript"><!--//--><![CDATA[//><!--
       var htmlOK_ERRORS_ARRAY = new Array();
       var htmlOK_ERRORS_merged = false;
      
       function htmlOK_ERRORS(viz) {
             var dom = document.getElementById("htmlOKDBG_ERRORWIN");
             if(htmlOK_ERRORS_merged) {   
                 dom.innerHTML = htmlOK_ERRORS_merged;
                 return;
             }
              for(i=0; i<htmlOK_ERRORS_HEADER.length;i++) {
                  htmlOK_ERRORS_ARRAY.splice(0, 0, htmlOK_ERRORS_HEADER[i]);
              }
             
            
             var str = "";
             for(i=0; i<htmlOK_ERRORS_ARRAY.length;i++) {
                  if(htmlOK_ERRORS_ARRAY[i]) {
                      str += (htmlOK_ERRORS_ARRAY[i] + "<br />");
                  }
             }
             htmlOK_ERRORS_merged = str;       
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
       
        if(!isset($INFO['userinfo']) ) return ; // && !$INFO['htmlOK_client']) return; 
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
        print_r($this->saved_inf,true);

        echo "</pre>";

        echo "</div>";
    }
    
     function write_debug($what) {
     return;
     $handle = fopen("htmlok_php.txt", "a");
     if(is_array($what)) $what = print_r($what,true);
     fwrite($handle,"$what\n");
     fclose($handle);
  }
}
?>
