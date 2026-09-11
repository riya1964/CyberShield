"""
teams_deepfake.py
------------------
Video Deepfake Simulation via Microsoft Teams / Google Meet

ARCHITECTURE:
1. DeepFaceLive → real-time face swap on webcam feed
2. v4l2loopback → virtual camera driver (Linux)
3. Teams/Meet → select virtual camera as video source
4. Target sees deepfaked face during video call

FULL FLOW:
Admin → selects target → schedules Teams meeting invite →
target joins call → sees deepfaked executive/IT person →
social engineering conversation → credential submission

MOCK_MODE = True  → simulates without real deepfake
MOCK_MODE = False → requires DeepFaceLive + GPU

INSTALLATION (when ready):
    # Virtual camera driver
    sudo apt install v4l2loopback-dkms v4l2loopback-utils
    sudo modprobe v4l2loopback devices=1 video_nr=10 card_label="CyberShield-Cam" exclusive_caps=1
    
    # DeepFaceLive
    git clone https://github.com/iperov/DeepFaceLive.git
    cd DeepFaceLive && pip install -r requirements.txt
    
    # OBS Virtual Camera (alternative)
    sudo apt install obs-studio
"""

import os
import sys
import subprocess
import mysql.connector
from mysql.connector import Error
from datetime import datetime

# ── CONFIG ────────────────────────────────────────────────────────────────
MOCK_MODE = True

# DeepFaceLive Config
DEEPFACE_PATH    = "/opt/DeepFaceLive"
FACE_MODEL_PATH  = "/opt/DeepFaceLive/models/face_model.dfm"
VIRTUAL_CAM_DEV  = "/dev/video10"

# Teams Meeting Config
TEAMS_WEBHOOK_URL = "https://outlook.office.com/webhook/YOUR_TEAMS_WEBHOOK"

# Twilio (for sending meeting invite via SMS/WhatsApp)
TWILIO_SID   = "ACxxxxxxxxxxxxxxx"
TWILIO_TOKEN = "xxxxxxxxxxxxxxx"
TWILIO_FROM  = "+1xxxxxxxxxx"

BASE_URL = "http://192.168.1.14/cybershield"

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

def log_event(campaign_id, target_id, token, event_type):
    conn = get_connection()
    if not conn: return
    cur = conn.cursor()
    if event_type == "call_initiated":
        cur.execute(
            "UPDATE simulation_logs SET is_email_sent=1 WHERE campaign_id=%s AND target_id=%s AND tracking_token=%s",
            (campaign_id, target_id, token)
        )
    elif event_type == "credential_submitted":
        cur.execute(
            "UPDATE simulation_logs SET is_credential_submitted=1 WHERE campaign_id=%s AND target_id=%s AND tracking_token=%s",
            (campaign_id, target_id, token)
        )
    conn.commit(); cur.close(); conn.close()

# ── STEP 1: Setup Virtual Camera ───────────────────────────────────────────
def setup_virtual_camera():
    """
    v4l2loopback se virtual camera setup karta hai.
    Real mode me:
    - sudo modprobe v4l2loopback devices=1 video_nr=10
    - /dev/video10 virtual camera ban jaata hai
    - Teams/Meet me ye camera select karo
    """
    if MOCK_MODE:
        print("[MOCK] Virtual camera setup simulated")
        print("[MOCK] Virtual device: /dev/video10 (CyberShield-Cam)")
        return True

    try:
        result = subprocess.run([
            "sudo", "modprobe", "v4l2loopback",
            "devices=1", "video_nr=10",
            "card_label=CyberShield-Cam",
            "exclusive_caps=1"
        ], capture_output=True, text=True)

        if result.returncode == 0:
            print("[OK] Virtual camera /dev/video10 ready")
            return True
        else:
            print(f"[ERROR] v4l2loopback: {result.stderr}")
            return False
    except Exception as e:
        print(f"[ERROR] Camera setup: {e}")
        return False

