<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Head {

    public function __construct(){
        add_action('wp_head', [$this,'meta'], 1);
    }

    public function meta(){
        if (!is_singular()) return;
        $pid = get_the_ID();
        $canonical = get_post_meta($pid, '_cai_canonical', true);
        $noindex = get_post_meta($pid, '_cai_noindex', true);
        if ($canonical){
            echo '<link rel="canonical" href="'.esc_url($canonical).'" />'."\n";
        }
        if ($noindex){
            echo '<meta name="robots" content="noindex,follow" />'."\n";
        }
    }
}
