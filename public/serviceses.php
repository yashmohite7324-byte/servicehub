<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Services - ServiceHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --gray: #adb5bd;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 600;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 25px;
        }

        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        .main-content {
            flex: 1;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title h2 {
            color: var(--dark);
            font-size: 2.2rem;
            margin-bottom: 15px;
        }
        
        .page-title p {
            color: var(--dark);
            opacity: 0.8;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .services-section {
            margin-bottom: 60px;
        }
        
        .section-title {
            color: var(--dark);
            font-size: 1.8rem;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            display: inline-block;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .service-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .service-icon {
            height: 120px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }
        
        .service-content {
            padding: 25px;
        }
        
        .service-content h3 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 1.4rem;
        }
        
        .service-content p {
            color: var(--dark);
            opacity: 0.8;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .service-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(76, 201, 240, 0.2);
            color: var(--success);
        }
        
        .status-upcoming {
            background: rgba(248, 150, 30, 0.2);
            color: var(--warning);
        }
        
        .video-section {
            margin: 60px 0;
            text-align: center;
        }
        
        .video-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            padding-top: 56.25%; /* 16:9 Aspect Ratio */
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .video-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark);
            flex-direction: column;
            padding: 20px;
        }
        
        .video-placeholder i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .cta-section {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-top: 40px;
        }
        
        .cta-section h3 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 1.6rem;
        }
        
        .cta-section p {
            color: var(--dark);
            opacity: 0.8;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .whatsapp-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #25D366;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .whatsapp-btn i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        
        .whatsapp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
        }
        
        footer {
            background: white;
            padding: 15px 0;
            text-align: center;
            font-size: 0.8rem;
            color: var(--gray);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }

            nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }

            nav ul li {
                margin: 5px 10px;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .logo h1 {
                font-size: 1.5rem;
            }
            
            .page-title h2 {
                font-size: 1.8rem;
            }
            
            .service-icon {
                height: 100px;
                font-size: 2rem;
            }
            
            .cta-section {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <a href="index.html" class="logo">
                <h1>ServiceHub</h1>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="services.html">Services</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="main-content">
        <div class="page-title">
            <h2>Our Services</h2>
            <!-- <p>Discover the range of services we offer to make your documentation process seamless and hassle-free.</p> -->
        </div>
        
        <div class="services-section">
            <h3 class="section-title">Currently Available Services</h3>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="service-content">
                        <h3>LLR Exam</h3>
                        <p>Get assistance with your Learner's License Registration and exam process. We guide you through the entire procedure.</p>
                        <span class="service-status status-active">Active Service</span>
                    </div>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="service-content">
                        <h3>DL Number Update</h3>
                        <p>Need to update your Driver's License number? We help you through the update process quickly and efficiently.</p>
                        <span class="service-status status-active">Active Service</span>
                    </div>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="service-content">
                        <h3>DL PDF Download</h3>
                        <p>Download your Driver's License in PDF format for easy access and printing whenever needed.</p>
                        <span class="service-status status-active">Active Service</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- <div class="video-section">
            <h3 class="section-title">How Our Services Work</h3>
            <div class="video-container">
                <div class="video-placeholder">
                    <i class="fas fa-play-circle"></i>
                    <p>Service demonstration video will play here</p>
                    <p><small>Replace with your actual video embed code</small></p>
                </div> -->
                <!-- Replace the div above with your actual video embed code -->
                <!-- <iframe src="https://www.youtube.com/embed/your-video-id" allowfullscreen></iframe> -->
            <!-- </div>
        </div>--> 
        
        <div class="services-section">
            <h3 class="section-title">Coming Soon</h3>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="service-content">
                        <h3>RC PDF Download</h3>
                        <p>Soon you'll be able to download your Registration Certificate in PDF format through our platform.</p>
                        <span class="service-status status-upcoming">Coming Soon</span>
                    </div>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="service-content">
                        <h3>Medical Certificate</h3>
                        <p>We'll soon help you obtain the required medical certificates for your license and registration needs.</p>
                        <span class="service-status status-upcoming">Coming Soon</span>
                    </div>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="service-content">
                        <h3>RC Number Update</h3>
                        <p>Updating your Registration Certificate number will be made simple with our upcoming service.</p>
                        <span class="service-status status-upcoming">Coming Soon</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="cta-section">
            <h3>Need Help With Our Services?</h3>
            <p>Our team is ready to assist you with any questions about our services or to help you get started.</p>
            <a href="https://wa.me/9604610640" class="whatsapp-btn" target="_blank">
                <i class="fab fa-whatsapp"></i> Contact Us on WhatsApp
            </a>
        </div>
    </div>
    
    <footer>
        &copy; 2023 ServiceHub. All rights reserved.
    </footer>
</body>
</html>