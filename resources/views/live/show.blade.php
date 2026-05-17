<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $screen->display_title ?? ($screen->exam->name ?? 'Live – IOE') }} | Màn hình trực tiếp</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:         #0d1117;
            --surface:    #161b22;
            --surface2:   #21262d;
            --border:     #30363d;
            --accent:     #1f6feb;
            --accent-glow:#388bfd;
            --green:      #3fb950;
            --orange:     #f0883e;
            --red:        #f85149;
            --text:       #e6edf3;
            --muted:      #8b949e;
            --code-bg:    #0d2137;
            --code-text:  #79c0ff;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        #live-root {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            background:
                radial-gradient(ellipse 80% 40% at 50% 0%, rgba(31,111,235,0.12) 0%, transparent 70%),
                var(--bg);
        }

        /* Header */
        .live-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .live-exam-name {
            font-size: clamp(1.25rem, 3vw, 2rem);
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .live-exam-level {
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--muted);
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 2rem;
            padding: 0.25rem 0.875rem;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
        .status-badge.waiting   { background: rgba(240,136,62,0.15); color: var(--orange); border: 1px solid rgba(240,136,62,0.3); }
        .status-badge.showing   { background: rgba(63,185,80,0.15);  color: var(--green);  border: 1px solid rgba(63,185,80,0.3); }
        .status-badge.finished  { background: rgba(139,148,158,0.15); color: var(--muted); border: 1px solid var(--border); }
        .status-badge.error     { background: rgba(248,81,73,0.15);  color: var(--red);    border: 1px solid rgba(248,81,73,0.3); }
        .pulse { animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

        /* Slot info */
        .slot-info {
            text-align: center;
            margin-bottom: 2rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem 2rem;
            min-width: min(90vw, 600px);
        }
        .slot-title { font-size: 1.5rem; font-weight: 700; color: var(--text); }
        .slot-meta  { font-size: 1rem; color: var(--muted); margin-top: 0.375rem; }

        /* Countdown */
        .countdown-block {
            text-align: center;
            margin-bottom: 2rem;
        }
        .countdown-label {
            font-size: 0.875rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.75rem;
        }
        .countdown-digits {
            font-size: clamp(4rem, 15vw, 10rem);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--text);
            text-shadow: 0 0 60px rgba(56,139,253,0.25);
            font-family: 'JetBrains Mono', monospace;
        }
        .countdown-digits.urgent { color: var(--orange); text-shadow: 0 0 60px rgba(240,136,62,0.4); }

        /* Code display */
        .code-block {
            text-align: center;
            background: var(--code-bg);
            border: 2px solid var(--accent);
            border-radius: 1.25rem;
            padding: 2rem 3rem;
            box-shadow: 0 0 60px rgba(31,111,235,0.2), inset 0 1px 0 rgba(255,255,255,0.05);
            min-width: min(90vw, 640px);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .code-block::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(31,111,235,0.06) 0%, transparent 60%);
        }
        .code-label {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent-glow);
            margin-bottom: 0.75rem;
        }
        .code-value {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: clamp(3rem, 12vw, 8rem);
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #fff;
            text-shadow: 0 0 40px rgba(56,139,253,0.6), 0 2px 4px rgba(0,0,0,0.5);
            word-break: break-all;
        }
        .code-sub {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--muted);
        }

        /* Message */
        .live-message {
            text-align: center;
            font-size: 1.125rem;
            color: var(--muted);
            max-width: 600px;
            line-height: 1.6;
        }

        /* Footer */
        .live-footer {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 2rem;
            font-size: 0.8rem;
            color: var(--border);
            font-variant-numeric: tabular-nums;
        }
        #server-clock { color: var(--muted); }

        /* Finished screen */
        .finished-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            opacity: 0.6;
        }
        .finished-text {
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 700;
            color: var(--muted);
            text-align: center;
        }
    </style>
</head>
<body>
<div id="live-root">
    {{-- Nội dung render từ server khi tải trang, JS sẽ override --}}
    <div id="live-content">
        @include('live._state', ['state' => $state, 'exam' => $screen->exam])
    </div>
</div>

{{-- Footer clock --}}
<div class="live-footer">
    <span id="server-clock">--:--:--</span>
    <span>IOE {{ $screen->exam->name ?? '' }}</span>
</div>

<script>
const TOKEN    = @json($token);
const POLL_MS  = 3000; // Polling mỗi 3 giây
let countdownTimer = null;
let countdownTarget = null;
let countdownEl = null;

// ── Render state ────────────────────────────────────────────────────────────
function renderState(state) {
    const root = document.getElementById('live-content');

    clearInterval(countdownTimer);
    countdownTarget = null;

    if (state.status === 'code_visible_before_start' || state.status === 'code_visible_after_start') {
        renderCodeVisible(root, state);
    } else if (state.status === 'waiting_next_slot') {
        renderWaiting(root, state);
    } else if (state.status === 'all_finished' || state.status === 'force_ended') {
        renderFinished(root, state);
    } else if (state.status === 'no_slots' || state.status === 'disabled') {
        renderNoSlots(root, state);
    } else if (state.status === 'missing_code') {
        renderMissingCode(root, state);
    } else {
        renderMessage(root, state.message ?? 'Đang tải...');
    }

    // Khởi động countdown
    if (state.countdown_target) {
        countdownTarget = new Date(state.countdown_target);
        startCountdown();
    }
}

