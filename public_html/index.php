<?php
header("Location: /lab/?" . $_SERVER['QUERY_STRING']);
die();
?>

<!--<html>
    <head>
        <title>Web Services</title>
        <link rel="stylesheet" type="text/css" href="/shatter/ui.css">
    </head>
    <body class="main-content-fullpage">
        <h1>Web services</h1>
        <p>This server hosts web services for Knot126's stuff.</p>
        <ul>
            <li><a href="/shatter/api.php?action=weak-user-login-ui">Manage Shatter account</a></li>
        </ul>
        <h2>What happened to Smash Hit Lab?</h2>
        <p>The Smash Hit Lab website was taken down on 31 July 2023 becuase it was not being maintained. You can still find the <a href="https://discord.gg/7kra7Z3UNn">Smash Hit Lab discord here</a>.</p>
        <script>
            function remove_logo() {
                var el = document.getElementsByTagName("*");
                
                for (e of el) {
                    if (e.style.zIndex) {
                        e.remove();
                    }
                }
            }
            
            setTimeout(remove_logo, 1);
        </script>
    </body>
</html>-->