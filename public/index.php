<?php
    // This file acts as proxy to load the requested form type.
    // I've created it to solve the problem with nginx redirections which were failing.
    // Probably not the best way, but quickest.
    // Script expects a 'f' URL parameter with the name of the form,ç
    // then, after ensuring the parameter is valid, calls the form php

    $valid_forms = ['main', 'din', 'dout', 'internal', 'ct'];

    if (empty($_REQUEST['f']) || !in_array(strtolower($_REQUEST['f']), $valid_forms)) {
        $form = 'main';
    } else {
        $form = strtolower($_REQUEST['f']);
    }

    require_once __DIR__  . '/' . $form . '.php';
