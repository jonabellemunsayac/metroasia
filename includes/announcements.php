<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';

function announcement_settings(?PDO $pdo = null): array
{
    $defaults=['image'=>'','enabled'=>false,'start'=>'','end'=>''];
    try {
        $pdo ??= db();
        $q=$pdo->prepare('SELECT config_value FROM site_config WHERE config_key=?');
        $q->execute(['announcement_popup']);
        $value=json_decode((string)$q->fetchColumn(),true);
        return is_array($value) ? array_replace($defaults,$value) : $defaults;
    } catch (Throwable) { return $defaults; }
}

function announcement_valid_date(string $date): bool
{
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    return $parsed && $parsed->format('Y-m-d')===$date;
}

function announcement_is_active(array $item, ?string $today=null): bool
{
    $today ??= date('Y-m-d');
    return !empty($item['enabled']) && !empty($item['image'])
        && announcement_valid_date((string)$item['start']) && announcement_valid_date((string)$item['end'])
        && $item['start'] <= $today && $item['end'] >= $today;
}

function announcement_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Please upload the announcement image again.');
    if (($file['size'] ?? 0)>5*1024*1024) throw new InvalidArgumentException('The image must be 5 MB or smaller.');
    $info=@getimagesize($file['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!$info || !isset($types[$info['mime']]) || $info[0]>12000 || $info[1]>12000) throw new InvalidArgumentException('Use a JPG, PNG, WEBP or GIF image, up to 12000 pixels per side.');
    $dir=dirname(__DIR__).'/uploads/announcements';
    if (!is_dir($dir) && !mkdir($dir,0755,true)) throw new RuntimeException('Could not create the announcement upload folder.');
    $path='uploads/announcements/'.bin2hex(random_bytes(16)).'.'.$types[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'],dirname(__DIR__).'/'.$path)) throw new RuntimeException('Could not save the image.');
    return $path;
}
