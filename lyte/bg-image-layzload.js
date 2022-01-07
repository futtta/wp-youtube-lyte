/*
*  Original source taken from: https://imagekit.io/blog/lazy-loading-images-complete-guide/#lazy-loading-css-background-images
*/

window.addEventListener("load", function() {
  var wyl_lazyloadImages;

  if ("IntersectionObserver" in window) {
    wyl_lazyloadImages = document.querySelectorAll(".wyl-lazy");
    var imageObserver = new IntersectionObserver(function(entries, observer) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          var image = entry.target;
          image.classList.remove("wyl-lazy");
          imageObserver.unobserve(image);
        }
      });
    });

    wyl_lazyloadImages.forEach(function(image) {
      imageObserver.observe(image);
    });
  } else {
    var lazyloadThrottleTimeout;
    wyl_lazyloadImages = document.querySelectorAll(".wyl-lazy");

    function wyl_lazyload () {
      if(lazyloadThrottleTimeout) {
        clearTimeout(lazyloadThrottleTimeout);
      }

      lazyloadThrottleTimeout = setTimeout(function() {
        var scrollTop = window.pageYOffset;
        wyl_lazyloadImages.forEach(function(img) {
            if(img.offsetTop < (window.innerHeight + scrollTop)) {
              img.classList.remove('wyl-lazy');
            }
        });
        if(wyl_lazyloadImages.length == 0) { 
          document.removeEventListener("scroll", wyl_lazyload);
          window.removeEventListener("resize", wyl_lazyload);
          window.removeEventListener("orientationChange", wyl_lazyload);
        }
      }, 20);
    }

    document.addEventListener("scroll", wyl_lazyload);
    window.addEventListener("resize", wyl_lazyload);
    window.addEventListener("orientationChange", wyl_lazyload);
  }
})
