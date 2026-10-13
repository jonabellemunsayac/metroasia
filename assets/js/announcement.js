(() => {
    const popup = document.getElementById('announcementPopup');
    if (!popup || typeof popup.showModal !== 'function') return;
    let scrollPosition = null;
    popup.querySelector('button').addEventListener('click', () => popup.close());
    popup.addEventListener('click', event => {
        const box = popup.getBoundingClientRect();
        if (event.target === popup && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) popup.close();
    });
    popup.addEventListener('close', () => {
        document.documentElement.classList.remove('announcement-visible');
        document.body.style.removeProperty('--announcement-scroll-top');
        if (scrollPosition) {
            window.scrollTo({...scrollPosition, behavior: 'instant'});
            scrollPosition = null;
        }
    });
    const show = () => {
        if (popup.open || !picture.naturalWidth) return;
        scrollPosition = {left: window.scrollX, top: window.scrollY};
        popup.showModal();
        document.body.style.setProperty('--announcement-scroll-top', `-${scrollPosition.top}px`);
        document.documentElement.classList.add('announcement-visible');
    };
    const picture = popup.querySelector('img');
    if (picture.complete) show();
    else picture.addEventListener('load', show, {once:true});
})();