function renderCodeVisible(root, state) {
    const isAfterStart = state.status === 'code_visible_after_start';
    const slot = state.current_slot ?? {};
    root.innerHTML = `
        <div class="live-header">
            ${state.exam ? `<div class="live-exam-name">${escHtml(state.exam.name ?? '')}</div>` : ''}
            ${state.exam?.level ? `<span class="live-exam-level">${escHtml(state.exam.level)}</span>` : ''}
        </div>
        <div class="status-badge showing">
            <span class="pulse">●</span>
            ${isAfterStart ? 'Ca thi đã bắt đầu' : 'Mã ca thi'}
        </div>
        ${slot.grade_label ? `<div class="slot-info">
            <div class="slot-title">${escHtml(slot.grade_label)}</div>
            <div class="slot-meta">${slot.starts_at ? 'Giờ thi: ' + formatTime(slot.starts_at) : ''} ${slot.student_count ? '· ' + slot.student_count + ' học sinh' : ''}</div>
        </div>` : ''}
        <div class="code-block">
            <div class="code-label">MÃ CA THI</div>
            <div class="code-value">${escHtml(state.code ?? '')}</div>
            <div class="code-sub" id="code-sub-msg">
                ${isAfterStart ? 'Mã sẽ tự ẩn sau ít phút...' : 'Còn <strong id="cd-text">--:--</strong> đến giờ bắt đầu làm bài'}
            </div>
        </div>`;
    countdownEl = document.getElementById('cd-text');
}

function renderWaiting(root, state) {
    const slot = state.next_slot ?? {};
    root.innerHTML = `
        <div class="live-header">
            ${state.exam ? `<div class="live-exam-name">${escHtml(state.exam.name ?? '')}</div>` : ''}
            ${state.exam?.level ? `<span class="live-exam-level">${escHtml(state.exam.level)}</span>` : ''}
        </div>
        <div class="status-badge waiting">⏳ Đang chờ ca thi</div>
        ${slot.grade_label ? `<div class="slot-info">
            <div class="slot-title">Ca tiếp theo: ${escHtml(slot.grade_label)}</div>
            <div class="slot-meta">Giờ thi: ${slot.starts_at ? formatTime(slot.starts_at) : '--:--'} ${slot.student_count ? '· ' + slot.student_count + ' học sinh' : ''}</div>
            <div class="slot-meta" style="margin-top:.25rem;font-size:.8rem">Mã ca thi sẽ hiển thị ${slot.reveal_at ? 'lúc ' + formatTime(slot.reveal_at) : '5 phút trước giờ thi'}</div>
        </div>` : ''}
        <div class="countdown-block">
            <div class="countdown-label">Giờ bắt đầu thi</div>
            <div class="countdown-digits" id="cd-main">--:--:--</div>
        </div>`;
    countdownEl = document.getElementById('cd-main');
}

function renderFinished(root, state) {
    root.innerHTML = `
        <div class="finished-icon">🏁</div>
        <div class="finished-text">Tất cả ca thi đã kết thúc</div>
        <div class="live-message" style="margin-top:1rem">${escHtml(state.message ?? '')}</div>`;
}

function renderNoSlots(root, state) {
    root.innerHTML = `
        <div style="text-align:center">
            <div style="font-size:4rem;margin-bottom:1rem;opacity:.4">📭</div>
            <div class="finished-text" style="font-size:1.5rem">${escHtml(state.message ?? 'Chưa có ca thi')}</div>
        </div>`;
}

function renderMissingCode(root, state) {
    const slot = state.slot ?? state.current_slot ?? {};
    root.innerHTML = `
        <div class="status-badge error">⚠ Thiếu mã ca thi</div>
        <div class="live-message" style="color:#f85149;font-size:1.25rem;margin-top:1rem">${escHtml(state.message ?? '')}</div>
        ${slot.grade_label ? `<div class="slot-meta" style="text-align:center;margin-top:.75rem">${escHtml(slot.grade_label)}</div>` : ''}`;
}

function renderMessage(root, msg) {
    root.innerHTML = `<div class="live-message">${escHtml(msg)}</div>`;
}

// ── Countdown ────────────────────────────────────────────────────────────────
function startCountdown() {
    clearInterval(countdownTimer);
    tick();
    countdownTimer = setInterval(tick, 500);
}

function tick() {
    if (! countdownTarget || ! countdownEl) return;
    const diff = Math.max(0, Math.floor((countdownTarget - Date.now()) / 1000));
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    const text = h > 0
        ? `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
        : `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    countdownEl.textContent = text;

    // Màu urgent khi < 60s
    if (countdownEl.closest && countdownEl.closest('.countdown-digits')) {
        countdownEl.closest('.countdown-digits').classList.toggle('urgent', diff < 60);
    }
}

// ── Server clock ─────────────────────────────────────────────────────────────
function updateClock() {
    document.getElementById('server-clock').textContent =
        new Date().toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}
setInterval(updateClock, 1000);
updateClock();

// ── Polling ──────────────────────────────────────────────────────────────────
async function pollState() {
    try {
        const resp = await fetch(`/live/${TOKEN}/state`, {headers: {'Accept': 'application/json'}});
        if (resp.ok) {
            const state = await resp.json();
            renderState(state);
        }
    } catch (e) {
        console.warn('Poll error:', e);
    }
}

// Render từ SSR data trước
renderState(@json($state));

// Bắt đầu polling
setInterval(pollState, POLL_MS);

// ── Utils ────────────────────────────────────────────────────────────────────
function formatTime(isoStr) {
    try {
        return new Date(isoStr).toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
    } catch { return '--:--'; }
}
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
