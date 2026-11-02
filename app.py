import os
import re
import json
import time
import base64
from datetime import datetime, date
import bcrypt
import requests
import mysql.connector
from werkzeug.utils import secure_filename
from flask import (
    Flask, render_template, request, redirect,
    url_for, session, jsonify, send_from_directory
)
from ai_helper import generate_ai_response, extract_text_for_evaluation

BASE_DIR = os.path.abspath(os.path.dirname(__file__))
UPLOAD_FOLDER = os.path.join(BASE_DIR, 'uploads')
PROFILES_FOLDER = os.path.join(UPLOAD_FOLDER, 'profiles')
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(PROFILES_FOLDER, exist_ok=True)

# --- 1. MAGIC .ENV SECRETS LOADER ---
env_path = os.path.join(BASE_DIR, '.env')
if os.path.exists(env_path):
    with open(env_path, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            os.environ.setdefault(k.strip(), v.strip())

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "core_staging_secret_key_2026")

# --- 2. RAILWAY / LOCAL DATABASE CONNECTION ---
DB_CONFIG = {
    'host': os.environ.get('MYSQLHOST', os.environ.get('DB_HOST', 'mysql.railway.internal')),
    'user': os.environ.get('MYSQLUSER', os.environ.get('DB_USER', 'root')),
    'password': os.environ.get('MYSQLPASSWORD', os.environ.get('DB_PASS', '')),
    'database': os.environ.get('MYSQLDATABASE', os.environ.get('DB_NAME', 'railway')),
    'port': int(os.environ.get('MYSQLPORT', 3306))
}

def get_db():
    return mysql.connector.connect(**DB_CONFIG)

# --- SERVE EXISTING CSS, JS, AND UPLOADS FOLDERS AUTOMATICALLY ---
@app.route('/css/<path:filename>')
def serve_css(filename):
    return send_from_directory(os.path.join(BASE_DIR, 'css'), filename)

@app.route('/js/<path:filename>')
def serve_js(filename):
    return send_from_directory(os.path.join(BASE_DIR, 'js'), filename)

@app.route('/uploads/<path:filename>')
def serve_uploads(filename):
    return send_from_directory(UPLOAD_FOLDER, filename)

