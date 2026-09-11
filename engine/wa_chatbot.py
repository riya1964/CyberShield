"""
wa_chatbot.py
--------------
WhatsApp AI Chatbot Phishing Engine

ARCHITECTURE:
1. Twilio WhatsApp Sandbox se incoming messages receive karo (webhook)
2. Ollama (local LLM) ya OpenAI se conversational response generate karo
3. Response target ko WhatsApp pe bhejo
4. Target ke responses track karo - jab link click kare to is_wa_clicked mark karo

FLOW:
Target receives WA message
    -> Replies to message
    -> Flask webhook receives reply
    -> LLM generates convincing response
    -> Bot replies with phishing link when appropriate
    -> Target clicks link -> landing_page.php tracks event

MOCK_MODE = True  -> sirf simulate karta hai
MOCK_MODE = False -> real Twilio + Ollama/OpenAI use karta hai

Real activation ke liye:
- Twilio WhatsApp Sandbox (free) ya paid account
- Ollama local install (free) ya OpenAI API (paid)
- Public URL chahiye webhook ke liye (ngrok free se ho sakta hai)
"""

import sys
import json
import mysql.connector
from mysql.connector import Error
from datetime import datetime

# ── CONFIG ────────────────────────────────────────────────────────────────
MOCK_MODE = True

# Twilio Config
TWILIO_SID       = "ACxxxxxxxxxxxxxxx"
TWILIO_TOKEN     = "xxxxxxxxxxxxxxx"
TWILIO_WA_FROM   = "whatsapp:+14155238886"  # Sandbox number

# LLM Config - Choose one
USE_OLLAMA  = True   # Free, local
USE_OPENAI  = False  # Paid

OLLAMA_URL    = "http://localhost:11434/api/generate"
OLLAMA_MODEL  = "llama3.2"
OPENAI_KEY    = "your-openai-key"

BASE_URL = "http://192.168.1.14/cybershield"

DB_CONFIG = {
    "host":     "localhost",
    "user":     "cybershield_user",
    "password": "kali",
    "database": "cybershield"
}

# ── Conversation State Store (In-Memory) ─────────────────────────────────
# Real deployment me Redis ya DB use karo
conversation_store = {}

# ── DB ─────────────────────────────────────────────────────────────────────
def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] DB: {e}")
        return None

def get_token_for_phone(phone_number):
    """Phone number se tracking token fetch karo."""
    conn = get_connection()
    if not conn: return None
    cur = conn.cursor()
    # Phone number se target find karo
    cur.execute(
        """SELECT sl.tracking_token, sl.campaign_id, sl.target_id
           FROM simulation_logs sl
           JOIN targets t ON sl.target_id = t.target_id
           WHERE t.phone_number = %s
           ORDER BY sl.event_timestamp DESC LIMIT 1""",
        (phone_number.replace("whatsapp:", "").replace("+", ""),)
    )
    row = cur.fetchone()
    cur.close(); conn.close()
    return row

def mark_wa_clicked(tracking_token):
    """Target ne WA link click kiya — is_wa_clicked mark karo."""
    conn = get_connection()
    if not conn: return
    cur = conn.cursor()
    cur.execute(
        "UPDATE simulation_logs SET is_wa_clicked=1 WHERE tracking_token=%s",
        (tracking_token,)
    )
    conn.commit(); cur.close(); conn.close()
    print(f"[TRACKED] WA click: {tracking_token}")

