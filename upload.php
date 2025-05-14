<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $uploadDir = 'uploads/';
    $pythonScript = '/home/bitnami/ocr_script.py';
    $venvPython = '/home/bitnami/venv/bin/python3';

    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            die("Failed to create upload directory: $uploadDir. Check permissions.");
        }
    }
    if (!is_writable($uploadDir)) {
        die("Upload directory is not writable: $uploadDir");
    }

    $results = [];
    $errors = [];

    // Loop through each uploaded file
    foreach ($_FILES['images']['tmp_name'] as $index => $tmpFile) {
        // Skip if no file was uploaded for this index
        if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for file " . $_FILES['images']['name'][$index] . ": " . $_FILES['images']['error'][$index];
            continue;
        }

        // Verify the temp file is readable
        if (!is_readable($tmpFile)) {
            $errors[] = "Cannot read temp file for " . $_FILES['images']['name'][$index];
            continue;
        }

        // Define the target path for the file
        $uploadFile = $uploadDir . basename($_FILES['images']['name'][$index]);

        // Move the uploaded file
        if (move_uploaded_file($tmpFile, $uploadFile)) {
            // Call Python script for OCR
            $command = escapeshellcmd("$venvPython $pythonScript " . escapeshellarg($uploadFile)) . " 2>&1";
            $output = shell_exec($command);

            if ($output) {
                // Parse the output into a structured format
                $result = [];
                $db_save_status = "";
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        list($key, $value) = explode(':', $line, 2);
                        $result[trim($key)] = trim($value);
                    } elseif (strpos($line, "Data saved to database.") !== false) {
                        $db_save_status = "<span style='color: green;'>Data saved to database successfully.</span>";
                    } elseif (strpos($line, "Duplicate record found") !== false) {
                        $db_save_status = "<span style='color: orange;'>$line</span>";
                    } elseif (strpos($line, "DB Save Error") !== false) {
                        $db_save_status = "<span style='color: red;'>$line</span>";
                    }
                }
                $results[$_FILES['images']['name'][$index]] = [
                    'data' => $result,
                    'db_status' => $db_save_status
                ];
            } else {
                $errors[] = "Error processing image " . $_FILES['images']['name'][$index] . ". Python script returned no output.";
            }
        } else {
            $errors[] = "Error uploading file " . $_FILES['images']['name'][$index] . ".";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Marksheet Scanner - Results</title>
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
            flex-direction: column;
            align-items: center;
            background: linear-gradient(45deg, #0a0a0a, #3a4452);
            overflow-x: hidden;
            position: relative;
            padding-top: 20px;
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

        /* Main Container */
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 800px;
            text-align: center;
            position: relative;
            animation: slideIn 1s ease-out;
            margin-bottom: 100px;
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

        h1, h2 {
            color: #1a1a1a;
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: linear-gradient(90deg, #007bff, #00d4ff);
            color: #fff;
            font-weight: 600;
        }

        td {
            background: #fff;
            color: #333;
        }

        ul {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        li {
            margin: 10px 0;
            font-size: 16px;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
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
            text-decoration: none;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }

        .back-button::before {
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

        .back-button:hover::before {
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
                margin-bottom: 80px;
            }

            h1, h2 {
                font-size: 22px;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 8px;
            }

            .back-button {
                padding: 12px 20px;
                font-size: 14px;
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

    <!-- Main Container -->
    <div class="container">
        <?php if (!empty($results)): ?>
            <h2>OCR Results</h2>
            <?php foreach ($results as $filename => $result): ?>
                <table>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                    <?php foreach ($result['data'] as $key => $value): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($key); ?></td>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p><?php echo $result['db_status']; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <h2>Errors</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li style='color: red;'><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (empty($results) && empty($errors)): ?>
            <p>No files uploaded or invalid request.</p>
        <?php endif; ?>

        <!-- Back to Home Page Button -->
        <a href="index.php" class="back-button">Back to Home Page</a>
    </div>

    <!-- Credits Box -->
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
<?php } else { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Marksheet Scanner - Results</title>
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
            flex-direction: column;
            align-items: center;
            background: linear-gradient(45deg, #0a0a0a, #3a4452);
            overflow-x: hidden;
            position: relative;
            padding-top: 20px;
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

        /* Main Container */
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 800px;
            text-align: center;
            position: relative;
            animation: slideIn 1s ease-out;
            margin-bottom: 100px;
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

        h1, h2 {
            color: #1a1a1a;
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        p {
            font-size: 16px;
            color: #333;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
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
            text-decoration: none;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }

        .back-button::before {
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

        .back-button:hover::before {
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
                margin-bottom: 80px;
            }

            h1, h2 {
                font-size: 22px;
            }

            p {
                font-size: 14px;
            }

            .back-button {
                padding: 12px 20px;
                font-size: 14px;
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

    <!-- Main Container -->
    <div class="container">
        <h2>OCR Results</h2>
        <p>No files uploaded or invalid request.</p>
        <!-- Back to Home Page Button -->
        <a href="index.php" class="back-button">Back to Home Page</a>
    </div>

    <!-- Credits Box -->
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
<?php } ?>
