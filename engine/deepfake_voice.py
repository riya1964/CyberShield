"""
deepfake_voice.py
------------------
Deepfake Voice Phishing (Vishing) Engine

ARCHITECTURE:
1. ElevenLabs API se target ki aawaaz clone karo (ya pre-set voice use karo)
2. Clone ki gayi aawaaz se phishing script ka audio generate karo
3. Generated audio file ko Twilio Voice Call me play karo (TwiML)
4. Target ka call answer hone pe audio play hoga

MOCK_MODE = True  -> sirf simulate karta hai, koi real call nahi
MOCK_MODE = False -> real ElevenLabs + Twilio use karta hai

Real activation ke liye:
- ElevenLabs API key chahiye (free tier: 10,000 chars/month)
- Twilio paid account chahiye (voice calls)
- ElevenLabs me ek voice ID set karna hoga
"""

import os
import sys
import mysql.connector
from mysql.connector import Error

# ── CONFIG ────────────────────────────────────────────────────────────────
MOCK_MODE = True  # False karo jab real APIs ready hon

ELEVENLABS_API_KEY = "your-elevenlabs-api-key"
ELEVENLABS_VOICE_ID = "21m00Tcm4TlvDq8ikWAM"  # Default "Rachel" voice
ELEVENLABS_API_URL = "https://api.elevenlabs.io/v1/text-to-speech"

TWILIO_SID    = "ACxxxxxxxxxxxxxxx"
TWILIO_TOKEN  = "xxxxxxxxxxxxxxx"
TWILIO_FROM   = "+1xxxxxxxxxx"

BASE_URL = "http://192.168.1.14/cybershield"
AUDIO_DIR = "/var/www/html/cybershield/uploads/audio"

DB_CONFIG = {
    "host":     "localhost",
    "user":     "cybershield_user",
    "password": "kali",
    "database": "cybershield"
}

# ── DB ─────────────────────────────────────────────────────────────────────
def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] DB: {e}")
        return None

def _log_dispatch(campaign_id, target_id, tracking_token):
    conn = get_connection()
    if not conn: return
    cur = conn.cursor()
    cur.execute(
        "UPDATE simulation_logs SET is_email_sent=1 WHERE campaign_id=%s AND target_id=%s AND tracking_token=%s",
        (campaign_id, target_id, tracking_token)
    )
    conn.commit(); cur.close(); conn.close()

# ── STEP 1: Generate Deepfake Audio via ElevenLabs ─────────────────────────
def generate_deepfake_audio(script_text, output_filename="vishing_audio.mp3"):
    """
    ElevenLabs API se text-to-speech audio generate karta hai.
    MOCK_MODE me: sirf ek dummy audio file create karta hai.

    Real mode me jo hoga:
    - ElevenLabs ki API call se high-quality human-like voice generate hogi
    - Voice ID se specific accent/gender/tone select kar sakte ho
    - Generated MP3 file /uploads/audio/ me save hogi
    - Twilio is file ko call me play karega
    """
    os.makedirs(AUDIO_DIR, exist_ok=True)
    output_path = os.path.join(AUDIO_DIR, output_filename)

    if MOCK_MODE:
        print(f"[MOCK DEEPFAKE] Generating audio for script: '{script_text[:50]}...'")
        print(f"[MOCK DEEPFAKE] Voice ID: {ELEVENLABS_VOICE_ID}")
        print(f"[MOCK DEEPFAKE] Output would be saved to: {output_path}")

        # Mock audio file banao (empty file as placeholder)
        with open(output_path, 'wb') as f:
            f.write(b'MOCK_AUDIO_CONTENT')

        print(f"[MOCK DEEPFAKE] Audio generated (mock): {output_filename}")
        return output_path

    else:
        # Real ElevenLabs API call
        try:
            import requests
            headers = {
                "xi-api-key": ELEVENLABS_API_KEY,
                "Content-Type": "application/json"
            }
            payload = {
                "text": script_text,
                "model_id": "eleven_monolingual_v1",
                "voice_settings": {
                    "stability": 0.5,
                    "similarity_boost": 0.75
                }
            }
            url = f"{ELEVENLABS_API_URL}/{ELEVENLABS_VOICE_ID}"
            response = requests.post(url, json=payload, headers=headers)

            if response.status_code == 200:
                with open(output_path, 'wb') as f:
                    f.write(response.content)
                print(f"[DEEPFAKE] Audio generated: {output_filename}")
                return output_path
            else:
                print(f"[ERROR] ElevenLabs: {response.status_code} - {response.text}")
                return None
        except Exception as e:
            print(f"[ERROR] ElevenLabs generation failed: {e}")
            return None

