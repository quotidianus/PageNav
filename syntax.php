<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 * @author     Quotidianus <pagenav@b67.net>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/search.php');

class syntax_plugin_pagenav extends DokuWiki_Syntax_Plugin {
    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }
    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }
    /**
     * Where to sort in?
     */
    function getSort(){
        return 155;
    }
    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\[<\d*>(?: [^\]]+)?\]',$mode,'plugin_pagenav');
    }
    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        //split the match in it's parts
        $match = substr($match,1,-1);
        list($mode,$glob)    = explode(' ',$match,2);
        $mode = (int) substr($mode,1,-1);
        if(!$mode) $mode = 2+4+8;
        return array(strtolower(trim($glob)),$mode);
    }
    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        global $conf;
        if($format != 'xhtml') return false;
        list($glob,$mode) = $data;
        $glob = preg_quote($glob,'/');
        // get all files in current namespace
        static $list = null; // static to reuse the array for multiple calls.
        $id = cleanID(getID());
        if(is_null($list)){
           $list = array();
           $nsdir = str_replace(':','/',getNS($id));
           $opts = array('listdirs'  => false,
                         'listfiles' => true,
                         'pagesonly' => true,
                         'depth'     => 1,
                         'skipacl'   => false, // to check for read right
                         'sneakyacl' => true,
                         'showhidden'=>false,
                         );
           search($list,$conf['datadir'],'search_universal',$opts,$nsdir);
        }
        // get all namespaces in the superior namespace
        static $listdir = null; // static to reuse the array for multiple calls.
        if(is_null($listdir)){
           $listdir = array();
           $supnsdir = substr($nsdir,0,strripos($nsdir,'/'));
           $opts = array('listdirs'  => true,
                         'listfiles' => false,
                         'depth'     => 1,
                         'skipacl'   => false, // to check for read right
                         'sneakyacl' => true,
                         'showhidden'=>false,
                         );
           search($listdir,$conf['datadir'],'search_universal',$opts,$supnsdir);
        }
        // find the start page
        $exists = false;
        $start = getNS($id).':';
        // following function sets $start to start page id and
        // $exists to true of false if it exists or not.
        resolve_pageid('',$start,$exists);
        $cnt = count($list);
        if($cnt < 2) return true; // there are no other doc in this namespace
        $cntdir = count($listdir);
        // In case of only one namespace on the $supns, we can use the [<8>] syntax
        $first = '';
        $prev  = '';
        $last  = '';
        $next  = '';
        $self  = false;
        if($id != $start) { // if we are not on a start page
          // we go through the list only once, handling all options and globs
          // only for the 'last' command the whole list is iterated
          for($i=0; $i < $cnt; $i++){
            $listid = $list[$i]['id'];
            if($listid == $id){
              $self = true;
            } else {
              if($glob && !preg_match('/'.$glob.'/',noNS($listid))) {
                continue;
              }
              // we don't link start page
              if($listid == $start) {
                continue;
              }
              if($self){
                // we're after the current id
                if(!$next){
                    $next = $listid;
                }
                $last = $listid;
                } else {
                  // we're before the current id
                  if(!$first){
                    $first = $listid;
                  }
                  $prev = $listid;
                }
            }
          }
        } else {  // if we are on a start page
          for($i=0; $i < $cntdir; $i++){
            if($listdir[$i]['id'] == substr($id,0,strripos($id,':'))){
              $self = true;
            } else {
              if($glob && !preg_match('/'.$glob.'/',noNS($listdir[$i]['id']))) {
                continue;
                }
                if($self) {
                    // we're after the current id
                    if(!$next){
                      $next = $listdir[$i]['id'].':';
                    }
                    $last = $listdir[$i]['id'].':';
                } else {
                  // we're before the current id
                  if(!$first){
                    $first = $listdir[$i]['id'].':';
                  }
                  $prev = $listdir[$i]['id'].':';
                }
              }
            }
        $start = $supns.'/';
        } // end if/else on start page
        $renderer->doc .= '<p class="plugin__pagenav">';
        if($mode & 4) $renderer->doc .= $this->_buildImgLink($first,'first');
        if($mode & 2) $renderer->doc .= $this->_buildImgLink($prev,'prev');
        if($mode & 8) $renderer->doc .= $this->_buildImgLink($start,'up');
        if($mode & 2) $renderer->doc .= $this->_buildImgLink($next,'next');
        if($mode & 4) $renderer->doc .= $this->_buildImgLink($last,'last');
        $renderer->doc .= '</p>';
        return true;
    }

    function _buildImgLink($page, $cmd) {
        if (!$page){
            return '<img src="'.DOKU_BASE.'lib/plugins/pagenav/img/'.$cmd.'-off.png" alt="" />';
        }
        $title = p_get_first_heading($page);
        return '<a href="'.wl($page).'" title="'.$this->getLang($cmd).': '.hsc($title).'" class="wikilink1"><img src="'.DOKU_BASE.'lib/plugins/pagenav/img/'.$cmd.'.png" alt="'.$this->getLang($cmd).'" /></a>';
    }
}
