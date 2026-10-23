<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Чат с Ollama</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #202a32;
            --muted: #718087;
            --paper: #f4f6f2;
            --panel: #fff;
            --line: #e1e7e2;
            --green: #1c755d;
            --green-dark: #145a48;
            --user: #e8f3ed;
            --danger: #aa3d32;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 28px 18px;
            color: var(--ink);
            background-color: var(--paper);
            background-image: radial-gradient(#d7dfd8 0.7px, transparent 0.7px);
            background-size: 18px 18px;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
        }

        .chat-shell {
            width: min(820px, 100%);
            height: min(820px, calc(100vh - 56px));
            min-height: 460px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 18px 55px rgb(33 55 44 / 10%);
            animation: arrive 420ms ease-out both;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 26px;
            border-bottom: 1px solid var(--line);
        }

        .title-row { display: flex; align-items: center; gap: 13px; }
        .mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: white;
            background: var(--green);
            font-family: Georgia, serif;
            font-size: 21px;
        }
        h1 { margin: 0; font-family: Georgia, "Times New Roman", serif; font-size: 22px; font-weight: 500; }
        .status { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; }
        .status::before { width: 8px; height: 8px; border-radius: 50%; background: #6aa982; content: ""; }

        #messages {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 18px;
            overflow-y: auto;
            padding: 28px 26px;
            scroll-behavior: smooth;
        }

        .message { max-width: min(78%, 600px); animation: arrive 220ms ease-out both; }
        .message.user { align-self: flex-end; }
        .message.assistant { align-self: flex-start; }
        .bubble { padding: 13px 16px; border-radius: 12px; font-size: 15px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
        .user .bubble { border: 1px solid #d8e9dd; background: var(--user); }
        .assistant .bubble { border: 1px solid var(--line); background: #fbfcfa; }
        .message.error .bubble { color: var(--danger); border-color: #efd5d1; background: #fff8f7; }

        .composer-wrap { padding: 16px 22px 20px; border-top: 1px solid var(--line); background: #fcfdfb; }
        form { display: flex; align-items: flex-end; gap: 10px; }
        textarea {
            flex: 1;
            min-height: 48px;
            max-height: 150px;
            resize: vertical;
            padding: 13px 15px;
            color: var(--ink);
            border: 1px solid #d6ded8;
            border-radius: 8px;
            outline: none;
            background: white;
            font: inherit;
            font-size: 15px;
            line-height: 1.4;
        }
        textarea:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgb(28 117 93 / 12%); }
        button {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            display: grid;
            place-items: center;
            color: white;
            border: 0;
            border-radius: 8px;
            background: var(--green);
            cursor: pointer;
            transition: background 140ms ease, transform 140ms ease;
        }
        button:hover { background: var(--green-dark); }
        button:active { transform: translateY(1px); }
        button:disabled { opacity: .55; cursor: wait; }
        button svg { width: 20px; height: 20px; }
        .hint { min-height: 16px; margin: 8px 2px 0; color: var(--muted); font-size: 12px; }

        @keyframes arrive { from { opacity: 0; transform: translateY(7px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 600px) {
            body { padding: 0; background-image: none; }
            .chat-shell { width: 100%; height: 100dvh; min-height: 420px; border: 0; border-radius: 0; }
            header { padding: 17px 18px; }
            #messages { padding: 22px 16px; }
            .message { max-width: 90%; }
            .composer-wrap { padding: 12px 12px max(14px, env(safe-area-inset-bottom)); }
            .status { font-size: 12px; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <main class="chat-shell">
        <header>
            <div class="title-row">
                <div class="mark" aria-hidden="true">O</div>
                <h1>Чат с Ollama</h1>
            </div>
            <div class="status">Подключение к модели</div>
        </header>
        <section id="messages" aria-live="polite" aria-label="Сообщения чата"></section>
        <div class="composer-wrap">
            <form id="chat-form">
                <textarea id="message-input" rows="1" maxlength="12000" placeholder="Напишите сообщение..." aria-label="Ваше сообщение" required></textarea>
                <button id="send-button" type="submit" aria-label="Отправить сообщение" title="Отправить сообщение">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12 20 4l-5 16-3-7-8-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m12 13 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </form>
            <p class="hint" id="hint">Enter отправляет, Shift+Enter переносит строку</p>
        </div>
    </main>
    <script>
        const form = document.querySelector('#chat-form');
        const input = document.querySelector('#message-input');
        const messagesElement = document.querySelector('#messages');
        const sendButton = document.querySelector('#send-button');
        const hint = document.querySelector('#hint');
        const messages = [];

        function addMessage(role, content, isError = false) {
            const wrapper = document.createElement('article');
            wrapper.className = `message ${role}${isError ? ' error' : ''}`;
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.textContent = content;
            wrapper.append(bubble);
            messagesElement.append(wrapper);
            messagesElement.scrollTop = messagesElement.scrollHeight;
            return wrapper;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const content = input.value.trim();
            if (!content || sendButton.disabled) return;

            messages.push({ role: 'user', content });
            addMessage('user', content);
            input.value = '';
            sendButton.disabled = true;
            hint.textContent = 'Модель отвечает...';
            const pending = addMessage('assistant', 'Думаю...');
            const abortController = new AbortController();
            const timeoutId = window.setTimeout(() => abortController.abort(), 50000);

            try {
                const response = await fetch('{{ route('test-chat') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ messages }),
                    signal: abortController.signal,
                });
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`Сервер вернул не JSON (HTTP ${response.status}). Проверьте журналы Laravel и Nginx.`);
                }
                pending.remove();

                if (!response.ok) throw new Error(data.message || `Ошибка сервера (HTTP ${response.status}).`);

                if (data.reply === '') {
                    hint.textContent = 'Enter отправляет, Shift+Enter переносит строку';
                    return;
                }

                messages.push({ role: 'assistant', content: data.reply });
                addMessage('assistant', data.reply);
                hint.textContent = 'Enter отправляет, Shift+Enter переносит строку';
            } catch (error) {
                pending.remove();
                messages.pop();
                const message = error.name === 'AbortError'
                    ? 'Модель не ответила за 50 секунд. Проверьте Ollama и журналы сервера.'
                    : error.message || 'Произошла ошибка соединения.';
                addMessage('assistant', message, true);
                hint.textContent = 'Не удалось получить ответ';
            } finally {
                window.clearTimeout(timeoutId);
                sendButton.disabled = false;
                input.focus();
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    </script>
</body>
</html>