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
        $data['page'] = $params[1];
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
        $renderer->doc .= $this->tumblr($data);
        return true;
    }

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

    function get_url_parameters() {
        $parameters = array(
            'page'      => $_REQUEST['page'] ? $_REQUEST['page'] : 0,
            'post'      => $_REQUEST['post'],
            'search'    => $_REQUEST['search'],
            'tagged'    => $_REQUEST['tagged']
        );
        return $parameters;
    }

    function get_url($url, $params) {
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

    function tumblr($options) {
        global $ID;

        $html = '';
        $params = $this->get_url_parameters();
        $url = $this->get_url($options['url'], $params);
        $pID = $options['page'] ? $options['page'] : $ID;
        $posts = $this->load_rss($url);
        if (!$posts) {
            print('RSS load failed');
            return false;
        }
        // render page
        $html .= '<div class="tumblr-container">';
        foreach($posts as $post) {
            $html .= '<div class="tumblr-post">';
            $html .= '<h2>'.$post['title'].'</h2>';

            $html .= '<div>'.$post['description'].'</div>';
            $html .= '<div class="tumblr-info">';
            $html .= '<dl>';
            $html .= '<dt>DATE</dt><dd>'.$post['date'].'</dd>';
            $html .= '<dt>PERMALINK</dt><dd>'.$this->make_link($post['permalink'], $pID).'</dd>';
            $html .= '</dl>';
            if ($post['tags']) {
                $html .= '<div class="tumblr-tags">'.$this->print_tags($post['tags'], $pID).'</div>';
            }
            $html .= '</div>';
            $html .= '</div>'; // end of tumblr-post
        }
        $html .= '<div class="tumblr-nav">';
        if (!$options['search']) {
            $html .= '<div class="tumblr-search">';
            $html .= '<form method="get">';
            $html .= '<input type="text" name="search">';
            $html .= '<button type="submit">검색</button>';
            $html .= '</form>';
            $html .= '</div>'; // end of tumblr-search
        }
        $html .= '</div>'; // end of tumblr-menu
        $html .= '</div>'; // end of tumblr-container
        return $html;
    }
}

// vim:ts=4:sw=4:et:
