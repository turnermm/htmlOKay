<?php

define('ACCESS_DIR',realpath(dirname(__FILE__).'/').'/conf/access/');
$directories = array();
$path = rawurldecode($_REQUEST['path']);

$abs_path = rawurldecode($_REQUEST['abs_path']);

$wiki_home = $path;

function init() {
  global $wiki_home;
  global $directories;

    $wiki_home = rtrim($wiki_home, '/');
    $directories[$wiki_home]['name'] = 'root';
    $directories[$wiki_home]['namespace'] = 'root';
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
  global $directories;

  $name = basename($path);
  if($name == '.' || $name == '..') return;
 
  if(is_dir($path)){
     $directories[$path] = array();     
     $directories[$path]['name'] = $name;
     $directories[$path]['namespace'] = get_namespace($path);
     $directories[$path]['files'] = array();
  }
  elseif(is_file($path)){
     $dir = dirname($path); 
     $directories[$dir]['files'][] = $name;
  }


}


function get_file_options($dir) {
 global $directories;

 $options = array();

  $files = $directories[$dir]['files'];

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
        global $path;
        $access_dir = rtrim($access_dir, '/');
        $file = $access_dir . '/' . $namespace;

       // echo  "\n\nOriginal access file: $file\n";
        if (file_exists($file))
        {
        //    echo "Tried Original access file: $file\n";
            return $file;
        }

        $dirs = explode('#', $namespace);
        foreach($dirs as $dir)
        {
            array_pop($dirs);
            $new_dir = implode('#', $dirs);
            $file = $access_dir . '/' . $new_dir;
          //  echo "Tried access file: $file\n";
            if (file_exists($file) && is_file($file))   //  && is_dir($path . $new_dir)
            {
              //  echo "File exists: $file\n";
                return $file;
            }
        }
        return $access_dir . '/' . $namespace;
    }




init();

traverseDirTree($path,'outputpath','outputpath');


$options = get_file_options($abs_path);


foreach($options as $option) { 
   echo "$option|";
}


$namespace_descriptor = $directories[$abs_path]['namespace'];
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
  flush();
exit;
echo '</SELECT>';

?>

