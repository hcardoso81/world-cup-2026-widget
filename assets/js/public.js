(function () {
    function fetchFixtures() {
        if (!window.WC26Widget || !window.WC26Widget.fixturesUrl || !window.fetch) {
            return Promise.resolve(null);
        }

        if (window.WC26Widget.fixturesPromise) {
            return window.WC26Widget.fixturesPromise;
        }

        window.WC26Widget.fixturesPromise = window.fetch(window.WC26Widget.fixturesUrl, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Fixture request failed');
                }

                return response.json();
            })
            .then(function (payload) {
                window.WC26Widget.fixturesPayload = payload;

                return payload;
            })
            .catch(function () {
                return null;
            });

        return window.WC26Widget.fixturesPromise;
    }

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
            setButtonState(prev, activeIndex === 0);
        }

        if (next) {
            setButtonState(next, activeIndex === days.length - 1);
        }

        carousel.setAttribute('data-wc26-active-index', String(activeIndex));
    }

    function setButtonState(button, disabled) {
        button.disabled = disabled;

        if (disabled) {
            if (!button.hasAttribute('data-wc26-tooltip-disabled')) {
                button.setAttribute('data-wc26-tooltip-disabled', button.getAttribute('data-wc26-tooltip') || '');
            }

            if (!button.hasAttribute('data-wc26-title-disabled')) {
                button.setAttribute('data-wc26-title-disabled', button.getAttribute('title') || '');
            }

            button.removeAttribute('data-wc26-tooltip');
            button.removeAttribute('title');

            return;
        }

        var tooltip = button.getAttribute('data-wc26-tooltip-disabled');
        var title = button.getAttribute('data-wc26-title-disabled');

        if (tooltip) {
            button.setAttribute('data-wc26-tooltip', tooltip);
        }

        if (title) {
            button.setAttribute('title', title);
        }
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

        fetchFixtures().then(function (payload) {
            carousel.wc26FixturesPayload = payload;
        });
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
