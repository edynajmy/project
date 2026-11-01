<!DOCTYPE html>
<html>
<head>
    <title>StudyMate</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:'Segoe UI',sans-serif;
        }

        body{
            min-height:100vh;
            background:linear-gradient(135deg,#5b5ff7,#7b5cff);
            overflow:hidden;
        }

        .container-fluid{
            height:100vh;
        }

        .left-section{
            color:white;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            text-align:center;
            padding:50px;
        }

        .left-section h1{
            font-size:80px;
            font-weight:bold;
        }

        .left-section p{
            font-size:28px;
            margin-top:20px;
        }

        .left-section img{
            width:350px;
            margin-top:30px;
        }

        .right-section{
            display:flex;
            justify-content:center;
            align-items:center;
        }

        .welcome-card{
            background:white;
            width:500px;
            border-radius:35px;
            padding:50px;
            box-shadow:0 20px 50px rgba(0,0,0,0.2);
        }

        .welcome-card h2{
            font-size:55px;
            color:#4f46e5;
            font-weight:bold;
            text-align:center;
        }

        .feature{
            display:flex;
            margin-bottom:25px;
        }

        .feature-icon{
            width:60px;
            height:60px;
            background:#f3f4f6;
            border-radius:15px;
            display:flex;
            justify-content:center;
            align-items:center;
            font-size:28px;
            margin-right:20px;
        }

        .btn-purple{
            background:#6d4cff;
            color:white;
            border-radius:40px;
            padding:15px;
            font-size:22px;
            width:100%;
        }

        .btn-outline-purple{
            border:2px solid #6d4cff;
            color:#6d4cff;
            border-radius:40px;
            padding:15px;
            font-size:22px;
            width:100%;
        }

        .btn-purple:hover{
            background:#5a3df0;
            color:white;
        }

        .btn-outline-purple:hover{
            background:#6d4cff;
            color:white;
        }

        @media(max-width:991px){

            .left-section{
                display:none;
            }

            .welcome-card{
                width:95%;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">

    <div class="row h-100">

        <div class="col-lg-6 left-section">

            <h1>📚 StudyMate</h1>

            <p>
                Your Personal Study Companion
            </p>

            <h4 class="mt-4">
                Stay organized. Stay productive.<br>
                Achieve more every day.
            </h4>

        </div>

        <div class="col-lg-6 right-section">

            <div class="welcome-card">

                <h5 class="text-center text-muted">
                    Welcome to
                </h5>

                <h2>StudyMate</h2>

                <hr class="my-4">

                <div class="feature">
                    <div class="feature-icon">📚</div>
                    <div>
                        <h5>Manage Assignments</h5>
                        <small>Keep track of all your tasks and due dates.</small>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">📅</div>
                    <div>
                        <h5>Schedule Exams</h5>
                        <small>Never miss an important exam.</small>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">📝</div>
                    <div>
                        <h5>Create Notes</h5>
                        <small>Organize your study materials.</small>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">🏆</div>
                    <div>
                        <h5>Earn Achievements</h5>
                        <small>Unlock amazing badges.</small>
                    </div>
                </div>

                <a href="auth/login.php"
                   class="btn btn-purple mt-4">

                    Sign In

                </a>

                <a href="auth/register.php"
                   class="btn btn-outline-purple mt-3">

                    Create Account

                </a>

            </div>

        </div>

    </div>

</div>

</body>
</html>