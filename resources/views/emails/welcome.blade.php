<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peritoneal Dialysis Monitoring</title>
    <style>
        :root {
            --color-primary: #395886;
            --color-secondary: #638ECB;
            --color-white: #FFFFFF;
            --color-green: #477977;
            --color-light-bg: #f8fafc;
            --color-text: #2d3748;
            --color-light-gray: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--color-light-bg);
            color: var(--color-text);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-light-gray);
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 1rem;
        }

        .tagline {
            font-size: 1.2rem;
            color: var(--color-secondary);
            font-weight: 500;
        }

        .hero {
            text-align: center;
            margin: 3rem 0;
        }

        .hero h1 {
            font-size: 2.5rem;
            color: var(--color-primary);
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 2rem;
        }

        .key-messages {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
            margin: 3rem 0;
        }

        .message-card {
            background-color: var(--color-white);
            border-radius: 0.5rem;
            padding: 2rem;
            width: 300px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-top: 4px solid var(--color-primary);
        }

        .message-card h2 {
            color: var(--color-primary);
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .message-card p {
            color: var(--color-text);
            margin-bottom: 1.5rem;
        }

        .cta-section {
            text-align: center;
            margin: 4rem 0;
            padding: 2rem;
            background-color: var(--color-white);
            border-radius: 0.5rem;
        }

        .cta-section h2 {
            color: var(--color-primary);
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        .cta-section p {
            max-width: 600px;
            margin: 0 auto 2rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background-color: var(--color-primary);
            color: var(--color-white);
            border-radius: 0.25rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn:hover {
            background-color: var(--color-secondary);
            transform: translateY(-2px);
        }

        footer {
            text-align: center;
            margin-top: auto;
            padding: 2rem 0;
            color: var(--color-text);
            border-top: 1px solid var(--color-light-gray);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .message-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">PD Monitor</div>
            <div class="tagline">Advanced Peritoneal Dialysis Tracking</div>
        </header>

        <main>
            <section class="hero">
                <h1>Optimizing Peritoneal Dialysis Care Through Intelligent Monitoring</h1>
                <p>Our comprehensive monitoring system helps healthcare providers deliver better peritoneal dialysis outcomes through real-time data tracking and analysis.</p>
            </section>

            <div class="key-messages">
                <div class="message-card">
                    <h2>Precision Tracking</h2>
                    <p>Monitor every aspect of peritoneal dialysis treatments with our accurate tracking system designed specifically for PD patients.</p>
                </div>
                
                <div class="message-card">
                    <h2>Patient-Centered</h2>
                    <p>Focus on what matters most - your patients' health and comfort during their dialysis journey.</p>
                </div>
                
                <div class="message-card">
                    <h2>Data-Driven Insights</h2>
                    <p>Gain valuable insights from treatment data to improve patient outcomes and quality of care.</p>
                </div>
            </div>

            <section class="cta-section">
                <h2>Ready to Transform Your PD Monitoring?</h2>
                <p>Discover how our specialized monitoring system can enhance your peritoneal dialysis program and improve patient experiences.</p>
                <button class="btn">Learn More About Our System</button>
            </section>
        </main>

        <footer>
            <p>© 2023 Peritoneal Dialysis Monitoring System. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>