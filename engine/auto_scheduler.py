"""
auto_scheduler.py
------------------
Har minute cron se chalta hai.
Scheduled campaigns ko check karta hai — agar launch time aa gaya
aur auto_dispatch = 1 hai, toh automatically dispatch karta hai.
"""

import mysql.connector
from mysql.connector import Error
from dispatcher import dispatch_campaign
from datetime import datetime

DB_CONFIG = {
    "host":     "localhost",
    "user":     "cybershield_user",
    "password": "kali",
    "database": "cybershield"
}

def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] {e}")
        return None

def run():
    conn = get_connection()
    if conn is None:
        return

    cursor = conn.cursor()

    # Jo campaigns scheduled hain aur launch time aa gaya
    cursor.execute("""
        SELECT campaign_id, campaign_name
        FROM campaigns
        WHERE status = 'scheduled'
        AND auto_dispatch = 1
        AND launch_date <= NOW()
    """)
    rows = cursor.fetchall()
    cursor.close()

    for campaign_id, campaign_name in rows:
        print(f"[AUTO-DISPATCH] Campaign #{campaign_id}: {campaign_name}")
        dispatch_campaign(campaign_id)

        # Status running me update karo
        
        upd = conn.cursor()
        upd.execute("UPDATE campaigns SET status = 'running' WHERE campaign_id = %s", (campaign_id,))
        conn.commit()
        upd.close()
        print(f"[DONE] Campaign #{campaign_id} auto-dispatched.")

    conn.close()

if __name__ == "__main__":
    run()
