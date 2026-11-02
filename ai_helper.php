<?php
// NEW: Added the $pdf_base64 parameter
function generateAIResponse($context_text, $mode = 'evaluator', $pdf_base64 = null) {
    
    if (!function_exists('curl_init')) {
        return "System Error: The cURL extension is not enabled in your XAMPP php.ini file.";
    }

    $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ''); 
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    if ($mode == 'evaluator') {
        $prompt = "
        You are an expert Cooperating Teacher and Pedagogy Evaluator.
        Task: Evaluate the following student submission based on the PPST (Philippine Professional Standards for Teachers).
        
        Context: $context_text
        
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
        ";
    } // 2. MENTOR MODE (For Student Chat)
    elseif ($mode == 'mentor') {
        $prompt = "
        You are a supportive PPST Mentor for student teachers.
        
        $context_text
        
        CRITICAL INSTRUCTION:
        Respond DIRECTLY to the 'USER QUESTION' provided above. Do NOT automatically generate a review or summary unless the user asks for one. If the user just says 'hello' or greets you, greet them back and ask how you can help them with their portfolio.
        
        OUTPUT RULES:
        1. STRICTLY PLAIN TEXT ONLY. No bolding (**), no markdown.
        2. Keep it encouraging and actionable.
        3. Use dashes (-) for lists.
        ";
    }
    // 3. CONSULTANT MODE (For Admin/Supervisor Chat)
    elseif ($mode == 'consultant') {
        $prompt = "
        You are an expert Educational Consultant assisting a Supervisor.
        
        $context_text
        
        CRITICAL INSTRUCTION:
        Respond DIRECTLY to the 'USER QUESTION' provided above. Do NOT automatically summarize the context unless explicitly asked. If the user simply says 'hello', greet them professionally and ask how you can assist with the evaluation of this file.
        
        OUTPUT RULES:
        1. STRICTLY PLAIN TEXT ONLY. No bolding (**), no markdown.
        2. Be concise, direct, and professional.
        3. If asked to summarize, provide clear bullet points using dashes (-).
        ";
    }

    // NEW: We structure the parts array to hold text AND files
    $parts = [
        ["text" => $prompt]
    ];

    // NEW: If a PDF was uploaded, we attach it natively to Google!
    if ($pdf_base64 !== null) {
        $parts[] = [
            "inlineData" => [
                "mimeType" => "application/pdf",
                "data" => $pdf_base64
            ]
        ];
    }

    $data = [
        "contents" => [
            [
                "parts" => $parts
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 

    $response = curl_exec($ch);
    
    if(curl_errno($ch)){ 
        return "CURL Connection Error: " . curl_error($ch); 
    }
    
    curl_close($ch);

    $json = json_decode($response, true);
    
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_text = $json['candidates'][0]['content']['parts'][0]['text'];
        return cleanMarkdown($raw_text);
    } else {
        if(isset($json['error']['message'])) {
            return "Google API Error: " . $json['error']['message'];
        }
        return "AI Error: The server received an empty or unreadable response from Google.";
    }
}

function cleanMarkdown($text) {
    $text = str_replace(['**', '__', '*'], '', $text);   
    $text = str_replace(['### ', '## ', '# '], '', $text);   
    $text = str_replace('|', ' - ', $text);   
    $text = str_replace('```', '', $text);   
    return trim($text);
}
