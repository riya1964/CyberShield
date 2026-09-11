"""
active_directory.py
--------------------
Active Directory / Microsoft Entra ID Integration

ARCHITECTURE:
1. LDAP (on-premise AD) ya Microsoft Graph API (Entra ID cloud)
2. Employee records import karna (name, email, department, phone)
3. Targets table me auto-sync karna
4. Department-based campaign targeting

TWO MODES:
- LDAP Mode: On-premise Active Directory (free, needs network access)
- Graph API Mode: Microsoft Entra ID / Azure AD (requires Microsoft 365)

SETUP:
LDAP Mode:
    pip install ldap3
    AD server ka IP/hostname chahiye
    Service account credentials chahiye

Graph API Mode:
    pip install msal requests
    Azure AD App Registration chahiye
    Microsoft 365 tenant chahiye
"""

import sys
import json
import mysql.connector
from mysql.connector import Error

# ── CONFIG ────────────────────────────────────────────────────────────────
MOCK_MODE = True

# Choose mode
USE_LDAP      = True   # On-premise AD
USE_GRAPH_API = False  # Entra ID / Azure AD

# LDAP Config (On-premise AD)
LDAP_SERVER   = "ldap://192.168.1.100"
LDAP_PORT     = 389
LDAP_USER     = "CN=ServiceAccount,DC=company,DC=com"
LDAP_PASS     = "your_service_account_password"
LDAP_BASE_DN  = "DC=company,DC=com"
LDAP_FILTER   = "(objectClass=person)"

# Graph API Config (Entra ID)
TENANT_ID     = "your-tenant-id"
CLIENT_ID     = "your-client-id"
CLIENT_SECRET = "your-client-secret"
GRAPH_SCOPE   = ["https://graph.microsoft.com/.default"]

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

# ── MOCK DATA ───────────────────────────────────────────────────────────────
MOCK_AD_USERS = [
    {"name": "Riya Malaviya",    "email": "riya@company.com",    "department": "IT",      "phone": "+91987654321", "title": "Security Analyst"},
    {"name": "Prachi Shah",      "email": "prachi@company.com",  "department": "Finance", "phone": "+91912345678", "title": "Finance Manager"},
    {"name": "Animesh Patel",    "email": "animesh@company.com", "department": "HR",      "phone": "+91923456789", "title": "HR Executive"},
    {"name": "Raj Mehta",        "email": "raj@company.com",     "department": "Sales",   "phone": "+91934567890", "title": "Sales Head"},
    {"name": "Nisha Gupta",      "email": "nisha@company.com",   "department": "IT",      "phone": "+91945678901", "title": "Developer"},
    {"name": "Kiran Joshi",      "email": "kiran@company.com",   "department": "Finance", "phone": "+91956789012", "title": "Accountant"},
    {"name": "Deepak Sharma",    "email": "deepak@company.com",  "department": "Sales",   "phone": "+91967890123", "title": "Sales Executive"},
    {"name": "Pooja Verma",      "email": "pooja@company.com",   "department": "HR",      "phone": "+91978901234", "title": "Recruiter"},
]

# ── LDAP IMPORT ─────────────────────────────────────────────────────────────
def import_from_ldap():
    """
    On-premise Active Directory se users import karta hai via LDAP.
    Real mode me:
    - ldap3 library se AD server se connect karta hai
    - sAMAccountName, mail, department, telephoneNumber fields fetch karta hai
    - Enabled users only (userAccountControl filter)
    """
    if MOCK_MODE:
        print("[MOCK LDAP] Simulating AD connection...")
        print(f"[MOCK LDAP] Server: {LDAP_SERVER}")
        print(f"[MOCK LDAP] Base DN: {LDAP_BASE_DN}")
        print(f"[MOCK LDAP] Found {len(MOCK_AD_USERS)} users")
        return MOCK_AD_USERS

    try:
        from ldap3 import Server, Connection, ALL, NTLM

        server = Server(LDAP_SERVER, port=LDAP_PORT, get_info=ALL)
        conn = Connection(server, user=LDAP_USER, password=LDAP_PASS, authentication=NTLM)

        if not conn.bind():
            print(f"[ERROR] LDAP bind failed: {conn.result}")
            return []

        # Query AD for active users
        conn.search(
            search_base=LDAP_BASE_DN,
            search_filter="(&(objectClass=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))",
            attributes=["displayName", "mail", "department", "telephoneNumber", "title", "sAMAccountName"]
        )

        users = []
        for entry in conn.entries:
            if entry.mail:
                users.append({
                    "name":       str(entry.displayName),
                    "email":      str(entry.mail),
                    "department": str(entry.department) if entry.department else "General",
                    "phone":      str(entry.telephoneNumber) if entry.telephoneNumber else "",
                    "title":      str(entry.title) if entry.title else "",
                })

        print(f"[LDAP] Found {len(users)} active users")
        conn.unbind()
        return users

    except ImportError:
        print("[ERROR] ldap3 not installed. Run: pip install ldap3")
        return []
    except Exception as e:
        print(f"[ERROR] LDAP: {e}")
        return []

