<?php
/**
 * Multidomain check index - for MODX Evolution
 * @category  plugin
 * @version   1.0
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @author    Marek Srejma (sam_web@yahoo.de)
 */

if (! function_exists('ExtendIndexFile')) {
  function ExtendIndexFile($params) {
    global $modx;
    // name of file storing version information
      $version_file   =   trim($params['version_file']);
    $indexfile_path  =  trim($params['index_path']);
    $search4lines  =  $params['search'];
    $replace2lines  =  $params['replace'];

    $check_version  =   false;
    $check_sum      =   false;
    $version_url    =   MODX_BASE_PATH . $version_file;
    // path of file to check
    $index_file     =   MODX_BASE_PATH . $indexfile_path . 'index.php';

    $last_version   =   (string)    file($version_url);
    $akt_vers       =   md5_file($index_file);

    // check version numbers
    if (!$last_version || ($last_version != $akt_vers)) {
      $check_version  =   true;
      $check_sum      =   true;
    }

    if ($check_version) {
      // open index.php
      if ($pt = fopen($index_file, 'r')) {
        // read index.php file
        $file_content   =   fread($pt, filesize($index_file));
        fclose($pt);

        // search and replace
        $out_content    =   str_replace($search4lines,
          "\n// ## changed by multidomain-plugin on ".date(DATE_RFC822).": ##\n".
          $replace2lines."\n// ## end of changes ##\n", $file_content);

        if ($file_content !== $out_content) {
          // rename old filename
          if (rename($index_file, $index_file . ".bak")) {
            // create new index.php
            if ($pt = fopen($index_file, 'w')) {
              fwrite($pt, $out_content, strlen($out_content));
              fclose($pt);
              $check_sum = true;
            } else {
              // error message if file could not be saved
              print_r("ERROR: Failed to write " . $index_file . "!\n\n");
              // rerename
              rename($index_file . ".bak", $index_file);
            }
            } else {
              // error message if original file could not be renamed
              print_r("ERROR: Faild to update " . $index_file .
                ". Could not be renamed!\n\n");
            }
        } else {
          $check_version = false;
        }
      } else {
        // error message if index.php could not be opened
        print_r("ERROR: Failed to read " . $index_file . "!\n\n");
      }
    }
    if ($check_sum) {
      // create new md5sum-checksum
      $akt_vers       =   md5_file($index_file);
      // and save it
      if ($pt = fopen($version_url, 'w')) {
        fwrite($pt, $akt_vers, strlen($akt_vers));
        fclose($pt);
          // check if there were changes
        if ($last_version !== $akt_vers) {
          $check_version = true;
        }
      } else {
        // error message if checksum file could not be written
        print_r("ERROR: Failed to write " . $version_url . "!\n\n\n");
      }
    }
    return;
  } // end function
} // end if

// execute
ExtendIndexFile(array(
  'index_path'    => '',
  'version_file'  => 'assets/files/.index.php.md5',
  
  'search'        => "\$modx = new DocumentParser;",
  
  'replace'       => "include_once MODX_BASE_PATH.'assets/libs/DocumentParser_Extended.php';\n".
                     "\$modx = new DocumentParser_Extended();\n".
                     "\$modx->getSites();"
));

ExtendIndexFile(array(
  'index_path'    => 'manager/',
  'version_file'  => 'assets/files/.manager_index.php.md5',
  
  'search'        => "\$modx = new DocumentParser;\n".
                     "\$modx->loadExtension(\"ManagerAPI\");\n".
                     "\$modx->getSettings();",
  
  'replace'       => "include_once MODX_BASE_PATH.'assets/libs/DocumentParser_Extended.php';\n".
                     "\$modx  = new DocumentParser_Extended();\n".
                     "\$modx->loadExtension(\"ManagerAPI\");\n".
                     "\$modx->getSettings();\n".
                     "\$modx->getSites();"
));
?>
