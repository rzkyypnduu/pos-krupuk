/**
 * Speech-to-text untuk form Transaksi Baru (POS Krupuk).
 * Alur: mikrofon -> Web Speech API (id-ID) -> POST /pos/speech/parse
 *      -> hasil ekstraksi diterapkan ke form (nama, qty per produk, catatan)
 *      -> form disimpan seperti biasa (user boleh mengoreksi dulu).
 *
 * Mikrofon tetap menyala selama ditekan (continuous + auto-restart), jadi jeda
 * singkat tidak mengakhiri sesi. Transkrip menumpuk antar tekanan mikrofon
 * sampai tombol "Bersihkan" ditekan.
 */
(function () {
    'use strict';

    var PARSE_URL = null;
    var recognition = null;
    var listening = false;
    var shouldListen = false;
    var restartCount = 0;
    var restartTimer = null;
    var MAX_RESTART = 30;
    var segments = [];
    var interimText = '';
    var startedAt = null;
    var speechSet = {};
    var fatal = false;
    var suppressEmptyNotice = false;
    var form = null;
    var els = {};

    function $(id) {
        return document.getElementById(id);
    }

    function setMethod(method) {
        var f = els.method;
        if (f) f.value = method;
    }

    function touchStart() {
        if (!startedAt) {
            startedAt = Date.now();
        }
    }

    function status(text, tone) {
        if (!els.status) return;
        els.status.textContent = text;
        els.status.className = 'tx-speech-status' + (tone ? ' ' + tone : '');
        if (els.dot) els.dot.className = 'tx-speech-dot' + (tone ? ' ' + tone : '');
    }

    function showPanel(show) {
        if (els.panel) els.panel.hidden = !show;
    }

    function transcriptSoFar() {
        return segments.map(function (hyp) {
            return hyp[0] || '';
        }).join(' ').replace(/\s+/g, ' ').trim();
    }

    function alternativesList() {
        var main = transcriptSoFar();
        var alts = [];
        if (!segments.length) return alts;
        for (var k = 1; k < 5; k++) {
            var text = segments.map(function (hyp) {
                return hyp[k] || hyp[0] || '';
            }).join(' ').replace(/\s+/g, ' ').trim();
            if (text && text !== main && alts.indexOf(text) === -1) {
                alts.push(text);
            }
        }
        return alts.slice(0, 4);
    }

    function renderLive() {
        if (!els.transcript) return;
        els.transcript.hidden = false;
        var text = (transcriptSoFar() + ' ' + interimText).replace(/\s+/g, ' ').trim();
        if (els.transcriptText) els.transcriptText.textContent = '"' + text + '"';
    }

    function renderResult(data) {
        if (els.transcript) {
            els.transcript.hidden = false;
            if (els.transcriptText) els.transcriptText.textContent = '"' + data.raw + '"';
        }
        if (els.items) {
            els.items.hidden = false;
            els.items.innerHTML = '';
            if (!data.items || !data.items.length) {
                var none = document.createElement('li');
                none.className = 'tx-speech-item none';
                none.textContent = 'Produk tidak terdeteksi — ucapkan nama produk atau isi manual.';
                els.items.appendChild(none);
            } else {
                data.items.forEach(function (item) {
                    var li = document.createElement('li');
                    li.className = 'tx-speech-item' + (item.confidence === 'loose' ? ' loose' : '');
                    li.textContent = item.name + ' × ' + item.qty + ' ' + (item.unit || 'kg') +
                        ' = ' + item.subtotal.toLocaleString('id-ID') +
                        (item.confidence === 'loose' ? ' (kurang yakin)' : '');
                    els.items.appendChild(li);
                });
            }
        }
    }

    function qtyInput(productId) {
        if (!form) return null;
        return form.querySelector('[data-product-id="' + productId + '"]');
    }

    function applyToForm(data) {
        if (!form) return;

        if (data.name) {
            var nameInput = form.querySelector('[name="txName"]');
            if (nameInput) nameInput.value = data.name;
        }

        var nextSet = {};
        (data.items || []).forEach(function (item) {
            var input = qtyInput(item.product_id);
            if (!input) return;
            input.value = String(item.qty).replace('.', ',');
            input.dispatchEvent(new Event('input', { bubbles: true }));
            nextSet[String(item.product_id)] = true;
        });

        // hanya kolom yang tadi diisi lewat suara yang dibersihkan,
        // input jumlah yang diketik manual tidak tersentuh
        Object.keys(speechSet).forEach(function (pid) {
            if (nextSet[pid]) return;
            var input = qtyInput(pid);
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        speechSet = nextSet;

        if (data.note) {
            var noteInput = form.querySelector('[name="tx_note"]');
            if (noteInput) noteInput.value = data.note;
        }

        if (typeof window.recalcTotals === 'function') {
            window.recalcTotals();
        }
    }

    function parseText(text, alternatives) {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        return fetch(PARSE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ text: text, alternatives: alternatives || [] })
        }).then(function (res) {
            if (!res.ok) {
                var message = 'Gagal memproses teks.';
                return res.json().then(function (body) {
                    if (body && body.message) message = body.message;
                    throw new Error(message);
                }, function () {
                    throw new Error(message);
                });
            }
            return res.json();
        }).then(function (data) {
            if (!data.ok) throw new Error('Hasil parse tidak valid.');
            applyToForm(data);
            renderResult(data);
            setMethod('speech');
            if (els.text) els.text.value = text;
            if (els.parsed) els.parsed.value = JSON.stringify(data);
            if (!data.items || !data.items.length) {
                status('Produk tidak terdeteksi — ucapkan nama produk, atau isi manual.', 'err');
            } else {
                status('Hasil diterapkan ke form. Periksa lalu simpan.', 'ok');
            }
            return data;
        });
    }

    function finalize() {
        var text = transcriptSoFar();
        var suppress = suppressEmptyNotice;
        suppressEmptyNotice = false;
        if (!text) {
            // "Tidak ada suara" tidak ditampilkan kalau pengguna baru menekan Bersihkan/Hapus
            if (!suppress && !fatal) status('Tidak ada suara terdeteksi. Coba lagi.', 'err');
            return;
        }
        showPanel(true);
        status('Memproses "' + text + '"…', 'live');
        parseText(text, alternativesList())['catch'](function (err) {
            status(err.message || 'Gagal memproses teks.', 'err');
        });
    }

    function stopRecognition() {
        var inRestartGap = !!restartTimer;
        shouldListen = false;
        if (restartTimer) {
            clearTimeout(restartTimer);
            restartTimer = null;
        }
        if (recognition && listening) {
            try { recognition.stop(); } catch (e) { /* abaikan */ }
        }
        listening = false;
        if (els.mic) els.mic.classList.remove('listening');
        // sedang di sela auto-restart: tidak akan ada event onend, proses sekarang
        if (inRestartGap) finalize();
    }

    function startRecognition() {
        if (!recognition) return;
        if (restartTimer) {
            clearTimeout(restartTimer);
            restartTimer = null;
        }
        touchStart();
        fatal = false;
        suppressEmptyNotice = false;
        restartCount = 0;
        interimText = '';
        shouldListen = true;
        try {
            recognition.start();
        } catch (e) {
            // sudah berjalan
        }
        listening = true;
        if (els.mic) els.mic.classList.add('listening');
        showPanel(true);
        status('Mendengarkan… ucapkan, contoh: "Budi dua kilo kuning"', 'live');
        if (els.items) els.items.hidden = true;
        renderLive();
    }

    function restartRecognition() {
        if (!recognition) return;
        interimText = '';
        try {
            recognition.start();
        } catch (e) {
            return;
        }
        listening = true;
        if (els.mic) els.mic.classList.add('listening');
        status('Mendengarkan…', 'live');
    }

    function toggleMic() {
        if (!recognition) {
            showPanel(true);
            status('Browser ini belum mendukung speech-to-text. Pakai Chrome/Edge; input manual tetap bisa.', 'err');
            return;
        }
        if (listening || shouldListen) {
            stopRecognition();
            status('Berhenti merekam…', '');
        } else {
            startRecognition();
        }
    }

    /** Reset teks suara + status tersembunyi (dipakai Bersihkan & Hapus). */
    function resetVoiceText() {
        suppressEmptyNotice = true;
        segments = [];
        interimText = '';
        speechSet = {};
        fatal = false;
        if (els.transcript) {
            els.transcript.hidden = true;
            if (els.transcriptText) els.transcriptText.textContent = '';
        }
        if (els.items) { els.items.hidden = true; els.items.innerHTML = ''; }
        if (els.text) els.text.value = '';
        if (els.parsed) els.parsed.value = '';
        setMethod('manual');
        // rekaman dihentikan supaya transkrip tidak langsung terisi lagi
        stopRecognition();
    }

    /**
     * Bersihkan: buang teks suara saja, isian di form
     * (nama, jumlah, catatan) TIDAK disentuh.
     */
    function deleteVoiceText() {
        resetVoiceText();
        showPanel(true);
        status('Teks suara dibersihkan — data di form tidak berubah.', '');
    }

    /** Hapus: buang teks suara + kosongkan semua jumlah produk di form. */
    function clearAll() {
        resetVoiceText();
        if (form) {
            form.querySelectorAll('[data-product-id]').forEach(function (input) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }
        if (typeof window.recalcTotals === 'function') {
            window.recalcTotals();
        }
        showPanel(true);
        status('Dihapus semua. Siap merekam lagi.', '');
    }

    function initRecognition() {
        var Ctor = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!Ctor) {
            if (els.mic) {
                els.mic.disabled = true;
                els.mic.title = 'Speech-to-text tidak didukung browser ini';
                els.mic.classList.add('unsupported');
            }
            return;
        }
        recognition = new Ctor();
        recognition.lang = 'id-ID';
        recognition.interimResults = true;
        recognition.continuous = true;
        recognition.maxAlternatives = 5;

        recognition.onresult = function (event) {
            var interim = '';
            for (var i = event.resultIndex; i < event.results.length; i++) {
                var res = event.results[i];
                var hyps = [];
                for (var j = 0; j < res.length && j < 5; j++) {
                    hyps.push(String(res[j].transcript).trim());
                }
                if (!hyps.length) {
                    hyps.push(String(res[0].transcript).trim());
                }
                if (res.isFinal) {
                    segments.push(hyps);
                } else {
                    interim += res[0].transcript;
                }
            }
            interimText = interim;
            renderLive();
            if (interim) status('Mendengarkan…', 'live');
        };

        recognition.onerror = function (event) {
            var map = {
                'not-allowed': 'Izin mikrofon ditolak. Aktifkan izin mikrofon lalu ulangi.',
                'service-not-allowed': 'Layanan speech tidak tersedia. Pakai input manual.',
                'audio-capture': 'Mikrofon tidak ditemukan.',
                'network': 'Gagal terhubung ke layanan speech (butuh internet).'
            };
            showPanel(true);
            if (map[event.error]) {
                fatal = true;
                shouldListen = false;
                listening = false;
                if (els.mic) els.mic.classList.remove('listening');
                status(map[event.error], 'err');
            } else if (event.error === 'no-speech') {
                status('Menunggu suara…', 'live');
            } else if (event.error !== 'aborted') {
                fatal = true;
                shouldListen = false;
                listening = false;
                if (els.mic) els.mic.classList.remove('listening');
                status('Error speech: ' + event.error + '. Pakai input manual.', 'err');
            }
        };

        recognition.onend = function () {
            // sesi berakhir karena jeda: langsung rekam lagi (jangan parse dulu)
            if (shouldListen && !fatal && restartCount < MAX_RESTART) {
                restartCount++;
                if (restartTimer) clearTimeout(restartTimer);
                restartTimer = setTimeout(restartRecognition, 250);
                return;
            }
            shouldListen = false;
            listening = false;
            if (els.mic) els.mic.classList.remove('listening');
            finalize();
        };
    }

    function initFormTracking() {
        if (!form) return;
        form.addEventListener('input', touchStart, true);
        form.addEventListener('submit', function () {
            touchStart();
            if (els.method) setMethod(els.method.value || 'manual');
            if (startedAt && els.duration) {
                els.duration.value = String(Date.now() - startedAt);
                if (els.started) els.started.value = new Date(startedAt).toISOString();
            }
        });
    }

    function init() {
        var panel = $('txSpeechPanel');
        if (!panel) return;
        PARSE_URL = panel.getAttribute('data-parse-url');
        els = {
            panel: panel,
            mic: $('txMicBtn'),
            status: $('txSpeechStatus'),
            dot: $('txSpeechDot'),
            transcript: $('txSpeechTranscript'),
            transcriptText: $('txSpeechTranscriptText'),
            items: $('txSpeechItems'),
            deleteBtn: $('txSpeechDelete'),
            clear: $('txSpeechClear'),
            method: $('inputMethod'),
            text: $('inputText'),
            parsed: $('inputParsed'),
            duration: $('inputDurationMs'),
            started: $('inputStartedAt')
        };
        form = $('txForm');
        if (els.mic) els.mic.addEventListener('click', toggleMic);
        if (els.deleteBtn) els.deleteBtn.addEventListener('click', deleteVoiceText);
        if (els.clear) els.clear.addEventListener('click', clearAll);
        setMethod('manual');
        initRecognition();
        initFormTracking();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
