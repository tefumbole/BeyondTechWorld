<div class="mai-help">
    <header class="chats-head">
        <div class="chats-head-person">
            <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'more']) }}" aria-label="Back">‹ Back</a>
            <div>
                <h2>Whats MAI help</h2>
                <p>This inbox is {{ $sessionPhone ?: '+237 675 321 739' }}</p>
            </div>
        </div>
    </header>
    <div class="mai-help-body">
        <p class="mai-help-lead">Use these steps to check that this WhatsApp session is working. Reply yourself. MAI can draft a reply, and you send it.</p>
        <ol class="mai-help-steps">
            <li>
                <h3>1. Confirm the line</h3>
                <p>Open Chats. The label must show <strong>{{ $sessionPhone ?: '+237 675 321 739' }}</strong>. Send a WhatsApp to that number from another phone. It should appear in the list.</p>
                <img src="{{ url('public/whatsapp-hub/help/line.svg') }}" alt="Chat list showing WhatsApp +237 675 321 739 and an unread message">
            </li>
            <li>
                <h3>2. Send a reply</h3>
                <p>Open that chat. Type a short message and tap the send arrow. Use + to attach a photo or a document, or paste one into the box. The tick should move from Sent to Delivered or Read.</p>
                <img src="{{ url('public/whatsapp-hub/help/send.svg') }}" alt="A reply in the chat and the message box with attach and send">
            </li>
            <li>
                <h3>3. Ask MAI</h3>
                <p>Tap the MAI button in the message box. Type in the white space. Attach is the gold button. You can also paste a file into that box. Tap MAI for the answer, then Use this reply, then send it yourself.</p>
                <img src="{{ url('public/whatsapp-hub/help/mai.svg') }}" alt="MAI panel with a typing area, Attach, and the MAI button">
            </li>
            <li>
                <h3>4. Keep it on your phone</h3>
                <p>Tap App. On iPhone choose Share, then Add to Home Screen. The name is Whats MAI. If the icon is old, delete that shortcut and add it again.</p>
                <img src="{{ url('public/whatsapp-hub/help/home.svg') }}" alt="Whats MAI home screen icon and the Sent, Delivered, and Read ticks">
            </li>
        </ol>
        <p class="mai-help-note">If a yellow note says the phone is not linked, reconnect this same number in Wasender. Do not scan a code for a different number into this inbox.</p>
    </div>
</div>
<style>
    .mai-help { display: flex; flex-direction: column; min-height: 0; height: 100%; background: #f7faff; }
    .mai-help-body { overflow: auto; padding: 8px 18px 28px; }
    .mai-help-lead, .mai-help-note { margin: 0 0 14px; color: #3b4a6b; font-size: 15px; line-height: 1.45; }
    .mai-help-note { margin-top: 8px; padding: 12px 14px; border-radius: 14px; background: #fff8e6; color: #072f6b; box-shadow: inset 0 0 0 1px #c6ab47; }
    .mai-help-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 18px; }
    .mai-help-steps h3 { margin: 0 0 6px; color: #072f6b; font-size: 18px; }
    .mai-help-steps p { margin: 0 0 10px; color: #3b4a6b; line-height: 1.45; }
    .mai-help-steps img { display: block; width: 100%; max-width: 720px; height: auto; border-radius: 18px; background: #fff; }
</style>
