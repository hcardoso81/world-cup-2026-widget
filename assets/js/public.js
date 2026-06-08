(function () {
    function setActiveDay(carousel, index) {
        var days = Array.prototype.slice.call(carousel.querySelectorAll('[data-wc26-day]'));
        var label = carousel.querySelector('[data-wc26-date-label]');
        var prev = carousel.querySelector('[data-wc26-prev]');
        var next = carousel.querySelector('[data-wc26-next]');

        if (days.length === 0) {
            return;
        }

        var activeIndex = Math.max(0, Math.min(index, days.length - 1));

        days.forEach(function (day, dayIndex) {
            day.hidden = dayIndex !== activeIndex;
        });

        if (label) {
            var dayLabel = days[activeIndex].getAttribute('data-wc26-label') || '';
            label.textContent = dayLabel;
            label.setAttribute('title', dayLabel);
            label.setAttribute('data-wc26-tooltip', dayLabel);
        }

        if (prev) {
            prev.disabled = activeIndex === 0;
        }

        if (next) {
            next.disabled = activeIndex === days.length - 1;
        }

        carousel.setAttribute('data-wc26-active-index', String(activeIndex));
    }

    function initCarousel(carousel) {
        var days = Array.prototype.slice.call(carousel.querySelectorAll('[data-wc26-day]'));
        var prev = carousel.querySelector('[data-wc26-prev]');
        var next = carousel.querySelector('[data-wc26-next]');
        var initialIndex = days.findIndex(function (day) {
            return !day.hidden;
        });

        setActiveDay(carousel, initialIndex === -1 ? 0 : initialIndex);

        if (prev) {
            prev.addEventListener('click', function () {
                setActiveDay(carousel, Number(carousel.getAttribute('data-wc26-active-index') || 0) - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                setActiveDay(carousel, Number(carousel.getAttribute('data-wc26-active-index') || 0) + 1);
            });
        }
    }

    function init() {
        document.querySelectorAll('[data-wc26-carousel]').forEach(initCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
