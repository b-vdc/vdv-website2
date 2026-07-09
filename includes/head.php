<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?></title>
  <meta name="description" content="<?= $description ?>">
<?php if (!empty($robots)): ?>
  <meta name="robots" content="<?= $robots ?>">
<?php endif; ?>
  <link rel="icon" type="image/png" href="https://vandervolpi.com/assets/logo/VDV_Favicon2_oranje_RGB.png">
  <link rel="stylesheet" href="css/style.css">
<?php if (!empty($ogDescription)): ?>
  <meta property="og:title" content="<?= $title ?>">
  <meta property="og:description" content="<?= $ogDescription ?>">
  <meta property="og:image" content="https://vandervolpi.com/assets/logo/VDV_Logo1_oranje_RGB.png">
  <meta property="og:type" content="website">
<?php endif; ?>
</head>
<body>
