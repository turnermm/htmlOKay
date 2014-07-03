<?php
/**
* Plugin Skeleton: Displays "Hello World!"
*
* @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
* @author Christopher Smith <chris@jalakai.co.uk>
*/

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_INC . 'inc/cache.php');

define('HTML_OK_INVALIDID', 0);
define('HTML_OK_NOTSUPPORTED', 1);
define('HTML_OK_EXCLUDEDWINDOW', 2);
define('HTML_OK_BADCLASSNAME', 3);
define('HTML_OK_BADSELECTOR', 4);
define('HTML_OK_BADFNNAME', 5);
// ini_set('display_errors', "on");
// ini_set('error_reporting', E_ALL);
/**
* All DokuWiki plugins to extend the parser/rendering mechanism
* need to inherit from this class
*/
class syntax_plugin_htmlOKay extends DokuWiki_Syntax_Plugin
{
    // $access_levels = array('none'=>0, 'strict'=>1, 'medium'=>2, 'lax'=>3, 'su'=>4));
    var $access_level = 0;
    var $client;
    var $msgs;
    var $msgs_inx = 0;
    var $htmlOK_errors;
    var $cycle = 0;
    var $open_div = 0;
    var $closed_div = 0;
    var $divs_reported = false;
    var $JS_ErrString = "";
    function syntax_plugin_htmlOKay()
    {
        global $INFO;
        global $ID;
        global $conf;

        $this->htmlOK_errors = array('Invalid ID', 'Element or Attribute not supported',
            'Internal Window Elements Not Supported', 'Invalid CSS Class name(s)',
            'ID Selectors not supported', 'Invalid Javascript function name(s)');

        $this->msgs = "";
       
        if ($INFO['htmlOK_client'])
        {
            $cache = new cache($ID, ".xhtml");
            trigger_event('PARSER_CACHE_USE', $cache);

            if ($INFO['hmtlOK_access_level'] > 0 && ! $conf['plugin']['htmlOKay']['custom_error_but'])
            {
                $this->JS_ErrString .= '<script language="javascript">show_htmlOKay_ERRORSLINK();</script>';
            }
        }
        $this->JS_ErrString .= $this->get_JSErrString("<b>User Info:</b>");
        $this->JS_ErrString .= $this->get_JSErrString("hmtlOK_access_level: " . $INFO['hmtlOK_access_level']);
        if ($INFO['htmlOK_client'])
        {
            $this->JS_ErrString .= $this->get_JSErrString("client:  " . $INFO['htmlOK_client']);
        }
        else
        {
            $this->JS_ErrString .= $this->get_JSErrString("client:  " . $INFO['client']);
        }
      #  $this->JS_ErrString .= $this->get_JSErrString("\$conf['htmlok']: " . $conf['htmlok']);
        $this->JS_ErrString .= $this->get_JSErrString("Scope: " . $INFO['htmlOK_access_scope']);
        $this->JS_ErrString .= $this->get_JSErrString("<b>---End User Info---</b>");
        if ($INFO['hmtlOK_access_level'] > 0)
        {
            $this->access_level = $INFO['hmtlOK_access_level'];
        }
        else
        {
            $this->access_level = $INFO['htmlOK_displayOnly'];
            // echo  "Display Only.  Access Level: $this->access_level<br />";
        }
          
    }


    /**
    * What kind of syntax are we?
    */
    function getType()
    {
        return 'protected';
    }

    /**
    * What about paragraphs? (optional)
    */
    function getPType()
    {
        return 'normal';
    }

    /**
    * Where to sort in?
    */
    function getSort()
    {
        return 180;
    }

    /**
    * Connect pattern to lexer
    *       $this->Lexer->addPattern('<(?i)\w+\s+.*?ID\s*=.*?>','plugin_htmlOKay');
    *       $this->Lexer->addPattern('<(?i)\w+\s+.*?CLASS\s*=.*?>','plugin_htmlOKay');
    */
    function connectTo($mode)
    {
        
        $this->cycle++;
  
        $this->Lexer->addEntryPattern('<html>(?=.*?</html>)', $mode, 'plugin_htmlOKay');
    }