# ── GRAPH API IMPORT ────────────────────────────────────────────────────────
def import_from_graph_api():
    """
    Microsoft Entra ID / Azure AD se users import karta hai via Graph API.
    Real mode me:
    - MSAL se OAuth2 token leta hai
    - /v1.0/users endpoint se users fetch karta hai
    - displayName, mail, department, mobilePhone fields
    """
    if MOCK_MODE:
        print("[MOCK GRAPH] Simulating Entra ID connection...")
        print(f"[MOCK GRAPH] Tenant: {TENANT_ID}")
        print(f"[MOCK GRAPH] Found {len(MOCK_AD_USERS)} users")
        return MOCK_AD_USERS

    try:
        import msal
        import requests

        # Auth token get karo
        app = msal.ConfidentialClientApplication(
            CLIENT_ID,
            authority=f"https://login.microsoftonline.com/{TENANT_ID}",
            client_credential=CLIENT_SECRET
        )

        result = app.acquire_token_silent(GRAPH_SCOPE, account=None)
        if not result:
            result = app.acquire_token_for_client(scopes=GRAPH_SCOPE)

        if "access_token" not in result:
            print(f"[ERROR] Auth failed: {result.get('error_description')}")
            return []

        token = result["access_token"]
        headers = {"Authorization": f"Bearer {token}"}

        # Users fetch karo
        users = []
        url = "https://graph.microsoft.com/v1.0/users?$select=displayName,mail,department,mobilePhone,jobTitle&$top=100"

        while url:
            response = requests.get(url, headers=headers)
            if response.status_code != 200:
                print(f"[ERROR] Graph API: {response.text}")
                break

            data = response.json()
            for user in data.get("value", []):
                if user.get("mail"):
                    users.append({
                        "name":       user.get("displayName", ""),
                        "email":      user.get("mail", ""),
                        "department": user.get("department", "General"),
                        "phone":      user.get("mobilePhone", ""),
                        "title":      user.get("jobTitle", ""),
                    })

            url = data.get("@odata.nextLink")  # Pagination

        print(f"[GRAPH] Found {len(users)} users in Entra ID")
        return users

    except ImportError:
        print("[ERROR] msal not installed. Run: pip install msal requests")
        return []
    except Exception as e:
        print(f"[ERROR] Graph API: {e}")
        return []

# ── SYNC TO DB ───────────────────────────────────────────────────────────────
def sync_users_to_db(users, update_existing=True):
    """
    AD/Entra ID users ko targets table me sync karta hai.
    - Naye users insert karta hai
    - Existing users update karta hai (agar update_existing=True)
    - Department information preserve karta hai
    """
    conn = get_connection()
    if not conn: return 0, 0

    cursor = conn.cursor()
    inserted = 0
    updated = 0

    for user in users:
        if not user.get("email"):
            continue

        # Check karo already exist karta hai ya nahi
        cursor.execute("SELECT target_id FROM targets WHERE email = %s", (user["email"],))
        existing = cursor.fetchone()

        if existing:
            if update_existing:
                cursor.execute(
                    """UPDATE targets SET name=%s, department=%s, phone_number=%s
                       WHERE email=%s""",
                    (user["name"], user.get("department", ""), user.get("phone", ""), user["email"])
                )
                updated += 1
        else:
            cursor.execute(
                """INSERT INTO targets (name, email, department, phone_number)
                   VALUES (%s, %s, %s, %s)""",
                (user["name"], user["email"], user.get("department", ""), user.get("phone", ""))
            )
            inserted += 1

    conn.commit()
    cursor.close()
    conn.close()

    print(f"[SYNC] Inserted: {inserted} new users, Updated: {updated} existing users")
    return inserted, updated

# ── DEPARTMENT REPORT ────────────────────────────────────────────────────────
def get_department_summary():
    """
    Department-wise target count dikhata hai.
    Campaign targeting ke liye useful.
    """
    conn = get_connection()
    if not conn: return

    cursor = conn.cursor()
    cursor.execute(
        "SELECT department, COUNT(*) as count FROM targets GROUP BY department ORDER BY count DESC"
    )
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    print("\n=== Department Summary ===")
    print(f"{'Department':<20} {'Users':>6}")
    print("-" * 28)
    for dept, count in rows:
        print(f"{(dept or 'Unknown'):<20} {count:>6}")

# ── FULL SYNC ─────────────────────────────────────────────────────────────────
def run_full_sync():
    """
    Complete AD sync run karta hai — import + DB sync + report.
    Cron se daily chalao:
    0 6 * * * /path/to/venv/python3 active_directory.py --sync
    """
    print("=== Active Directory / Entra ID Sync ===")
    print(f"Mode: {'MOCK' if MOCK_MODE else 'REAL'}")
    print(f"Source: {'LDAP (On-premise AD)' if USE_LDAP else 'Microsoft Graph API (Entra ID)'}\n")

    # Import users
    if USE_LDAP:
        users = import_from_ldap()
    else:
        users = import_from_graph_api()

    if not users:
        print("[ABORT] No users found")
        return

    # Sync to DB
    inserted, updated = sync_users_to_db(users)

    # Department report
    get_department_summary()

    print(f"\n[DONE] Sync complete — {inserted} added, {updated} updated")
    print("[TIP] Now create campaigns targeting specific departments!")

# ── TEST ────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if "--sync" in sys.argv:
        run_full_sync()
    elif "--ldap" in sys.argv:
        users = import_from_ldap()
        print(json.dumps(users[:3], indent=2))
    elif "--graph" in sys.argv:
        users = import_from_graph_api()
        print(json.dumps(users[:3], indent=2))
    else:
        print("Usage:")
        print("  python3 active_directory.py --sync    # Full sync")
        print("  python3 active_directory.py --ldap    # Test LDAP import")
        print("  python3 active_directory.py --graph   # Test Graph API import")
        print()
        run_full_sync()
