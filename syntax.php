<?php
/**
 * DokuWiki Plugin tumblr (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Lee Kwangyoung <ipari@leaflette.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_tumblr extends DokuWiki_Syntax_Plugin {
    function getInfo() {
        return array(
        'author'  => 'Lee, Kwangyoung',
        'email'   => 'ipari@leaflette.com',
        'date'    => '2011-04-04',
        'name'    => 'Tumblr Plugin',
        'desc'    => 'Read rss from Tumblr',
        'url'     => 'http://leaflette.com'
        );
    }

    /**
     * @return string Syntax mode type
     */
    function getType() {
        return 'substition';
    }
    /**
     * @return string Paragraph type
     */
    function getPType() {
        return 'normal';
    }
    /**
     * https://www.dokuwiki.org/devel:parser:getsort_list
     *
     * @return int Sort order - Low numbers go before high numbers
     */
    function getSort() {
        return 315;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{tumblr>[^}]*\}\}',$mode,'plugin_tumblr');
    }

//    function postConnect() {
//        $this->Lexer->addExitPattern('</FIXME>','plugin_tumblr');
//    }

    /**
     * Handle matches of the tumblr syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    function handle($match, $state, $pos, Doku_Handler &$handler){
        $match = substr($match,9,-2); //strip '{{tumblr>' and '}}'
        $params = explode(',',$match);

        $data = array();
        $data['url'] = $params[0];
        // more options here

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    function render($mode, Doku_Renderer &$renderer, $data) {
        if($mode != 'xhtml') return false;

        // prevent caching to show lastest posts
        $renderer->info['cache'] = false;
        $renderer->doc .= $this->_tumblr($data);
        return true;
    }

    function _loadRSS($url) {
        require_once(DOKU_INC.'inc/FeedParser.php');
        $feed = new FeedParser();
        $feed->set_feed_url($url);
        $rc = $feed->init();
        if (!$rc) {
            return false;
        }

        $posts = array();
        foreach($feed->get_items() as $item) {
            $posts[] = array(
                'title'         => $item->get_title(),
                'date'          => $item->get_local_date($conf['dformat']),
                'permalink'     => $item->get_permalink(),
                'description'   => $item->get_description(),
                'tags'          => $this->_tags($item->get_categories())
            );
        }
        return $posts;
    }

    function _getURL($url) {
        // return rss url by URL parameter
        $page = $_REQUEST['page'];
        $post = $_REQUEST['post'];
        $search = $_REQUEST['search'];
        $tagged = $_REQUEST['tagged'];

        if (!$page) {
            $page = 0;
        }
        if ($search) {
            $new_url = $url.'/search/'.$search.'/page/'.$page.'/rss';
        } elseif ($tagged) {
            $new_url = $url.'/tagged/'.$tagged.'/page/'.$page.'/rss';
        } elseif ($post) {
            $new_url = $url.'/'.$post.'/rss';
        } else {
            $new_url = $url.'/page/'.$page.'/rss';
        }
        return $new_url;
    }

    function _tags($tags) {
        // SimplePie Object to Array
        $new_tags = array();
        foreach($tags as $tag) {
            $new_tags[] = $tag->term;
        }
        return $new_tags;
    }

    function _tumblr($data) {
        $ret = '';
        $url = $this->_getURL($data['url']);
        $posts = $this->_loadRss($url);
        if (!$posts) {
            print('RSS load failed');
            return false;
        }
        foreach($posts as $post) {
            // Do something
        }
        return $posts;
    }
}

// vim:ts=4:sw=4:et:
