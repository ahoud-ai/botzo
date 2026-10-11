<?php

return [
    // Wait this long after a customer's last text/voice message before replying, so a burst of
    // messages gets one reply. Button and list taps are answered immediately. 0 disables it.
    'debounce_seconds' => (int) env('AUTO_REPLY_DEBOUNCE_SECONDS', 4),

    // Messages from the same customer this close together count as one burst
    // (used to keep "first message in conversation" working for bursts).
    'burst_window_seconds' => (int) env('AUTO_REPLY_BURST_WINDOW_SECONDS', 120),

    // One automatic responder (flow, basic reply or AI) per contact at a time.
    'lock_seconds' => (int) env('AUTO_REPLY_LOCK_SECONDS', 90),
    'lock_wait_seconds' => (int) env('AUTO_REPLY_LOCK_WAIT_SECONDS', 30),
];