# ── STEP 2: Make Deepfake Voice Call via Twilio ─────────────────────────────
def make_deepfake_call(phone_number, script_text, campaign_id, target_id, tracking_token):
    """
    Target ko deepfake voice call karta hai.

    Real mode flow:
    1. ElevenLabs se audio generate karo
    2. Audio file ko public URL pe serve karo
    3. Twilio se call karo - TwiML <Play> tag se audio play karega
    4. Target call receive karega aur deepfake voice sunegi

    MOCK_MODE me: sirf simulate karta hai
    """
    audio_filename = f"vishing_{campaign_id}_{target_id}.mp3"
    audio_path = generate_deepfake_audio(script_text, audio_filename)

    if MOCK_MODE:
        print(f"[MOCK DEEPFAKE CALL] -> {phone_number}")
        print(f"[MOCK DEEPFAKE CALL] Script: {script_text}")
        print(f"[MOCK DEEPFAKE CALL] Audio: {audio_filename}")
        print(f"[MOCK DEEPFAKE CALL] TwiML would play: {BASE_URL}/uploads/audio/{audio_filename}")
    else:
        try:
            from twilio.rest import Client
            client = Client(TWILIO_SID, TWILIO_TOKEN)

            audio_url = f"{BASE_URL}/uploads/audio/{audio_filename}"

            # TwiML: audio play karega
            twiml = f"""<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Play>{audio_url}</Play>
    <Pause length="2"/>
    <Say>Please press 1 to verify your account or press 2 to disconnect.</Say>
    <Gather numDigits="1" action="{BASE_URL}/vishing_response.php?uid={tracking_token}"/>
</Response>"""

            call = client.calls.create(
                twiml=twiml,
                to=phone_number,
                from_=TWILIO_FROM
            )
            print(f"[DEEPFAKE CALL] -> {phone_number} | SID: {call.sid}")
        except Exception as e:
            print(f"[ERROR] Twilio call failed: {e}")

    _log_dispatch(campaign_id, target_id, tracking_token)

# ── STEP 3: Campaign Dispatch ───────────────────────────────────────────────
def dispatch_deepfake_campaign(campaign_id):
    """
    Campaign ke saare targets ko deepfake voice call karta hai.
    simulation_logs se token fetch karta hai.
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
    cursor.close()
    conn.close()

    if not rows:
        print("[WARN] Koi target nahi mila — pehle dispatcher.py chalao.")
        return

    for target_id, name, phone, token in rows:
        if not phone:
            print(f"[SKIP] {name} — phone number missing")
            continue

        # Personalized phishing script
        script = (
            f"Hello {name}, this is calling from IT Security Department. "
            f"We have detected unauthorized access to your account. "
            f"Your account will be suspended in 30 minutes unless you verify immediately. "
            f"Please stay on the line to verify your identity."
        )

        print(f"[DEEPFAKE DISPATCH] {name} ({phone})")
        make_deepfake_call(phone, script, campaign_id, target_id, token)

    print(f"[DONE] Deepfake campaign {campaign_id} complete.")

# ── TEST ────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    print("=== Deepfake Voice Engine Test ===")
    print(f"Mode: {'MOCK' if MOCK_MODE else 'REAL'}")
    print()

    # Test audio generation
    audio = generate_deepfake_audio(
        "Hello, this is IT Security. Your account has been compromised.",
        "test_audio.mp3"
    )
    print(f"Audio path: {audio}")
    print()

    # Test call simulation
    make_deepfake_call(
        "+91XXXXXXXXXX",
        "Hello, this is IT Security calling about your account.",
        campaign_id=1,
        target_id=1,
        tracking_token="test-deepfake-token"
    )
