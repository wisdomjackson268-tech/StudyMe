
const StudyMeFeedback = (() => {

    let soundEnabled = localStorage.getItem("studyme_sound_enabled") !== "false";
    let hapticEnabled = localStorage.getItem("studyme_haptic_enabled") !== "false";
    let audioCtx = null;

    function getAudioContext() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playTone(ctx, freq, type, duration, startTime, volume = 0.1) {
        const osc = ctx.createOscillator();
        const gainNode = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, startTime);

        gainNode.gain.setValueAtTime(volume, startTime);

        gainNode.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);

        osc.connect(gainNode);
        gainNode.connect(ctx.destination);

        osc.start(startTime);
        osc.stop(startTime + duration);
    }

    function triggerVibration(pattern) {
        if (hapticEnabled && typeof navigator !== 'undefined' && navigator.vibrate) {
            try {
                navigator.vibrate(pattern);
            } catch (e) {

            }
        }
    }

    function triggerSound(type) {
        if (!soundEnabled) return;

        try {
            const ctx = getAudioContext();
            const now = ctx.currentTime;

            switch (type) {
                case 'success':

                    playTone(ctx, 523.25, 'sine', 0.12, now, 0.12);
                    playTone(ctx, 659.25, 'sine', 0.12, now + 0.06, 0.12);
                    playTone(ctx, 783.99, 'sine', 0.12, now + 0.12, 0.12);
                    playTone(ctx, 1046.50, 'sine', 0.25, now + 0.18, 0.15);
                    break;
                case 'error':

                    playTone(ctx, 130, 'sawtooth', 0.15, now, 0.08);
                    playTone(ctx, 90, 'sine', 0.25, now + 0.08, 0.15);
                    break;
                case 'click':

                    playTone(ctx, 700, 'sine', 0.02, now, 0.05);
                    break;
                case 'notification':

                    playTone(ctx, 880, 'sine', 0.08, now, 0.1);
                    playTone(ctx, 880, 'sine', 0.16, now + 0.1, 0.1);
                    break;
                case 'achievement':

                    playTone(ctx, 392.00, 'sine', 0.1, now, 0.1);
                    playTone(ctx, 523.25, 'sine', 0.1, now + 0.05, 0.1);
                    playTone(ctx, 659.25, 'sine', 0.1, now + 0.1, 0.1);
                    playTone(ctx, 783.99, 'sine', 0.1, now + 0.15, 0.1);
                    playTone(ctx, 1046.50, 'sine', 0.1, now + 0.2, 0.1);
                    playTone(ctx, 1318.51, 'sine', 0.35, now + 0.25, 0.12);
                    break;
            }
        } catch (e) {
            console.warn('AudioContext synthesis failed:', e);
        }
    }

    return {

        success: () => {
            triggerSound('success');
            triggerVibration([40, 40, 40]);
        },
        error: () => {
            triggerSound('error');
            triggerVibration([120]);
        },
        click: () => {
            triggerSound('click');
            triggerVibration(10);
        },
        notification: () => {
            triggerSound('notification');
            triggerVibration([60]);
        },
        achievement: () => {
            triggerSound('achievement');
            triggerVibration([50, 40, 50, 40, 80]);
        },

        isSoundEnabled: () => soundEnabled,
        setSoundEnabled: (enabled) => {
            soundEnabled = !!enabled;
            localStorage.setItem("studyme_sound_enabled", soundEnabled);
        },

        isHapticEnabled: () => hapticEnabled,
        setHapticEnabled: (enabled) => {
            hapticEnabled = !!enabled;
            localStorage.setItem("studyme_haptic_enabled", hapticEnabled);
        },

        vibrate: (pattern) => {
            triggerVibration(pattern);
        }
    };
})();

document.addEventListener("DOMContentLoaded", () => {

    const soundBtn = document.querySelector(".sound-btn");
    function updateSoundUI() {
        if (!soundBtn) return;
        if (StudyMeFeedback.isSoundEnabled()) {
            soundBtn.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
            soundBtn.setAttribute("aria-label", "Mute Sound");
            soundBtn.title = "Mute Sound";
        } else {
            soundBtn.innerHTML = '<i class="bi bi-volume-mute-fill"></i>';
            soundBtn.setAttribute("aria-label", "Unmute Sound");
            soundBtn.title = "Unmute Sound";
        }
    }

    updateSoundUI();

    if (soundBtn) {
        soundBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            const newState = !StudyMeFeedback.isSoundEnabled();
            StudyMeFeedback.setSoundEnabled(newState);
            updateSoundUI();
            if (newState) {
                StudyMeFeedback.success();
            }
        });
    }

    document.body.addEventListener("click", (e) => {

        const target = e.target.closest("button, .btn, .nav-link, .feedback-trigger");
        if (target) {

            if (target.classList.contains("sound-btn")) {
                return;
            }

            if (target.dataset.lastFeedback && Date.now() - target.dataset.lastFeedback < 200) {
                return;
            }
            target.dataset.lastFeedback = Date.now();

            const feedbackType = target.dataset.feedback;
            if (feedbackType === 'none') {
                return;
            }

            if (feedbackType) {
                if (typeof StudyMeFeedback[feedbackType] === 'function') {
                    StudyMeFeedback[feedbackType]();
                    return;
                }
            }

            if (target.classList.contains("btn-danger")) {
                StudyMeFeedback.error();
            } else if (target.classList.contains("btn-success")) {
                StudyMeFeedback.success();
            } else {
                StudyMeFeedback.click();
            }
        }
    });
});
