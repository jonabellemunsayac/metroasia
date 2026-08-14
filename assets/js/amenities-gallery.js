(() => {
  const slider=document.querySelector('[data-amenities-slider]'); if(!slider)return;
  const track=slider.querySelector('.metro-amenities-track');
  const slides=[...slider.querySelectorAll('[data-amenity-slide]')];
  const prev=document.querySelector('[data-amenities-prev]');
  const next=document.querySelector('[data-amenities-next]');
  let index=0;
  const visible=()=>innerWidth<=640?1:innerWidth<=1000?2:3;
  const maxIndex=()=>Math.max(0,slides.length-visible());
  function update(){
    index=Math.max(0,Math.min(index,maxIndex()));
    const first=slides[0]; if(!first)return;
    const gap=parseFloat(getComputedStyle(track).gap)||0;
    track.style.transform=`translateX(-${index*(first.getBoundingClientRect().width+gap)}px)`;
    if(prev)prev.disabled=index<=0;
    if(next)next.disabled=index>=maxIndex();
  }
  prev?.addEventListener('click',()=>{index--;update()});
  next?.addEventListener('click',()=>{index++;update()});
  let sx=0;
  slider.addEventListener('touchstart',e=>{sx=e.touches[0]?.clientX||0},{passive:true});
  slider.addEventListener('touchend',e=>{const dx=(e.changedTouches[0]?.clientX||0)-sx;if(Math.abs(dx)>45){index+=dx<0?1:-1;update()}},{passive:true});
  addEventListener('resize',update);

  const modal=document.querySelector('[data-amenity-modal]');
  const img=modal?.querySelector('[data-amenity-modal-image]');
  const title=modal?.querySelector('[data-amenity-modal-title]');
  document.querySelectorAll('[data-amenity-lightbox]').forEach(b=>b.addEventListener('click',()=>{
    if(!modal||!img)return;
    img.src=b.dataset.image||''; img.alt=b.dataset.title||'Amenity photo';
    if(title)title.textContent=b.dataset.title||'';
    modal.classList.remove('hidden'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';
  }));
  const close=()=>{if(!modal)return;modal.classList.add('hidden');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''};
  modal?.querySelector('[data-amenity-close]')?.addEventListener('click',close);
  modal?.addEventListener('click',e=>{if(e.target===modal)close()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
  update();
})();