    function postConnect()
    {
        $this->Lexer->addPattern('.<(?i)IFRAME.*?/IFRAME\s*>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('.<(?i)ILAYER.*?/ILAYER\s*>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('<(?i)a.*?javascript.*?</a\s*>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('<(?i)FORM.*?</FORM\s*>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('<(?i)DIV.*?>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('<(?i)/DIV.*?>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('.<(?i)STYLE.*?/STYLE\s*>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('<(?i)SCRIPT.*?/script\s*>', 'plugin_htmlOKay');
        $this->Lexer->addPattern('<(?i)TABLE.*?</TABLE\s*>', 'plugin_htmlOKay');

        $this->Lexer->addPattern('(?i)ID\s*=\s*\W?.*?\W', 'plugin_htmlOKay');
        $this->Lexer->addPattern('(?i)class\s*=\s*\W?.*?\W', 'plugin_htmlOKay');

        $this->Lexer->addExitPattern('</html>', 'plugin_htmlOKay');
    }

    /**
    *   level 2 permissions:  guarded access
    */
    function rewrite_match_medium($match)
    {
        if (preg_match('/<FORM(.*?)>/i', $match, $matches))
        {
            if (preg_match('/(action)/i', $matches[1], $action))
            {
                return $this->getError(HTML_OK_NOTSUPPORTED, $match, $action[1]);
            } elseif (preg_match('/(onsubmit)/i', $matches[1], $onsubmit))
            {
                return $this->getError(HTML_OK_NOTSUPPORTED, $match, $onsubmit[1]);
            }
        }
        elseif (preg_match('/<script/i', $match))
        {
            return $this->script_matches($match, 2);
        } 
        elseif (preg_match('/<IFRAME/i', $match, $matches))
        {
            return $this->getError(HTML_OK_EXCLUDEDWINDOW, $match, "IFRAME");
        } 
        elseif (preg_match('/<ILAYER/i', $match, $matches))
        {
            return $this->getError(HTML_OK_EXCLUDEDWINDOW, $match, "ILAYER");
        }
        elseif (preg_match('/<(DIV)/i', $match, $matches))
        {
            $this->open_div++;
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, $matches[1], "div");
        } 
        elseif (preg_match('/<a.*?javascript.*?<\/a\s*>/i', $match))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, "javascript urls");
        } 
        elseif (preg_match('/<STYLE/i', $match))
        {
            return $this->style_matches($match, 2);
        }
        elseif (preg_match('/<TABLE/i', $match, $matches))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, "TABLE");
        }

        $retv = $this->class_id_matches($match);
        if ($retv !== false) return $retv;