# ── LLM Response Generation ───────────────────────────────────────────────
def generate_llm_response(user_message, conversation_history, target_name, tracking_token):
    """
    LLM se conversational phishing response generate karta hai.

    SYSTEM PROMPT:
    - Bot ek bank/company ka customer support agent ban-ta hai
    - Convincingly respond karta hai
    - Jab appropriate ho phishing link deta hai
    - Target ko suspicious nahi lagta

    MOCK_MODE me: simple scripted responses
    Real mode me: Ollama ya OpenAI se dynamic responses
    """

    phishing_url = f"{BASE_URL}/landing_page.php?uid={tracking_token}"

    system_prompt = f"""You are a customer support agent for a bank.
You are chatting with {target_name} on WhatsApp.
Your goal is to convince them to click this link: {phishing_url}
Be professional, helpful, and create urgency about account security.
Keep responses short (1-3 sentences) as this is WhatsApp.
When the time is right, share the verification link naturally."""

    if MOCK_MODE:
        # Scripted mock responses based on conversation stage
        stage = len(conversation_history)
        mock_responses = [
            f"Hello {target_name}! This is Bank Security Team. We've detected unusual activity on your account. Are you currently using your account?",
            "We need to verify your identity immediately to prevent unauthorized access. Please confirm your registered email address.",
            f"Thank you. To secure your account, please click this link immediately and complete verification: {phishing_url}",
            "This link expires in 15 minutes. Please complete verification as soon as possible.",
            "Have you completed the verification? Your account security depends on it."
        ]
        response = mock_responses[min(stage, len(mock_responses)-1)]
        print(f"[MOCK LLM] Stage {stage}: {response[:60]}...")
        return response

    elif USE_OLLAMA:
        try:
            import requests
            # Build conversation context
            messages = "\n".join([
                f"{'Agent' if i%2==0 else 'Customer'}: {msg}"
                for i, msg in enumerate(conversation_history)
            ])

            prompt = f"{system_prompt}\n\nConversation so far:\n{messages}\nCustomer: {user_message}\nAgent:"

            response = requests.post(OLLAMA_URL, json={
                "model": OLLAMA_MODEL,
                "prompt": prompt,
                "stream": False,
                "options": {"temperature": 0.7, "num_predict": 100}
            }, timeout=30)

            if response.status_code == 200:
                return response.json()['response'].strip()
            else:
                return f"Thank you for contacting us {target_name}. Please verify: {phishing_url}"

        except Exception as e:
            print(f"[ERROR] Ollama: {e}")
            return f"Please verify your account immediately: {phishing_url}"

    elif USE_OPENAI:
        try:
            import openai
            openai.api_key = OPENAI_KEY
            messages = [{"role": "system", "content": system_prompt}]
            for i, msg in enumerate(conversation_history):
                role = "assistant" if i % 2 == 0 else "user"
                messages.append({"role": role, "content": msg})
            messages.append({"role": "user", "content": user_message})

            response = openai.ChatCompletion.create(
                model="gpt-3.5-turbo",
                messages=messages,
                max_tokens=150
            )
            return response.choices[0].message.content.strip()
        except Exception as e:
            print(f"[ERROR] OpenAI: {e}")
            return f"Please verify: {phishing_url}"

# ── Send WhatsApp Message ─────────────────────────────────────────────────
def send_wa_message(to_number, message):
    """WhatsApp pe message bhejta hai."""
    if MOCK_MODE:
        print(f"[MOCK WA SEND] -> {to_number}: {message[:80]}...")
        return True
    else:
        try:
            from twilio.rest import Client
            client = Client(TWILIO_SID, TWILIO_TOKEN)
            msg = client.messages.create(
                from_=TWILIO_WA_FROM,
                body=message,
                to=f"whatsapp:{to_number}"
            )
            print(f"[WA SENT] -> {to_number} | SID: {msg.sid}")
            return True
        except Exception as e:
            print(f"[ERROR] WA send: {e}")
            return False

# ── Initial WA Campaign Message ───────────────────────────────────────────
def send_initial_wa_message(phone_number, target_name, campaign_id, target_id, tracking_token):
    """
    Campaign start hone pe initial WA message bhejta hai.
    Ye conversation ki shuruat karta hai.
    """
    initial_message = f"Hello {target_name}! This is Bank Security Alert. We have detected suspicious login attempts on your account. Please reply 'YES' if this was not you so we can secure your account immediately."

    if MOCK_MODE:
        print(f"[MOCK WA INITIAL] -> {phone_number}")
        print(f"[MOCK WA INITIAL] Message: {initial_message}")
    else:
        send_wa_message(phone_number, initial_message)

    # Conversation initialize karo
    conversation_store[phone_number] = {
        'target_name': target_name,
        'tracking_token': tracking_token,
        'campaign_id': campaign_id,
        'target_id': target_id,
        'history': [initial_message],
        'started_at': datetime.now().isoformat()
    }

    print(f"[WA CHATBOT] Conversation initialized for {target_name} ({phone_number})")

