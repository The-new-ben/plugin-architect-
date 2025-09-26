/* global caiVars */
// placeholder
jQuery(function($){
    // Generation page
    $('#cai-analyze-site').on('click', function(e){
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text('מנתח...');
        $.post(caiVars.ajaxurl, { nonce: caiVars.nonce,  action:'cai_analyze_site' }, function(resp){
            if(resp && resp.success && resp.data && resp.data.plan){
                $('#cai-plan').val(resp.data.plan);
            } else {
                alert('שגיאה בניתוח האתר');
            }
            $btn.prop('disabled', false).text('נתח אתר והצע אשכולות');
        });
    });

    $('#cai-apply-arch').on('click', function(e){
        e.preventDefault();
        const plan = $('#cai-plan').val();
        const $btn = $(this);
        $btn.prop('disabled', true).text('מיישם...');
        $.post(caiVars.ajaxurl, { nonce: caiVars.nonce,  action:'cai_apply_arch', plan: plan }, function(resp){
            if(resp && resp.success){
                alert('נוצרו/עודכנו אשכולות/קטגוריות: ' + JSON.stringify(resp.data.created));
            }else{
                alert('שגיאה ביישום הארכיטקטורה');
            }
            $btn.prop('disabled', false).text('יישם ארכיטקטורה והכן קטגוריות');
        });
    });

    $('#cai-generate-now').on('click', function(e){
        e.preventDefault();
        const ppc = parseInt($('#cai-ppc').val(), 10) || 3;
        const $btn = $(this);
        $btn.prop('disabled', true).text('יוצר...');
        $('#cai-generate-log').prepend('<div>הפעלה התחילה...</div>');
        $.post(caiVars.ajaxurl, { nonce: caiVars.nonce,  action:'cai_generate_now', ppc: ppc }, function(resp){
            if(resp && resp.success){
                $('#cai-generate-log').prepend('<div>נוצרו ' + resp.data.created + ' פוסטים: ' + (resp.data.ids||[]).join(', ') + '</div>');
            } else {
                $('#cai-generate-log').prepend('<div>כשלון בהרצה</div>');
            }
            $btn.prop('disabled', false).text('צור עכשיו');
        });
    });

    $('#cai-generate-topic').on('click', function(e){
        e.preventDefault();
        const topic = $('#cai-topic').val();
        if(!topic){ alert('נא להזין נושא'); return; }
        const $btn = $(this);
        $btn.prop('disabled', true).text('יוצר...');
        $.post(caiVars.ajaxurl, { nonce: caiVars.nonce,  action:'cai_generate_from_topic', topic: topic }, function(resp){
            if(resp && resp.success){
                $('#cai-generate-log').prepend('<div>נוצר פוסט #' + resp.data.post_id + '</div>');
            } else {
                $('#cai-generate-log').prepend('<div>כשלון ביצירת פוסט</div>');
            }
            $btn.prop('disabled', false).text('יצירת פוסט יחיד');
        });
    });
});

jQuery(function($){
    $(document).on('click','#cai-test-connection', function(e){
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text('בודק...');
        $.post(caiVars.ajaxurl, { action:'cai_test_ai', nonce: caiVars.nonce }, function(resp){
            if(resp && resp.success){
                $('#cai-test-result').text('✓ חיבור תקין ('+(resp.data.ok||'OK')+')');
            } else {
                $('#cai-test-result').text('✗ שגיאה: '+ (resp && resp.data ? resp.data : ''));
            }
            $btn.prop('disabled', false).text('בדוק חיבור');
        });
    });
});