        return $match;
    }

    /**
    * treat level 1 permissions:  restricted access
    */

    function rewrite_match_strict($match)
    {
        if (preg_match('/<FORM/i', $match))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, 'FORM');

        } elseif (preg_match('/<script/i', $match))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, 'SCRIPT');
    
        } elseif (preg_match('/<IFRAME/i', $match, $matches))
        {
            return $this->getError(HTML_OK_EXCLUDEDWINDOW, $match, "IFRAME");
        } 
        elseif (preg_match('/<ILAYER/i', $match, $matches))
        {
            return $this->getError(HTML_OK_EXCLUDEDWINDOW, $match, "ILAYER");

        } elseif (preg_match('/<(DIV)/i', $match, $matches))
        {
            $this->open_div++;
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, $matches[1], "div");

        } elseif (preg_match('/<TABLE/i', $match, $matches))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, "TABLE");

        } elseif (preg_match('/<STYLE/i', $match))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, "STYLE");

        } elseif (preg_match('/<a.*?javascript.*?<\/a\s*>/i', $match))
        {
            return $this->getError(HTML_OK_NOTSUPPORTED, $match, "javascript urls");
        }

        $retv = $this->class_id_matches($match);
        if ($retv !== false) return $retv;

        return $match;
    }

    function rewrite_match_lax($match)
    {
        $div = false;
        if (preg_match('/<FORM(.*?)>/i', $match, $matches))
        {
            if (preg_match('/action/i', $matches[1]))
            {
                return $this->getError(HTML_OK_NOTSUPPORTED, $match, $matches[1], "form");
            }

        } elseif (preg_match('/<STYLE/i', $match))
        {
            $match = $this->style_matches($match, 3);
            return $match;

        } elseif (preg_match('/<(IFRAME|ILAYER)/i', $match, $matches))
        {
            return $this->getError(HTML_OK_EXCLUDEDWINDOW, $match, $matches[1], "");
        
        } elseif (preg_match('/<script/i', $match))
        {
            return $this->script_matches($match, 3);

        } elseif (preg_match('/<(DIV)/i', $match))
        {
            $div = true;
            $this->open_div++;
        }

        $retv = $this->class_id_matches($match, $div);
        if ($retv !== false) return $retv;

        return $match;
    }

    /**
    * Super-user access
    */
    function rewrite_match_su($match)
    {
        global $conf; 
        if($conf['plugin']['htmlOKay']['su_unrestricted']) 
           return $match;

        $div = false;
        if (preg_match('/<STYLE/i', $match))
        {
            $match = $this->style_matches($match, 4);
            return $match;
        } elseif (preg_match('/<(DIV)/i', $match))
        {
            $div = true;
            $this->open_div++;
        }

        $retv = $this->class_id_matches($match, $div);
        if ($retv !== false) return $retv;

        return $match;
    }

    function script_matches($match, $level)
    {
        if ($level < 3)
        {
            if (preg_match('/\blocation\b/', $match))
            {
                return $this->getError(HTML_OK_NOTSUPPORTED, $match, "location");
            }

            if (preg_match('/(ActiveX|XMLHttpRequest)/i', $match, $matches))
            {
                return $this->getError(HTML_OK_NOTSUPPORTED, $match, $matches[1]);
            }

            if (preg_match('/(onsubmit|addEventListener|createEvent|attachEvent|captureEvents)/i', $match, $matches))
            {
                return $this->getError(HTML_OK_NOTSUPPORTED, $match, $matches[1]);
            }
        }

        if (preg_match_all('/function\s+(.*?)[\s\n]*\(/i', $match, $matches))
        {
            $err = array();
            foreach($matches[1] as $index => $m)
            {
                if(!preg_match('/^htmlO_K_/', $m)) {
                    $err[] = $m;
                }
            }
            if (count($err))
            {
                return $this->getError(HTML_OK_BADFNNAME, $match, $err);
            }
        }

        return $match;
    }

    function class_id_matches($match, $div = "")
    {
        if (preg_match('/id\s*=\s*\W?(.*)/i', $match, $matches))
        {
            if (isset($matches[1]) && !preg_match('/^htmlO_K_/', $matches[1]))
            {
                if ($div) $div = 'div';
                $value = rtrim($matches[1], ' ">');
                return $this->getError(HTML_OK_INVALIDID, $match, $value, $div);
            }
        }

        if (preg_match('/class\s*=\s*\W?(.*)/i', $match, $matches))
        {
            if (isset($matches[1]) && !preg_match('/^htmlO_K_/', $matches[1]))
            {
                if ($div) $div = 'div';
                $value = rtrim($matches[1], ' "');
                return $this->getError(HTML_OK_INVALIDID, $match, $value, $div);
            }
        }

        return false;
    }


    /**
    *
    *   level 2: no use of #id's, check all for class name errors, missing htmlO_K_
    */
    function style_matches($match, $level)
    {
        if (!isset($match)) return "";
        // medium: no use of id's
        if ($level == 2 && preg_match_all('/(#\w+)/', $match, $matches))
        {
            $err = array();
            foreach($matches[1] as $index => $m)
            {
                $err[] = $m;
            }
            if (count($err))
            {
                return $this->getError(HTML_OK_BADSELECTOR, $match, $err);
            }
        }

        if (preg_match_all('/\.(\w+)/', $match, $matches))
        {
            $err = array();
            foreach($matches[1] as $index => $m)
            {
                if (!preg_match('/^htmlO_K_/', $m))
                {
                    $err[] = $m;
                }
            }
            if (count($err))
            {
                return $this->getError(HTML_OK_BADCLASSNAME, $match, $err);
            }
        }
        return $match;
    }

    /**
    * Handle the match
    */
    function handle($match, $state, $pos, &$handler)
    {
        global $conf;
        $this->pos = $pos;
        if (!$conf['htmlok'])
        {
            $match = preg_replace ('/</', '&lt;', $match) . '<br />';
            return array($state, $match);
        }
        elseif($this->JS_ErrString) {
              echo $this->JS_ErrString;   
              $this->JS_ErrString = false;   
        }

        switch ($state)
        {
            case DOKU_LEXER_ENTER :
                return array($state, $match);
                break;

            case DOKU_LEXER_MATCHED :
                if (preg_match('/\/DIV\s*>/i', $match))
                {
                    $this->closed_div++;
                }
                if ($this->access_level == 1)
                {
                    $match = $this->rewrite_match_strict($match);
                } elseif ($this->access_level == 2)
                {
                    $match = $this->rewrite_match_medium($match);
                } elseif ($this->access_level == 3)
                {
                    $match = $this->rewrite_match_lax($match);
                } elseif ($this->access_level == 4)
                {
                    $match = $this->rewrite_match_su($match);
                }

                return array($state, $match);
                break;

            case DOKU_LEXER_UNMATCHED :
                if (preg_match('/<h(\d)>.*?<\/\\1>/i', $match, $matches))
                {
                    $match = preg_replace('/<h\d>/', '<h' . $matches[1] . '  style="border-bottom:0px;">', $match);

                }

                return array($state, $match);
                break;

            case DOKU_LEXER_EXIT :
                if ($this->open_div != $this->closed_div)
                {
                    if (!$this->divs_reported)
                    //    echo $this->get_JSErrString("<b>Mismatched Div Elements:</b> Open Divs: {$this->open_div}; Closed Divs:  {$this->closed_div}");
                    $this->divs_reported = true;
                }

                return array($state, $match);
                break;

            case DOKU_LEXER_SPECIAL :
                return array($state, $match);

                break;
        }
        return array();
    }

    /**
    * Create output
    */

    function render($mode, &$renderer, $data)
    {
        if ($mode == 'xhtml')
        {
            list($state, $match) = $data;
            switch ($state)
            {
                case DOKU_LEXER_ENTER :
                    break;
                case DOKU_LEXER_UNMATCHED :
                    $renderer->doc .= $match;
                    break;
                case DOKU_LEXER_MATCHED :
                    $renderer->doc .= $match;
                    break;
                case DOKU_LEXER_EXIT :

                    break;
            }

            return true;
        }

        return false;
    }

    /*
    * $htmlOK_ERRORS = array('Invalid ID', 'Element not supported', 'Internal Window Elements Not Supported');
    *
    */

    function getError($TYPE, $match, $problem_str, $xtra = "")
    {
        $error_string = "";

        if ($xtra) $xtra = "<{$xtra}>";
        if ($TYPE == HTML_OK_INVALIDID)
        {
            $xtra = ">{$xtra}";
        }

        $error_string = "{$xtra}<center><dl style='border-width:0px 0px 0px 0px; border-color: #ffffff; '><DT><DD><TABLE WIDTH='80%' cellpadding='10' border>"
         . "<TD  align='center' style='background-color:#eeeeee;font-weight:normal; font-size: 10pt; font-family:sans-serif;'>\n";

        if (is_string($problem_str))
        {
            $error_string .= compact_string(preg_replace ('/</', '&lt;', $match)) . '<br />';
            $problem_str = htmlspecialchars ($problem_str, ENT_QUOTES);
        }

        switch ($TYPE)
        {
            case HTML_OK_INVALIDID:
                $error_string .= $this->htmlOK_errors[$TYPE] . ": <b>{$problem_str}</b><br />";
                $js = $this->htmlOK_errors[$TYPE] . '.'
                 . "  htmlO_K_ prefix required for all ID's: <b>htmlO_K_{$problem_str}</b>";
                $error_string .= $this->get_JSErrString($js);
                break;

            case HTML_OK_NOTSUPPORTED:
                $error_string .= $this->htmlOK_errors[$TYPE] . ": <b>{$problem_str}</b><br />";
                $js = $this->htmlOK_errors[$TYPE] . " at current HTML access level:  <b>{$problem_str}</b>";
                $error_string .= $this->get_JSErrString($js);
                break;

            case HTML_OK_EXCLUDEDWINDOW:
                $error_string .= $this->htmlOK_errors[$TYPE] . ": <b>{$problem_str}</b><br />";
                $js = $this->htmlOK_errors[$TYPE] . ". External files cannot be included in wiki documents: <b>{$problem_str}</b>. ";
                $error_string .= $this->get_JSErrString($js);
                break;

            case HTML_OK_BADCLASSNAME:
            case HTML_OK_BADSELECTOR:
            case HTML_OK_BADFNNAME:
                $error_string .= $this->htmlOK_errors[$TYPE] . ':<BR />';
                $name_errs = "";
                foreach($problem_str as $p)
                {
                    $p = htmlspecialchars ($p, ENT_QUOTES);
                    $name_errs .= " {$p}, ";
                }

                $name_errs = rtrim($name_errs, ' ,');
                $name_errs = "<b>$name_errs</b>";
                $error_string .= $name_errs;
                if ($TYPE == HTML_OK_BADCLASSNAME)
                {
                    $js = $this->htmlOK_errors[$TYPE] . ". htmlO_K_ prefix required for class names: <b>{$name_errs}</b>. ";
                } elseif ($TYPE == HTML_OK_BADFNNAME)
                {
                    $js = $this->htmlOK_errors[$TYPE] . ". htmlO_K_ prefix required for function names:<BR />&nbsp;&nbsp;&nbsp;&nbsp;<b>{$name_errs}</b>. ";
                }
                else
                {
                    $js = $this->htmlOK_errors[$TYPE] . "  at current HTML access level:   <b>{$name_errs}</b>. ";
                }
                $error_string .= $this->get_JSErrString($js);
                break;

            default:
                break;
        }

        return $error_string . '</TABLE></dl></center><br />';
    }

    /**
    * Constructs the Javascript Error String for outut in the Errors window
    */
    function get_JSErrString($msg)
    {
        global $INFO;
        $msg = trim($msg);
        if (!isset($msg) || empty($msg)) return "";

        $msg = '<script language="javascript">htmlOK_ERRORS_ARRAY[' . $this->msgs_inx . ']="' . $msg . '"; </script>' . "\n";
        $this->msgs_inx++;

        return $msg;
    }
}

function compact_string($string_x)
{
    if ($len = strlen($string_x) > 400)
    {

        $string_a = substr($string_x, 0, 200);
        $string_b = substr($string_x, -200);
        $string_x = $string_a . '<br /><b>. . .</b><br />' . $string_b;
    }

    return $string_x;
}

?>