# --- 3. UNIVERSAL HEADER & SIDEBAR DATA (header.php & sidebar.php) ---
@app.context_processor
def inject_header_data():
    header_uid = int(session.get('user_id', 0))
    user_role = session.get('role', 'user')
    current_page = request.path.lstrip('/') or 'landing.php'

    h_user = {
        'fullname': session.get('fullname', 'User'),
        'username': session.get('username', ''),
        'email': session.get('email', ''),
        'role': user_role,
        'gender': session.get('gender', ''),
        'profile_pic': '',
        'supervisor_name': ''
    }
    if header_uid > 0:
        try:
            conn = get_db()
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT u.fullname, u.username, u.email, u.role, u.gender, u.profile_pic, s.fullname as supervisor_name 
                FROM users u 
                LEFT JOIN users s ON u.assigned_supervisor_id = s.id 
                WHERE u.id = %s LIMIT 1
            """, (header_uid,))
            row = cursor.fetchone()
            if row:
                h_user = row
                user_role = row.get('role') or user_role
            cursor.close()
            conn.close()
        except Exception:
            pass

    h_role_label = (user_role or 'User').replace('_', ' ').title()
    has_pic = bool(h_user.get('profile_pic'))
    gender_clean = (h_user.get('gender') or '').strip().lower()

    avatar_class = "avatar-neutral"
    avatar_icon = '<i class="fas fa-user-astronaut"></i>'
    avatar_style = ""

    if has_pic:
        avatar_class = "avatar-custom-img"
        avatar_style = f"background-image: url('{h_user['profile_pic']}');"
    else:
        if gender_clean == 'male':
            avatar_class = "avatar-male"
            avatar_icon = '<i class="fas fa-user"></i>'
        elif gender_clean == 'female':
            avatar_class = "avatar-female"
            avatar_icon = '<i class="fas fa-user"></i>'

    return dict(
        h_user=h_user,
        u_data=h_user,
        h_role_label=h_role_label,
        u_role_badge=h_role_label,
        user_role=user_role,
        current_page=current_page,
        has_pic=has_pic,
        avatar_class=avatar_class,
        avatar_icon=avatar_icon,
        avatar_style=avatar_style,
        current_year=datetime.now().year
    )


# =========================================================
# 1. LANDING PAGE (LOADS FIRST ON "/"), LOGIN & LOGOUT
# =========================================================
@app.route('/')
@app.route('/landing')
@app.route('/landing.php')
def landing():
    return render_template('landing.html')

@app.route('/index.php', methods=['GET', 'POST'])
@app.route('/login', methods=['GET', 'POST'])
def index_page():
    if session.get('user_id'):
        role = session.get('role')
        if role == 'admin':
            return redirect('/admin_dashboard.php')
        elif role == 'supervisor':
            return redirect('/supervisor_dashboard.php')
        elif role == 'student_teacher':
            return redirect('/dashboard.php')

    error = ""
    if request.method == 'POST':
        login_input = request.form.get('login_identifier', '')
        password = request.form.get('password', '')

        conn = get_db()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM users WHERE username = %s OR email = %s", (login_input, login_input))
        user = cursor.fetchone()
        cursor.close()
        conn.close()

        valid_pw = False
        if user and user.get('password'):
            stored_hash = user['password'].encode('utf-8').replace(b'$2y$', b'$2b$', 1)
            try:
                valid_pw = bcrypt.checkpw(password.encode('utf-8'), stored_hash)
            except Exception:
                valid_pw = False

        if user and valid_pw:
            if (user.get('status') or '').strip().lower() == 'suspended':
                error = "This account is suspended. Please contact the administrator."
            else:
                session['user_id'] = user['id']
                session['role'] = user['role']
                session['fullname'] = user['fullname']
                session['username'] = user.get('username', '')
                session['email'] = user.get('email', '')
                session['gender'] = user.get('gender', '')

                if user['role'] == 'admin':
                    return redirect('/admin_dashboard.php')
                elif user['role'] == 'supervisor':
                    return redirect('/supervisor_dashboard.php')
                else:
                    return redirect('/dashboard.php')
        else:
            error = "Invalid login credentials."

    return render_template('index.html', error=error)

@app.route('/logout')
@app.route('/logout.php')
def logout():
    session.clear()
    return redirect(url_for('landing'))


# =========================================================
# 2. ADMIN DASHBOARD (admin_dashboard.php)
# =========================================================
@app.route('/admin_dashboard')
@app.route('/admin_dashboard.php')
def admin_dashboard():
    if session.get('role') != 'admin' or not session.get('user_id'):
        session.clear()
        return redirect('/index.php')

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT COUNT(*) as count FROM users WHERE role='student_teacher'")
    total_students = (cursor.fetchone() or {}).get('count', 0)

    cursor.execute("SELECT COUNT(*) as count FROM users WHERE role='supervisor'")
    total_supervisors = (cursor.fetchone() or {}).get('count', 0)

    cursor.execute("""
        SELECT s.*, u.fullname, u.assigned_supervisor_id 
        FROM submissions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.status = 'pending' 
        ORDER BY s.upload_date ASC
    """)
    pending_subs = cursor.fetchall()

    cursor.execute("SELECT COUNT(*) as count FROM users WHERE reset_request = 1")
    reset_count = (cursor.fetchone() or {}).get('count', 0)

    cursor.execute("""
        SELECT u.id, u.fullname, u.course, u.year_level,
        COALESCE((SELECT AVG(competency_score) FROM evaluations WHERE user_id = u.id AND status='accepted'), 0) as avg_score
        FROM users u 
        WHERE u.role='student_teacher'
        ORDER BY avg_score DESC LIMIT 5
    """)
    students = cursor.fetchall()

    cursor.execute("""
        SELECT s.*, 
        (SELECT COUNT(*) FROM users WHERE assigned_supervisor_id = s.id) as student_count 
        FROM users s 
        WHERE s.role='supervisor' 
        ORDER BY s.fullname ASC
    """)
    supervisors = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'admin_dashboard.html',
        total_students=total_students,
        total_supervisors=total_supervisors,
        pending_subs=pending_subs,
        reset_count=reset_count,
        students=students,
        supervisors=supervisors
    )


# =========================================================
# 3. ADD MANUAL EVALUATION (add_evaluation_admin.php)
# =========================================================
@app.route('/add_evaluation_admin', methods=['GET', 'POST'])
@app.route('/add_evaluation_admin.php', methods=['GET', 'POST'])
def add_evaluation_admin():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    admin_id = session['user_id']
    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    if request.method == 'POST' and 'submit_grade' in request.form:
        student_id = int(request.form.get('student_id', 0))
        eval_title = request.form.get('eval_title', '')
        score = request.form.get('score', '')
        notes = request.form.get('notes', '')

        cursor.execute("""
            INSERT INTO evaluations 
            (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, status, upload_date) 
            VALUES (%s, %s, 0, %s, %s, %s, 'accepted', NOW())
        """, (student_id, admin_id, eval_title, score, notes))
        conn.commit()
        new_eval_id = cursor.lastrowid
        cursor.close()
        conn.close()
        return redirect(f"/admin_view_evaluation.php?id={new_eval_id}")

    cursor.execute("SELECT * FROM users WHERE role='student_teacher' ORDER BY fullname ASC")
    students = cursor.fetchall()
    cursor.close()
    conn.close()

    today_str = datetime.now().strftime("%b %d, %Y")
    return render_template('add_evaluation_admin.html', students=students, today_str=today_str)


# =========================================================
# 4. ADD USER (admin_add_user.php)
# =========================================================
@app.route('/admin_add_user', methods=['GET', 'POST'])
@app.route('/admin_add_user.php', methods=['GET', 'POST'])
def admin_add_user():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    msg = ""
    error = ""

    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        fullname = request.form.get('fullname', '').strip()
        email = request.form.get('email', '').strip()
        raw_password = request.form.get('password', '')
        hashed_pw = bcrypt.hashpw(raw_password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
        role = request.form.get('role', 'student_teacher')
        status = request.form.get('status', 'active')

        college = request.form.get('college', '').strip()
        course = request.form.get('course', '').strip()
        year_level = request.form.get('year_level', '').strip()
        section = request.form.get('section', '').strip()
        birthdate_str = request.form.get('birthdate', '')
        gender = request.form.get('gender', '')
        address = request.form.get('address', '').strip()
        partner_school = request.form.get('partner_school', '').strip()

        age = 0
        if birthdate_str:
            try:
                dob = datetime.strptime(birthdate_str, "%Y-%m-%d").date()
                today = date.today()
                age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            except ValueError:
                age = 0

        try:
            conn = get_db()
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO users 
                (username, fullname, email, password, role, status, college, course, year_level, section, birthdate, age, gender, address, partner_school) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (username, fullname, email, hashed_pw, role, status, college, course, year_level, section, birthdate_str, age, gender, address, partner_school))
            conn.commit()
            cursor.close()
            conn.close()
            msg = "User successfully registered in the system!"
        except Exception as e:
            error = f"Error adding user: {str(e)}"

    return render_template('admin_add_user.html', msg=msg, error=error)


# =========================================================
# 5. ADMIN EVALUATE SUBMISSION (admin_evaluate_submission.php)
# =========================================================
@app.route('/admin_evaluate_submission', methods=['GET', 'POST'])
@app.route('/admin_evaluate_submission.php', methods=['GET', 'POST'])
def admin_evaluate_submission():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    admin_id = session['user_id']
    sub_id = request.args.get('sub_id', 0, type=int)

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT s.*, u.fullname 
        FROM submissions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = %s
    """, (sub_id,))
    sub = cursor.fetchone()

    if not sub:
        cursor.close()
        conn.close()
        return redirect('/admin_dashboard.php')

    file_content_msg = ""
    if sub.get('file_path'):
        clean_rel_path = sub['file_path'].replace('../', '').replace('..\\', '').lstrip('/\\')
        actual_path = os.path.join(BASE_DIR, clean_rel_path)
        if os.path.exists(actual_path):
            extracted_text = extract_text_for_evaluation(actual_path)
            file_content_msg = f"\n\n=== [EVIDENCE FILE CONTENT] ===\n{extracted_text}\n==============================\n"

    master_context = f"Title: {sub['title']}\nStudent Context Description: {sub['description']}{file_content_msg}"
    ai_scores = {'obj': '', 'con': '', 'meth': '', 'ass': '', 'fmt': '', 'total': ''}
    ai_feedback = ""

    if request.method == 'POST':
        if 'send_chat' in request.form:
            message = request.form.get('message', '').strip()
            context = request.form.get('context', '')
            if message:
                cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'user', %s)", (admin_id, message))
                conn.commit()
                ai_response = generate_ai_response(f"Context:\n{context}\n\nUser Question: {message}", 'consultant')
                cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'ai', %s)", (admin_id, ai_response))
                conn.commit()
                cursor.close()
                conn.close()
                return redirect(f"/admin_evaluate_submission.php?sub_id={sub_id}")

        elif 'generate_ai' in request.form:
            full_prompt = f"""You are a professional teacher evaluator grading a lesson plan. Use this exact 100-point rubric:
            1. Objectives (Max 20)
            2. Content (Max 20)
            3. Methodology (Max 30)
            4. Assessment (Max 20)
            5. Formatting (Max 10)
            
            Student Work: {master_context}
            
            CRITICAL: If the document content contains a SYSTEM ALERT about a missing or unreadable file, DO NOT PANIC AND DO NOT FAIL. You MUST evaluate the lesson plan based SOLELY on the 'Student Context Description'.
            
            RETURN ONLY VALID JSON EXACTLY LIKE THIS FORMAT. DO NOT ADD ANY OTHER TEXT:
            {{
                "obj": 18,
                "con": 15,
                "meth": 25,
                "ass": 15,
                "fmt": 10,
                "total": 83,
                "feedback": "Detailed feedback..."
            }}"""
            raw_ai_text = generate_ai_response(full_prompt, 'evaluator')
            match = re.search(r'\{[\s\S]*\}', raw_ai_text)
            if match:
                try:
                    parsed = json.loads(match.group(0))
                    if parsed and 'total' in parsed:
                        ai_scores = parsed
                        ai_feedback = parsed.get('feedback', '')
                    else:
                        ai_feedback = "AI JSON Error. Raw output: " + match.group(0)
                except Exception:
                    ai_feedback = "AI JSON Error. Raw output: " + match.group(0)
            else:
                ai_feedback = "AI failed to return the scoring format. Raw output: " + raw_ai_text

        elif 'submit_grade' in request.form:
            eval_title = request.form.get('eval_title', '')
            score = request.form.get('human_total', 0)
            notes = (
                f"RUBRIC BREAKDOWN:\n"
                f"Objectives: {request.form.get('human_obj')}/20\n"
                f"Content: {request.form.get('human_con')}/20\n"
                f"Methodology: {request.form.get('human_meth')}/30\n"
                f"Assessment: {request.form.get('human_ass')}/20\n"
                f"Formatting: {request.form.get('human_fmt')}/10\n\n"
                f"FEEDBACK:\n{request.form.get('notes', '')}"
            )
            student_id = sub['user_id']
            target_file = sub.get('file_path', '')

            uploaded_file = request.files.get('file')
            if uploaded_file and uploaded_file.filename:
                safe_name = secure_filename(uploaded_file.filename)
                filename = f"admin_{int(time.time())}_{safe_name}"
                uploaded_file.save(os.path.join(UPLOAD_FOLDER, filename))
                target_file = f"uploads/{filename}"

            cursor.execute("""
                INSERT INTO evaluations 
                (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, file_path, status) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending')
            """, (student_id, admin_id, sub_id, eval_title, score, notes, target_file))
            cursor.execute("UPDATE submissions SET status='evaluated' WHERE id=%s", (sub_id,))
            conn.commit()
            cursor.close()
            conn.close()
            return redirect(f"/admin_evaluations.php?student_id={student_id}")

    cursor.execute("SELECT * FROM chat_logs WHERE user_id=%s ORDER BY created_at ASC", (admin_id,))
    chat_history = cursor.fetchall()
    cursor.close()
    conn.close()

    return render_template(
        'admin_evaluate_submission.html',
        sub=sub,
        master_context=master_context,
        ai_scores=ai_scores,
        ai_feedback=ai_feedback,
        chat_history=chat_history
    )


