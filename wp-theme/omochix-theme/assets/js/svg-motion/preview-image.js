/**
 * SVG Motion Studio: the preview, shown as an image.
 *
 * An SVG in <img> cannot run scripts or load anything, and in WebKit it is not
 * limited the way a sandboxed frame is. An image cannot be paused from outside,
 * so each change builds a new picture: time is a negative animation-delay and
 * pausing is animation-play-state, which keeps the motion where it was. A new
 * picture replaces the old one only after it is decoded, so nothing goes blank.
 *
 * Playback state lives here and is never saved; settings live in studio.js.
 */
(function (root) {
    'use strict';

    function create(host, onChange) {
        var image = null;
        var url = null;
        var prepared = null;
        var plan = null;
        var playing = false;
        var heldTime = 0;
        var startedAt = 0;
        var token = 0;
        var finishTimer = 0;
        var resting = false; // At the plan's resting pose: a changed plan moves it to the new resting pose.

        function now() {
            return root.performance.now();
        }

        function currentTime() {
            if (!plan) return 0;
            var time = playing ? now() - startedAt : heldTime;
            return plan.loops ? time : Math.min(time, plan.length);
        }

        function notify() {
            if (onChange) onChange({ playing: playing, time: currentTime() });
        }

        function scheduleFinish() {
            root.clearTimeout(finishTimer);
            if (!playing || plan.loops) return;
            finishTimer = root.setTimeout(function () {
                heldTime = plan.length;
                playing = false;
                resting = true;
                notify();
            }, Math.max(0, plan.length - currentTime()) + 20);
        }

        /* Latest request wins: an older picture still decoding is dropped. */
        function render(time, alt) {
            var mine = ++token;
            var text = root.OmxSvgFrame.animatedSvg(prepared, plan, { time: time, frozen: !playing });
            var nextUrl = URL.createObjectURL(new Blob([text], { type: 'image/svg+xml' }));
            var next = new Image();
            next.className = 'svg-motion__preview';
            next.setAttribute('data-svgm-preview', '');
            next.alt = alt || (image ? image.alt : '');
            next.src = nextUrl;
            var ready = next.decode ? next.decode() : Promise.resolve();
            return ready.then(function () {
                if (mine !== token) { URL.revokeObjectURL(nextUrl); return false; }
                if (image) image.replaceWith(next); else host.appendChild(next);
                if (url) URL.revokeObjectURL(url);
                image = next;
                url = nextUrl;
                // The new picture's clock is taken as starting here; the phase error this leaves is measured in the tests.
                if (playing) startedAt = now() - time;
                scheduleFinish();
                notify();
                return true;
            }, function () {
                URL.revokeObjectURL(nextUrl);
                if (mine === token) throw new Error('DECODE_FAILED');
                return false;
            });
        }

        return {
            /** A new logo or preset: start from the beginning, or rest when not playing. */
            show: function (nextPrepared, nextPlan, options) {
                prepared = nextPrepared;
                plan = nextPlan;
                playing = !!(options && options.play);
                heldTime = playing ? 0 : plan.restTime;
                resting = !playing;
                return render(heldTime, options && options.alt);
            },
            /** New values for the same logo: the motion stays at its point in time. */
            update: function (nextPlan, options) {
                var time = currentTime();
                plan = nextPlan;
                if (resting) time = plan.restTime;
                else if (!plan.loops && time > plan.length) time = plan.length;
                heldTime = time;
                return render(time, options && options.alt);
            },
            play: function () {
                if (!plan) return Promise.resolve(false);
                var time = currentTime();
                // A finished motion, or one resting at its end, plays again from the start.
                if (!plan.loops && time >= plan.length) time = 0;
                if (resting) time = 0;
                playing = true;
                resting = false;
                heldTime = time;
                return render(time);
            },
            pause: function () {
                if (!plan) return Promise.resolve(false);
                heldTime = currentTime();
                playing = false;
                resting = false;
                root.clearTimeout(finishTimer);
                return render(heldTime);
            },
            /** Stopped at the resting pose, where the logo is fully visible. The next play starts from the beginning. */
            reset: function () {
                if (!plan) return Promise.resolve(false);
                playing = false;
                resting = true;
                heldTime = plan.restTime;
                root.clearTimeout(finishTimer);
                return render(heldTime);
            },
            seek: function (time) {
                if (!plan) return Promise.resolve(false);
                playing = false;
                resting = false;
                heldTime = time;
                root.clearTimeout(finishTimer);
                return render(time);
            },
            state: function () {
                return { playing: playing, time: currentTime() };
            },
            element: function () {
                return image;
            }
        };
    }

    root.OmxSvgPreview = { create: create };
}(window));