# ── Webhook Handler (Flask) ───────────────────────────────────────────────
def create_webhook_app():
    """
    Flask webhook server banata hai jo Twilio se incoming WA messages receive karta hai.

    Setup:
    1. flask run --port=5001
    2. ngrok http 5001  (public URL ke liye)
    3. Twilio Console me webhook URL set karo:
       https://your-ngrok-url/wa_webhook

    Real deployment me:
    - Public server pe deploy karo
    - HTTPS zaroori hai (Twilio requires it)
    - Ya ngrok free tier use karo testing ke liye
    """
    try:
        from flask import Flask, request, Response
        app = Flask(__name__)

        @app.route('/wa_webhook', methods=['POST'])
        def wa_webhook():
            """Twilio se incoming WA message receive karta hai."""
            from_number = request.form.get('From', '').replace('whatsapp:', '')
            body = request.form.get('Body', '').strip()

            print(f"[WA RECEIVED] From: {from_number} | Message: {body}")

            # Conversation state fetch karo
            if from_number in conversation_store:
                conv = conversation_store[from_number]
                conv['history'].append(body)

                # LLM se response generate karo
                response = generate_llm_response(
                    body,
                    conv['history'],
                    conv['target_name'],
                    conv['tracking_token']
                )

                conv['history'].append(response)

                # Agar link mention hua response me to mark clicked
                if conv['tracking_token'] in response:
                    mark_wa_clicked(conv['tracking_token'])

                # Response bhejo
                send_wa_message(from_number, response)

                # TwiML response (Twilio ko confirm karo)
                return Response(
                    '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
                    content_type='text/xml'
                )
            else:
                # Unknown number - generic response
                return Response(
                    '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
                    content_type='text/xml'
                )

        @app.route('/wa_status', methods=['GET'])
        def wa_status():
            """Active conversations ka status dikhata hai."""
            return {
                'active_conversations': len(conversation_store),
                'conversations': [
                    {
                        'phone': k,
                        'target': v['target_name'],
                        'messages': len(v['history']),
                        'started': v['started_at']
                    }
                    for k, v in conversation_store.items()
                ]
            }

        return app

    except ImportError:
        print("[ERROR] Flask not installed. Run: pip install flask")
        return None

# ── Campaign Dispatch ─────────────────────────────────────────────────────
def dispatch_wa_chatbot_campaign(campaign_id):
    """
    Campaign ke saare targets ko initial WA message bhejta hai
    aur chatbot conversations initialize karta hai.
    """
    conn = get_connection()
    if not conn: return

    cursor = conn.cursor()
    cursor.execute(
        """SELECT t.target_id, t.name, t.phone_number, sl.tracking_token
           FROM campaign_targets ct
           JOIN targets t ON ct.target_id = t.target_id
           JOIN simulation_logs sl ON sl.target_id = t.target_id AND sl.campaign_id = %s
           WHERE ct.campaign_id = %s""",
        (campaign_id, campaign_id)
    )
    rows = cursor.fetchall()
    cursor.close(); conn.close()

    if not rows:
        print("[WARN] Koi target nahi — pehle dispatcher.py chalao.")
        return

    for target_id, name, phone, token in rows:
        if not phone:
            print(f"[SKIP] {name} — phone missing")
            continue
        print(f"[WA CHATBOT DISPATCH] {name} ({phone})")
        send_initial_wa_message(phone, name, campaign_id, target_id, token)

    print(f"[DONE] WA Chatbot campaign {campaign_id} initialized.")
    print(f"[INFO] Start webhook server: python3 wa_chatbot.py --server")

# ── TEST ────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == '--server':
        print("=== Starting WA Chatbot Webhook Server ===")
        print("Webhook URL: http://localhost:5001/wa_webhook")
        print("Status URL: http://localhost:5001/wa_status")
        print("For public URL: ngrok http 5001")
        app = create_webhook_app()
        if app:
            app.run(port=5001, debug=False)
    else:
        print("=== WhatsApp AI Chatbot Test ===")
        print(f"Mode: {'MOCK' if MOCK_MODE else 'REAL'}")
        print(f"LLM: {'Ollama' if USE_OLLAMA else 'OpenAI'}")
        print()

        # Test conversation
        send_initial_wa_message(
            "+91XXXXXXXXXX",
            "Riya",
            campaign_id=1,
            target_id=1,
            tracking_token="test-wa-token-123"
        )
        print()

        # Simulate target reply
        print("--- Simulating target reply: 'YES, I did not login' ---")
        response = generate_llm_response(
            "YES, I did not login",
            ["Hello Riya! This is Bank Security..."],
            "Riya",
            "test-wa-token-123"
        )
        print(f"Bot response: {response}")
        print()
        print("To start webhook server: python3 wa_chatbot.py --server")
