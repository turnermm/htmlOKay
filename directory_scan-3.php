<?php
global $wiki_home,$htmlokay_directories,$htmlokay_abs_path,$htmlokay_path;
if (!defined('ACCESS_DIR')) define('ACCESS_DIR',realpath(dirname(__FILE__).'/').'/conf/access/');

function init() {
  global $wiki_home, $htmlokay_path,$htmlokay_abs_path;
  global $htmlokay_directories;

    $htmlokay_directories = array();
    $htmlokay_path = rawurldecode($_REQUEST['path']);
    $htmlokay_abs_path = rawurldecode($_REQUEST['abs_path']);
    $wiki_home = $htmlokay_path;

    $wiki_home = rtrim($wiki_home, '/');
    $htmlokay_directories[$wiki_home]['name'] = 'root';
    $htmlokay_directories[$wiki_home]['namespace'] = 'root';
    $wiki_home = ltrim($wiki_home, '/');
    $wiki_home = preg_quote($wiki_home, '/');
}


function traverseDirTree($base,$fileFunc,$dirFunc=null,$afterDirFunc=null){
  $subdirectories=opendir($base);
  while (($subdirectory=readdir($subdirectories))!==false){
    $path=$base.$subdirectory;
    if (is_file($path)){
      if ($fileFunc!==null) $fileFunc($path);
    }else{
      if ($dirFunc!==null) $dirFunc($path);
      if (($subdirectory!='.') && ($subdirectory!='..')){
        traverseDirTree($path.'/',$fileFunc,$dirFunc,$afterDirFunc);
      }
      if ($afterDirFunc!==null) $afterDirFunc($path);
    }
  }
           closedir($subdirectories); 
}


function get_namespace($path) {
  global $wiki_home;
     $namespace_string =  trim($path, '/');
     $namespace_string = preg_replace('/^' . $wiki_home . '/', "", $namespace_string); 
     $namespace_string = preg_replace('%/%',':',$namespace_string);
     return ltrim($namespace_string, ':');

}

function outputPath($path){
  global $htmlokay_directories;

  $name = basename($path);
  if($name == '.' || $name == '..') return;
 
  if(is_dir($path)){
     $htmlokay_directories[$path] = array();     
     $htmlokay_directories[$path]['name'] = $name;
     $htmlokay_directories[$path]['namespace'] = get_namespace($path);
     $htmlokay_directories[$path]['files'] = array();
  }
  elseif(is_file($path)){
     $dir = dirname($path); 
     $htmlokay_directories[$dir]['files'][] = $name;
  }
}


function get_file_options($dir) {
 global $htmlokay_directories;

     $options = array();
      $files = $htmlokay_directories[$dir]['files'];

    // currently these two options are the same -- may change in future
      if(count($files) == 0) {
           $options[] =  'No files found:none:selected';
             $options[] = 'ALL:all' ;
      }
      else {
             $options[] ='No Files Selected:none:selected';
             $options[] = 'ALL:all' ;
      }
       foreach($files as $file) {   
       $options[] = "$file:$file";
       }


    return $options;
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
        global $htmlokay_path;
        $access_dir = rtrim($access_dir, '/');
        $file = $access_dir . '/' . $namespace;       
        if (file_exists($file))
        {        
            return $file;
        }

        $dirs = explode('#', $namespace);
        foreach($dirs as $dir)
        {
            array_pop($dirs);
            $new_dir = implode('#', $dirs);
            $file = $access_dir . '/' . $new_dir;         
            if (file_exists($file) && is_file($file))  
            {         
                return $file;
            }
        }
        return $access_dir . '/' . $namespace;
    }

 function access_process() {  
     global $wiki_home,$htmlokay_directories,$htmlokay_abs_path,$htmlokay_path;
     init();
     traverseDirTree($htmlokay_path,'outputpath','outputpath');
    $options = get_file_options($htmlokay_abs_path);
    foreach($options as $option) { 
       echo "$option|";
    }
  }

function access_data() {
    global $wiki_home,$htmlokay_directories,$htmlokay_abs_path,$htmlokay_path;  
    $namespace_descriptor = $htmlokay_directories[$htmlokay_abs_path]['namespace'];
    if($namespace_descriptor == 'root')
       $namespace_descriptor = '_ROOT_';
    else {
     $namespace_descriptor = preg_replace('/\:/','#', $namespace_descriptor);
    }
    $data = array();
    $access_file =   get_access_file(ACCESS_DIR, $namespace_descriptor);  
    if(file_exists($access_file)) {
     $str = file_get_contents ($access_file);
     $data = unserialize($str);      
    }
    
    $output=""; 
    foreach($data as $name=>$val) {
        if(is_array($val)) { 
           $output.= "$name=>("; 
           foreach($val as $key=>$item) {
               if(is_string($key)) {
                     $key = $key . ':';
               }
               else $key = "";
               $output .= "{$key}$item,";
           }
           $output = rtrim($output, ',');
           $output .= ');;'; 
         } 
    }
   $output =rtrim($output, ';');
   echo '%%' .$output;
 } 

  access_process();
  access_data() ;
  flush();

?>

