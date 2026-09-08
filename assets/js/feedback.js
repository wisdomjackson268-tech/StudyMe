/**
 * StudyMe Centralized Feedback System (Sound and Haptic / Vibration)
 * Synthesizes clean, high-quality tones using the Web Audio API (zero external assets needed).
 * Integrates smooth vibration using the navigator.vibrate API.
 */

const StudyMeFeedback = (() => {
    // State management and preferences
    let soundEnabled = localStorage.getItem("studyme_sound_enabled") !== "false";
    let hapticEnabled = localStorage.getItem("studyme_haptic_enabled") !== "false";
    let audioCtx = null;

    // Initialize AudioContext lazily on user gesture
    function getAudioContext() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // Helper to synthesize a single pitch tone with smooth gain envelope
    function playTone(ctx, freq, type, duration, startTime, volume = 0.1) {
        const osc = ctx.createOscillator();
        const gainNode = ctx.createGain();
        
        osc.type = type;
        osc.frequency.setValueAtTime(freq, startTime);
        
        // Setup initial gain
        gainNode.gain.setValueAtTime(volume, startTime);
        
        // Prevent clicking at the end of tone by fading exponentially to 0.0001
        gainNode.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
        
        osc.connect(gainNode);
        gainNode.connect(ctx.destination);
        
        osc.start(startTime);
        osc.stop(startTime + duration);
    }

    // Trigger haptic response on device
    function triggerVibration(pattern) {
        if (hapticEnabled && typeof navigator !== 'undefined' && navigator.vibrate) {
            try {
                navigator.vibrate(pattern);
            } catch (e) {
                // Fail silently on security-blocked or non-supported devices
            }
        }
    }

    // Play procedural tone based on event type
    function triggerSound(type) {
        if (!soundEnabled) return;

        try {
            const ctx = getAudioContext();
            const now = ctx.currentTime;
            
            switch (type) {
                case 'success':
                    // Ascending major chord (C5 -> E5 -> G5 -> C6)
                    playTone(ctx, 523.25, 'sine', 0.12, now, 0.12);
                    playTone(ctx, 659.25, 'sine', 0.12, now + 0.06, 0.12);
                    playTone(ctx, 783.99, 'sine', 0.12, now + 0.12, 0.12);
                    playTone(ctx, 1046.50, 'sine', 0.25, now + 0.18, 0.15);
                    break;
                case 'error':
                    // Low warning sawtooth followed by a sine drop
                    playTone(ctx, 130, 'sawtooth', 0.15, now, 0.08);
                    playTone(ctx, 90, 'sine', 0.25, now + 0.08, 0.15);
                    break;
                case 'click':
                    // Subtle dynamic interface click/tick
                    playTone(ctx, 700, 'sine', 0.02, now, 0.05);
                    break;
                case 'notification':
                    // Double high chime (A5 -> A5)
                    playTone(ctx, 880, 'sine', 0.08, now, 0.1);
                    playTone(ctx, 880, 'sine', 0.16, now + 0.1, 0.1);
                    break;
                case 'achievement':
                    // Epic rising chime sequence with high ring
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

    // Public API
    return {
        // Trigger generic / specific feedback flows
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

        // Sound Toggle API
        isSoundEnabled: () => soundEnabled,
        setSoundEnabled: (enabled) => {
            soundEnabled = !!enabled;
            localStorage.setItem("studyme_sound_enabled", soundEnabled);
        },

        // Haptic Toggle API
        isHapticEnabled: () => hapticEnabled,
        setHapticEnabled: (enabled) => {
            hapticEnabled = !!enabled;
            localStorage.setItem("studyme_haptic_enabled", hapticEnabled);
        },

        // Manual vibration trigger
        vibrate: (pattern) => {
            triggerVibration(pattern);
        }
    };
})();

// Attach event listeners to document once loaded to play sound/vibe on key UI interactions
document.addEventListener("DOMContentLoaded", () => {
    // Sound Button Toggle UI Setup
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
            e.stopPropagation(); // Avoid double click trigger
            const newState = !StudyMeFeedback.isSoundEnabled();
            StudyMeFeedback.setSoundEnabled(newState);
            updateSoundUI();
            if (newState) {
                StudyMeFeedback.success(); // Play a feedback tone showing it is enabled
            }
        });
    }

    // Attach click feedback to standard action elements
    document.body.addEventListener("click", (e) => {
        // Apply feedback on buttons, links acting as buttons, and tab clicks
        const target = e.target.closest("button, .btn, .nav-link, .feedback-trigger");
        if (target) {
            // Ignore sound btn to avoid double sound play
            if (target.classList.contains("sound-btn")) {
                return;
            }

            // Prevent spam clicks by keeping a cooldown
            if (target.dataset.lastFeedback && Date.now() - target.dataset.lastFeedback < 200) {
                return;
            }
            target.dataset.lastFeedback = Date.now();

            // Check if there is an explicit data-feedback override
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

            // Fallback to class-based defaults
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