# ── STEP 2: Start DeepFaceLive ─────────────────────────────────────────────
def start_deepfake_stream(face_model_path=None):
    """
    DeepFaceLive ko virtual camera pe stream karta hai.
    Real mode me:
    - Webcam input lete hai (real person ka face)
    - Trained face model se face swap karta hai
    - Output virtual camera /dev/video10 pe jaata hai
    - Teams/Meet usi virtual camera ko use karta hai

    Free face models:
    - DeepFaceLive ke default models (mediapipe)
    - Custom .dfm model files
    """
    if MOCK_MODE:
        print("[MOCK] DeepFaceLive stream simulated")
        print("[MOCK] Face model: executive_face.dfm")
        print("[MOCK] Source: webcam /dev/video0")
        print("[MOCK] Output: virtual camera /dev/video10")
        print("[MOCK] Real-time face swap would start here")
        return True

    model = face_model_path or FACE_MODEL_PATH
    if not os.path.exists(model):
        print(f"[ERROR] Face model not found: {model}")
        print("[INFO] Download from: https://github.com/iperov/DeepFaceLive/releases")
        return False

    try:
        # DeepFaceLive CLI mode
        proc = subprocess.Popen([
            "python3", f"{DEEPFACE_PATH}/main.py",
            "--source", "0",           # webcam
            "--target", VIRTUAL_CAM_DEV,  # virtual camera
            "--model", model,
            "--no-gui"
        ])
        print(f"[OK] DeepFaceLive started (PID: {proc.pid})")
        print(f"[OK] Streaming to: {VIRTUAL_CAM_DEV}")
        return proc
    except Exception as e:
        print(f"[ERROR] DeepFaceLive: {e}")
        return None

# ── STEP 3: Create Teams Meeting Invite ────────────────────────────────────
def create_teams_meeting(target_name, target_email, meeting_topic="IT Security Review"):
    """
    Microsoft Teams meeting invite bhejta hai target ko.
    Real mode me Microsoft Graph API use karta hai.

    Graph API setup:
    - Azure AD App Registration
    - Calendar.ReadWrite permission
    - OnlineMeetings.ReadWrite permission
    """
    if MOCK_MODE:
        fake_link = f"https://teams.microsoft.com/l/meetup-join/SIMULATED_MEETING_ID"
        print(f"[MOCK TEAMS] Meeting created for: {target_name} ({target_email})")
        print(f"[MOCK TEAMS] Topic: {meeting_topic}")
        print(f"[MOCK TEAMS] Link: {fake_link}")
        return {
            "join_url": fake_link,
            "meeting_id": "SIMULATED_ID",
            "topic": meeting_topic
        }

    try:
        import requests
        # Microsoft Graph API — Teams meeting create
        headers = {
            "Authorization": f"Bearer YOUR_GRAPH_API_TOKEN",
            "Content-Type": "application/json"
        }
        payload = {
            "subject": meeting_topic,
            "startDateTime": datetime.now().isoformat() + "Z",
            "endDateTime": datetime.now().isoformat() + "Z",
            "attendees": [
                {
                    "emailAddress": {"address": target_email, "name": target_name},
                    "type": "required"
                }
            ],
            "isOnlineMeeting": True,
            "onlineMeetingProvider": "teamsForBusiness"
        }
        response = requests.post(
            "https://graph.microsoft.com/v1.0/me/events",
            json=payload, headers=headers
        )
        if response.status_code == 201:
            data = response.json()
            print(f"[TEAMS] Meeting created: {data.get('onlineMeeting', {}).get('joinUrl')}")
            return data
        else:
            print(f"[ERROR] Graph API: {response.text}")
            return None
    except Exception as e:
        print(f"[ERROR] Teams meeting: {e}")
        return None

# ── STEP 4: Send Meeting Invite to Target ──────────────────────────────────
def send_meeting_invite(phone_number, target_name, meeting_link, tracking_token):
    """
    Meeting link target ko SMS/WhatsApp pe bhejta hai.
    """
    message = (
        f"Hi {target_name}, this is IT Security Team. "
        f"We need to discuss an urgent security matter regarding your account. "
        f"Please join this confidential Teams meeting immediately: {meeting_link} "
        f"Please verify after joining: {BASE_URL}/landing_page.php?uid={tracking_token}"
    )

    if MOCK_MODE:
        print(f"[MOCK INVITE] -> {phone_number}")
        print(f"[MOCK INVITE] Message: {message[:100]}...")
        return True

    try:
        from twilio.rest import Client
        client = Client(TWILIO_SID, TWILIO_TOKEN)
        msg = client.messages.create(
            body=message,
            from_=TWILIO_FROM,
            to=phone_number
        )
        print(f"[INVITE SENT] -> {phone_number} | SID: {msg.sid}")
        return True
    except Exception as e:
        print(f"[ERROR] Invite send: {e}")
        return False

