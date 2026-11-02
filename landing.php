<?php
// landing.php - AI Lesson Plan Evaluator Landing Page
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CORE - Competency and Evaluation Readiness</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0f172a, #1e3a8a, #2563eb);
            min-height: 100vh;
            color: white;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 8%;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
        }

        .logo {
            font-size: 1.6rem;
            font-weight: bold;
        }

        .nav-buttons a {
            text-decoration: none;
            margin-left: 12px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: bold;
            transition: 0.3s ease;
        }

        .login-btn {
            background: white;
            color: #1e3a8a;
        }

        .register-btn {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .nav-buttons a:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 80px 8%;
            flex-wrap: wrap;
            gap: 40px;
        }

        .hero-text {
            flex: 1;
            min-width: 320px;
        }

        .hero-text h1 {
            font-size: 3.4rem;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-text p {
            font-size: 1.15rem;
            line-height: 1.8;
            color: #dbeafe;
            margin-bottom: 30px;
        }

        .highlight-tagline {
            display: inline-block;
            background: linear-gradient(90deg, #facc15, #fb7185);
            color: #0f172a;
            padding: 12px 18px;
            border-radius: 14px;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        }

        .hero-text p {
            font-size: 1.15rem;
            line-height: 1.8;
            color: #dbeafe;
            margin-bottom: 30px;
        }

        .cta-buttons a {
            display: inline-block;
            text-decoration: none;
            padding: 14px 26px;
            border-radius: 12px;
            margin-right: 15px;
            font-weight: bold;
            transition: 0.3s ease;
        }

        .primary-btn {
            background: white;
            color: #1e3a8a;
        }

        .secondary-btn {
            border: 2px solid white;
            color: white;
        }

        .cta-buttons a:hover {
            transform: scale(1.05);
        }

        .hero-card {
            flex: 1;
            min-width: 300px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }

        .hero-card h3 {
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .feature {
            margin-bottom: 18px;
            padding: 14px;
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
        }

        .features-section {
            padding: 70px 8%;
            background: rgba(0,0,0,0.15);
        }

        .features-section h2 {
            text-align: center;
            font-size: 2.4rem;
            margin-bottom: 40px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .feature-box {
            background: rgba(255,255,255,0.1);
            padding: 25px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .feature-box h3 {
            margin-bottom: 12px;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #dbeafe;
        }

        @media (max-width: 768px) {
            .hero-text h1 {
                font-size: 2.3rem;
            }

            .navbar {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">CORE</div>
        <div class="nav-buttons">
            <a href="index.php" class="login-btn">Login</a>
            <a href="register.php" class="register-btn">Register</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-text">
            <h1>CORE<br><span style="font-size: 2rem; color: #dbeafe;">Competency and Evaluation Readiness</span></h1>
            <div class="highlight-tagline">✨ Your virtual consultant for your teaching journey starts here</div>
            <p>
                Upload student-created lesson plans and receive instant AI-based evaluation,
                curriculum alignment checks, teaching strategy analysis, rubric-based scoring,
                and personalized improvement recommendations.
            </p>
            <div class="cta-buttons">
                <a href="index.php" class="primary-btn">Get Started</a>
                <a href="register.php" class="secondary-btn">Create Account</a>
            </div>
        </div>

        <div class="hero-card">
            <h3>What AI Evaluates</h3>
            <div class="feature">📘 Lesson Objectives Alignment</div>
            <div class="feature">🧠 Teaching Methodology Quality</div>
            <div class="feature">📊 Assessment Strategy Review</div>
            <div class="feature">✍️ Structure, Clarity, and Completeness</div>
            <div class="feature">🎯 Standards & Curriculum Compliance</div>
        </div>
    </section>

    <section class="features-section">
        <h2>Why Choose CORE?</h2>
        <div class="feature-grid">
            <div class="feature-box">
                <h3>Instant Feedback</h3>
                <p>Eliminate long manual checking by generating evaluations in seconds.</p>
            </div>
            <div class="feature-box">
                <h3>Rubric-Based Scoring</h3>
                <p>Evaluate lesson plans using standardized academic criteria.</p>
            </div>
            <div class="feature-box">
                <h3>Smart Recommendations</h3>
                <p>AI suggests specific improvements to strengthen teaching plans.</p>
            </div>
            <div class="feature-box">
                <h3>Railway Ready</h3>
                <p>PHP-compatible lightweight deployment structure for Railway hosting.</p>
            </div>
        </div>
    </section>

    <footer>
        © <?php echo date('Y'); ?> CORE (Competency and Evaluation Readiness) — Your Virtual Consultant for Teaching Excellence
    </footer>

</body>
</html>
