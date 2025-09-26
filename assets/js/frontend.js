
document.addEventListener('DOMContentLoaded', function(){
  // Copy link
  document.querySelectorAll('[data-copy]').forEach(function(btn){
    btn.addEventListener('click', function(){
      const v = btn.getAttribute('data-copy');
      navigator.clipboard.writeText(v).then(function(){
        btn.textContent = 'הועתק ✔';
        setTimeout(()=>btn.textContent='העתק קישור', 1500);
      });
    });
  });
  // Reading progress bar
  var bar = document.createElement('div');
  bar.className = 'cai-progress';
  document.body.appendChild(bar);
  var update = function(){
    var h = document.documentElement;
    var scrolled = (h.scrollTop) / (h.scrollHeight - h.clientHeight);
    bar.style.transform = 'scaleX('+ (scrolled || 0) +')';
  };
  window.addEventListener('scroll', update, {passive:true});
  window.addEventListener('resize', update);
  update();
});
