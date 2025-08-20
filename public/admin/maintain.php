<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>We're Making Magic! ✨</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --secondary: #a29bfe;
            --accent: #fd79a8;
        }
        
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #fdcbf1, #e6dee9, #a6c1ee, #fbc2eb);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #2d3436;
            touch-action: manipulation;
            overflow-x: hidden;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .maintenance-container {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            width: 92%;
            max-width: 500px;
            padding: 30px 25px;
            margin: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 480px) {
            .maintenance-container {
                padding: 25px 20px;
                border-radius: 16px;
            }
        }
        
        .hero-image {
            width: 160px;
            height: 160px;
            margin: 0 auto 15px;
            display: block;
            animation: float 6s ease-in-out infinite;
        }
        
        @media (max-width: 480px) {
            .hero-image {
                width: 130px;
                height: 130px;
            }
        }
        
        h1 {
            color: var(--primary);
            margin: 0 0 12px 0;
            font-weight: 700;
            font-size: clamp(1.5rem, 5vw, 2rem);
            line-height: 1.3;
        }
        
        p {
            color: #636e72;
            line-height: 1.6;
            margin: 0 0 20px 0;
            font-size: clamp(0.95rem, 3vw, 1.1rem);
        }
        
        .back-button {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 44px;
            height: 44px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            z-index: 10;
            font-size: 20px;
            padding: 0;
        }
        
        .back-button:hover, .back-button:focus {
            background: var(--primary);
            color: white;
            transform: translateX(-3px);
        }
        
        .back-button:active {
            transform: scale(0.96);
        }
        
        .progress-container {
            margin: 25px auto;
            width: 85%;
            max-width: 300px;
            height: 6px;
            background: #dfe6e9;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            width: 45%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 10px;
            animation: progress 2s ease-in-out infinite, rainbow 8s linear infinite;
            background-size: 200% 100%;
        }
        
        .happy-emoji {
            font-size: 1.8rem;
            display: inline-block;
            animation: bounce 2s infinite;
            vertical-align: middle;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }
        
        @keyframes progress {
            0% { width: 45%; }
            50% { width: 65%; }
            100% { width: 45%; }
        }
        
        @keyframes rainbow {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* Mobile-specific optimizations */
        @media (max-width: 768px) {
            body {
                animation: gradientBG 25s ease infinite;
            }
            
            .maintenance-container {
                width: 90%;
                padding: 25px 20px;
            }
            
            .back-button {
                width: 42px;
                height: 42px;
                top: 12px;
                left: 12px;
            }
        }
        
        /* Very small devices */
        @media (max-width: 360px) {
            h1 {
                font-size: 1.4rem;
            }
            
            p {
                font-size: 0.9rem;
            }
            
            .hero-image {
                width: 110px;
                height: 110px;
            }
        }
        
        /* Prevent zoom on input */
        @media screen and (-webkit-min-device-pixel-ratio:0) {
            select,
            textarea,
            input {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <button class="back-button" onclick="window.history.back()" aria-label="Go back">←</button>
        
        <img src="https://cdn-icons-png.flaticon.com/512/1055/1055687.png" alt="Happy maintenance" class="hero-image">
        
        <h1>We're Making Things Better! <span class="happy-emoji" aria-hidden="true">😊</span></h1>
        <p>Our team is working hard to bring you an amazing experience. Thank you for your patience!</p>
        
        <div class="progress-container">
            <div class="progress-bar" aria-hidden="true"></div>
        </div>
        
        <p>Good things come to those who wait... and we promise it'll be worth it!</p>
    </div>

    <script>
        // Mobile-friendly touch enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Make back button more responsive to touch
            const backButton = document.querySelector('.back-button');
            
            backButton.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.96)';
            }, { passive: true });
            
            backButton.addEventListener('touchend', function() {
                this.style.transform = '';
            }, { passive: true });
            
            // Prevent zooming on double-tap
            document.documentElement.addEventListener('touchstart', function(event) {
                if (event.touches.length > 1) {
                    event.preventDefault();
                }
            }, { passive: false });
            
            // Optimize animations for mobile
            if ('matchMedia' in window && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.querySelectorAll('*').forEach(el => {
                    el.style.animationPlayState = 'paused !important';
                });
            }
        });
    </script>
</body>
</html>