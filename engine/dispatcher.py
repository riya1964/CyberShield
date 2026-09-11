"""
dispatcher.py
--------------
1. Tests connection to the cybershield MySQL database.
2. Dispatches a campaign: for every target, generates a unique tracking
   token and creates a row in simulation_logs (is_email_sent = 1).
"""
SEND_REAL_EMAIL = False
import mysql.connector
import uuid
from mysql.connector import Error

# ---- DB CONFIG: apna password yahan daalo ----
DB_CONFIG = {
    "host": "localhost",
    "user": "cybershield_user",
    "password": "kali",   # wahi jo user banate waqt set kiya tha
    "database": "cybershield"
}


def get_connection():
    """Database se connection banata hai aur return karta hai."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            print("[OK] Database se connection ban gaya.")
        return conn
    except Error as e:
        print(f"[ERROR] Connection fail hua: {e}")
        return None


def dispatch_campaign(campaign_id):
    """
    Ek campaign ke liye, campaigns table check karta hai ki wo exist
    karta hai ya nahi, phir saare targets ko fetch karke har ek ke
    liye simulation_logs me ek unique tracking token wali entry banata hai.
    """
    conn = get_connection()
    if conn is None:
        return

    cursor = conn.cursor()

    # Saare targets fetch karo
    cursor.execute("""
        SELECT t.target_id, t.name, t.email
        FROM targets t
        JOIN campaign_targets ct ON t.target_id = ct.target_id
        WHERE ct.campaign_id = %s
    """, (campaign_id,))
    targets = cursor.fetchall()

    if not targets:
        print("[WARN] Koi target nahi mila targets table me.")
        cursor.close()
        conn.close()
        return

    for target_id, name, email in targets:
        token = str(uuid.uuid4())  # unique tracking token generate karo

        insert_query = """
            INSERT INTO simulation_logs
                (campaign_id, target_id, tracking_token, is_email_sent)
            VALUES (%s, %s, %s, 1)
        """
        cursor.execute(insert_query, (campaign_id, target_id, token))
        print(f"[DISPATCHED] {name} ({email}) -> token: {token}")

                # PHP email sender ko call karo (agar SEND_REAL_EMAIL = True ho)
        if SEND_REAL_EMAIL:
            import subprocess
            result = subprocess.run([
                'php', '-r',
                f"require '/var/www/html/cybershield/email_sender.php';"
                f"$r = send_phishing_email('{email}', '{name}', "
                f"'Action Required: Verify Your Account', "
                f"'<p>Dear {name},</p><p>Please verify: {{{{tracking_link}}}}</p>{{{{tracking_pixel}}}}', "
                f"'{token}');"
                f"echo $r['message'];"
            ], capture_output=True, text=True)
            print(f"[EMAIL] {result.stdout}")


    conn.commit()
    cursor.close()
    conn.close()
    print(f"[DONE] Campaign {campaign_id} dispatch complete.")


if __name__ == "__main__":
    # Test run: pehle connection check karo
    conn = get_connection()
    if conn:
        conn.close()

    # Phir dispatch test karo (campaign_id = 1 abhi sirf placeholder hai,
    # actual campaign campaigns table me pehle create karna hoga)
    # dispatch_campaign(1)
