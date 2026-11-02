import os
import re
import zipfile
import xml.etree.ElementTree as ET
import requests

def clean_markdown(text):
    if not text:
        return ""
    for token in ['**', '__', '*', '### ', '## ', '# ', '```']:
        text = text.replace(token, '')
    text = text.replace('|', ' - ')
    return text.strip()

def generate_ai_response(context_text, mode='evaluator', pdf_base64=None):
    api_key = os.environ.get('GEMINI_API_KEY', '')
    api_url = f"[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=){api_key}"

    if mode == 'evaluator':
        prompt = f"""
        You are an expert Cooperating Teacher and Pedagogy Evaluator.
        Task: Evaluate the following student submission based on the PPST (Philippine Professional Standards for Teachers).
        
        Context: {context_text}
        
        CRITICAL OUTPUT RULES:
        1. Start with [SCORE: X] (1-10).
        2. STRICTLY PLAIN TEXT ONLY. No markdown, no bolding (**), no tables (|).
        3. Use standard dashes (-) for lists.
        4. Be objective and professional.
        
        Structure:
        [SCORE: X]
        STRENGTHS:
        - Point 1
        AREAS FOR IMPROVEMENT:
        - Point 1
        RECOMMENDATION:
        (Summary)
        """
    elif mode == 'mentor':
        prompt = f"""
        You are a supportive PPST Mentor for student teachers.
        
        {context_text}
        
        CRITICAL INSTRUCTION:
        Respond DIRECTLY to the 'USER QUESTION' provided above. Do NOT automatically generate a review or summary unless the user asks for one. If the user just says 'hello' or greets you, greet them back and ask how you can help them with their portfolio.
        
        OUTPUT RULES:
        1. STRICTLY PLAIN TEXT ONLY. No bolding (**), no markdown.
        2. Keep it encouraging and actionable.
        3. Use dashes (-) for lists.
        """
    elif mode == 'consultant':
        prompt = f"""
        You are an expert Educational Consultant assisting a Supervisor.
        
        {context_text}
        
        CRITICAL INSTRUCTION:
        Respond DIRECTLY to the 'USER QUESTION' provided above. Do NOT automatically summarize the context unless explicitly asked. If the user simply says 'hello', greet them professionally and ask how you can assist with the evaluation of this file.
        
        OUTPUT RULES:
        1. STRICTLY PLAIN TEXT ONLY. No bolding (**), no markdown.
        2. Be concise, direct, and professional.
        3. If asked to summarize, provide clear bullet points using dashes (-).
        """
    else:
        prompt = context_text

    parts = [{"text": prompt}]

    if pdf_base64 is not None:
        parts.append({
            "inlineData": {
                "mimeType": "application/pdf",
                "data": pdf_base64
            }
        })

    payload = {"contents": [{"parts": parts}]}

    try:
        response = requests.post(api_url, json=payload, timeout=60)
        json_data = response.json()
        if 'candidates' in json_data and json_data['candidates']:
            raw_text = json_data['candidates'][0]['content']['parts'][0]['text']
            return clean_markdown(raw_text)
        elif 'error' in json_data:
            return "Google API Error: " + json_data['error'].get('message', 'Unknown error')
        return "AI Error: The server received an empty or unreadable response from Google."
    except Exception as e:
        return f"Connection Error: {str(e)}"

def extract_text_for_evaluation(file_path, max_len=15000):
    if not file_path or not os.path.exists(file_path):
        return "[SYSTEM ALERT: File missing. Evaluate based on Description only.]"
    
    ext = os.path.splitext(file_path)[1].lower().lstrip('.')
    if ext == 'pdf':
        return "[SYSTEM ALERT: PDF uploaded. AI cannot read PDFs. Evaluate based on Description only.]"
    
    text = ""
    if ext == 'docx':
        try:
            with zipfile.ZipFile(file_path, 'r') as z:
                if 'word/document.xml' in z.namelist():
                    xml_content = z.read('word/document.xml')
                    tree = ET.fromstring(xml_content)
                    text = "".join(tree.itertext())
        except Exception:
            return "[SYSTEM ALERT: Cannot extract DOCX on this server. Evaluate based on Description only.]"
    elif ext in ['txt', 'php', 'html', 'css', 'js', 'json', 'md', 'sql', 'py', 'c']:
        try:
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                text = f.read()
        except Exception:
            return "[SYSTEM ALERT: Cannot read text file.]"
    else:
        return f"[SYSTEM NOTE: File type .{ext} not supported.]"

    text = re.sub(r'[\x00-\x1F\x7F]', ' ', text)
    return text.strip()[:max_len]