# =========================================================
# 6. STUDENT SCORECARDS DIRECTORY (admin_evaluations.php)
# =========================================================
@app.route('/admin_evaluations')
@app.route('/admin_evaluations.php')
def admin_evaluations():
    if session.get('role') not in ['admin', 'supervisor'] or not session.get('user_id'):
        return redirect('/index.php')

    viewer_role = session['role']
    student_id = request.args.get('student_id', 0, type=int)

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    if student_id == 0:
        cursor.execute("SELECT * FROM users WHERE role='student_teacher' ORDER BY fullname ASC")
        students = cursor.fetchall()
        cursor.close()
        conn.close()
        return render_template('admin_evaluations.html', student_id=0, students=students, viewer_role=viewer_role)
    else:
        cursor.execute("SELECT fullname FROM users WHERE id=%s", (student_id,))
        stu_row = cursor.fetchone()
        stu_name = stu_row['fullname'] if stu_row else "Unknown Student"

        cursor.execute("SELECT * FROM submissions WHERE user_id=%s AND status='pending' ORDER BY created_at DESC", (student_id,))
        pending = cursor.fetchall()

        cursor.execute("SELECT * FROM evaluations WHERE user_id=%s ORDER BY upload_date DESC", (student_id,))
        completed = cursor.fetchall()

        cursor.close()
        conn.close()
        return render_template(
            'admin_evaluations.html',
            student_id=student_id,
            stu_name=stu_name,
            pending=pending,
            completed=completed,
            viewer_role=viewer_role
        )


# =========================================================
# 7. MANAGE USERS (admin_manage.php)
# =========================================================
@app.route('/admin_manage')
@app.route('/admin_manage.php')
def admin_manage():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    delete_id = request.args.get('delete_id', type=int)
    if delete_id:
        if delete_id != session['user_id']:
            cursor.execute("DELETE FROM users WHERE id=%s", (delete_id,))
            conn.commit()
        cursor.close()
        conn.close()
        return redirect('/admin_manage.php')

    filter_param = request.args.get('filter', '')
    where_clause = "WHERE reset_request = 1" if filter_param == 'reset' else ""
    filter_msg = "Showing only Password Reset Requests" if filter_param == 'reset' else ""

    cursor.execute(f"SELECT * FROM users {where_clause} ORDER BY reset_request DESC, role DESC, fullname ASC")
    users = cursor.fetchall()
    cursor.close()
    conn.close()

    return render_template('admin_manage.html', users=users, filter_msg=filter_msg)


