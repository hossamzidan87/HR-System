<?php
session_start();

if (isset($_COOKIE['remember_me'])) {

    
    // You can add additional checks here if needed
    // Redirect to the login page if cookies are not set
    header("Location: welcome.php");
    exit(); // Ensure the script stops executing after redirection
    // Redirect to the welcome page

} else {

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Design by foolishdeveloper.com -->
    <title>Rubyred Fetih HR Management System</title>
 
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
    <!--Stylesheet-->
    <style media="screen">
      *,
*:before,
*:after{
    padding: 0;
    margin: 0;
    box-sizing: border-box;
}
body{
    background-color: rgb(172, 20, 20);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    overflow: hidden;
}
.container{
    position: relative;
    width: 100%;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
.background{
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    z-index: 0;
}
.background .shape{
    height: 200px;
    width: 200px;
    position: absolute;
    border-radius: 50%;
}
.shape:first-child{
    background: linear-gradient(
        #1845ad,
        #23a2f6
    );
    left: -100px;
    top: -100px;
}
.shape:last-child{
    background: linear-gradient(
        to right,
        #ff512f,
        #f09819
    );
    right: -100px;
    bottom: -100px;
}
.content{
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30px;
}
.header-link{
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 14px 30px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 1.1em;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.header-link:hover{
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
}
.header-demo{
    display: inline-block;
    background: linear-gradient(135deg, #cbcccf 0%, #540707 100%);
    color: white;
    padding: 14px 30px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 1.1em;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.header-demo:hover{
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(39, 48, 88, 0.5);
}
form{
    width: 400px;
    background-color: rgba(255,255,255,0.13);
    border-radius: 10px;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.1);
    box-shadow: 0 8px 32px rgba(8,7,16,0.6);
    padding: 50px 40px;
}
form *{
    font-family: 'Poppins',sans-serif;
    color: #ffffff;
    letter-spacing: 0.5px;
    outline: none;
    border: none;
}
form h3{
    font-size: 32px;
    font-weight: 600;
    line-height: 42px;
    text-align: center;
    margin-bottom: 30px;
}

label{
    display: block;
    margin-top: 25px;
    font-size: 16px;
    font-weight: 500;
}
label:first-of-type{
    margin-top: 0;
}
input{
    display: block;
    height: 50px;
    width: 100%;
    background-color: rgba(255,255,255,0.07);
    border-radius: 5px;
    padding: 0 15px;
    margin-top: 10px;
    font-size: 14px;
    font-weight: 300;
    transition: background-color 0.3s ease;
}
input:focus{
    background-color: rgba(255,255,255,0.15);
}
::placeholder{
    color: #e5e5e5;
}
button{
    margin-top: 40px;
    width: 100%;
    background-color: #ffffff;
    color: #080710;
    padding: 15px 0;
    font-size: 18px;
    font-weight: 600;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}
button:hover{
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

    </style>
</head>
<body>
    <div class="background">
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="content">
                <a href="http://10.64.52.52/project/" class="header-demo">
            📅 Demo HR System
        </a>
        <a href="http://10.64.52.52/meeting/" class="header-link">
            📅 Meeting Room Booking System
        </a>
        
        <form action="login.php" method="post" onsubmit="return validateForm()">
            <h3>Login Here</h3>

            <label for="username">Username</label>
            <input type="text" name="username" placeholder="Username" id="User">

            <label for="password">Password</label>
            <input type="password" name="password" placeholder="password" id="Password">

            <button>Log In</button>
        </form>
    </div>

    <script>
        function validateForm() {
            var username = document.getElementById("User").value;
            var password = document.getElementById("Password").value;
            if (username == "" || password == "") {
                alert("Username and Password must be filled out");
                return false;
            }
            return true;
        }
    </script>
    
</body>
</html>
