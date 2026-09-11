"""
multichannel_bot.py
---------------------
SMS (Smishing), Voice (Vishing), aur WhatsApp phishing dispatch karta hai.
MOCK_MODE = True  -> sirf console print, koi real message nahi
MOCK_MODE = False -> real Twilio API use karta hai
"""

import sys
import re
import mysql.connector
from mysql.connector import Error

# CONFIG
MOCK_MODE        = True   # False karo jab Twilio credentials ready hon
TWILIO_SID       = "ACxxxxxxxxxxxxxxx"
TWILIO_TOKEN     = "xxxxxxxxxxxxxxx"
TWILIO_SMS_FROM  = "+1xxxxxxxxxx"
TWILIO_WA_FROM   = "whatsapp:+14155238886"
SEND_REAL_EMAIL  = False

DB_CONFIG = {
    "host":     "localhost",
    "user":     "cybershield_user",
    "password": "kali",
    "database": "cybershield"
}

BASE_URL = "http://192.168.1.14/cybershield"  # apna real IP

def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] DB connection fail: {e}")
        return None

def _log_dispatch(campaign_id, target_id, tracking_token):
    conn = get_connection()
    if conn is None:
        return
    cursor = conn.cursor()
    cursor.execute(
        "UPDATE simulation_logs SET is_email_sent = 1 WHERE campaign_id = %s AND target_id = %s AND tracking_token = %s",
        (campaign_id, target_id, tracking_token)
    )
    conn.commit()
    cursor.close()
    conn.close()

def _get_campaign_targets(campaign_id):
    conn = get_connection()
    if conn is None:
        return [], ""
    cursor = conn.cursor()
    cursor.execute(
        """SELECT t.body_html FROM campaigns c
           LEFT JOIN templates t ON c.template_id = t.template_id
           WHERE c.campaign_id = %s""",
        (campaign_id,)
    )
    camp = cursor.fetchone()
    raw_body = camp[0] if camp and camp[0] else "Verify your account: {{tracking_link}}"
    message_body = re.sub(r'<[^>]+>', '', raw_body).strip()

    cursor.execute(
        """SELECT t.target_id, t.name, t.phone_number, sl.tracking_token
           FROM campaign_targets ct
           JOIN targets t ON ct.target_id = t.target_id
           JOIN simulation_logs sl ON sl.target_id = t.target_id AND sl.campaign_id = %s
           WHERE ct.campaign_id = %s""",
        (campaign_id, campaign_id)
    )
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows, message_body

# WhatsApp
def send_whatsapp(phone_number, message_body, campaign_id, target_id, tracking_token):
    phishing_url = f"{BASE_URL}/landing_page.php?uid={tracking_token}"
    full_message = message_body.replace("{{tracking_link}}", phishing_url)

    if MOCK_MODE:
        print(f"[MOCK WA] -> {phone_number}")
        print(f"[MOCK WA] Message: {full_message}")
    else:
        try:
            from twilio.rest import Client
            client = Client(TWILIO_SID, TWILIO_TOKEN)
            msg = client.messages.create(
                from_=TWILIO_WA_FROM,
                body=full_message,
                to=f"whatsapp:{phone_number}"
            )
            print(f"[WA SENT] -> {phone_number} | SID: {msg.sid}")
        except Exception as e:
            print(f"[WA ERROR] {phone_number}: {e}")

    _log_dispatch(campaign_id, target_id, tracking_token)

# SMS
def send_sms(phone_number, message_body, campaign_id, target_id, tracking_token):
    phishing_url = f"{BASE_URL}/landing_page.php?uid={tracking_token}"
    full_message = message_body.replace("{{tracking_link}}", phishing_url)

    if MOCK_MODE:
        print(f"[MOCK SMS] -> {phone_number}: {full_message}")
    else:
        try:
            from twilio.rest import Client
            client = Client(TWILIO_SID, TWILIO_TOKEN)
            msg = client.messages.create(
                from_=TWILIO_SMS_FROM,
                body=full_message,
                to=phone_number
            )
            print(f"[SMS SENT] -> {phone_number} | SID: {msg.sid}")
        except Exception as e:
            print(f"[SMS ERROR] {phone_number}: {e}")

    _log_dispatch(campaign_id, target_id, tracking_token)

# Voice
def trigger_vishing_call(phone_number, script_text, campaign_id, target_id, tracking_token):
    if MOCK_MODE:
        print(f"[MOCK CALL] -> {phone_number}: {script_text}")
    else:
        try:
            from twilio.rest import Client
            client = Client(TWILIO_SID, TWILIO_TOKEN)
            call = client.calls.create(
                twiml=f'<Response><Say voice="alice">{script_text}</Say></Response>',
                to=phone_number,
                from_=TWILIO_SMS_FROM
            )
            print(f"[CALL MADE] -> {phone_number} | SID: {call.sid}")
        except Exception as e:
            print(f"[CALL ERROR] {phone_number}: {e}")

    _log_dispatch(campaign_id, target_id, tracking_token)

# WhatsApp Campaign Dispatch
def dispatch_whatsapp_campaign(campaign_id):
    rows, message_body = _get_campaign_targets(campaign_id)
    if not rows:
        print("[WARN] Koi target nahi mila — pehle dispatcher.py chalao.")
        return
    for target_id, name, phone, token in rows:
        if not phone:
            print(f"[SKIP] {name} — phone number missing")
            continue
        personalized = message_body.replace("{{name}}", name)
        print(f"[WA DISPATCH] {name} ({phone})")
        send_whatsapp(phone, personalized, campaign_id, target_id, token)
    print(f"[DONE] WhatsApp campaign {campaign_id} dispatch complete.")

# SMS Campaign Dispatch
def dispatch_sms_campaign(campaign_id):
    rows, message_body = _get_campaign_targets(campaign_id)
    if not rows:
        print("[WARN] Koi target nahi mila — pehle dispatcher.py chalao.")
        return
    for target_id, name, phone, token in rows:
        if not phone:
            print(f"[SKIP] {name} — phone number missing")
            continue
        personalized = message_body.replace("{{name}}", name)
        print(f"[SMS DISPATCH] {name} ({phone})")
        send_sms(phone, personalized, campaign_id, target_id, token)
    print(f"[DONE] SMS campaign {campaign_id} dispatch complete.")

# Voice Campaign Dispatch
def dispatch_voice_campaign(campaign_id):
    rows, _ = _get_campaign_targets(campaign_id)
    if not rows:
        print("[WARN] Koi target nahi mila — pehle dispatcher.py chalao.")
        return
    for target_id, name, phone, token in rows:
        if not phone:
            print(f"[SKIP] {name} — phone number missing")
            continue
        script = f"Hello {name}, this is a security alert from your IT department. Your account has been compromised. Please visit our portal immediately to secure your account."
        print(f"[VOICE DISPATCH] {name} ({phone})")
        trigger_vishing_call(phone, script, campaign_id, target_id, token)
    print(f"[DONE] Voice campaign {campaign_id} dispatch complete.")

if __name__ == "__main__":
    # Test
    send_whatsapp("+91XXXXXXXXXX", "Hi, verify your account: {{tracking_link}}", 1, 1, "test-token-wa")
    send_sms("+91XXXXXXXXXX", "Alert: {{tracking_link}}", 1, 1, "test-token-sms")