# =========================================================
# 8. VIEW SINGLE EVALUATION (admin_view_evaluation.php)
# =========================================================
@app.route('/admin_view_evaluation')
@app.route('/admin_view_evaluation.php')
def admin_view_evaluation():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    eval_id = request.args.get('id', 0, type=int)
    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            e.*, 
            s.title AS sub_title, 
            s.description AS sub_desc, 
            s.file_path AS sub_file, 
            u.fullname AS student_name,
            ev.fullname AS evaluator_name
        FROM evaluations e
        LEFT JOIN submissions s ON e.submission_id = s.id
        JOIN users u ON e.user_id = u.id
        JOIN users ev ON e.evaluator_id = ev.id
        WHERE e.id = %s
    """, (eval_id,))
    data = cursor.fetchone()
    cursor.close()
    conn.close()

    if not data:
        return "Evaluation not found.", 404

    return render_template('admin_view_evaluation.html', data=data)


# =========================================================
# 9. ADMIN VIEW STUDENT PROFILE (admin_view_student.php)
# =========================================================
@app.route('/admin_view_student', methods=['GET', 'POST'])
@app.route('/admin_view_student.php', methods=['GET', 'POST'])
def admin_view_student():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    admin_id = session['user_id']
    student_id = request.args.get('id', 0, type=int)
    msg = ""

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM users WHERE id=%s AND role='student_teacher'", (student_id,))
    student = cursor.fetchone()
    if not student:
        cursor.close()
        conn.close()
        return redirect('/admin_dashboard.php')

    if request.method == 'POST':
        eval_title = request.form.get('eval_title', '')
        score = request.form.get('score', 0)
        notes = request.form.get('notes', '')
        target_file = ""

        uploaded_file = request.files.get('file')
        if uploaded_file and uploaded_file.filename:
            safe_name = secure_filename(uploaded_file.filename)
            filename = f"{student_id}_admin_eval_{int(time.time())}_{safe_name}"
            uploaded_file.save(os.path.join(UPLOAD_FOLDER, filename))
            target_file = f"uploads/{filename}"

        try:
            cursor.execute("""
                INSERT INTO evaluations 
                (user_id, evaluator_id, evaluation_title, competency_score, readiness_notes, file_path, status) 
                VALUES (%s, %s, %s, %s, %s, %s, 'pending')
            """, (student_id, admin_id, eval_title, score, notes, target_file))
            conn.commit()
            msg = "General evaluation submitted successfully!"
        except Exception:
            msg = "Database Error."

    cursor.execute("""
        SELECT e.*, ev.fullname as evaluator_name, ev.role as evaluator_role 
        FROM evaluations e 
        LEFT JOIN users ev ON e.evaluator_id = ev.id
        WHERE e.user_id = %s 
        ORDER BY e.upload_date ASC
    """, (student_id,))
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    dates = []
    scores = []
    for r in rows:
        if r.get('status') == 'accepted' and r.get('upload_date'):
            dt_str = r['upload_date'].strftime("%b %d") if hasattr(r['upload_date'], 'strftime') else str(r['upload_date'])[:10]
            dates.append(dt_str)
            scores.append(r['competency_score'])

    history_data = list(reversed(rows))

    return render_template(
        'admin_view_student.html',
        student=student,
        msg=msg,
        history_data=history_data,
        dates=json.dumps(dates),
        scores=json.dumps(scores),
        admin_id=admin_id
    )


# =========================================================
# 10. API EVALUATE ENDPOINT (api_evaluate.php)
# =========================================================
@app.route('/api_evaluate', methods=['POST'])
@app.route('/api_evaluate.php', methods=['POST'])
def api_evaluate():
    data = request.get_json(silent=True) or {}
    submission_id = data.get('submission_id', 0)

    if not submission_id:
        return jsonify({'error': 'No submission ID provided'})

    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM submissions WHERE id = %s", (submission_id,))
    submission = cursor.fetchone()
    cursor.close()
    conn.close()

    if not submission:
        return jsonify({'error': 'Submission not found'})

    file_path = submission.get('file_path', '')
    title = submission.get('title', '')
    context = submission.get('description', '')

    actual_path = os.path.join(BASE_DIR, file_path) if file_path else ''
    file_content = extract_text_for_evaluation(actual_path, max_len=20000)

    prompt = (
        f"You are a strict academic supervisor evaluating a student submission.\n"
        f"DETAILS:\nTitle: {title}\nStudent Context: {context}\n\n"
        f"FILE CONTENT:\n{file_content}\n\n"
        f"TASK: Provide a structured evaluation in JSON format with these fields: "
        f"'score' (1-10), 'title' (a short summary title), and 'feedback' (detailed critique)."
    )

    api_key = os.environ.get('GEMINI_API_KEY', '')
    api_url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={api_key}"

    post_data = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"responseMimeType": "application/json"}
    }

    try:
        resp = requests.post(api_url, json=post_data, timeout=60)
        return app.response_class(response=resp.text, status=resp.status_code, mimetype='application/json')
    except Exception as e:
        return jsonify({'error': str(e)}), 500


# =========================================================
# 13. JSON AI CHAT API (chat.php / api_chat.php)
# =========================================================
@app.route('/chat.php', methods=['POST'])
@app.route('/api_chat.php', methods=['POST'])
def api_chat():
    if not session.get('user_id'):
        return jsonify({"error": "SESSION_EXPIRED"})

    data = request.get_json(silent=True) or {}
    user_message = data.get('message', '')
    context = data.get('context', '')

    api_key = os.environ.get('GEMINI_API_KEY', '')
    if not api_key:
        return jsonify({"error": "API Key is missing! Check Railway variables or your .env file."})

    url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={api_key}"
    prompt = f"You are a helpful teaching assistant. Context: \n{context}\n\nUser Question: {user_message}"
    payload = {"contents": [{"parts": [{"text": prompt}]}]}

    try:
        resp = requests.post(url, json=payload, timeout=60)
        decoded = resp.json()
        if 'candidates' in decoded and decoded['candidates']:
            reply_text = decoded['candidates'][0]['content']['parts'][0]['text']
            return jsonify({"reply": reply_text})
        elif 'error' in decoded and 'message' in decoded['error']:
            return jsonify({"error": "Google API Error: " + decoded['error']['message']})
        else:
            return jsonify({"error": "Unknown API Error. The key might be invalid."})
    except Exception as e:
        return jsonify({"error": f"Connection Error: {str(e)}"})


# =========================================================
# 14. PORTFOLIO CHAT HANDLER (chat_handler.php)
# =========================================================
@app.route('/chat_handler', methods=['POST'])
@app.route('/chat_handler.php', methods=['POST'])
def chat_handler():
    user_id = int(session.get('user_id', 0))
    conn = get_db()
    cursor = conn.cursor()

    try:
        message = request.form.get('message', '')
        context = request.form.get('context', '')
        mode = request.form.get('mode', 'mentor')
        student_file_path = request.form.get('student_file_path', '')

        file_text = ""
        pdf_base64 = None

        chat_file = request.files.get('chat_file')
        if chat_file and chat_file.filename:
            ext = os.path.splitext(chat_file.filename)[1].lower().lstrip('.')
            if ext == 'pdf':
                pdf_base64 = base64.b64encode(chat_file.read()).decode('utf-8')
                file_text += "\n\n[SYSTEM: A PDF file has been natively attached for you to analyze.]\n"
            else:
                temp_path = os.path.join(UPLOAD_FOLDER, f"tmp_{int(time.time())}_{secure_filename(chat_file.filename)}")
                chat_file.save(temp_path)
                extracted = extract_text_for_evaluation(temp_path)
                if os.path.exists(temp_path):
                    os.remove(temp_path)
                if extracted:
                    file_text += f"\n\n=== NEW ATTACHMENT ===\n{extracted}\n======================\n"

        if student_file_path and pdf_base64 is None:
            clean_path = student_file_path.replace('../', '').replace('..\\', '').lstrip('/\\')
            full_path = os.path.join(BASE_DIR, clean_path)
            if os.path.exists(full_path):
                ext = os.path.splitext(full_path)[1].lower().lstrip('.')
                if ext == 'pdf':
                    with open(full_path, 'rb') as pf:
                        pdf_base64 = base64.b64encode(pf.read()).decode('utf-8')
                    file_text += "\n\n[SYSTEM: The student's PDF portfolio file is attached natively.]\n"
                else:
                    extracted_student_file = extract_text_for_evaluation(full_path)
                    if extracted_student_file:
                        file_text += f"\n\n=== STUDENT SUBMISSION FILE CONTENT ===\n{extracted_student_file}\n=======================================\n"

        if message and user_id > 0:
            cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'user', %s)", (user_id, message))
            conn.commit()

            full_prompt = f"CONTEXT:\n{context}\n{file_text}\n\nUSER QUESTION:\n{message}"
            ai_response = generate_ai_response(full_prompt, mode, pdf_base64)

            cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'ai', %s)", (user_id, ai_response))
            conn.commit()

    except Exception as e:
        if user_id > 0:
            error_msg = f"System Error: {str(e)}"
            cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'ai', %s)", (user_id, error_msg))
            conn.commit()
    finally:
        cursor.close()
        conn.close()

    return redirect('/student_portfolio.php')


# =========================================================
# 15. STUDENT DASHBOARD (dashboard.php)
# =========================================================
@app.route('/dashboard')
@app.route('/dashboard.php')
def dashboard():
    if not session.get('user_id'):
        return redirect('/index.php')
    if session.get('role') == 'admin':
        return redirect('/admin_dashboard.php')
    if session.get('role') == 'supervisor':
        return redirect('/supervisor_dashboard.php')

    user_id = session['user_id']
    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT upload_date, competency_score 
        FROM evaluations 
        WHERE user_id = %s AND status='accepted' 
        ORDER BY upload_date ASC
    """, (user_id,))
    graph_rows = cursor.fetchall()

    dates = []
    scores = []
    for row in graph_rows:
        dt = row['upload_date'].strftime("%b %d") if hasattr(row['upload_date'], 'strftime') else str(row['upload_date'])[:10]
        dates.append(dt)
        scores.append(row['competency_score'])

    cursor.execute("SELECT COUNT(*) as count FROM evaluations WHERE user_id = %s AND status='pending'", (user_id,))
    pending_count = (cursor.fetchone() or {}).get('count', 0)

    cursor.execute("SELECT * FROM submissions WHERE user_id = %s ORDER BY upload_date DESC LIMIT 3", (user_id,))
    portfolio_items = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'dashboard.html',
        dates=json.dumps(dates),
        scores=json.dumps(scores),
        pending_count=pending_count,
        portfolio_items=portfolio_items
    )


