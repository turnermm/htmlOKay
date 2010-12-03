<pre>
<?php

if($argc > 1) {
  echo $argv[1], "\n";
  get_data($argv[1]);
}

else {
  foreach (glob("*") as $filename) {
       get_data($filename) ;
  }
}
echo "</pre>\n";
exit;


function get_data($filename) {

     if(preg_match('/.*?\.php/', $filename)) return;
     if(is_dir($filename)) return;
     echo "$filename size " . filesize($filename) . "\n";

     $inf_str = file_get_contents ($filename);
     $inf = unserialize($inf_str);
     print_r($inf);


}
?>
