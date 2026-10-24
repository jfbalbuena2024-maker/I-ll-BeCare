(() => {
    'use strict';

    const API = document.body.dataset.api;
    const LOGIN = document.body.dataset.login;
    const $ = (id) => document.getElementById(id);

    const forms = { 1: $('stepEmail'), 2: $('stepOtp'), 3: $('stepReset') };
    const stepItems = document.querySelectorAll('.fp-steps li');
    const titleEl = $('fpTitle');
    const subEl = $('fpSub');
    const statusEl = $('status');
    const resendBtn = $('resend');

    let currentEmail = '';
    let timer = null;

    /* ---------- helpers ---------- */
    function say(msg, ok) {
        statusEl.textContent = msg || '';
        statusEl.className = 'fp-status' + (msg ? (ok ? ' is-success' : ' is-error') : '');
    }

    async function call(data) {
        try {
            const res = await fetch(API, {
                method: 'POST',
                body: new URLSearchParams(data),
                credentials: 'same-origin'
            });
            return await res.json();
        } catch (e) {
            return { ok: false, message: 'Something went wrong. Check your connection and try again.' };
        }
    }

    function busy(btn, on, text) {
        if (on) {
            btn.dataset.label = btn.textContent;
            btn.textContent = text;
            btn.disabled = true;
        } else {
            btn.textContent = btn.dataset.label || btn.textContent;
            btn.disabled = false;
        }
    }

    function startCooldown(sec) {
        clearInterval(timer);
        let left = sec;
        resendBtn.disabled = true;
        const tick = () => {
            if (left <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend code';
                return;
            }
            resendBtn.textContent = 'Resend code in ' + left + 's';
            left--;
        };
        tick();
        timer = setInterval(tick, 1000);
    }

    const copy = {
        1: ['Forgot your password?', "Enter the email you registered with and we'll send you a 6-digit code."],
        2: ['Check your email', null],
        3: ['Create a new password', 'Choose a new password for your account.']
    };

    function go(step) {
        Object.entries(forms).forEach(([n, f]) => { f.hidden = Number(n) !== step; });
        stepItems.forEach((li) => {
            const n = Number(li.dataset.step);
            li.classList.toggle('is-active', n === step);
            li.classList.toggle('is-done', n < step);
        });
        titleEl.textContent = copy[step][0];
        subEl.textContent = step === 2
            ? 'If ' + currentEmail + ' is registered, we sent a 6-digit code to it. The code expires in 10 minutes.'
            : copy[step][1];
        const first = forms[step].querySelector('input');
        if (first) first.focus();
    }

    /* ---------- step 1: send code ---------- */
    async function sendCode(btn, busyText) {
        const email = $('email').value.trim();
        if (!$('email').checkValidity() || !email) {
            say('Enter a valid email address.', false);
            $('email').focus();
            return;
        }
        currentEmail = email;
        if (btn) busy(btn, true, busyText);
        const r = await call({ action: 'send', email });
        if (btn) busy(btn, false);

        if (r.ok) {
            say('', true);
            go(2);
            startCooldown(60);
        } else {
            say(r.message, false);
        }
    }

    forms[1].addEventListener('submit', (e) => {
        e.preventDefault();
        sendCode(forms[1].querySelector('.fp-btn'), 'Sending…');
    });

    resendBtn.addEventListener('click', async () => {
        resendBtn.disabled = true;
        const r = await call({ action: 'send', email: currentEmail });
        say(r.ok ? 'A new code has been sent if the email is registered.' : r.message, r.ok);
        if (r.ok) startCooldown(60); else resendBtn.disabled = false;
    });

    $('changeEmail').addEventListener('click', () => {
        clearInterval(timer);
        $('otp').value = '';
        say('', true);
        go(1);
    });

    /* ---------- step 2: verify code ---------- */
    $('otp').addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
    });

    forms[2].addEventListener('submit', async (e) => {
        e.preventDefault();
        const otp = $('otp').value.trim();
        if (!/^\d{6}$/.test(otp)) {
            say('Enter the 6-digit code from your email.', false);
            return;
        }
        const btn = forms[2].querySelector('.fp-btn');
        busy(btn, true, 'Verifying…');
        const r = await call({ action: 'verify', otp });
        busy(btn, false);

        if (r.ok) {
            clearInterval(timer);
            say(r.message, true);
            go(3);
        } else {
            say(r.message, false);
            $('otp').select();
        }
    });

    /* ---------- step 3: new password ---------- */
    forms[3].addEventListener('submit', async (e) => {
        e.preventDefault();
        const pw = $('pw').value;
        const pw2 = $('pw2').value;
        if (pw.length < 8) { say('Password must be at least 8 characters.', false); return; }
        if (pw !== pw2) { say('Passwords do not match.', false); return; }

        const btn = forms[3].querySelector('.fp-btn');
        busy(btn, true, 'Saving…');
        const r = await call({ action: 'reset', password: pw, confirm: pw2 });

        if (r.ok) {
            say(r.message + ' Redirecting to login…', true);
            btn.textContent = 'Done';
            setTimeout(() => { window.location.href = LOGIN; }, 1500);
        } else {
            busy(btn, false);
            say(r.message, false);
        }
    });

    /* ---------- show / hide password ---------- */
    document.querySelectorAll('.fp-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = $(btn.dataset.target);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    $('backLogin').setAttribute('href', LOGIN);
})();