# =========================================================
# 16. EDIT EVALUATION (edit_evaluation.php)
# =========================================================
@app.route('/edit_evaluation', methods=['GET', 'POST'])
@app.route('/edit_evaluation.php', methods=['GET', 'POST'])
def edit_evaluation():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    eval_id = request.args.get('id', 0, type=int)
    msg = ""
    error = ""

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT e.*, u.fullname as student_name 
        FROM evaluations e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.id = %s
    """, (eval_id,))
    eval_data = cursor.fetchone()

    if not eval_data:
        cursor.close()
        conn.close()
        return redirect('/admin_evaluations.php')

    if request.method == 'POST':
        eval_title = request.form.get('eval_title', '')
        score = request.form.get('score', 0)
        notes = request.form.get('notes', '')
        file_path = eval_data.get('file_path', '')

        uploaded_file = request.files.get('file')
        if uploaded_file and uploaded_file.filename:
            new_file_name = f"admin_edit_{int(time.time())}_{secure_filename(uploaded_file.filename)}"
            uploaded_file.save(os.path.join(UPLOAD_FOLDER, new_file_name))
            file_path = f"uploads/{new_file_name}"

        try:
            cursor.execute("""
                UPDATE evaluations 
                SET evaluation_title=%s, competency_score=%s, readiness_notes=%s, file_path=%s 
                WHERE id=%s
            """, (eval_title, score, notes, file_path, eval_id))
            conn.commit()
            msg = "Evaluation updated successfully!"

            cursor.execute("""
                SELECT e.*, u.fullname as student_name 
                FROM evaluations e 
                JOIN users u ON e.user_id = u.id 
                WHERE e.id = %s
            """, (eval_id,))
            eval_data = cursor.fetchone()
        except Exception as e:
            error = f"Database Error: {str(e)}"

    cursor.close()
    conn.close()
    return render_template('edit_evaluation.html', eval=eval_data, msg=msg, error=error)


# =========================================================
# 17. EDIT USER (edit_user.php)
# =========================================================
@app.route('/edit_user', methods=['GET', 'POST'])
@app.route('/edit_user.php', methods=['GET', 'POST'])
def edit_user():
    if session.get('role') != 'admin' or not session.get('user_id'):
        return redirect('/index.php')

    user_id = request.args.get('id', 0, type=int)
    msg = ""
    error = ""

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))
    user = cursor.fetchone()
    if not user:
        cursor.close()
        conn.close()
        return redirect('/admin_manage.php')

    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        fullname = request.form.get('fullname', '').strip()
        email = request.form.get('email', '').strip()
        gender = request.form.get('gender', '')
        birthdate_str = request.form.get('birthdate') or None

        college = request.form.get('college', '').strip()
        course = request.form.get('course', '').strip()
        year_level = request.form.get('year_level', '').strip()
        section = request.form.get('section', '').strip()
        address = request.form.get('address', '').strip()
        partner_school = request.form.get('partner_school', '').strip()
        role = request.form.get('role', 'student_teacher')
        status = request.form.get('status', 'active')
        supervisor_id = int(request.form.get('supervisor_id') or 0)

        age = 0
        if birthdate_str:
            try:
                dob = datetime.strptime(birthdate_str, "%Y-%m-%d").date()
                today = date.today()
                age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            except Exception:
                age = 0

        new_pass = request.form.get('new_password', '').strip()
        sql = """
            UPDATE users 
            SET username=%s, fullname=%s, email=%s, age=%s, gender=%s, birthdate=%s, 
                college=%s, course=%s, year_level=%s, section=%s, address=%s, 
                partner_school=%s, assigned_supervisor_id=%s, role=%s, status=%s
        """
        params = [
            username, fullname, email, age, gender, birthdate_str,
            college, course, year_level, section, address,
            partner_school, supervisor_id, role, status
        ]

        if new_pass:
            hashed = bcrypt.hashpw(new_pass.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
            sql += ", password=%s, reset_request=0"
            params.append(hashed)

        sql += " WHERE id=%s"
        params.append(user_id)

        try:
            cursor.execute(sql, tuple(params))
            conn.commit()
            msg = "User record successfully updated!"
            cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))
            user = cursor.fetchone()
        except Exception as e:
            error = f"Database Error: {str(e)}"

    cursor.execute("SELECT id, fullname FROM users WHERE role='supervisor' OR role='admin'")
    supervisors = cursor.fetchall()
    cursor.close()
    conn.close()

    return render_template('edit_user.html', user=user, supervisors=supervisors, msg=msg, error=error)


# =========================================================
# 18. WORKDAY PROFILE STUDIO (profile.php)
# =========================================================
@app.route('/profile', methods=['GET', 'POST'])
@app.route('/profile.php', methods=['GET', 'POST'])
def profile():
    if not session.get('user_id'):
        return redirect('/index.php')

    user_id = int(session['user_id'])
    role = session.get('role', 'student_teacher')
    msg = ""
    error = ""

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    sql_user = """
        SELECT u.*, s.fullname as supervisor_name 
        FROM users u 
        LEFT JOIN users s ON u.assigned_supervisor_id = s.id 
        WHERE u.id = %s
    """
    cursor.execute(sql_user, (user_id,))
    user = cursor.fetchone() or {}
    initials = (user.get('fullname') or 'U')[0].upper()

    if request.method == 'POST':
        fullname = request.form.get('fullname', '').strip()
        birthdate_str = request.form.get('birthdate') or None
        gender = request.form.get('gender', '')
        address = request.form.get('address', '').strip()

        age = int(request.form.get('age') or 0)
        if birthdate_str:
            try:
                dob = datetime.strptime(birthdate_str, "%Y-%m-%d").date()
                today = date.today()
                age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            except Exception:
                pass

        profile_pic_path = user.get('profile_pic')

        # 1. Remove Photo
        if request.form.get('remove_photo') == '1':
            if profile_pic_path:
                old_full = os.path.join(BASE_DIR, profile_pic_path)
                if os.path.exists(old_full):
                    try:
                        os.remove(old_full)
                    except OSError:
                        pass
            profile_pic_path = None

        # 2. Webcam Snapshot (Base64)
        elif request.form.get('captured_image_data'):
            img_data = request.form.get('captured_image_data', '')
            img_data = img_data.replace('data:image/png;base64,', '').replace(' ', '+')
            try:
                decoded_img = base64.b64decode(img_data)
                filename = f"user_{user_id}_{int(time.time())}.png"
                target_file = os.path.join(PROFILES_FOLDER, filename)
                with open(target_file, 'wb') as f:
                    f.write(decoded_img)
                if profile_pic_path:
                    old_full = os.path.join(BASE_DIR, profile_pic_path)
                    if os.path.exists(old_full):
                        try:
                            os.remove(old_full)
                        except OSError:
                            pass
                profile_pic_path = f"uploads/profiles/{filename}"
            except Exception as e:
                error = f"Webcam image error: {str(e)}"

        # 3. Standard File Upload
        elif 'profile_pic' in request.files and request.files['profile_pic'].filename:
            pic_file = request.files['profile_pic']
            ext = os.path.splitext(secure_filename(pic_file.filename))[1]
            filename = f"user_{user_id}_{int(time.time())}{ext}"
            target_file = os.path.join(PROFILES_FOLDER, filename)
            pic_file.save(target_file)
            if profile_pic_path:
                old_full = os.path.join(BASE_DIR, profile_pic_path)
                if os.path.exists(old_full):
                    try:
                        os.remove(old_full)
                    except OSError:
                        pass
            profile_pic_path = f"uploads/profiles/{filename}"

        try:
            if role == 'student_teacher':
                course = request.form.get('course', '').strip()
                college = request.form.get('college', '').strip()
                year_level = request.form.get('year_level', '').strip()
                section = request.form.get('section', '').strip()
                cursor.execute("""
                    UPDATE users 
                    SET fullname=%s, age=%s, gender=%s, birthdate=%s, address=%s, 
                        course=%s, college=%s, year_level=%s, section=%s, profile_pic=%s 
                    WHERE id=%s
                """, (fullname, age, gender, birthdate_str, address, course, college, year_level, section, profile_pic_path, user_id))
            else:
                cursor.execute("""
                    UPDATE users 
                    SET fullname=%s, age=%s, gender=%s, birthdate=%s, address=%s, profile_pic=%s 
                    WHERE id=%s
                """, (fullname, age, gender, birthdate_str, address, profile_pic_path, user_id))

            conn.commit()
            msg = "Profile updated successfully!"
            session['fullname'] = fullname
            cursor.execute(sql_user, (user_id,))
            user = cursor.fetchone() or {}
            initials = (user.get('fullname') or 'U')[0].upper()
        except Exception as e:
            error = f"Error updating profile: {str(e)}"

    cursor.close()
    conn.close()
    return render_template('profile.html', user=user, role=role, initials=initials, msg=msg, error=error)


# =========================================================
# 19. ACCOUNT REGISTRATION (register.php)
# =========================================================
@app.route('/register', methods=['GET', 'POST'])
@app.route('/register.php', methods=['GET', 'POST'])
def register():
    msg = ""
    error = ""

    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        fullname = request.form.get('fullname', '').strip()
        email = request.form.get('email', '').strip()
        raw_pass = request.form.get('password', '')
        hashed_pw = bcrypt.hashpw(raw_pass.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')

        role = 'student_teacher'
        status = 'active'

        college = request.form.get('college', '').strip()
        course = request.form.get('course', '').strip()
        year_level = request.form.get('year_level', '').strip()
        section = request.form.get('section', '').strip()
        birthdate_str = request.form.get('birthdate') or None
        gender = request.form.get('gender', '')
        address = request.form.get('address', '').strip()
        partner_school = request.form.get('partner_school', '').strip()

        age = 0
        if birthdate_str:
            try:
                dob = datetime.strptime(birthdate_str, "%Y-%m-%d").date()
                today = date.today()
                age = today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))
            except Exception:
                age = 0

        conn = get_db()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id FROM users WHERE email = %s OR username = %s", (email, username))
        existing = cursor.fetchone()

        if existing:
            error = "An account with that username or email already exists."
        else:
            try:
                cursor.execute("""
                    INSERT INTO users 
                    (username, fullname, email, password, role, status, college, course, year_level, section, birthdate, age, gender, address, partner_school) 
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, (username, fullname, email, hashed_pw, role, status, college, course, year_level, section, birthdate_str, age, gender, address, partner_school))
                conn.commit()
                msg = "Account created successfully! You can now log in."
            except Exception as e:
                error = f"Error creating account: {str(e)}"

        cursor.close()
        conn.close()

    return render_template('register.html', msg=msg, error=error)


