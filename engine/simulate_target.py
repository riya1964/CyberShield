"""
simulate_target.py
---------------------
Simulates a target's full journey through a phishing campaign:
  1. Opens the email        -> hits track_open.php   (is_opened = 1)
  2. Clicks the link        -> hits landing_page.php  (is_clicked = 1)
  3. Submits credentials    -> POSTs to landing_page.php (is_credential_submitted = 1)

Then prints the simulation_logs row before and after, so you can see
every event getting captured in real time -- useful for demos and
testing without manually clicking through the browser every time.

Usage:
    python3 simulate_target.py <tracking_token>
"""

import sys
import time
import requests
import mysql.connector
from mysql.connector import Error

BASE_URL = "http://localhost/cybershield"

DB_CONFIG = {
    "host": "localhost",
    "user": "cybershield_user",
    "password": "kali",   # apna password
    "database": "cybershield"
}


def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] Connection fail hua: {e}")
        return None


def print_log_status(token, label):
    conn = get_connection()
    if conn is None:
        return
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        """SELECT is_email_sent, is_opened, is_clicked,
                  is_credential_submitted, is_attachment_download,
                  is_qr_scanned, is_report_phishing
           FROM simulation_logs WHERE tracking_token = %s""",
        (token,)
    )
    row = cursor.fetchone()
    cursor.close()
    conn.close()

    print(f"\n--- {label} ---")
    if row:
        for key, value in row.items():
            print(f"  {key}: {value}")
    else:
        print("  [WARN] Token simulation_logs me nahi mila.")


def simulate_target_journey(token):
    print(f"Simulating target journey for token: {token}")

    print_log_status(token, "BEFORE")

    # Step 1: target "opens" the email (loads the tracking pixel)
    print("\n[STEP 1] Email open simulate ho raha hai...")
    requests.get(f"{BASE_URL}/track_open.php", params={"uid": token})
    time.sleep(1)

    # Step 2: target "clicks" the phishing link (GET request to landing page)
    print("[STEP 2] Link click simulate ho raha hai...")
    requests.get(f"{BASE_URL}/landing_page.php", params={"uid": token})
    time.sleep(1)

    # Step 3: target "submits" fake credentials
    print("[STEP 3] Credential submit simulate ho raha hai...")
    requests.post(f"{BASE_URL}/landing_page.php", data={
        "uid": token,
        "email": "target@test.com",
        "password": "dummy-password-not-real"  # never stored server-side
    })
    time.sleep(1)

    print_log_status(token, "AFTER")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 simulate_target.py <tracking_token>")
        sys.exit(1)

    simulate_target_journey(sys.argv[1])
