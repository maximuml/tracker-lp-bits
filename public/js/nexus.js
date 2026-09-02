/**
 * Image preview + lazy-load (native JS, no jQuery).
 */
document.addEventListener('DOMContentLoaded', function () {
    function getImgPosition(e, imgEle) {
        let imgWidth = imgEle.naturalWidth;
        let imgHeight = imgEle.naturalHeight;
        let ratio = imgWidth / imgHeight;
        let offsetX = 10;
        let offsetY = 10;
        let width = window.innerWidth - e.clientX;
        let height = window.innerHeight - e.clientY;
        let changeOffsetY = 0;
        let changeOffsetX = false;
        if (e.clientX > window.innerWidth / 2 && e.clientX + imgWidth > window.innerWidth) {
            changeOffsetX = true;
            width = e.clientX;
        }
        if (e.clientY > window.innerHeight / 2) {
            if (e.clientY + imgHeight / 2 > window.innerHeight) {
                changeOffsetY = 1;
                height = e.clientY;
            } else if (e.clientY + imgHeight > window.innerHeight) {
                changeOffsetY = 2;
                height = e.clientY;
            }
        }
        if (imgWidth > width) {
            imgWidth = width;
            imgHeight = imgWidth / ratio;
        }
        if (imgHeight > height) {
            imgHeight = height;
            imgWidth = imgHeight * ratio;
        }
        if (changeOffsetX) {
            offsetX = -(e.clientX - width + 10);
        }
        if (changeOffsetY === 1) {
            offsetY = -(imgHeight - (window.innerHeight - e.clientY));
        } else if (changeOffsetY === 2) {
            offsetY = -imgHeight / 2;
        }
        return { imgWidth, imgHeight, offsetX, offsetY };
    }

    function getPosition(e, position) {
        if (!position) {
            return {};
        }
        return {
            left: e.pageX + position.offsetX,
            top: e.pageY + position.offsetY,
            width: position.imgWidth,
            height: position.imgHeight
        };
    }

    let previewEle = document.getElementById('nexus-preview');
    let imgEle = null;
    let imgPosition = null;
    let selector = 'img.preview';

    document.body.addEventListener('mouseover', function (e) {
        let target = e.target;
        if (!target || !target.matches || !target.matches(selector)) return;
        imgEle = target;
        imgPosition = getImgPosition(e, imgEle);
        let position = getPosition(e, imgPosition);
        let src = imgEle.getAttribute('src');
        if (src && previewEle) {
            previewEle.setAttribute('src', src);
            Object.assign(previewEle.style, {
                display: 'block',
                left: position.left + 'px',
                top: position.top + 'px',
                width: position.width + 'px',
                height: position.height + 'px'
            });
            previewEle.style.opacity = '1';
        }
    });

    document.body.addEventListener('mouseout', function (e) {
        let target = e.target;
        if (!target || !target.matches || !target.matches(selector)) return;
        if (previewEle) {
            previewEle.style.display = 'none';
        }
    });

    document.body.addEventListener('mousemove', function (e) {
        let target = e.target;
        if (!target || !target.matches || !target.matches(selector)) return;
        if (previewEle && imgPosition) {
            let position = getPosition(e, imgPosition);
            Object.assign(previewEle.style, {
                left: position.left + 'px',
                top: position.top + 'px'
            });
        }
    });

    // lazy load
    if ("IntersectionObserver" in window) {
        const fallbackImage = 'pic/misc/spinner.svg';
        const domainList = ['img1.doubanio.com', 'img2.doubanio.com', 'img3.doubanio.com', 'img9.doubanio.com'];
        const imgList = [...document.querySelectorAll('.nexus-lazy-load')];
        const loadedImages = {};
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const el = entry.target;
                const intersectionRatio = entry.intersectionRatio;
                if (intersectionRatio > 0 && intersectionRatio <= 1 && !el.classList.contains('preview')) {
                    let src = el.dataset.src;
                    if (src && src.includes('doubanio.com') && src.includes('l_ratio_poster')) {
                        src = src.replace('l_ratio_poster', 's_ratio_poster');
                        el.dataset.src = src;
                    }
                    el.src = src;
                    el.classList.add('preview');
                    loadedImages[src] = true;
                    el.onload = el.onerror = () => io.unobserve(el);
                    el.onerror = () => handleImageError(el, src);
                }
            });
        });
        imgList.forEach(img => io.observe(img));
        function handleImageError(img, currentSrc) {
            if (!currentSrc.includes('doubanio.com')) {
                img.src = fallbackImage;
            } else {
                tryNextDomain(img, currentSrc, 0);
            }
        }
        function tryNextDomain(img, currentSrc, index = 0) {
            if (index >= domainList.length) {
                img.src = fallbackImage;
                return;
            }
            img.src = currentSrc.replace(/https:\/\/[a-zA-Z0-9.-]+\.doubanio\.com/, `https://${domainList[index]}`);
            img.onerror = () => tryNextDomain(img, currentSrc, index + 1);
        }
    }
});
