<?php
session_start();
include 'connection.php'; // Wajib ada untuk tarik senarai komponen bagi ruangan search

if (!isset($_SESSION['student_id'])) {
    echo "<script>alert('Please sign in as a student to access this page.'); window.location.href='signin.php';</script>";
    exit(); 
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Tracelot System</title>
    <style>
        /* Import Fonts: Inter for UI/Body, Playfair Display for the serif Headings */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            /* Light grayish-blue background gradient mimicking the image */
            background: linear-gradient(135deg, #f6f8fb 0%, #e9edf3 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- NAVIGATION BAR --- */
        .navbar-container {
            width: 100%;
            display: flex;
            justify-content: center;
            position: fixed;
            top: 0;
            z-index: 100;
        }

        .navbar {
            width: 98%;
            max-width: 1400px;
            background-color: #ffffff;
            height: 80px;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-left img {
            height: 45px;
            object-fit: contain;
        }

        .nav-divider {
            width: 1px;
            height: 40px;
            background-color: #e0e0e0;
        }

        .nav-center {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-item {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 14px;
            position: relative;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .nav-item.active {
            color: #0d3b66;
        }

        /* Active tab indicator pill */
        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -28px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 4px;
            background-color: #0d3b66;
            border-radius: 4px 4px 0 0;
        }

        .nav-text-divider {
            color: #ccc;
            font-weight: 300;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .profile-icon {
            width: 35px;
            height: 35px;
            background-color: #000;
            color: #fff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* --- HERO SECTION --- */
        .hero {
            max-width: 1300px;
            margin: 0 auto;
            padding: 180px 40px 60px 40px; /* Top padding clears the fixed navbar */
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            position: relative;
        }

        .hero-text {
            width: 600px;
            flex: 0 0 600px;
            max-width: 600px;
        }

        .hero-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: 56px;
            line-height: 1.1;
            color: #143e70; /* Dark blue */
            margin-bottom: 25px;
        }

        .hero-text h1 span {
            font-family: 'Playfair Display', serif;
            color: #a800ff; /* Bright purple */
        }

        .hero-text p {
            font-size: 18px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 50px;
            max-width: 500px;
        }

        /* --- HERO IMAGE --- */
        .hero-image {
            width: 560px;
            height: 430px;
            flex: 0 0 560px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 20px 30px rgba(0,0,0,0.15));
            transition: opacity 0.5s ease-in-out; /* Smooth transition untuk slideshow */
        }


        /* --- CUSTOM SEARCH BOX --- */
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            background: white;
            border-radius: 30px;
            padding: 5px 5px 5px 20px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            font-size: 15px;
            color: #333;
            min-width: 0;
        }

        .search-box button {
            background: #9340ff;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 10px;
            white-space: nowrap;
        }

        .suggestion-box {
            display: none;
            position: absolute;
            top: 58px;
            left: 20px;
            right: 110px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            z-index: 999;
            max-height: 240px;
            overflow-y: auto;
        }

        .suggestion-item {
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            text-align: left;
        }

        .suggestion-item:hover {
            background: #f1f5f9;
            color: #0d3b66;
        }

        /* --- SCROLL DOWN INDICATOR --- */
        .scroll-down {
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background-color: #fff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            color: #555;
        }

        /* --- INFO SECTION (SCROLL DOWN CONTENT) --- */
        .info-section {
            max-width: 1300px;
            margin: 0 auto;
            padding: 80px 40px 120px 40px;
            text-align: center;
        }

        .info-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            color: #143e70;
            margin-bottom: 50px;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .info-card {
            background-color: #ffffff;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
        }

        .info-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            font-size: 45px;
            margin-bottom: 20px;
        }

        .info-card h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .info-card p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 1100px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding-top: 140px;
            }

            .hero-text {
                width: 100%;
                flex: 0 0 auto;
                max-width: 600px;
            }

            .hero-text p {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-text form {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-image {
                width: 100%;
                max-width: 560px;
                height: 360px;
                flex: 0 0 auto;
            }

            .suggestion-box {
                left: 15px;
                right: 100px;
            }
        }
    </style>
</head>
<body>

    <div class="navbar-container">
        <header class="navbar">
            
            <div class="nav-left">
                <img src="uitmlogo.png" alt="UiTM Logo">
                <div class="nav-divider"></div>
                <img src="traceiotlogo.jpeg" alt="Tracelot System">
            </div>

            <nav class="nav-center">
                <a href="home.php" class="nav-item active">Home</a>
                <span class="nav-text-divider">|</span>
                <a href="components.php" class="nav-item">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="borrowing_status.php" class="nav-item">My Borrowings</a>
            </nav>

            <div class="nav-right">
                <a href="profile.php" class="profile-section" style="text-decoration: none; color: inherit;">
    <div class="profile-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
    </div>

    <div style="display: flex; flex-direction: column; line-height: 1.2;">
        <span style="font-size: 13px; font-weight: 800;">
    <?php 
    $display_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';
    if (strlen($display_name) > 18) {
        $display_name = substr($display_name, 0, 18) . '...';
    }
    echo htmlspecialchars($display_name);
    ?>
</span>
        <span style="font-size: 11px; color: #64748b; font-weight: 600;">
            My Profile
        </span>
    </div>
</a>
            </div>
        </header>
    </div>

    <main class="hero">
        
        <div class="hero-text">
            <h1>Empowering Innovation<br>Through <span>IoT Access</span></h1>
            <p>Get seamless access to IoT equipment through a fast, reliable, and structured lending system.</p>
            
            <form action="components.php" method="GET">
                <div class="search-box">
                    <input 
                        type="text" 
                        name="search" 
                        id="searchInput"
                        placeholder="Search for Components ..." 
                        autocomplete="off" 
                        required
                    >

                    <button type="submit">
                        🔍 Search
                    </button>

                    <div class="suggestion-box" id="suggestionBox">
                        <?php
                        // Tarik nama dari table COMPONENT
                        if(isset($conn)) {
                            $query_search_recom = mysqli_query($conn, "SELECT name FROM COMPONENT ORDER BY name ASC");
                            if(mysqli_num_rows($query_search_recom) > 0) {
                                while($row_recom = mysqli_fetch_assoc($query_search_recom)) {
                                    $component_name = htmlspecialchars($row_recom['name'], ENT_QUOTES);
                                    echo "<div class='suggestion-item' onclick=\"selectSuggestion('{$component_name}')\">{$component_name}</div>";
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </form>
        </div> 
        
        <div class="hero-image">
            <img id="iot-slideshow" src="homekit.jpeg" alt="IoT Equipment">
        </div>

        <div class="scroll-down" onclick="scrollToInfo()">
    <svg width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M1 1L7 7L13 1" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</div>
        
    </main>

    <section class="info-section" id="infoSection">
    <h2>Why Use TraceIoT?</h2>

    <div class="cards-container">
        
        <div class="info-card">
            <div class="card-icon">🛒</div>
            <h3>Easy Equipment Booking</h3>
            <p>
                Students can request IoT components online without using manual forms. 
                This makes the borrowing process faster and more organized.
            </p>
        </div>

        <div class="info-card">
            <div class="card-icon">📊</div>
            <h3>Real-Time Borrowing Status</h3>
            <p>
                Students can check whether their request is pending, approved, rejected, 
                or returned directly through the system.
            </p>
        </div>

        <div class="info-card">
            <div class="card-icon">✅</div>
            <h3>Safer Return Management</h3>
            <p>
                Students must upload proof images and return details, helping admins verify 
                the condition of each component before completing the return process.
            </p>
        </div>

    </div>
</section>


    <script>
        const searchInput = document.getElementById("searchInput");
        const suggestionBox = document.getElementById("suggestionBox");
        const suggestionItems = document.querySelectorAll(".suggestion-item");

        if (searchInput && suggestionBox) {
            searchInput.addEventListener("input", function() {
                const keyword = this.value.toLowerCase().trim();
                let hasMatch = false;

                suggestionItems.forEach(item => {
                    const text = item.textContent.toLowerCase();

                    if (text.includes(keyword) && keyword.length > 0) {
                        item.style.display = "block";
                        hasMatch = true;
                    } else {
                        item.style.display = "none";
                    }
                });

                suggestionBox.style.display = hasMatch ? "block" : "none";
            });

            searchInput.addEventListener("focus", function() {
                const keyword = this.value.toLowerCase().trim();
                let hasMatch = false;

                suggestionItems.forEach(item => {
                    const text = item.textContent.toLowerCase();

                    if (text.includes(keyword) && keyword.length > 0) {
                        item.style.display = "block";
                        hasMatch = true;
                    } else {
                        item.style.display = "none";
                    }
                });

                suggestionBox.style.display = hasMatch ? "block" : "none";
            });

            document.addEventListener("click", function(e) {
                if (!e.target.closest(".search-box")) {
                    suggestionBox.style.display = "none";
                }
            });
        }

        function selectSuggestion(value) {
            if (searchInput && suggestionBox) {
                searchInput.value = value;
                suggestionBox.style.display = "none";
            }
        }
    </script>

    <script>
        // Sila letakkan nama fail gambar-gambar projek IoT awak di sini.
        // Pastikan nama fail ejaannya betul dan berada dalam folder yang sama/betul.
        const senaraiGambar = [
            "homekit.jpeg",   // Gambar pertama
            "homekit2.png",    // Sila tukar nama fail ini
            "homekit3.png",
            "homekit4.png"     // Sila tukar nama fail ini
        ];
        
        let indexSemasa = 0;
        const gambarElemen = document.getElementById("iot-slideshow");

        if(gambarElemen && senaraiGambar.length > 1) {
            setInterval(() => {
                // 1. Pudarkan gambar semasa
                gambarElemen.style.opacity = 0;
                
                // 2. Tunggu sebentar, tukar gambar, dan terangkan semula
                setTimeout(() => {
                    indexSemasa = (indexSemasa + 1) % senaraiGambar.length;
                    gambarElemen.src = senaraiGambar[indexSemasa];
                    
                    gambarElemen.style.opacity = 1;
                }, 500); // 500ms adalah masa transition opacity
                
            }, 3500); // Gambar akan bertukar setiap 3.5 saat
        }
    </script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($_SESSION['login_success'])): ?>
<script>
Swal.fire({
    title: 'Login Successful!',
    text: <?php echo json_encode($_SESSION['login_success']); ?>,
    icon: 'success',
    confirmButtonText: 'Continue'
});
</script>
<?php 
unset($_SESSION['login_success']); 
endif; 
?>

<script>
function scrollToInfo() {
    document.getElementById("infoSection").scrollIntoView({
        behavior: "smooth"
    });
}
</script>

</body>
</html>