# =========================================================
# 20. REQUEST PASSWORD RESET (request_reset.php)
# =========================================================
@app.route('/request_reset', methods=['GET', 'POST'])
@app.route('/request_reset.php', methods=['GET', 'POST'])
def request_reset():
    msg = ""
    error = ""

    if request.method == 'POST':
        email = request.form.get('email', '').strip()
        conn = get_db()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id FROM users WHERE email = %s", (email,))
        found = cursor.fetchone()

        if found:
            cursor.execute("UPDATE users SET reset_request = 1 WHERE email = %s", (email,))
            conn.commit()
            msg = "Request sent! Please contact the Admin to complete the reset."
        else:
            error = "Email address not found."

        cursor.close()
        conn.close()

    return render_template('request_reset.html', msg=msg, error=error)


# =========================================================
# 21. STUDENT PORTFOLIO HUB (student_portfolio.php)
# =========================================================
@app.route('/student_portfolio', methods=['GET', 'POST'])
@app.route('/student_portfolio.php', methods=['GET', 'POST'])
def student_portfolio():
    if not session.get('user_id') or session.get('role') != 'student_teacher':
        return redirect('/index.php')

    user_id = int(session['user_id'])
    msg = request.args.get('msg', '').strip()
    action = request.args.get('action', '')

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    # 1. ACTION: Create New Portfolio Draft
    if action == 'new':
        default_title = f"Untitled Lesson Plan - {datetime.now().strftime('%b %d, %Y')}"
        cursor.execute(
            "INSERT INTO submissions (user_id, title, description, status, chat_transcript) VALUES (%s, %s, '', 'draft', '[]')",
            (user_id, default_title)
        )
        conn.commit()
        new_id = cursor.lastrowid
        cursor.close()
        conn.close()
        return redirect(f"/student_portfolio.php?id={new_id}")

    # 2. ACTION: Delete Draft Portfolio
    if request.method == 'POST' and 'delete_draft' in request.form:
        del_id = int(request.form.get('portfolio_id') or request.args.get('id') or 0)
        if del_id > 0:
            cursor.execute("DELETE FROM submissions WHERE id=%s AND user_id=%s AND status='draft'", (del_id, user_id))
            conn.commit()
        cursor.close()
        conn.close()
        return redirect("/student_portfolio.php?msg=Draft+portfolio+deleted.")

    # 3. ACTION: Make a Revision Copy
    if action == 'revise':
        orig_id = request.args.get('orig_id', 0, type=int)
        cursor.execute("SELECT * FROM submissions WHERE id=%s AND user_id=%s", (orig_id, user_id))
        orig = cursor.fetchone()
        if orig:
            rev_title = f"{orig['title']} (Revision)"
            cursor.execute(
                "INSERT INTO submissions (user_id, title, description, file_path, status, chat_transcript) VALUES (%s, %s, %s, %s, 'draft', %s)",
                (user_id, rev_title, orig.get('description', ''), orig.get('file_path', ''), orig.get('chat_transcript', '[]'))
            )
            conn.commit()
            new_id = cursor.lastrowid
            cursor.close()
            conn.close()
            return redirect(f"/student_portfolio.php?id={new_id}")

    # 4. FETCH ALL SUBMISSIONS
    cursor.execute("""
        SELECT s.*, e.competency_score, e.readiness_notes 
        FROM submissions s 
        LEFT JOIN evaluations e ON s.id = e.submission_id 
        WHERE s.user_id = %s 
        ORDER BY s.id DESC
    """, (user_id,))
    all_submissions = cursor.fetchall()

    # 5. LOAD ACTIVE PORTFOLIO
    portfolio_id = request.args.get('id', 0, type=int)
    if portfolio_id == 0 and all_submissions:
        portfolio_id = int(all_submissions[0]['id'])

    portfolio = None
    is_graded = False

    if portfolio_id > 0:
        cursor.execute("""
            SELECT s.*, e.competency_score, e.readiness_notes, u_eval.fullname as evaluator_name 
            FROM submissions s 
            LEFT JOIN evaluations e ON s.id = e.submission_id 
            LEFT JOIN users u_eval ON e.evaluator_id = u_eval.id 
            WHERE s.id=%s AND s.user_id=%s LIMIT 1
        """, (portfolio_id, user_id))
        portfolio = cursor.fetchone()
        if portfolio:
            if portfolio.get('competency_score') or portfolio.get('status') in ['accepted', 'graded']:
                is_graded = True
        else:
            portfolio_id = 0

    # 6. INSTANT ASYNC FILE UPLOAD (AJAX)
    if request.method == 'POST' and 'ajax_file_upload' in request.form and portfolio_id > 0:
        uploaded_file = request.files.get('file')
        if uploaded_file and uploaded_file.filename:
            clean_name = re.sub(r'[^a-zA-Z0-9._-]', '', os.path.basename(uploaded_file.filename))
            filename = f"{user_id}_evidence_{int(time.time())}_{clean_name}"
            target_file = os.path.join(UPLOAD_FOLDER, filename)
            rel_path = f"uploads/{filename}"
            uploaded_file.save(target_file)

            cursor.execute("UPDATE submissions SET file_path=%s WHERE id=%s AND user_id=%s", (rel_path, portfolio_id, user_id))
            conn.commit()
            cursor.close()
            conn.close()
            return jsonify({'success': True, 'file_path': rel_path})
        cursor.close()
        conn.close()
        return jsonify({'success': False})

    # 7. COPILOT ASYNC CHAT & EVALUATION (AJAX)
    if request.method == 'POST' and 'ajax_chat' in request.form and portfolio_id > 0 and portfolio:
        user_msg = request.form.get('message', '').strip()
        auto_eval = request.form.get('run_eval') == '1'
        current_desc = (request.form.get('context_description') or portfolio.get('description') or '').strip()
        current_ttl = (request.form.get('context_title') or portfolio.get('title') or '').strip()

        if auto_eval:
            user_msg = (
                "Please evaluate this portfolio submission against the 100-Point PPST Rubric. "
                "Evaluate Objectives (20), Content (20), Methodology (30), Assessment (20), and Mechanics (10). "
                "Provide an estimated score out of 100 with clear marks [GREAT: 90-100], [GOOD: 75-89], or [NEEDS REVISION: <75]."
            )

        if not user_msg:
            cursor.close()
            conn.close()
            return jsonify({'success': False, 'error': 'Message is empty'})

        try:
            chats = json.loads(portfolio.get('chat_transcript') or '[]')
            if not isinstance(chats, list):
                chats = []
        except Exception:
            chats = []

        user_time = datetime.now().strftime('%I:%M %p')
        chats.append({'sender': 'user', 'message': user_msg, 'time': user_time})

        file_context_str = ""
        if portfolio.get('file_path'):
            full_file_path = os.path.join(BASE_DIR, portfolio['file_path'].lstrip('/\\'))
            extracted_text = extract_text_for_evaluation(full_file_path, max_len=7000)
            if extracted_text:
                file_context_str = (
                    f"\n\n--- ATTACHED EVIDENCE / LESSON ARTIFACT ({os.path.basename(portfolio['file_path'])}) ---\n"
                    f"{extracted_text}\n--- END OF ATTACHMENT ---\n"
                )

        if auto_eval:
            ai_prompt = (
                "You are an expert Academic Evaluator for pre-service teachers.\n"
                "Task: Evaluate this lesson plan/portfolio submission against the 100-Point PPST Rubric:\n"
                "1. Objectives (20 pts): Specific, Measurable, HOTS-aligned (Bloom's Taxonomy).\n"
                "2. Content (20 pts): Accurate subject matter aligned with curriculum.\n"
                "3. Methodology (30 pts): Active student-centered pedagogy and instructional flow.\n"
                "4. Assessment (20 pts): Formative/summative tools aligned with objectives.\n"
                "5. Formatting & Mechanics (10 pts): Professional documentation.\n\n"
                f"Portfolio Title: {current_ttl}\n"
                f"Lesson Content / Procedures:\n{current_desc or '(No text entered in editor)'}\n"
                f"{file_context_str}\n\n"
                "Provide an estimated score out of 100, classify as [GREAT: 90-100], [GOOD: 75-89], or [NEEDS REVISION: <75], and provide actionable advice."
            )
        else:
            ai_prompt = (
                "You are a supportive, knowledgeable Academic Copilot and Teacher Education Mentor for Pre-Service Teachers.\n"
                "The student is asking you a question. You are fully capable of answering ANY question outside of just grading, including:\n"
                "- General teaching methods, lesson planning ideas, learning objectives (Bloom's Taxonomy), pedagogical approaches.\n"
                "- Explaining concepts in education, subject matter explanations, classroom management, or assessment design.\n"
                "- Answering general questions, brainstorming, or having friendly professional conversation.\n\n"
                "Workspace Context (Reference only if relevant):\n"
                f"- Portfolio Title: {current_ttl or 'Untitled'}\n"
                f"- Lesson Content: {current_desc or 'None typed yet'}\n"
                f"{('- Attached File:' + file_context_str) if file_context_str else ''}\n"
                f"Student's Message: \"{user_msg}\"\n\n"
                "RULES:\n"
                "1. Answer the student's question directly, clearly, and supportively.\n"
                "2. Do NOT demand a lesson plan or refuse to answer if no files are uploaded. Answer their question naturally!\n"
                "3. Keep your tone encouraging and professional."
            )

        ai_reply = generate_ai_response(ai_prompt, 'mentor')
        ai_time = datetime.now().strftime('%I:%M %p')
        chats.append({'sender': 'ai', 'message': ai_reply, 'time': ai_time})

        cursor.execute(
            "UPDATE submissions SET chat_transcript=%s WHERE id=%s AND user_id=%s",
            (json.dumps(chats), portfolio_id, user_id)
        )
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({
            'success': True,
            'user_msg': user_msg,
            'user_time': user_time,
            'ai_reply': ai_reply,
            'ai_time': ai_time
        })

    # 8. ACTION: Save Draft or Submit Portfolio (Main Form)
    if request.method == 'POST' and 'save_portfolio' in request.form and portfolio_id > 0 and not is_graded:
        title_val = request.form.get('title', 'Untitled Portfolio').strip()
        desc_val = request.form.get('description', '').strip()
        new_status = 'pending' if request.form.get('save_portfolio') == 'submit' else 'draft'

        target_file = portfolio.get('file_path') or ''
        uploaded_file = request.files.get('file')
        if uploaded_file and uploaded_file.filename:
            clean_name = re.sub(r'[^a-zA-Z0-9._-]', '', os.path.basename(uploaded_file.filename))
            filename = f"{user_id}_evidence_{int(time.time())}_{clean_name}"
            uploaded_file.save(os.path.join(UPLOAD_FOLDER, filename))
            target_file = f"uploads/{filename}"

        cursor.execute(
            "UPDATE submissions SET title=%s, description=%s, file_path=%s, status=%s WHERE id=%s AND user_id=%s",
            (title_val, desc_val, target_file, new_status, portfolio_id, user_id)
        )
        conn.commit()
        cursor.close()
        conn.close()
        notif = "Portfolio submitted for official review!" if new_status == 'pending' else "Draft saved successfully!"
        return redirect(f"/student_portfolio.php?id={portfolio_id}&msg={notif}")

    chats = []
    if portfolio and portfolio.get('chat_transcript'):
        try:
            chats = json.loads(portfolio['chat_transcript'])
        except Exception:
            chats = []

    cursor.close()
    conn.close()
    return render_template(
        'student_portfolio.html',
        all_submissions=all_submissions,
        portfolio=portfolio,
        portfolio_id=portfolio_id,
        is_graded=is_graded,
        chats=chats,
        msg=msg
    )


