<?php
ini_set('display_errors', 1);

/*
    Glitched Image Generator, v2
    
    Generates a glitched image based on old video game consoles.
*/

//Get all the relevant data
require('gen_functions.php');

//Save the output of the image generation function
imagepng(generate_image(), 'output.png');

?>