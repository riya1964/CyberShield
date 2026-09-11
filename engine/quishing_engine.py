"""
quishing_engine.py
---------------------
QR-code phishing (Quishing) ke liye QR code images generate karta hai.

Har target ke tracking_token ko ek URL me encode karta hai
(jo asal me PHP landing page tak le jayega -- track_open ya
landing_page.php), aur uska QR code image bana ke save karta hai.

Jab target QR code scan karega (asal duniya me phone camera se),
wo generated URL khulega -> PHP side is_qr_scanned = 1 mark karega.
Ye script sirf QR *banata* hai, scan track PHP side karega.
"""

import os
import qrcode
import mysql.connector
from mysql.connector import Error

DB_CONFIG = {
    "host": "localhost",
    "user": "cybershield_user",
    "password": "kali",   # apna password
    "database": "cybershield"
}

BASE_TRACKING_URL = "http://localhost/cybershield/landing_page.php"
QR_OUTPUT_DIR = "/var/www/html/cybershield/qrcodes"


def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] Connection fail hua: {e}")
        return None


def generate_qr_for_target(campaign_id, target_id, tracking_token):
    if not os.path.exists(QR_OUTPUT_DIR):
        os.makedirs(QR_OUTPUT_DIR)

    tracking_url = f"{BASE_TRACKING_URL}?uid={tracking_token}&src=qr"

    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    qr.add_data(tracking_url)
    qr.make(fit=True)

    img = qr.make_image(fill_color="black", back_color="white")

    filename = f"{QR_OUTPUT_DIR}/qr_{campaign_id}_{target_id}.png"
    img.save(filename)

    print(f"[QR GENERATED] target_id {target_id} -> {filename} (encodes: {tracking_url})")
    return filename


def generate_qr_for_campaign(campaign_id):
    conn = get_connection()
    if conn is None:
        return

    cursor = conn.cursor()
    cursor.execute(
        """SELECT target_id, tracking_token FROM simulation_logs
           WHERE campaign_id = %s""",
        (campaign_id,)
    )
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    if not rows:
        print("[WARN] Is campaign ke liye simulation_logs me koi entry nahi hai. Pehle dispatcher.py chalao.")
        return

    for target_id, tracking_token in rows:
        generate_qr_for_target(campaign_id, target_id, tracking_token)

    print(f"[DONE] Campaign {campaign_id} ke saare QR codes generate ho gaye -> {QR_OUTPUT_DIR}/")


if __name__ == "__main__":
    generate_qr_for_campaign(1)
