<?php
    // This file acts as proxy to load the requested form type.
    // I've created it to solve the problem with tunneled nginx redirections which were failing.
    // Probably not the best way, but quickest.
    // Script expects a 'f' URL parameter with the name of the form,
    // then, after ensuring the parameter is valid, calls the form php

    require_once __DIR__ . '/../includes/machinonSerial.php';

    $valid_forms = [
        'main' => 'ADC|machinon ADC Setup',
        'ct' => 'CT|machinon CT Setup',
        'din' => 'DIN|machinon DIN Setup',
        'dout' => 'DOUT|machinon DOUT Setup',
        'internal' => 'GENERAL|machinon Internal Setup',
    ];

    if (empty($_REQUEST['f']) || !key_exists(strtolower($_REQUEST['f']), $valid_forms)) {
        $form = 'main';
    } else {
        $form = strtolower($_REQUEST['f']);
    }

    list($link, $title) = explode('|', $valid_forms[$form])

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $title ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="//stackpath.bootstrapcdn.com/bootswatch/4.2.1/cerulean/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-62+JPIF7fVYAPS4itRiqKa7VU321chxfKZRtkSY0tGoTwcUItAFEH/HGTpvDH6e6" crossorigin="anonymous">
    <link href="//gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
    <link href="//use.fontawesome.com/releases/v5.6.3/css/all.css" rel="stylesheet"
          integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" media="screen" href="css/main.css" />
</head>
<body>
    <div class="container text-center">
        <div class="row m-auto justify-content-center">
            <div class="col-md-12 top-menu">
            <?php
                foreach ($valid_forms as $f => $menu_item) {
                    list($link) = explode('|', $menu_item);
                    $active = '';
                    if ($form == $f) {
                        $active = 'active-blue';
                    }
                    echo '<a class="'.$active.'" href="?f=' . $f . '">' . $link . '</a>';
                }
            ?>
                <hr>
            </div>
        </div>
        <div class="row m-auto justify-content-center">
            <div class="col-md-8">
                <?php
                require_once __DIR__ . '/../forms/' . $form . '.php';
                ?>
            </div>
        </div>
    </div>
    <script src="//code.jquery.com/jquery-3.3.1.min.js"
            integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"
            integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa"
            crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {

        });
    </script>
</body>
</html>