# =========================================================
# 22. SUPERVISOR DASHBOARD (supervisor_dashboard.php)
# =========================================================
@app.route('/supervisor_dashboard')
@app.route('/supervisor_dashboard.php')
def supervisor_dashboard():
    if not session.get('user_id') or session.get('role') != 'supervisor':
        return redirect('/index.php')

    supervisor_id = int(session['user_id'])
    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT COUNT(*) as total FROM users WHERE role='student_teacher' AND assigned_supervisor_id = %s", (supervisor_id,))
    total_students = (cursor.fetchone() or {}).get('total', 0)

    cursor.execute("""
        SELECT COUNT(*) as count 
        FROM evaluations e 
        JOIN users u ON e.user_id = u.id 
        WHERE u.assigned_supervisor_id = %s AND e.status='pending'
    """, (supervisor_id,))
    pending_count = (cursor.fetchone() or {}).get('count', 0)

    cursor.execute("""
        SELECT s.*, u.fullname 
        FROM submissions s 
        JOIN users u ON s.user_id = u.id 
        WHERE u.assigned_supervisor_id = %s AND s.status = 'pending' 
        ORDER BY s.upload_date ASC
    """, (supervisor_id,))
    pending_subs = cursor.fetchall()

    cursor.execute("""
        SELECT u.id, u.fullname, u.email, u.course,
        COALESCE((SELECT AVG(competency_score) FROM evaluations WHERE user_id = u.id AND status='accepted'), 0) as avg_score
        FROM users u 
        WHERE u.role='student_teacher' AND u.assigned_supervisor_id = %s
        ORDER BY avg_score DESC
    """, (supervisor_id,))
    students = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'supervisor_dashboard.html',
        total_students=total_students,
        pending_count=pending_count,
        pending_subs=pending_subs,
        students=students
    )


