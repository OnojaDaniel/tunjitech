<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Under Maintenance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        .container {
            text-align: center;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 40px;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .illustration {
            width: 280px;
            height: 280px;
            margin: 0 auto 40px;
            position: relative;
        }

        .gear {
            position: absolute;
            border-radius: 50%;
            border: 8px solid #667eea;
        }

        .gear-large {
            width: 120px;
            height: 120px;
            top: 80px;
            left: 80px;
            animation: rotate 4s linear infinite;
            border-width: 12px;
        }

        .gear-small {
            width: 80px;
            height: 80px;
            top: 40px;
            right: 60px;
            animation: rotateReverse 3s linear infinite;
        }

        .gear::before,
        .gear::after {
            content: '';
            position: absolute;
            background: #667eea;
        }

        .gear-large::before {
            width: 40px;
            height: 8px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .gear-large::after {
            width: 8px;
            height: 40px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .wrench {
            position: absolute;
            width: 100px;
            height: 100px;
            bottom: 60px;
            left: 40px;
            animation: swing 2s ease-in-out infinite;
        }

        .wrench::before {
            content: '';
            position: absolute;
            width: 12px;
            height: 70px;
            background: #764ba2;
            left: 50%;
            transform: translateX(-50%) rotate(45deg);
            border-radius: 6px;
        }

        .wrench::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            border: 6px solid #764ba2;
            border-radius: 50%;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes rotateReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }

        @keyframes swing {
            0%, 100% { transform: rotate(-10deg); }
            50% { transform: rotate(10deg); }
        }

        h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }

        p {
            font-size: 1.2em;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .loading-dots {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .dot {
            width: 12px;
            height: 12px;
            background: #667eea;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        .background-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .shape1 {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
            animation: float 6s ease-in-out infinite;
        }

        .shape2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            right: -50px;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(20px); }
        }

        @media (max-width: 600px) {
            .container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 2em;
            }

            p {
                font-size: 1em;
            }

            .illustration {
                width: 200px;
                height: 200px;
            }

            .gear-large {
                width: 90px;
                height: 90px;
                top: 55px;
                left: 55px;
            }

            .gear-small {
                width: 60px;
                height: 60px;
                top: 30px;
                right: 40px;
            }

            .wrench {
                width: 70px;
                height: 70px;
                bottom: 40px;
                left: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="background-shapes">
        <div class="shape shape1"></div>
        <div class="shape shape2"></div>
    </div>

    <div class="container">
        <div class="illustration">
            <div class="gear gear-large"></div>
            <div class="gear gear-small"></div>
            <div class="wrench"></div>
        </div>

        <h1>We'll Be Back Soon!</h1>
        <p>Our website is currently undergoing scheduled maintenance to bring you an even better experience. We appreciate your patience.</p>
        <p><strong>Service will return shortly.</strong></p>

        <div class="loading-dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>
</body>
</html>