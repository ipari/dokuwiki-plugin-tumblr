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
        'desc'    => 'Embed tumblr into DokuWiki',
        'url'     => 'http://www.dokuwiki.org/plugin:tumblr'
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
        $params = explode('&',$match);

        $data = array();
        foreach($params as $param) {
            $param = explode('=',$param);
            $data[$param[0]] = $param[1];
        }
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
        $renderer->doc .= $this->tumblr($data);
        return true;
    }

    public $tumblr_url = false;

    function load_rss($url) {
        global $conf;
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
                'tags'          => $this->tags($item->get_categories())
            );
        }
        return $posts;
    }

    function page_exists($url) {
        require_once(DOKU_INC.'inc/FeedParser.php');
        $feed  = new FeedParser();
        $feed->set_feed_url($url);
        $feed->init();
        return $feed->get_item_quantity();
    }

    function get_url_parameters() {
        $parameters = array(
            'page'      => $_REQUEST['page'] ? $_REQUEST['page'] : 0,
            'post'      => $_REQUEST['post'],
            'search'    => $_REQUEST['search'],
            'tagged'    => $_REQUEST['tagged']
        );
        return $parameters;
    }

    function get_url($page=false) {
        $url = $this->tumblr_url;
        $params = $this->get_url_parameters();
        if ($page !== false) {
            $params['page'] = $page;
        }
        // return rss url by URL parameter
        if ($params['search']) {
            $new_url = $url.'/search/'.$params['search'].'/page/'.$params['page'].'/rss';
        } elseif ($params['tagged']) {
            $new_url = $url.'/tagged/'.$params['tagged'].'/page/'.$params['page'].'/rss';
        } elseif ($params['post']) {
            $new_url = $url.'/'.$params['post'].'/rss';
        } else {
            $new_url = $url.'/page/'.$params['page'].'/rss';
        }
        return $new_url;
    }

    function tags($tags) {
        // SimplePie Object to Array
        if(!$tags) {
            return false;
        }
        $new_tags = array();
        foreach($tags as $tag) {
            $new_tags[] = $tag->term;
        }
        return $new_tags;
    }

    function make_link($url, $ID, $inner=false) {
        // tumblr post url format is http://oo.tumblr.com/post/123456789012
        $post_id = end(explode('/',$url));
        $html .= '<a href="'.wl($ID, 'post='.$post_id).'">';
        $html .= $inner ? $inner : $url;
        $html .= '</a>';
        return $html;
    }

    function print_tags($tags, $ID) {
        if(!$tags) {
            return false;
        }
        $html .= '<ul>';
        foreach($tags as $tag) {
            $html .= '<li>';
            $html .= '<a href="'.wl($ID, 'tagged='.str_replace(" ", "-", $tag)).'">'.$tag.'</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    function get_nav_query($next) {
        $params = $this->get_url_parameters();
        // page 1 is not exists on tumblr
        if ($next) {
            $params['page'] = ($params['page'] == 0) ? 2 : $params['page'] + 1;
        } else {
            $params['page'] = ($params['page'] == 2) ? 0 : $params['page'] - 1;
        }

        // check url exists
        $url = $this->get_url($params['page']);
        if ($params['page'] >= 0 && $this->page_exists($url)) {
            return http_build_query($params, '', '&amp;');
        } else {
            return false;
        }
    }

    function tumblr($options) {
        global $lang;
        global $ID;
        $this->tumblr_url = $options['url'];

        $html = '';
        $url = $this->get_url();
        $pID = $options['target'] ? $options['target'] : $ID;
        $posts = $this->load_rss($url);
        if (!$posts) {
            $html .= '<div class="tumblr-post">';
            $html .= '<h2>'.$this->getLang('page_not_exists').'</h2>';
            $html .= '<p>';
            $html .= '<a href="javascript:history.go(-1)">'.$this->getLang('back_to_list').'</a><br />';
            $html .= $this->getLang('err_shown_when');
            $html .= '</p>';
            $html .= '<ul>';
            $html .= '<li>'.$this->getLang('err_no_result').'</li>';
            $html .= '<li>'.$this->getLang('err_not_accessible').'</li>';
            $html .= '<li>'.$this->getLang('err_wrong_url').'</li>';
            $html .= '</ul>';
            $html .= '</div>';
            return $html;
        }
        // render page
        $html .= '<div class="tumblr-container">';
        if ($options['type'] != 'list') {
            foreach($posts as $post) {
                $html .= '<div class="tumblr-post">';
                $html .= '<h2>'.$this->make_link($post['permalink'], $pID, $post['title']).'</h2>';
                $html .= '<div>'.$post['description'].'</div>';

                // post meta
                $html .= '<div class="tumblr-meta">';
                $html .= '<dl>';
                $html .= '<dt>DATE</dt>';
                $html .= '<dd>'.$post['date'].'</dd>';
                $html .= '<dt>PERMALINK</dt>';
                $html .= '<dd>'.$this->make_link($post['permalink'], $pID).'</dd>';
                $html .= '</dl>';

                // tags
                if ($post['tags']) {
                    $html .= '<div class="tumblr-tags">';
                    $html .= $this->print_tags($post['tags'], $pID);
                    $html .= '</div>';
                }
                $html .= '</div>';
                $html .= '</div>'; // end of tumblr-post
            }
        } else {
            $html .= '<div class="tumblr-list">';
            $html .= '<table>';
            foreach($posts as $post) {
                $html .= '<tr>';
                $html .= '<td class="post-date"><time>'.$post['date'].'</time></td>';
                $html .= '<td class="post-title">';
                $html .= $this->make_link($post['permalink'], $pID, $post['title']);
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        // page navigation
        $html .= '<div class="tumblr-nav">';
        if ($_REQUEST['post']) {
            $html .= '<div class="tumblr-btn-left" style="visibility:hidden">a</div>';
            // go back to list
            $html .= '<div class="tumblr-btn-right">';
            $html .= '<a href="javascript:history.go(-1)">'.$this->getLang('back_to_list').'</a>';
            $html .= '</div>';
        } else {
            $prev_query = $this->get_nav_query(false);
            $next_query = $this->get_nav_query(true);
            // left button
            $html .= '<div class="tumblr-btn-left"';
            if (!$prev_query) { $html .= ' style="visibility:hidden"'; }
            $html .= '>';
            $html .= '<a href="'.wl($ID, $prev_query).'">'.$this->getLang('newer_posts').'</a>';
            $html .= '</div>';
            // right button
            $html .= '<div class="tumblr-btn-right"';
            if (!$next_query) { $html .= ' style="visibility:hidden"'; }
            $html .= '>';
            $html .= '<a href="'.wl($ID, $next_query).'">'.$this->getLang('older_posts').'</a>';
            $html .= '</div>';
        }
        // search form
        if (!$options['search']) {
            $html .= '<div class="tumblr-search">';
            $html .= '<form method="get">';
            $html .= '<input type="text" name="search">';
            $html .= '<button type="submit">'.$lang['btn_search'].'</button>';
            $html .= '</form>';
            $html .= '</div>'; // end of tumblr-search
        }
        $html .= '<div class="tumblr-clear"></div>';
        $html .= '</div>'; // end of tumblr-nav
        $html .= '</div>'; // end of tumblr-container
        return $html;
    }
}

// vim:ts=4:sw=4:et:
