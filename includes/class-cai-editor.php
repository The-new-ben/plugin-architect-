<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Editor {
    public function __construct(){
        add_action('admin_footer-post.php', [$this,'footer_js']);
        add_action('admin_footer-post-new.php', [$this,'footer_js']);
    }
    public function footer_js(){
        ?>
        <script>
        jQuery(function($){
            $('#cai-generate-meta').on('click', function(e){
                e.preventDefault();
                const postId = $(this).data('post');
                $(this).prop('disabled', true).text('יוצר...');
                $.post(ajaxurl, { action:'cai_generate_meta', post_id: postId }, function(resp){
                    if(resp && resp.success && resp.data){
                        if(resp.data.title) $('input[name="cai_meta_title"]').val(resp.data.title);
                        if(resp.data.description) $('textarea[name="cai_meta_desc"]').val(resp.data.description);
                    } else {
                        alert('שגיאה בתשובת ה-AI');
                    }
                    $('#cai-generate-meta').prop('disabled', false).text('צור עם AI');
                });
            });
            $('#cai-generate-content').on('click', function(e){
                e.preventDefault();
                const topic = $('#cai-topic-input').val();
                if(!topic){ alert('נא להזין נושא'); return; }
                const $btn = $(this);
                $btn.prop('disabled', true).text('יוצר תוכן...');
                $.post(ajaxurl, { action:'cai_generate_from_topic', topic: topic }, function(resp){
                    if(resp && resp.success && resp.data && resp.data.post_id){
                        alert('נוצר פוסט #' + resp.data.post_id);
                        location.href = resp.data.edit_link || location.href;
                    }else{
                        alert('נכשלה יצירת התוכן');
                    }
                    $btn.prop('disabled', false).text('צור תוכן עם AI');
                });
            });
            $('#cai-reindex').on('click', function(e){
                e.preventDefault();
                const $btn = $(this);
                $btn.prop('disabled', true).text('מריץ...');
                $.post(ajaxurl, { action:'cai_reindex' }, function(resp){
                    $('#cai-reindex-log').text(JSON.stringify(resp, null, 2));
                    $btn.prop('disabled', false).text('הפעל אינדוקס');
                });
            });
        });
        </script>
        <?php
    }
}