# ── STEP 5: Full Campaign Dispatch ─────────────────────────────────────────
def dispatch_deepfake_teams_campaign(campaign_id):
    """
    Full deepfake Teams campaign dispatch karta hai.
    1. Virtual camera setup
    2. DeepFaceLive start
    3. Per-target: Teams meeting create + invite bhejo
    """
    conn = get_connection()
    if not conn: return

    print(f"\n[DEEPFAKE TEAMS] Starting campaign #{campaign_id}")
    print("="*50)

    # Setup virtual camera + deepfake stream
    if not setup_virtual_camera():
        print("[ABORT] Virtual camera setup failed")
        return

    deepfake_proc = start_deepfake_stream()
    if not deepfake_proc and not MOCK_MODE:
        print("[ABORT] DeepFaceLive failed to start")
        return

    # Targets fetch karo
    cursor = conn.cursor()
    cursor.execute(
        """SELECT t.target_id, t.name, t.email, t.phone_number, sl.tracking_token
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
        print("[WARN] No targets found — run dispatcher.py first")
        return

    for target_id, name, email, phone, token in rows:
        print(f"\n[TARGET] {name} ({email})")

        # Teams meeting create karo
        meeting = create_teams_meeting(name, email, "Urgent IT Security Review")
        if not meeting:
            print(f"[SKIP] Could not create meeting for {name}")
            continue

        meeting_link = meeting.get("join_url", "")

        # Invite bhejo
        if phone:
            send_meeting_invite(phone, name, meeting_link, token)

        # Log karo
        log_event(campaign_id, target_id, token, "call_initiated")
        print(f"[OK] Deepfake Teams campaign dispatched for {name}")

    print(f"\n[DONE] Campaign #{campaign_id} complete")
    print("[INFO] When target joins Teams call:")
    print("  1. They will see deepfaked face (executive/IT person)")
    print("  2. Social engineer them to visit the phishing link")
    print("  3. Their credential submission will be tracked automatically")

# ── REQUIREMENTS CHECK ─────────────────────────────────────────────────────
def check_requirements():
    """System requirements check karta hai."""
    print("=== Deepfake Teams Requirements Check ===\n")
    checks = [
        ("v4l2loopback", "lsmod | grep v4l2loopback", "Virtual camera driver"),
        ("DeepFaceLive", f"ls {DEEPFACE_PATH}", "Deepfake engine"),
        ("Face model", f"ls {FACE_MODEL_PATH}", "Trained face model"),
        ("OBS Studio", "which obs", "Alternative virtual camera"),
        ("GPU (NVIDIA)", "nvidia-smi", "GPU for real-time processing"),
    ]
    for name, cmd, desc in checks:
        try:
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
            status = "✅ Available" if result.returncode == 0 else "❌ Not found"
        except:
            status = "❌ Error"
        print(f"  {status} — {desc} ({name})")

    print("\n=== Installation Commands ===")
    print("sudo apt install v4l2loopback-dkms obs-studio")
    print("git clone https://github.com/iperov/DeepFaceLive.git /opt/DeepFaceLive")
    print("pip install -r /opt/DeepFaceLive/requirements.txt")

# ── TEST ────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--check":
        check_requirements()
    else:
        print("=== Deepfake Teams Campaign Test ===")
        print(f"Mode: {'MOCK' if MOCK_MODE else 'REAL'}\n")
        setup_virtual_camera()
        start_deepfake_stream()
        meeting = create_teams_meeting("Riya", "riya@company.com")
        send_meeting_invite("+91XXXXXXXXXX", "Riya", meeting["join_url"], "test-token-deepfake")
        print("\n[To check requirements]: python3 teams_deepfake.py --check")
        print("[To dispatch campaign]:  dispatch_deepfake_teams_campaign(campaign_id)")
