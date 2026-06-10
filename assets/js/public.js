(function () {
    var pollTimer = null;

    function fetchFixtures(force) {
        if (!window.WC26Widget || !window.WC26Widget.fixturesUrl || !window.fetch) {
            return Promise.resolve(null);
        }

        if (window.WC26Widget.fixturesPromise) {
            return window.WC26Widget.fixturesPromise;
        }

        window.WC26Widget.fixturesPromise = window.fetch(cacheBustedUrl(window.WC26Widget.fixturesUrl), {
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
                updateCarousels(payload);

                return payload;
            })
            .catch(function () {
                return null;
            })
            .finally(function () {
                window.WC26Widget.fixturesPromise = null;
            });

        return window.WC26Widget.fixturesPromise;
    }

    function cacheBustedUrl(url) {
        var separator = url.indexOf('?') === -1 ? '?' : '&';

        return url + separator + 'wc26_t=' + Date.now();
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

    function updateCarousels(payload) {
        var fixtures = payload && Array.isArray(payload.fixtures) ? payload.fixtures : [];

        if (fixtures.length === 0) {
            return;
        }

        document.querySelectorAll('[data-wc26-carousel]').forEach(function (carousel) {
            fixtures.forEach(function (fixture) {
                updateFixtureCard(carousel, fixture);
            });
        });
    }

    function updateFixtureCard(carousel, fixture) {
        var fixtureData = fixture && fixture.fixture ? fixture.fixture : {};
        var id = Number(fixtureData.id || 0);

        if (!id) {
            return;
        }

        var card = carousel.querySelector('[data-wc26-match-id="' + id + '"]');

        if (!card) {
            return;
        }

        var model = fixtureModel(fixture);
        var statusLabel = card.querySelector('[data-wc26-status-label]');
        var statusExtra = card.querySelector('[data-wc26-status-extra]');
        var score = card.querySelector('[data-wc26-score]');

        if (statusLabel) {
            statusLabel.textContent = model.statusLabel.toUpperCase();
        }

        if (statusExtra) {
            statusExtra.textContent = model.statusExtra;
        }

        if (score) {
            score.textContent = model.score;
        }

        setStatusClasses(card, model.status, model.isCurrent);
    }

    function fixtureModel(fixture) {
        var fixtureData = fixture.fixture || {};
        var status = fixtureData.status || {};
        var goals = fixture.goals || {};
        var score = fixture.score || {};
        var penalties = score.penalty || {};
        var statusShort = String(status.short || 'NS');
        var elapsed = Number(status.elapsed || 0);
        var isCurrent = isLiveStatus(statusShort);
        var isNotStarted = (statusShort === 'NS' || statusShort === 'TBD') && !isCurrent;

        return {
            status: statusShort,
            statusLabel: isCurrent ? 'En juego' : statusText(statusShort),
            statusExtra: statusExtra(statusShort, elapsed, fixtureData.date || ''),
            score: isNotStarted ? '-' : formatScore(goals, penalties),
            isCurrent: isCurrent,
        };
    }

    function setStatusClasses(card, status, isCurrent) {
        Array.prototype.slice.call(card.classList).forEach(function (className) {
            if (className.indexOf('wc26-match--') === 0 && className !== 'wc26-match--current') {
                card.classList.remove(className);
            }
        });

        card.classList.add('wc26-match--' + status.toLowerCase());
        card.classList.toggle('wc26-match--current', isCurrent);
    }

    function statusExtra(status, elapsed, date) {
        if (elapsed > 0 && ['FT', 'AET', 'PEN'].indexOf(status) === -1) {
            return String(elapsed) + "'";
        }

        if (status === 'NS' || status === 'TBD' || ['FT', 'AET', 'PEN'].indexOf(status) !== -1) {
            return formatTime(date);
        }

        return '';
    }

    function formatScore(goals, penalties) {
        var home = goals.home == null ? '0' : String(goals.home);
        var away = goals.away == null ? '0' : String(goals.away);

        if (penalties.home != null || penalties.away != null) {
            return home + ' (' + (penalties.home == null ? '0' : String(penalties.home)) + ') - ' + away + ' (' + (penalties.away == null ? '0' : String(penalties.away)) + ')';
        }

        return home + ' - ' + away;
    }

    function formatTime(date) {
        if (!date) {
            return '';
        }

        var parsed = new Date(date);

        if (Number.isNaN(parsed.getTime())) {
            return '';
        }

        return parsed.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function isLiveStatus(status) {
        return ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'].indexOf(status) !== -1;
    }

    function statusText(status) {
        var labels = {
            TBD: 'A definir',
            NS: 'No iniciado',
            '1H': 'Primer tiempo',
            HT: 'Entretiempo',
            '2H': 'Segundo tiempo',
            ET: 'Tiempo extra',
            BT: 'Descanso',
            P: 'Penales',
            FT: 'Finalizado',
            AET: 'Finalizado en tiempo extra',
            PEN: 'Finalizado por penales',
            SUSP: 'Suspendido',
            INT: 'Interrumpido',
            PST: 'Postergado',
            CANC: 'Cancelado',
            ABD: 'Abandonado',
        };

        return labels[status] || status;
    }

    function startPolling() {
        var interval = Number(window.WC26Widget && window.WC26Widget.pollInterval ? window.WC26Widget.pollInterval : 60);

        if (!window.WC26Widget || !window.WC26Widget.fixturesUrl || interval <= 0) {
            return;
        }

        stopPolling();
        schedulePoll(interval);
    }

    function schedulePoll(interval) {
        var jitter = Math.floor(Math.random() * 15000);

        pollTimer = window.setTimeout(function () {
            if (document.visibilityState === 'visible') {
                fetchFixtures(true).then(function () {
                    schedulePoll(interval);
                });

                return;
            }

            schedulePoll(interval);
        }, (interval * 1000) + jitter);
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function init() {
        document.querySelectorAll('[data-wc26-carousel]').forEach(initCarousel);
        startPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
