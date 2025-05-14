<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Marksheet Scanner - Upload</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(45deg, #0a0a0a, #3a4452);
            overflow: hidden;
        }

        /* Background Animation */
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        canvas {
            width: 100%;
            height: 100%;
            opacity: 0.3;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px;
            text-align: center;
            position: relative;
            animation: slideIn 1s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        h1 {
            color: #1a1a1a;
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }

        label {
            display: block;
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 500;
        }

        input[type="file"] {
            width: 100%;
            padding: 15px;
            border: 2px dashed #007bff;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        input[type="file"]:hover {
            border-color: #0056b3;
            background-color: #e9ecef;
            transform: scale(1.02);
        }

        button {
            background: linear-gradient(90deg, #007bff, #00d4ff);
            color: #fff;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        button:hover::before {
            left: 100%;
        }

        .credits-box {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.9), rgba(0, 212, 255, 0.7));
            color: #fff;
            font-size: 15px;
            border-radius: 12px;
            line-height: 1.8;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            animation: fadeIn 2s ease-in;
            transition: transform 0.3s ease;
            text-align: center;
        }

        .credits-box:hover {
            transform: scale(1.05);
        }

        .credits-box .guide {
            font-size: 18px;
            font-weight: 600;
            color: #ffd700;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .credits-box .creators {
            font-size: 14px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .credits-box .creators a {
            font-size: 16px;
            font-weight: 600;
            color: #ffd700;
            text-decoration: none;
            transition: color 0.3s ease;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .credits-box .creators a:hover {
            color: #fff;
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 600px) {
            .container {
                padding: 25px;
                margin: 15px;
            }

            h1 {
                font-size: 22px;
            }

            .credits-box {
                font-size: 13px;
                padding: 15px;
            }

            .credits-box .guide {
                font-size: 16px;
            }

            .credits-box .creators {
                font-size: 12px;
                gap: 10px;
                flex-direction: column;
                align-items: center;
            }

            .credits-box .creators a {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="background-animation">
        <canvas id="bgCanvas"></canvas>
    </div>
    <div class="container">
        <h1>OCR Marksheet Scanner</h1>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="images">Select Marksheet Images (Multiple Allowed):</label>
                <input type="file" name="images[]" id="images" accept="image/*" multiple required>
            </div>
            <button type="submit">Upload & Process</button>
        </form>
    </div>
    <div class="credits-box">
        <div class="guide">Under Guidance: Dr. Shaurabh Khare</div>
        <div>Created By:</div>
        <div class="creators">
            <a href="https://www.linkedin.com/in/rizik-saxena-7653a4208" target="_blank">Rizik Saxena (22017C04056)</a>
            <a href="https://www.linkedin.com/in/vishesh-chaurasiya-3858a8324/" target="_blank">Vishesh Chaurasiya (22017C04073)</a>
        </div>
    </div>

    <script>
        // Background Technical Animation (Particle Network)
        const canvas = document.getElementById('bgCanvas');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const particles = [];
        const particleCount = 100;

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 1;
                this.speedX = Math.random() * 0.5 - 0.25;
                this.speedY = Math.random() * 0.5 - 0.25;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                if (this.x < 0 || this.x > canvas.width) this.speedX *= -1;
                if (this.y < 0 || this.y > canvas.height) this.speedY *= -1;
            }

            draw() {
                ctx.fillStyle = 'rgba(0, 123, 255, 0.5)';
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function init() {
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Particle());
            }
        }

        function connectParticles() {
            for (let a = 0; a < particles.length; a++) {
                for (let b = a; b < particles.length; b++) {
                    const dx = particles[a].x - particles[b].x;
                    const dy = particles[a].y - particles[b].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);

                    if (distance < 100) {
                        ctx.strokeStyle = `rgba(0, 123, 255, ${1 - distance / 100})`;
                        ctx.lineWidth = 0.5;
                        ctx.beginPath();
                        ctx.moveTo(particles[a].x, particles[a].y);
                        ctx.lineTo(particles[b].x, particles[b].y);
                        ctx.stroke();
                    }
                }
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });
            connectParticles();
            requestAnimationFrame(animate);
        }

        init();
        animate();

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    </script>
</body>
</html>
