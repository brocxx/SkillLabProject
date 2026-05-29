<!-- =============================================
     CHATBOT WIDGET
     Drop this include at the bottom of any page
     ============================================= -->

<!-- The round floating button -->
<button id="chat-toggle-btn" onclick="toggleChat()" title="Ask about inventory or shipments">
    💬
</button>

<!-- The chat popup box -->
<div id="chat-box">
    <div id="chat-header">
        <span>📦 Supply Assistant</span>
        <button onclick="toggleChat()" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">✕</button>
    </div>

    <div id="chat-messages">
        <div class="bot-msg">Hi! Ask me anything about inventory stock or shipments. 📦</div>
    </div>

    <div id="chat-input-row">
        <input type="text" id="chat-input" placeholder="e.g. What's in stock?" onkeydown="if(event.key==='Enter') sendMessage()" />
        <button onclick="sendMessage()">Send</button>
    </div>
</div>

<style>
    /* The round floating button */
    #chat-toggle-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 55px;
        height: 55px;
        border-radius: 50%;
        background-color: #16a085;
        color: white;
        font-size: 22px;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        transition: background-color 0.2s, transform 0.1s;
    }

    #chat-toggle-btn:hover {
        background-color: #117a65;
        transform: scale(1.05);
    }

    /* The popup chat window */
    #chat-box {
        display: none;
        position: fixed;
        bottom: 95px;
        right: 30px;
        width: 320px;
        height: 420px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        z-index: 9998;
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #dcdde1;
    }

    /* Header bar */
    #chat-header {
        background-color: #34495e;
        color: white;
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Messages area */
    #chat-messages {
        flex: 1;
        padding: 14px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #f8f9fa;
    }

    /* Bot message bubble */
    .bot-msg {
        background: white;
        border: 1px solid #dcdde1;
        border-radius: 10px 10px 10px 2px;
        padding: 10px 12px;
        font-size: 13px;
        color: #2c3e50;
        max-width: 90%;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    /* User message bubble */
    .user-msg {
        background: #16a085;
        color: white;
        border-radius: 10px 10px 2px 10px;
        padding: 10px 12px;
        font-size: 13px;
        max-width: 85%;
        align-self: flex-end;
        line-height: 1.5;
    }

    /* Input row at the bottom */
    #chat-input-row {
        display: flex;
        padding: 10px;
        border-top: 1px solid #dcdde1;
        background: white;
        gap: 8px;
    }

    #chat-input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #dcdde1;
        border-radius: 20px;
        font-size: 13px;
        outline: none;
        width: auto;
    }

    #chat-input:focus {
        border-color: #16a085;
    }

    #chat-input-row button {
        background: #16a085;
        color: white;
        border: none;
        border-radius: 20px;
        padding: 8px 14px;
        font-size: 13px;
        cursor: pointer;
        font-weight: 600;
    }

    #chat-input-row button:hover {
        background: #117a65;
    }
</style>

<script>
    // Open or close the chat box
    function toggleChat() {
        var box = document.getElementById('chat-box');
        if (box.style.display === 'flex') {
            box.style.display = 'none';
        } else {
            box.style.display = 'flex';
            document.getElementById('chat-input').focus();
        }
    }

    // Add a message bubble to the chat window
    function addMessage(text, who) {
        var messages = document.getElementById('chat-messages');
        var div = document.createElement('div');
        div.className = who === 'user' ? 'user-msg' : 'bot-msg';
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight; // auto-scroll down
    }

    // Send the user's message to chatbot.php
    function sendMessage() {
        var input = document.getElementById('chat-input');
        var msg   = input.value.trim();

        if (msg === '') return;

        addMessage(msg, 'user');
        input.value = '';

        // Show a typing indicator
        var messages = document.getElementById('chat-messages');
        var typing = document.createElement('div');
        typing.className = 'bot-msg';
        typing.id = 'typing-indicator';
        typing.textContent = '...typing';
        messages.appendChild(typing);
        messages.scrollTop = messages.scrollHeight;

        // Send the message to the backend
        var formData = new FormData();
        formData.append('message', msg);

        fetch('chatbot.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            // Remove typing indicator
            var t = document.getElementById('typing-indicator');
            if (t) t.remove();

            // Show bot reply
            addMessage(data.reply, 'bot');
        })
        .catch(function() {
            // Remove typing indicator
            var t = document.getElementById('typing-indicator');
            if (t) t.remove();

            addMessage("Sorry, something went wrong. Try again!", 'bot');
        });
    }
</script>
