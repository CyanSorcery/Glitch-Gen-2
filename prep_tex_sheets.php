<?php
ini_set('display_errors', 1);

/*
    prep_tex_sheets.php

    This file reads the texture sheets and prepares usable graphics with all extra/empty space removed,
    as well as reduce the color count to be more easily usable by the image generation routine.
*/

//Scan the directory for all texture pages
$page_pngs      = scandir('./tex/');

if ($page_pngs === false)
    die('Texture page directory is empty!');

//Go through each one and figure out which ones are PNGs
$tex_pages      = [];
foreach ($page_pngs as $tex_file)
{
    //Is this a PNG? If not, skip it
    if (mime_content_type("./tex/$tex_file") != 'image/png')
    {
        print("File is not image, skipping...\r\n");
        continue;
    }

    try
    {
        print("Reading file $tex_file...\r\n");
        $tex_pages[]        = imagecreatefrompng("./tex/$tex_file");
    }
    catch (Exception $e)
    {
        die($e);
    }
}

if (empty($tex_pages))
    die('No valid PNGs were found!');

//Arrays which will hold each 8x8 tile from the texture pages
$nes_gb_tiles           = [];
$snes_tiles             = [];

//We should now have some textures to work with, so let's work with them!
$tmp_num        = 0;
foreach ($tex_pages as $page)
{
    //Get the image dimensions
    $page_w         = imagesx($page);
    $page_h         = imagesy($page);

    //If this is not true color, convert it to true color so we can palette it properly later
    if (!imageistruecolor($page))
    {
        print("Image was not true color, converting to true color...\r\nIdeally, all images should be true color.\r\n");
        imagepalettetotruecolor($page);
    }
    
    //Make the image grayscale
    imagefilter($page, IMG_FILTER_GRAYSCALE);

    //Make a copy of it
    $page_hd        = imagecreatetruecolor($page_w, $page_h);
    imagealphablending($page_hd, false);
    imagesavealpha($page_hd, true);
    imagesavealpha($page, true);

    imagecopymerge($page_hd, $page, 0, 0, 0, 0, $page_w, $page_h, 100);

    //Allow for transparency
    imagecolortransparent($page, imagecolorat($page, $page_w - 1, $page_h - 1));
    imagecolortransparent($page_hd, imagecolorat($page_hd, $page_w - 1, $page_h - 1));

    //Convert the texture pages to low color mode
    imagetruecolortopalette($page, false, 3);
    imagetruecolortopalette($page_hd, false, 15);

    //Temporary
    imagepng($page, "./{$tmp_num}.png");
    $tmp_num++;
    imagepng($page_hd, "./{$tmp_num}.png");
    $tmp_num++;
}




?>