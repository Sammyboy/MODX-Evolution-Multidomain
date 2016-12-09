<?php
/**
 * Multidomain DocumentParser Extended - for MODX Evolution
 * @category  plugin
 * @version   1.0.2
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @author    Marek Srejma (sam_web@yahoo.de)
 */

class DocumentParser_Extended extends DocumentParser {

  var $sites = array(); // storage for all sites data
  var $site;            // current site
  
  private function getTVbyID($id, $tv_name) {
    $tv_arr = $this->getTemplateVar($tv_name,'*',$id);
    return $tv_arr['value'];
  }
  
  function getSites() {
    $xml_file = 'assets/files/domains.xml';
    $err_msg  = '';
    $xml      = @simplexml_load_file( MODX_BASE_PATH . $xml_file, 'SimpleXMLElement', LIBXML_NOCDATA );
    if ($xml !== false && isset($xml->domain)) {
      foreach($xml->domain as $d ) {
        if (isset($d->name)) {
          $domain = new stdClass();
          $domain->name              = (string) $d->name;
          $domain->protocol          = (string) $d->protocol;
          $domain->site_id           = (int)    $d->site_id;
          $domain->site_start        = (int)    $d->site_start;
          $domain->error_page        = (int)    $d->error_page;
          $domain->offline_page      = (int)    $d->offline_page;
          $domain->unauthorized_page = (int)    $d->unauthorized_page;
          $domain->page_not_found    = (int)    $d->page_not_found;
          $domain->keyword           = (string) $d->keyword;
          
          $domain->is_online         = !(bool)  $this->getTVbyID($domain->site_id, 'hidemenu');
          $domain->alias             = (string) $this->getTVbyID($domain->site_id, 'alias');

          $this->sites['alias'][$domain->alias] = $domain;
          
          // set current site
          if ( $domain->name === $_SERVER['HTTP_HOST'] ) {
            $this->site = $domain;
          }
          unset($domain);
        } else {
          $err_msg.= 'ERROR: Failed reading '.$xml_file.'!<br />';
        }
      }
    } else { 
      $err_msg.= 'ERROR: Failed to open file '.$xml_file.'!<br />';
    }
    
    if ($err_msg !== '') { 
      print_r($err_msg);
    }
    
    $this->setPlaceholder('domainkey', $this->site->keyword);
    $this->setPlaceholder('is_online', $this->site->is_online);
    $this->setPlaceholder('domain', $this->site->name);
    return;
  }

  function getDocumentIdentifier($method) {
    $docIdentifier = (($this->site->is_online || $_SESSION['mgrRole']) ? $this->site->site_start : $this->site->offline_page);
    switch ($method) {
      case 'alias' :
        $docIdentifier= $this->db->escape($_REQUEST['q']);
        if ($this->config['use_alias_path'] == 1) {
          // check if domain is marked online:
          $docIdentifier = $this->site->alias . '/' . 
            (($this->site->is_online || $_SESSION['mgrRole']) ? $docIdentifier : $this->site->offline_page);
        }
        break;
      case 'id' :
        if (!is_numeric($_REQUEST['id'])) {
          $this->sendErrorPage();
        } else {
          $docIdentifier = intval($_REQUEST['id']);
        }
        break;
    }
    return $docIdentifier;
  }


  function sendErrorPage() {
    $this->invokeEvent('OnPageNotFound');
    $this->sendForward(
      $this->site->error_page,
      'HTTP/1.0 404 Not Found'
    );
    exit();
  }


  function sendUnauthorizedPage() {
    $_REQUEST['refurl'] = $this->documentIdentifier;
    $this->invokeEvent('OnPageUnauthorized');
    if ($this->site->unauthorized_page) {
      $unauthorizedPage= $this->site->unauthorized_page;
    } elseif ($this->site->error_page) {
      $unauthorizedPage = $this->site->error_page;
    } else {
      $unauthorizedPage= $this->site->site_start;
    }
    $this->sendForward($unauthorizedPage, 'HTTP/1.1 401 Unauthorized');
    exit();
  }


  function makeUrl($id, $alias= '', $args= '', $scheme= '') {
    $url  = parent::makeUrl($id, $alias, $args, $scheme);
    if (strpos($url,'/') === 0) { // "/domainalias/folder/document.html"
      $url = substr($url,1);
    }
    $parts = explode('/', $url);
    if ($parts[0] === $this->site->alias) {
      unset($parts[0]);
      $url = $this->config['base_url'] . implode('/',$parts);
    }
    if ($other_site = $this->sites['alias'][$parts[0]] ) {
      $parts[0] = $other_site->protocol . $other_site->name;
      $url = implode('/',$parts);
    }
    return str_replace($this->site->alias."/","",$url);
  }


  function _makeUrl($id) {
    return $this->makeUrl($id[1]);
  }


  function rewriteUrls($documentSource) {
    $documentSource = preg_replace_callback(
      '!\[\~([0-9]+)\~\]!',
      array($this, '_makeUrl'),
      $documentSource
    );
    return $documentSource; 
  }
}