# =========================================================
# 23. SUPERVISOR EVALUATE SUBMISSION (supervisor_evaluate_submission.php)
# =========================================================
@app.route('/supervisor_evaluate_submission', methods=['GET', 'POST'])
@app.route('/supervisor_evaluate_submission.php', methods=['GET', 'POST'])
def supervisor_evaluate_submission():
    if not session.get('user_id') or session.get('role') != 'supervisor':
        return redirect('/index.php')

    supervisor_id = int(session['user_id'])
    sub_id = request.args.get('sub_id', 0, type=int)

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT s.*, u.fullname FROM submissions s JOIN users u ON s.user_id = u.id WHERE s.id = %s", (sub_id,))
    sub = cursor.fetchone()
    if not sub:
        cursor.close()
        conn.close()
        return redirect('/supervisor_dashboard.php')

    file_content_msg = ""
    if sub.get('file_path'):
        clean_rel_path = sub['file_path'].replace('../', '').replace('..\\', '').lstrip('/\\')
        actual_path = os.path.join(BASE_DIR, clean_rel_path)
        if os.path.exists(actual_path):
            extracted_text = extract_text_for_evaluation(actual_path)
            file_content_msg = f"\n\n=== [EVIDENCE FILE CONTENT] ===\n{extracted_text}\n==============================\n"

    master_context = f"Title: {sub['title']}\nStudent Context Description: {sub['description']}{file_content_msg}"
    ai_scores = {'obj': '', 'con': '', 'meth': '', 'ass': '', 'fmt': '', 'total': ''}
    ai_feedback = ""

    if request.method == 'POST':
        if 'send_chat' in request.form:
            message = request.form.get('message', '').strip()
            context = request.form.get('context', '')
            if message:
                cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'user', %s)", (supervisor_id, message))
                conn.commit()
                ai_response = generate_ai_response(f"Context:\n{context}\n\nUser Question: {message}", 'consultant')
                cursor.execute("INSERT INTO chat_logs (user_id, sender, message) VALUES (%s, 'ai', %s)", (supervisor_id, ai_response))
                conn.commit()
                cursor.close()
                conn.close()
                return redirect(f"/supervisor_evaluate_submission.php?sub_id={sub_id}")

        elif 'generate_ai' in request.form:
            full_prompt = f"""You are a professional teacher evaluator grading a lesson plan. Use this exact 100-point rubric:
            1. Objectives (Max 20)
            2. Content (Max 20)
            3. Methodology (Max 30)
            4. Assessment (Max 20)
            5. Formatting (Max 10)
            
            Student Work: {master_context}
            
            CRITICAL: If the document content contains a SYSTEM ALERT about a missing or unreadable file, DO NOT PANIC AND DO NOT FAIL. You MUST evaluate the lesson plan based SOLELY on the 'Student Context Description'.
            
            RETURN ONLY VALID JSON EXACTLY LIKE THIS FORMAT. DO NOT ADD ANY OTHER TEXT:
            {{
                "obj": 18,
                "con": 15,
                "meth": 25,
                "ass": 15,
                "fmt": 10,
                "total": 83,
                "feedback": "Detailed feedback..."
            }}"""
            raw_ai_text = generate_ai_response(full_prompt, 'evaluator')
            match = re.search(r'\{[\s\S]*\}', raw_ai_text)
            if match:
                try:
                    parsed = json.loads(match.group(0))
                    if parsed and 'total' in parsed:
                        ai_scores = parsed
                        ai_feedback = parsed.get('feedback', '')
                    else:
                        ai_feedback = "AI JSON Error. Raw output: " + match.group(0)
                except Exception:
                    ai_feedback = "AI JSON Error. Raw output: " + match.group(0)
            else:
                ai_feedback = "AI failed to return the scoring format. Raw output: " + raw_ai_text

        elif 'submit_grade' in request.form:
            eval_title = request.form.get('eval_title', '')
            score = request.form.get('human_total', 0)
            notes = (
                f"RUBRIC BREAKDOWN:\n"
                f"Objectives: {request.form.get('human_obj')}/20\n"
                f"Content: {request.form.get('human_con')}/20\n"
                f"Methodology: {request.form.get('human_meth')}/30\n"
                f"Assessment: {request.form.get('human_ass')}/20\n"
                f"Formatting: {request.form.get('human_fmt')}/10\n\n"
                f"FEEDBACK:\n{request.form.get('notes', '')}"
            )
            student_id = sub['user_id']
            target_file = sub.get('file_path', '')

            uploaded_file = request.files.get('file')
            if uploaded_file and uploaded_file.filename:
                safe_name = secure_filename(uploaded_file.filename)
                filename = f"eval_{int(time.time())}_{safe_name}"
                uploaded_file.save(os.path.join(UPLOAD_FOLDER, filename))
                target_file = f"uploads/{filename}"

            cursor.execute("""
                INSERT INTO evaluations 
                (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, file_path, status) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending')
            """, (student_id, supervisor_id, sub_id, eval_title, score, notes, target_file))
            cursor.execute("UPDATE submissions SET status='evaluated' WHERE id=%s", (sub_id,))
            conn.commit()
            cursor.close()
            conn.close()
            return redirect(f"/admin_evaluations.php?student_id={student_id}")

    cursor.execute("SELECT * FROM chat_logs WHERE user_id=%s ORDER BY created_at ASC", (supervisor_id,))
    chat_history = cursor.fetchall()
    cursor.close()
    conn.close()

    return render_template(
        'supervisor_evaluate_submission.html',
        sub=sub,
        master_context=master_context,
        ai_scores=ai_scores,
        ai_feedback=ai_feedback,
        chat_history=chat_history
    )


# =========================================================
# 24. SUPERVISOR VIEW EVALUATION (supervisor_view_evaluation.php)
# =========================================================
@app.route('/supervisor_view_evaluation')
@app.route('/supervisor_view_evaluation.php')
def supervisor_view_evaluation():
    if not session.get('user_id') or session.get('role') != 'supervisor':
        return redirect('/index.php')

    eval_id = request.args.get('id', 0, type=int)
    supervisor_id = int(session['user_id'])

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT e.*, 
               student.fullname as student_name, 
               student.assigned_supervisor_id,
               evaluator.fullname as evaluator_name, 
               evaluator.role as evaluator_role 
        FROM evaluations e 
        JOIN users student ON e.user_id = student.id 
        LEFT JOIN users evaluator ON e.evaluator_id = evaluator.id 
        WHERE e.id = %s
    """, (eval_id,))
    eval_data = cursor.fetchone()
    cursor.close()
    conn.close()

    if not eval_data:
        return "Evaluation not found.", 404

    if eval_data.get('assigned_supervisor_id') != supervisor_id and eval_data.get('evaluator_id') != supervisor_id:
        return "Access Denied: You are not assigned to this student.", 403

    file_path = eval_data.get('file_path') or ''
    file_ext = os.path.splitext(file_path)[1].lower().lstrip('.')

    return render_template(
        'supervisor_view_evaluation.html',
        eval=eval_data,
        supervisor_id=supervisor_id,
        file_ext=file_ext
    )


# =========================================================
# 25. SUPERVISOR VIEW STUDENT (supervisor_view_student.php)
# =========================================================
@app.route('/supervisor_view_student', methods=['GET', 'POST'])
@app.route('/supervisor_view_student.php', methods=['GET', 'POST'])
def supervisor_view_student():
    if not session.get('user_id') or session.get('role') != 'supervisor':
        return redirect('/index.php')

    supervisor_id = int(session['user_id'])
    student_id = request.args.get('id', 0, type=int)
    msg = ""

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM users WHERE id=%s AND assigned_supervisor_id=%s", (student_id, supervisor_id))
    student = cursor.fetchone()
    if not student:
        cursor.close()
        conn.close()
        return "Access Denied: This student is not assigned to you.", 403

    if request.method == 'POST':
        eval_title = request.form.get('eval_title', '')
        score = request.form.get('score', 0)
        notes = request.form.get('notes', '')
        target_file = ""

        uploaded_file = request.files.get('file')
        if uploaded_file and uploaded_file.filename:
            safe_name = secure_filename(uploaded_file.filename)
            filename = f"{student_id}_sup_eval_{int(time.time())}_{safe_name}"
            uploaded_file.save(os.path.join(UPLOAD_FOLDER, filename))
            target_file = f"uploads/{filename}"

        try:
            cursor.execute("""
                INSERT INTO evaluations 
                (user_id, evaluator_id, evaluation_title, competency_score, readiness_notes, file_path, status) 
                VALUES (%s, %s, %s, %s, %s, %s, 'pending')
            """, (student_id, supervisor_id, eval_title, score, notes, target_file))
            conn.commit()
            msg = "General evaluation submitted successfully!"
        except Exception:
            msg = "Database Error."

    cursor.execute("SELECT * FROM evaluations WHERE user_id = %s ORDER BY upload_date ASC", (student_id,))
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    dates = []
    scores = []
    for r in rows:
        if r.get('status') == 'accepted' and r.get('upload_date'):
            dt_str = r['upload_date'].strftime("%b %d") if hasattr(r['upload_date'], 'strftime') else str(r['upload_date'])[:10]
            dates.append(dt_str)
            scores.append(r['competency_score'])

    history_data = list(reversed(rows))

    return render_template(
        'supervisor_view_student.html',
        student=student,
        msg=msg,
        history_data=history_data,
        dates=json.dumps(dates),
        scores=json.dumps(scores)
    )


if __name__ == '__main__':
    app.run(debug=True)